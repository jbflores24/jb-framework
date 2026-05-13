<?php

declare(strict_types=1);

namespace Jb\Cache;

class FileCache implements CacheInterface
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Read a cached value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return $default;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload) || (int) $payload['expires_at'] < time()) {
            $this->forget($key);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    /**
     * Store a cached value.
     */
    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }

        file_put_contents($this->file($key), json_encode([
            'expires_at' => time() + $ttl,
            'value' => $value,
        ], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    /**
     * Remove one cached value.
     */
    public function forget(string $key): void
    {
        $file = $this->file($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /**
     * Clear the cache store.
     */
    public function clear(): void
    {
        foreach (glob($this->path . DIRECTORY_SEPARATOR . '*.cache') ?: [] as $file) {
            unlink($file);
        }
    }

    private function file(string $key): string
    {
        return $this->path . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
