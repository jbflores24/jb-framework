<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jb\Cache\CacheInterface;
use Jb\Core\Config;
use Jb\Security\config\SecurityConfig;
use Jb\Security\services\CsrfService;
use Jb\Tests\BaseTestCase;

class CsrfServiceTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        SecurityConfig::clearTesting();
    }

    public function test_token_is_random_and_single_use(): void
    {
        SecurityConfig::setForTesting([
            'csrf_enabled' => true,
        ]);

        $service = $this->makeService();

        $token1 = $service->token(10);
        $token2 = $service->token(10);

        $this->assertNotSame($token1, $token2);
        $this->assertTrue($service->valid(10, $token2));
        $this->assertFalse($service->valid(10, $token2));
    }

    public function test_token_for_one_user_is_not_valid_for_another_user(): void
    {
        SecurityConfig::setForTesting([
            'csrf_enabled' => true,
        ]);

        $service = $this->makeService();
        $token = $service->token(10);

        $this->assertFalse($service->valid(20, $token));
    }

    private function makeService(): CsrfService
    {
        $config = new SecurityConfig(new Config(sys_get_temp_dir()));

        return new CsrfService($config, $this->makeCache());
    }

    private function makeCache(): CacheInterface
    {
        return new class implements CacheInterface {
            /** @var array<string, mixed> */
            private array $values = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }

            public function put(string $key, mixed $value, int $ttl = 3600): void
            {
                $this->values[$key] = $value;
            }

            public function forget(string $key): void
            {
                unset($this->values[$key]);
            }

            public function clear(): void
            {
                $this->values = [];
            }
        };
    }
}