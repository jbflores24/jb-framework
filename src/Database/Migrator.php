<?php

declare(strict_types=1);

namespace Jb\Database;

use RuntimeException;

class Migrator
{
    private const TABLE = 'jb_migrations';

    public function __construct(private readonly Connection $connection, private readonly string $path) {}

    /**
     * Run all pending migrations and return executed names.
     *
     * @return list<string>
     */
    public function run(): array
    {
        $this->ensureTable();
        $ran = $this->ran();
        $executed = [];
        $batch = $this->lastBatch() + 1;

        foreach ($this->files() as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = $this->load($file);
            $pdo = $this->connection->pdo();
            $pdo->beginTransaction();
            try {
                $migration->up();
                $this->record($name, $batch);
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * Roll back the last migration batch.
     *
     * @return list<string>
     */
    public function rollback(): array
    {
        $this->ensureTable();
        $batch = $this->lastBatch();
        if ($batch === 0) {
            return [];
        }

        $rolledBack = [];
        foreach (array_reverse($this->batchMigrations($batch)) as $name) {
            $file = $this->path . DIRECTORY_SEPARATOR . $name . '.php';
            $migration = $this->load($file);
            $pdo = $this->connection->pdo();
            $pdo->beginTransaction();
            try {
                $migration->down();
                $this->forget($name);
                if ($pdo->inTransaction()) {
                    $pdo->commit();
                }
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Return migration status rows.
     *
     * @return list<array{name: string, ran: bool}>
     */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->ran();

        return array_map(fn(string $file): array => [
            'name' => basename($file, '.php'),
            'ran' => in_array(basename($file, '.php'), $ran, true),
        ], $this->files());
    }

    private function ensureTable(): void
    {
        $id = $this->connection->driver() === 'mysql' ? '`id`' : '"id"';
        $migration = $this->connection->driver() === 'mysql' ? '`migration`' : '"migration"';
        $batch = $this->connection->driver() === 'mysql' ? '`batch`' : '"batch"';
        $idType = $this->connection->driver() === 'pgsql' ? 'BIGSERIAL' : ($this->connection->driver() === 'sqlite' ? 'INTEGER' : 'BIGINT AUTO_INCREMENT');
        $this->connection->pdo()->exec("CREATE TABLE IF NOT EXISTS {$this->wrap(self::TABLE)} ({$id} {$idType} PRIMARY KEY, {$migration} VARCHAR(255) NOT NULL, {$batch} INTEGER NOT NULL)");
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);

        return $files;
    }

    private function load(string $file): Migration
    {
        if (!is_file($file)) {
            throw new RuntimeException("Migration file not found: $file");
        }

        $before = get_declared_classes();
        $migration = require $file;
        if (!$migration instanceof Migration) {
            foreach (array_diff(get_declared_classes(), $before) as $class) {
                if (is_subclass_of($class, Migration::class)) {
                    return new $class($this->connection);
                }
            }

            throw new RuntimeException("Migration must return or declare a Migration instance: $file");
        }

        return $migration;
    }

    /** @return list<string> */
    private function ran(): array
    {
        return array_column($this->connection->pdo()->query('SELECT migration FROM ' . $this->wrap(self::TABLE) . ' ORDER BY migration')->fetchAll(), 'migration');
    }

    private function record(string $name, int $batch): void
    {
        $statement = $this->connection->pdo()->prepare('INSERT INTO ' . $this->wrap(self::TABLE) . ' (migration, batch) VALUES (:migration, :batch)');
        $statement->execute(['migration' => $name, 'batch' => $batch]);
    }

    private function forget(string $name): void
    {
        $statement = $this->connection->pdo()->prepare('DELETE FROM ' . $this->wrap(self::TABLE) . ' WHERE migration = :migration');
        $statement->execute(['migration' => $name]);
    }

    private function lastBatch(): int
    {
        return (int) $this->connection->pdo()->query('SELECT COALESCE(MAX(batch), 0) FROM ' . $this->wrap(self::TABLE))->fetchColumn();
    }

    /** @return list<string> */
    private function batchMigrations(int $batch): array
    {
        $statement = $this->connection->pdo()->prepare('SELECT migration FROM ' . $this->wrap(self::TABLE) . ' WHERE batch = :batch ORDER BY migration');
        $statement->execute(['batch' => $batch]);

        return array_column($statement->fetchAll(), 'migration');
    }

    private function wrap(string $identifier): string
    {
        return $this->connection->driver() === 'mysql'
            ? '`' . str_replace('`', '``', $identifier) . '`'
            : '"' . str_replace('"', '""', $identifier) . '"';
    }
}
