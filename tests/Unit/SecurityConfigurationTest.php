<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Core\Application;
use Jb\Core\HttpException;
use Jb\Tests\BaseTestCase;

class SecurityConfigurationTest extends BaseTestCase
{
    public function test_fails_if_jwt_secret_is_default_in_production(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('JWT_SECRET debe configurarse');

        $basePath = $this->createTempPath('security-test-');
        $this->writeFile($basePath . '/config/app.php', '<?php return ["env" => "production"];');
        $this->writeFile($basePath . '/config/auth.php', '<?php return ["jwt_secret" => "change-me"];');
        $this->writeFile($basePath . '/config/database.php', '<?php return ["driver" => "sqlite", "path" => ":memory:"];');

        $app = new Application($basePath);
        $app->bootstrap();
    }

    public function test_fails_if_jwt_secret_too_short_in_production(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('al menos 32 caracteres');

        $basePath = $this->createTempPath('security-test-');
        $this->writeFile($basePath . '/config/app.php', '<?php return ["env" => "production"];');
        $this->writeFile($basePath . '/config/auth.php', '<?php return ["jwt_secret" => "too-short"];');
        $this->writeFile($basePath . '/config/database.php', '<?php return ["driver" => "sqlite", "path" => ":memory:"];');

        $app = new Application($basePath);
        $app->bootstrap();
    }

    public function test_allows_short_secret_in_development(): void
    {
        $basePath = $this->createTempPath('security-test-');
        $this->writeFile($basePath . '/config/app.php', '<?php return ["env" => "development"];');
        $this->writeFile($basePath . '/config/auth.php', '<?php return ["jwt_secret" => "short"];');
        $this->writeFile($basePath . '/config/database.php', '<?php return ["driver" => "sqlite", "path" => ":memory:"];');

        $app = new Application($basePath);
        $app->bootstrap();

        $this->assertTrue(true); // Si llegamos aquí, pasó
    }

    public function test_allows_strong_secret_in_production(): void
    {
        $basePath = $this->createTempPath('security-test-');
        $strongSecret = bin2hex(random_bytes(32));
        
        $this->writeFile($basePath . '/config/app.php', '<?php return ["env" => "production"];');
        $this->writeFile($basePath . '/config/auth.php', '<?php return ["jwt_secret" => "' . $strongSecret . '"];');
        $this->writeFile($basePath . '/config/database.php', '<?php return ["driver" => "sqlite", "path" => ":memory:"];');

        $app = new Application($basePath);
        $app->bootstrap();

        $this->assertTrue(true); // Si llegamos aquí, pasó
    }
}
