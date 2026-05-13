<?php

declare(strict_types=1);

namespace Jb\Console;

class Generator
{
    public function __construct(private readonly string $cwd, private readonly string $frameworkPath)
    {
    }

    /**
     * Generate code for a make:* command.
     */
    public function make(string $command, string $name): void
    {
        match ($command) {
            'make:controller' => $this->file('controller.stub', "app/Controllers/{$name}Controller.php", $name),
            'make:model' => $this->file('model.stub', "app/Models/$name.php", $name),
            'make:migration' => $this->migration($name),
            'make:seeder' => $this->file('seeder.stub', "database/seeders/{$name}Seeder.php", $name),
            'make:middleware' => $this->file('middleware.stub', "app/Middleware/{$name}Middleware.php", $name),
            'make:test' => $this->file('test.stub', "tests/Unit/{$name}Test.php", $name),
            'make:crud' => $this->crud($name),
            'make:scaffold' => $this->scaffold($name),
            default => null,
        };
    }

    /**
     * Publish editable stubs to the current project.
     */
    public function publishStubs(): void
    {
        $this->copyDirectory($this->frameworkPath . '/stubs', $this->cwd . '/stubs');
    }

    private function crud(string $name): void
    {
        $this->file('scaffold/controller.stub', "app/Controllers/{$name}Controller.php", $name);
        $this->file('scaffold/model.stub', "app/Models/$name.php", $name);
        $this->file('scaffold/migration.stub', 'database/migrations/' . date('Y_m_d_His') . '_create_' . $this->table($name) . '.php', $name);
        $this->file('seeder.stub', "database/seeders/{$name}Seeder.php", $name);
        $this->appendRoutes($name);
    }

    private function scaffold(string $name): void
    {
        $this->crud($name);
        $this->file('test.stub', "tests/Unit/{$name}UnitTest.php", $name . 'Unit');
        $this->file('scaffold/test.stub', "tests/Integration/{$name}ScaffoldTest.php", $name);
        $this->appendSecurityPermission($name);
        $this->appendAudit($name);
    }

    private function migration(string $name): void
    {
        $table = str_starts_with($name, 'create_') ? substr($name, 7) : $name;
        $this->file('migration.stub', 'database/migrations/' . date('Y_m_d_His') . "_$name.php", $table);
    }

    private function file(string $stub, string $relative, string $name): void
    {
        $this->write($relative, $this->render($stub, $name));
    }

    private function render(string $stub, string $name): string
    {
        $path = $this->cwd . '/stubs/' . $stub;
        if (!is_file($path)) {
            $path = $this->frameworkPath . '/stubs/' . $stub;
        }

        $class = $this->className($name);
        $values = [
            '{{ClassName}}' => $class,
            '{{className}}' => lcfirst($class),
            '{{class_name}}' => $this->snake($class),
            '{{table_name}}' => $this->table($class),
            '{{timestamp}}' => date('Y-m-d H:i:s'),
            '{{namespace}}' => 'App',
        ];

        return strtr((string) file_get_contents($path), $values);
    }

    private function appendRoutes(string $name): void
    {
        $class = $this->className($name);
        $base = '/' . $this->table($class);
        $block = "\n// JB scaffold: $class\n";
        $block .= "\$router->get('$base', [App\\Controllers\\{$class}Controller::class, 'index']);\n";
        $block .= "\$router->get('$base/{id}', [App\\Controllers\\{$class}Controller::class, 'show']);\n";
        $block .= "\$router->post('$base', [App\\Controllers\\{$class}Controller::class, 'store']);\n";
        $block .= "\$router->put('$base/{id}', [App\\Controllers\\{$class}Controller::class, 'update']);\n";
        $block .= "\$router->delete('$base/{id}', [App\\Controllers\\{$class}Controller::class, 'destroy']);\n";
        $this->appendOnce('routes/api.php', "JB scaffold: $class", $block);
    }

    private function appendSecurityPermission(string $name): void
    {
        $permission = strtoupper($this->snake($name)) . '_ADMIN';
        $path = $this->cwd . '/config/security.php';
        $current = is_file($path) ? (string) file_get_contents($path) : "<?php\n\nreturn [\n    'permissions' => [],\n];\n";

        if (str_contains($current, "'$permission'")) {
            return;
        }

        $updated = str_replace("'permissions' => []", "'permissions' => ['$permission']", $current);
        if ($updated === $current) {
            $updated = str_replace("'permissions' => [", "'permissions' => ['$permission', ", $current);
        }

        file_put_contents($path, $updated);
    }

    private function appendAudit(string $name): void
    {
        $line = date('c') . ' scaffold ' . $this->className($name) . PHP_EOL;
        $this->appendOnce('storage/logs/audit_scaffold.log', 'scaffold ' . $this->className($name), $line);
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->cwd . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $content);
    }

    private function appendOnce(string $relative, string $needle, string $content): void
    {
        $path = $this->cwd . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $current = is_file($path) ? (string) file_get_contents($path) : '';
        if (!str_contains($current, $needle)) {
            file_put_contents($path, $content, FILE_APPEND);
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }

        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === 'project') {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $target . DIRECTORY_SEPARATOR . $item;
            is_dir($from) ? $this->copyDirectory($from, $to) : copy($from, $to);
        }
    }

    private function className(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    private function table(string $name): string
    {
        return $this->snake($name) . 's';
    }

    private function snake(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name);
    }
}
