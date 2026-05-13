<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Core\Config;
use Jb\Tests\BaseTestCase;

final class ConfigTest extends BaseTestCase
{
    public function testLoadsEnvAndConfigFiles(): void
    {
        $basePath = $this->createTempPath('config-');

        $this->writeFile($basePath . DIRECTORY_SEPARATOR . '.env', "APP_ENV=testing\nAPP_DEBUG=true\n");
        $this->writeFile(
            $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
            "<?php\n\nreturn [\n    'name' => 'JB Test',\n    'env' => getenv('APP_ENV') ?: 'production',\n    'debug' => getenv('APP_DEBUG') ?: false,\n];\n"
        );

        $config = new Config($basePath);
        $config->load();

        $this->assertSame('JB Test', $config->get('app.name'));
        $this->assertSame('testing', $config->get('app.env'));
        $this->assertTrue($config->isDebug());
        $this->assertFalse($config->isProduction());
    }

    public function testSetAndGetWithDotNotation(): void
    {
        $config = new Config($this->createTempPath('set-get-'));
        $config->set('database.driver', 'sqlite');
        $config->set('database.path', ':memory:');

        $this->assertSame('sqlite', $config->get('database.driver'));
        $this->assertSame(':memory:', $config->get('database.path'));
        $this->assertSame('default', $config->get('database.missing', 'default'));
    }
}
