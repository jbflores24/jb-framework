<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jb\Core\Application;
use Jb\Core\HttpException;
use Jb\Tests\BaseTestCase;

class ApplicationSecurityTest extends BaseTestCase
{
    public function test_bootstrap_allows_local_environment_with_default_csrf_secret(): void
    {
        $basePath = $this->makeProjectBasePath('local');

        $app = new Application($basePath);

        $this->assertSame($app, $app->bootstrap());
    }

    public function test_bootstrap_blocks_production_when_csrf_secret_is_default_and_csrf_is_enabled(): void
    {
        $basePath = $this->makeProjectBasePath('production');

        $app = new Application($basePath);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('csrf_secret debe configurarse con un valor seguro en producción');

        $app->bootstrap();
    }

    private function makeProjectBasePath(string $env): string
    {
        $basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jb-framework-' . $env . '-' . bin2hex(random_bytes(4));
        $configPath = $basePath . DIRECTORY_SEPARATOR . 'config';

        mkdir($configPath, 0777, true);

        $this->writeConfig($configPath . DIRECTORY_SEPARATOR . 'app.php', [
            'env' => $env,
        ]);
        $this->writeConfig($configPath . DIRECTORY_SEPARATOR . 'auth.php', [
            'jwt_secret' => str_repeat('a', 32),
        ]);
        $this->writeConfig($configPath . DIRECTORY_SEPARATOR . 'security.php', [
            'csrf_enabled' => true,
            'csrf_secret' => 'change-me',
        ]);

        return $basePath;
    }

    private function writeConfig(string $path, array $values): void
    {
        $export = var_export($values, true);
        $content = "<?php\n\nreturn {$export};\n";
        file_put_contents($path, $content);
    }
}