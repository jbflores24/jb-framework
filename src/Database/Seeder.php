<?php

declare(strict_types=1);

namespace Jb\Database;

abstract class Seeder
{
    public function __construct(protected readonly Connection $connection)
    {
    }

    /**
     * Execute the database seeder.
     */
    abstract public function run(): void;

    /**
     * Create a query builder for a table.
     */
    protected function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this->connection->pdo(), $table, $this->connection->driver());
    }
}
