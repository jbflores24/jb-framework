<?php

declare(strict_types=1);

namespace Jb\Security\services;

use Jb\Cache\CacheInterface;
use Jb\Security\config\SecurityConfig;

class CsrfService
{
    private const TTL_SECONDS = 1800; // 30 minutos

    public function __construct(
        private readonly SecurityConfig $config,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Return whether CSRF validation is enabled for security admin actions.
     */
    public function enabled(): bool
    {
        return (bool) $this->config->get('csrf_enabled', false);
    }

    /**
     * Generate a random, single-use CSRF token bound to a user id and store it server-side.
     */
    public function token(int|string $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->cache->put($this->cacheKey($userId), $token, self::TTL_SECONDS);

        return $token;
    }

    /**
     * Validate a provided CSRF token. On success, the token is invalidated immediately
     * (single use) to prevent replay.
     */
    public function valid(int|string $userId, ?string $token): bool
    {
        if (!$this->enabled()) {
            return true;
        }

        if ($token === null) {
            return false;
        }

        $stored = $this->cache->get($this->cacheKey($userId));
        $isValid = is_string($stored) && hash_equals($stored, $token);

        if ($isValid) {
            $this->cache->forget($this->cacheKey($userId));
        }

        return $isValid;
    }

    private function cacheKey(int|string $userId): string
    {
        return 'csrf:' . $userId;
    }
}