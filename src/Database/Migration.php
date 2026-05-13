<?php

declare(strict_types=1);

namespace Jb\Database;

abstract class Migration
{
    public function __construct(protected readonly Connection $connection)
    {
    }

    /**
     * Execute the migration.
     */
    abstract public function up(): void;

    /**
     * Reverse the migration.
     */
    abstract public function down(): void;

    /**
     * Create a database table.
     */
    protected function create(string $table, callable $definition): void
    {
        $blueprint = new Blueprint($this->connection->driver());
        $definition($blueprint);
        $sql = 'CREATE TABLE ' . $this->wrap($table) . ' (' . implode(', ', $blueprint->columns()) . ')';
        $this->connection->pdo()->exec($sql);
    }

    /**
     * Drop a database table if it exists.
     */
    protected function drop(string $table): void
    {
        $this->connection->pdo()->exec('DROP TABLE IF EXISTS ' . $this->wrap($table));
    }

    /**
     * Execute raw SQL for advanced migrations.
     */
    protected function statement(string $sql): void
    {
        $this->connection->pdo()->exec($sql);
    }

    private function wrap(string $identifier): string
    {
        return $this->connection->driver() === 'mysql'
            ? '`' . str_replace('`', '``', $identifier) . '`'
            : '"' . str_replace('"', '""', $identifier) . '"';
    }
}
