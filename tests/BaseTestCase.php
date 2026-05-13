<?php

declare(strict_types=1);

namespace Jb\Tests;

use Jb\Core\Config;
use Jb\Core\Response;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class BaseTestCase extends TestCase
{
    /** @var list<string> */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            $this->deleteDirectory($path);
        }

        $this->tempPaths = [];
        parent::tearDown();
    }

    protected function createTempPath(string $prefix = 'jb-tests-'): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'jb-framework';
        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        $path = $base . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
        mkdir($path, 0777, true);
        $this->tempPaths[] = $path;

        return $path;
    }

    protected function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $content);
    }

    protected function makeConfig(string $basePath, array $app = [], array $database = []): Config
    {
        $config = new Config($basePath);
        foreach ($app as $key => $value) {
            $config->set('app.' . $key, $value);
        }

        foreach ($database as $key => $value) {
            $config->set('database.' . $key, $value);
        }

        return $config;
    }

    /** @return array<string, mixed> */
    protected function responsePayload(Response $response): array
    {
        $reflection = new ReflectionClass($response);
        $property = $reflection->getProperty('payload');
        $property->setAccessible(true);

        return $property->getValue($response);
    }

    protected function responseStatus(Response $response): int
    {
        $reflection = new ReflectionClass($response);
        $property = $reflection->getProperty('status');
        $property->setAccessible(true);

        return $property->getValue($response);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $current = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($current)) {
                $this->deleteDirectory($current);
                continue;
            }

            @unlink($current);
        }

        @rmdir($path);
    }
}
