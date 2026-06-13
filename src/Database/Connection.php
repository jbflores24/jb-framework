<?php

declare(strict_types=1);

namespace Jb\Database;

use Jb\Core\Config;
use PDO;
use PDOException;

class Connection
{
    private static ?self $instance = null;

    private ?PDO $pdo = null;

    /** @param array<string, mixed>|null $options */
    private function __construct(
        private readonly Config $config,
        private readonly ?array $options = null
    ) {
    }

    /**
     * Initialize the singleton connection manager.
     *
     * @param array<string, mixed>|null $options
     */
    public static function init(Config $config, ?array $options = null): self
    {
        return self::$instance = new self($config, $options);
    }

    /**
     * Return the current singleton connection manager.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new PDOException('Database connection has not been initialized.');
        }

        return self::$instance;
    }

    /**
     * Return an active PDO connection.
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Reconnect and return the new PDO instance.
     */
    public function reconnect(): PDO
    {
        $this->pdo = null;

        return $this->pdo();
    }

    /**
     * Execute a callback inside a transaction.
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Return the configured database driver.
     */
    public function driver(): string
    {
        return (string) $this->config->get('database.driver', 'mysql');
    }

    private function connect(): void
    {
        $this->pdo = new PDO(
            $this->dsn(),
            (string) $this->config->get('database.username', ''),
            (string) $this->config->get('database.password', ''),
            $this->options ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    private function dsn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'sqlite:' . $this->sqlitePath(),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $this->config->get('database.host', 'localhost'),
                $this->config->get('database.port', '5432'),
                $this->config->get('database.name', '')
            ),
            default => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config->get('database.host', 'localhost'),
                $this->config->get('database.port', '3306'),
                $this->config->get('database.name', ''),
                $this->config->get('database.charset', 'utf8mb4')
            ),
        };
    }

    private function sqlitePath(): string
    {
        $path = (string) $this->config->get('database.path', 'storage/database.sqlite');
        if ($path === ':memory:' || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->config->basePath() . DIRECTORY_SEPARATOR . $path;
    }
}
