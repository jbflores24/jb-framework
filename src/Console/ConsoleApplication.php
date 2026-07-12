<?php

declare(strict_types=1);

namespace Jb\Console;

use Jb\Core\Config;
use Jb\Database\Connection;
use Jb\Database\Migrator;
use Jb\Database\Seeder;

class ConsoleApplication
{
    public function __construct(private readonly string $cwd, private readonly string $frameworkPath)
    {
    }

    /**
     * Run the CLI command.
     *
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $argument = $argv[2] ?? null;

        try {
            return match ($command) {
                'new' => $this->new($argument),
                'serve' => $this->passthru('php -S 127.0.0.1:8000 -t public'),
                'env' => $this->env(),
                'make:controller', 'make:model', 'make:migration', 'make:seeder',
                'make:middleware', 'make:test', 'make:service', 'make:crud', 'make:scaffold' => $this->make($command, $argument),
                'stub:publish' => $this->publishStubs(),
                'migrate' => $this->migrate('run'),
                'migrate:rollback' => $this->migrate('rollback'),
                'migrate:fresh' => $this->fresh(),
                'migrate:status' => $this->status(),
                'seed' => $this->seed($argument),
                'cache:clear' => $this->clear('storage/cache'),
                'logs:clear' => $this->clear('storage/logs'),
                'test' => $this->passthru('composer test'),
                'docs:generate' => $this->docs(),
                'alerts:check-silence' => $this->checkSilence($argument),
                'producer:erase' => $this->eraseProducer($argument),
                'db:backup' => $this->backupDb(),
                'report:generate' => $this->generateReport($argument, $argv[3] ?? null),
                default => $this->help(),
            };
        } catch (\Throwable $exception) {
            return $this->fail($exception->getMessage());
        }
    }

    private function new(?string $name): int
    {
        if ($name === null) {
            return $this->fail('Uso: php jb new <nombre>');
        }

        (new ProjectBuilder($this->frameworkPath))->create($this->cwd, $name);
        $this->line("Proyecto [$name] creado.");

        return 0;
    }

    private function make(string $command, ?string $name): int
    {
        if ($name === null) {
            return $this->fail("Uso: php jb $command <Nombre>");
        }

        (new Generator($this->cwd, $this->frameworkPath))->make($command, $name);

        if ($command === 'make:model') {
            $this->line('Repositorio ' . $this->singularize($name) . 'Repository creado en app/Repositories/');
        } elseif ($command === 'make:service') {
            $this->line("Servicio {$name}Service creado en app/Services/");
        } else {
            $this->line("$command [$name] generado.");
        }

        return 0;
    }

    /**
     * Singularize a table name to its PascalCase class name.
     * Example: "estudiantes" -> "Estudiante", "alumno_cursos" -> "AlumnoCurso"
     */
    private function singularize(string $name): string
    {
        $singular = match (true) {
            str_ends_with(strtolower($name), 'es') => substr($name, 0, -2),
            str_ends_with(strtolower($name), 's') => substr($name, 0, -1),
            default => $name,
        };

        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $singular)));
    }

    private function publishStubs(): int
    {
        (new Generator($this->cwd, $this->frameworkPath))->publishStubs();
        $this->line('Stubs publicados en stubs/.');

        return 0;
    }

    private function migrate(string $action): int
    {
        $migrator = new Migrator($this->connection(), $this->cwd . '/database/migrations');
        $done = $action === 'rollback' ? $migrator->rollback() : $migrator->run();
        $this->line($done === [] ? 'Sin cambios.' : implode(PHP_EOL, $done));

        return 0;
    }

    private function fresh(): int
    {
        while ($this->migrate('rollback') === 0 && $this->statusHasRan()) {
        }

        return $this->migrate('run');
    }

    private function status(): int
    {
        foreach ((new Migrator($this->connection(), $this->cwd . '/database/migrations'))->status() as $row) {
            $this->line(($row['ran'] ? '[x] ' : '[ ] ') . $row['name']);
        }

        return 0;
    }

    private function seed(?string $name): int
    {
        $this->connection();

        $path = $this->cwd . '/database/seeders';
        $files = $name === null
            ? glob($path . '/*.php') ?: []
            : array_values(array_filter([
                $path . '/' . $name . '.php',
                $path . '/' . $name . 'Seeder.php',
            ], 'is_file'));

        if ($name !== null && $files === []) {
            return $this->fail('Seeder no encontrado: ' . $name);
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                return $this->fail('Seeder no encontrado: ' . $file);
            }

            $seeder = require $file;
            if ($seeder instanceof Seeder) {
                $seeder->run();
                $this->line('Seeder ejecutado: ' . basename($file));
            }
        }

        return 0;
    }

    private function env(): int
    {
        $config = $this->config();
        foreach (['app.name', 'app.env', 'database.driver', 'security.enabled'] as $key) {
            $this->line($key . '=' . json_encode($config->get($key)));
        }

        return 0;
    }

    private function docs(): int
    {
        $target = $this->cwd . '/docs/swagger.yaml';
        $routesPath = $this->cwd . '/routes/api.php';
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        if (!is_file($routesPath)) {
            return $this->fail('No existe routes/api.php para generar la documentacion.');
        }

        $routes = $this->parseRoutes((string) file_get_contents($routesPath));
        $lines = [
            'openapi: 3.0.3',
            'info:',
            '  title: JB API',
            '  version: 1.0.0',
            'paths:',
        ];

        foreach ($routes as $path => $methods) {
            $lines[] = '  ' . $path . ':';
            foreach ($methods as $method) {
                $lines[] = '    ' . strtolower($method) . ':';
                $lines[] = '      summary: Generated route';
                $lines[] = '      responses:';
                $lines[] = '        "200":';
                $lines[] = '          description: OK';
            }
        }

        file_put_contents($target, implode(PHP_EOL, $lines) . PHP_EOL);
        $this->line('Documentacion generada en docs/swagger.yaml');

        return 0;
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseRoutes(string $contents): array
    {
        $routes = [];

        preg_match_all('/\$router->(get|post|put|patch|delete)\(\s*\'([^\']+)\'/i', $contents, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $method = strtoupper($match[1]);
            $path = $match[2];
            if ($path === '') {
                continue;
            }

            $routes[$path] ??= [];
            if (!in_array($method, $routes[$path], true)) {
                $routes[$path][] = $method;
            }
        }

        if (str_contains($contents, 'SecurityRoutes::register')) {
            foreach ($this->securityRoutes() as $path => $methods) {
                $routes[$path] ??= [];
                foreach ($methods as $method) {
                    if (!in_array($method, $routes[$path], true)) {
                        $routes[$path][] = $method;
                    }
                }
            }
        }

        ksort($routes);

        return $routes;
    }

    /**
     * @return array<string, list<string>>
     */
    private function securityRoutes(): array
    {
        return [
            '/security/dashboard' => ['GET'],
            '/security/blocks' => ['GET'],
            '/security/blocks/block' => ['POST'],
            '/security/blocks/unblock' => ['POST'],
            '/security/logs' => ['GET'],
            '/security/whitelist' => ['GET'],
            '/security/whitelist/add' => ['POST'],
            '/security/whitelist/remove' => ['POST'],
            '/security/blacklist' => ['GET'],
            '/security/blacklist/add' => ['POST'],
            '/security/blacklist/remove' => ['POST'],
            '/security/export/csv' => ['GET'],
        ];
    }

    private function clear(string $relative): int
    {
        foreach (glob($this->cwd . '/' . $relative . '/*') ?: [] as $file) {
            is_file($file) ? unlink($file) : null;
        }

        $this->line("$relative limpiado.");

        return 0;
    }

    private function connection(): Connection
    {
        return Connection::init($this->config());
    }

    private function config(): Config
    {
        $config = new Config($this->cwd);
        $config->load();

        return $config;
    }

    private function statusHasRan(): bool
    {
        foreach ((new Migrator($this->connection(), $this->cwd . '/database/migrations'))->status() as $row) {
            if ($row['ran']) {
                return true;
            }
        }

        return false;
    }

    private function passthru(string $command): int
    {
        passthru($command, $code);

        return (int) $code;
    }

    private function help(): int
    {
        $this->line('JB Framework CLI');
        $this->line('Comandos: new, serve, env, make:controller|model|migration|seeder|middleware|test|service|crud|scaffold, stub:publish, migrate, seed, cache:clear, logs:clear, test, docs:generate');

        return 0;
    }

    private function fail(string $message): int
    {
        fwrite(STDERR, $message . PHP_EOL);

        return 1;
    }

    private function checkSilence(?string $hoursArg): int
    {
        $hours = $hoursArg !== null ? (int)$hoursArg : 24;
        $db = $this->connection()->pdo();
        
        $estanquesStmt = $db->query("SELECT id, nombre FROM estanques WHERE deleted_at IS NULL");
        $estanques = $estanquesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        
        $now = time();
        $seconds = $hours * 3600;
        
        $alertsGenerated = 0;
        foreach ($estanques as $estanque) {
            $estanqueId = (int)$estanque['id'];
            
            $stmt = $db->prepare("SELECT created_at FROM registers WHERE estanque_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$estanqueId]);
            $lastCreated = $stmt->fetchColumn();
            
            $silent = false;
            if (!$lastCreated) {
                $silent = true;
            } else {
                $lastTime = strtotime($lastCreated);
                if ($now - $lastTime > $seconds) {
                    $silent = true;
                }
            }
            
            if ($silent) {
                $stmtAlert = $db->prepare("
                    SELECT id FROM alerts 
                    WHERE estanque_id = ? AND message LIKE 'Alerta: Silencio de datos%' AND resolved = 0
                ");
                $stmtAlert->execute([$estanqueId]);
                $exists = $stmtAlert->fetchColumn();
                
                if (!$exists) {
                    $msg = "Alerta: Silencio de datos detectado en el estanque [{$estanque['nombre']}]. No ha reportado lecturas en más de {$hours} horas.";
                    $stmtInsert = $db->prepare("
                        INSERT INTO alerts (estanque_id, variable_id, severity, message, resolved, created_at)
                        VALUES (?, 1, 'WARNING', ?, 0, NOW())
                    ");
                    $stmtInsert->execute([$estanqueId, $msg]);
                    $alertsGenerated++;
                }
            }
        }
        
        $this->line("Detección de silencio finalizada. Alertas generadas: {$alertsGenerated}");
        return 0;
    }

    private function eraseProducer(?string $idArg): int
    {
        if ($idArg === null) {
            return $this->fail("Uso: php jb producer:erase <producer_id>");
        }
        $producerId = (int)$idArg;
        $db = $this->connection()->pdo();
        
        $stmt = $db->prepare("SELECT id, user_id FROM producers WHERE id = ?");
        $stmt->execute([$producerId]);
        $producer = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$producer) {
            return $this->fail("Productor con ID {$producerId} no encontrado.");
        }
        
        $userId = (int)$producer['user_id'];
        
        $db->beginTransaction();
        try {
            $estanquesStmt = $db->prepare("SELECT id FROM estanques WHERE producer_id = ?");
            $estanquesStmt->execute([$producerId]);
            $estanqueIds = $estanquesStmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            
            if (!empty($estanqueIds)) {
                $placeholders = implode(',', array_fill(0, count($estanqueIds), '?'));
                
                $db->prepare("
                    DELETE FROM registers_audit 
                    WHERE register_id IN (SELECT id FROM registers WHERE estanque_id IN ($placeholders))
                ")->execute($estanqueIds);
                
                $db->prepare("DELETE FROM alerts WHERE estanque_id IN ($placeholders)")->execute($estanqueIds);
                $db->prepare("DELETE FROM registers WHERE estanque_id IN ($placeholders)")->execute($estanqueIds);
                $db->prepare("DELETE FROM estanques WHERE producer_id = ?")->execute([$producerId]);
            }
            
            $db->prepare("DELETE FROM producers WHERE id = ?")->execute([$producerId]);
            $db->prepare("DELETE FROM role_user WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            
            $db->commit();
            $this->line("Productor con ID {$producerId} y todos sus registros asociados han sido eliminados de forma definitiva.");
            return 0;
        } catch (\Throwable $e) {
            $db->rollBack();
            return $this->fail("Error al borrar productor: " . $e->getMessage());
        }
    }

    private function backupDb(): int
    {
        $config = $this->config();
        $dbName = $config->get('database.database', 'apiPruebas');
        $dbUser = $config->get('database.username', 'root');
        $dbPass = $config->get('database.password', '');
        
        $backupDir = $this->cwd . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . '/backup_' . date('Y_m_d_H_i_s') . '.sql';
        
        $mysqldumpPath = 'mysqldump';
        if (is_file('C:\\xampp\\mysql\\bin\\mysqldump.exe')) {
            $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        }
        
        $passwordOption = $dbPass !== '' ? ' -p' . escapeshellarg($dbPass) : '';
        $cmd = escapeshellarg($mysqldumpPath) . ' -u ' . escapeshellarg($dbUser) . $passwordOption . ' ' . escapeshellarg($dbName) . ' > ' . escapeshellarg($backupFile);
        
        exec($cmd, $output, $returnVar);
        
        if ($returnVar !== 0) {
            return $this->fail("Error al generar backup. Código de retorno: {$returnVar}");
        }
        
        $files = glob($backupDir . '/backup_*.sql') ?: [];
        $now = time();
        $retentionSeconds = 7 * 24 * 3600;
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > $retentionSeconds)) {
                unlink($file);
            }
        }
        
        $this->line("Backup de base de datos generado exitosamente en: {$backupFile}");
        $this->line("Política de retención aplicada: Backups conservados por 7 días.");
        return 0;
    }

    private function generateReport(?string $producerIdArg, ?string $daysArg): int
    {
        if ($producerIdArg === null) {
            return $this->fail("Uso: php jb report:generate <producer_id> [dias_periodo]");
        }
        
        $producerId = (int)$producerIdArg;
        $days = $daysArg !== null ? (int)$daysArg : 7;
        
        $db = $this->connection()->pdo();
        
        $stmtUser = $db->prepare("
            SELECT u.email, u.name 
            FROM producers p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.id = ?
        ");
        $stmtUser->execute([$producerId]);
        $user = $stmtUser->fetch(\PDO::FETCH_ASSOC);
        if (!$user) {
            return $this->fail("Productor no encontrado.");
        }
        
        $dateLimit = date('Y-m-d H:i:s', time() - ($days * 24 * 3600));
        $stmt = $db->prepare("
            SELECT r.id, e.nombre as estanque_nombre, v.nombre as variable_nombre, r.valor, r.created_at 
            FROM registers r
            JOIN estanques e ON r.estanque_id = e.id
            JOIN variables v ON r.variable_id = v.id
            WHERE e.producer_id = ? AND r.created_at >= ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$producerId, $dateLimit]);
        $registers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        
        $reportDir = $this->cwd . '/storage/reports';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        $filename = $reportDir . '/report_prod_' . $producerId . '_' . date('Y_m_d') . '.csv';
        $file = fopen($filename, 'w');
        fputcsv($file, ['ID', 'Estanque', 'Variable', 'Valor', 'Fecha']);
        foreach ($registers as $reg) {
            fputcsv($file, [
                $reg['id'],
                $reg['estanque_nombre'],
                $reg['variable_nombre'],
                $reg['valor'],
                $reg['created_at']
            ]);
        }
        fclose($file);
        
        $this->line("Reporte periódico generado para {$user['name']} ({$user['email']}) en: {$filename}");
        $this->line("Total registros en periodo (últimos {$days} días): " . count($registers));
        return 0;
    }

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
