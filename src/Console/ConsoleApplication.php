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

    private function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
