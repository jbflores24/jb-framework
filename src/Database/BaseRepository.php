<?php

declare(strict_types=1);

namespace Jb\Database;

class BaseRepository
{
    public function __construct(
        protected readonly Connection $connection,
        protected readonly string $table,
        protected readonly string $primaryKey = 'id'
    ) {
    }

    /**
     * Create a new query builder for the repository table.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->connection->pdo(), $this->table, $this->connection->driver());
    }

    /**
     * Return all rows.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->query()->get();
    }

    /**
     * Find one row by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function find(int|string $id): ?array
    {
        return $this->query()->where($this->primaryKey, $id)->first();
    }

    /**
     * Insert a row and return its id.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        return $this->query()->insert($data);
    }

    /**
     * Update one row by primary key.
     *
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): int
    {
        return $this->query()->where($this->primaryKey, $id)->update($data);
    }

    /**
     * Delete one row by primary key.
     */
    public function delete(int|string $id): int
    {
        return $this->query()->where($this->primaryKey, $id)->delete();
    }
}
