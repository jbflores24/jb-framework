<?php

declare(strict_types=1);

namespace Jb\Cache;

interface CacheInterface
{
    /**
     * Read a cached value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a cached value.
     */
    public function put(string $key, mixed $value, int $ttl = 3600): void;

    /**
     * Remove one cached value.
     */
    public function forget(string $key): void;

    /**
     * Clear the cache store.
     */
    public function clear(): void;
}
