<?php

declare(strict_types=1);

namespace Jb\Core;

class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Load .env and PHP config files from the application base path.
     */
    public function load(): void
    {
        $this->loadEnv($this->basePath . DIRECTORY_SEPARATOR . '.env');
        $this->loadConfigDirectory($this->basePath . DIRECTORY_SEPARATOR . 'config');
    }

    /**
     * Read a configuration value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value using dot notation.
     */
    public function set(string $key, mixed $value): void
    {
        $target = &$this->items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    /**
     * Return true when the app is running with debug output enabled.
     */
    public function isDebug(): bool
    {
        return filter_var($this->get('app.debug', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Return true when APP_ENV is production.
     */
    public function isProduction(): bool
    {
        return $this->get('app.env', 'production') === 'production';
    }

    /**
     * Return the application base path.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    private function loadEnv(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    private function loadConfigDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (glob($path . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $this->items[$name] = require $file;
        }
    }
}
