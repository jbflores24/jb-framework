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
     * Devuelve si la validación CSRF está habilitada para las acciones de administración de seguridad.
     */
    public function enabled(): bool
    {
        return (bool) $this->config->get('csrf_enabled', false);
    }

    /**
     * Genera un token CSRF aleatorio, de un solo uso, ligado a un ID de usuario y lo almacena del lado del servidor.
     */
    public function token(int|string $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->cache->put($this->cacheKey($userId), $token, self::TTL_SECONDS);

        return $token;
    }

    /**
     * Valida un token CSRF proporcionado. Si la validación es correcta, el token se invalida de inmediato
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