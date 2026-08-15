<?php

declare(strict_types=1);

namespace Jb\Security\config;

use Jb\Core\Config;

class SecurityConfig
{
    /** @var array<string, mixed> */
    private static array $testing = [];

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Sobrescribe la configuración de seguridad para las pruebas.
     *
     * @param array<string, mixed> $values
     */
    public static function setForTesting(array $values): void
    {
        self::$testing = $values;
    }

    /**
     * Borra las sobreescrituras de prueba.
     */
    public static function clearTesting(): void
    {
        self::$testing = [];
    }

    /**
     * Lee un valor de seguridad.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$testing)) {
            return self::$testing[$key];
        }

        return $this->config->get('security.' . $key, $default);
    }

    /**
     * Devuelve si el módulo de seguridad está habilitado.
     */
    public function enabled(): bool
    {
        return filter_var($this->get('enabled', true), FILTER_VALIDATE_BOOL);
    }
}
