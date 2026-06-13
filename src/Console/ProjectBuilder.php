<?php

declare(strict_types=1);

namespace Jb\Console;

use RuntimeException;

class ProjectBuilder
{
    public function __construct(private readonly string $frameworkPath) {}

    /**
     * Create a new JB API project.
     */
    public function create(string $basePath, string $name): void
    {
        $target = $basePath . DIRECTORY_SEPARATOR . $name;
        if (is_dir($target)) {
            throw new RuntimeException("El directorio ya existe: $target");
        }

        $this->copyDirectory($this->frameworkPath . '/stubs/project', $target);
        copy($target . '/.env.example', $target . '/.env');
        $this->writeProjectFiles($target, $name);
    }

    private function copyDirectory(string $source, string $target): void
    {
        mkdir($target, 0775, true);
        foreach (scandir($source) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $target . DIRECTORY_SEPARATOR . $item;
            is_dir($from) ? $this->copyDirectory($from, $to) : copy($from, $to);
        }
    }

    private function writeProjectFiles(string $target, string $name): void
    {
        $composerName = $this->composerPackageName($name);

        file_put_contents($target . '/composer.json', json_encode([
            'name' => $composerName,
            'type' => 'project',
            'require' => ['php' => '>=8.2', 'jb/framework' => 'dev-main'],
            'require-dev' => ['phpunit/phpunit' => '^11.0'],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
            'repositories' => [[
                'type' => 'vcs',
                'url' => 'https://github.com/jbflores24/jb-framework.git',
            ]],
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
            'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests/']],
            'scripts' => ['test' => 'phpunit'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        file_put_contents($target . '/phpunit.xml', $this->phpunitXml());
        file_put_contents($target . '/.gitignore', "/vendor\n/.env\n/storage/logs/*\n/storage/cache/*\n/storage/rate_limit/*\n");
        file_put_contents($target . '/jb', $this->launcher());
        if (!is_dir($target . '/tests')) {
            mkdir($target . '/tests', 0775, true);
        }
        file_put_contents($target . '/tests/BaseTestCase.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Tests;\n\nuse PHPUnit\\Framework\\TestCase;\n\nabstract class BaseTestCase extends TestCase\n{\n}\n");
    }

    private function composerPackageName(string $name): string
    {
        $parts = array_values(array_filter(explode('/', str_replace('\\\\', '/', strtolower($name))), static fn(string $part): bool => $part !== ''));

        $vendor = $this->composerSlug($parts[0] ?? 'jb');
        $package = $this->composerSlug($parts[count($parts) - 1] ?? 'api');

        if ($vendor === '') {
            $vendor = 'jb';
        }

        if ($package === '') {
            $package = 'api';
        }

        return $vendor . '/' . $package;
    }

    private function composerSlug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($slug, '-');
    }

    private function launcher(): string
    {
        return <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

chdir(__DIR__);

$autoload = __DIR__ . '/vendor/autoload.php';
$frameworkPath = __DIR__ . '/vendor/jb/framework';

if (!is_file($autoload) || !is_dir($frameworkPath)) {
    fwrite(STDERR, "Dependencias no instaladas. Ejecuta: composer install\n");
    exit(1);
}

require $autoload;

exit((new Jb\Console\ConsoleApplication(__DIR__, $frameworkPath))->run($argv));
PHP;
    }

    private function phpunitXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
    }
}
