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

    /**
     * Load related rows from another table where `$foreignKey` matches `$id`
     * (one-to-many / hasMany relationship).
     *
     * @return Collection
     */
    protected function hasMany(string $relatedTable, string $foreignKey, int|string $id): Collection
    {
        $rows = (new QueryBuilder($this->connection->pdo(), $relatedTable, $this->connection->driver()))
            ->where($foreignKey, $id)
            ->get();

        return new Collection($rows);
    }

    /**
     * Load a single related row (many-to-one / belongsTo relationship).
     *
     * @return array<string, mixed>|null
     */
    protected function belongsTo(string $relatedTable, string $localKeyColumn, mixed $value, string $relatedPrimaryKey = 'id'): ?array
    {
        if ($value === null) {
            return null;
        }

        return (new QueryBuilder($this->connection->pdo(), $relatedTable, $this->connection->driver()))
            ->where($relatedPrimaryKey, $value)
            ->first();
    }

    /**
     * Eager-load a relation onto a list of rows, avoiding N+1 queries.
     *
     * For each row, reads `$localKey` and batches a single query against
     * `$relatedTable` filtered by `$foreignKey IN (...)`. Attaches the
     * result(s) under `$as` on each row.
     *
     * @param list<array<string, mixed>> $rows
     * @param 'one'|'many' $type
     * @return list<array<string, mixed>>
     */
    protected function eagerLoad(
        array $rows,
        string $relatedTable,
        string $localKey,
        string $foreignKey,
        string $as,
        string $type = 'many'
    ): array {
        if ($rows === []) {
            return $rows;
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn (array $row): mixed => $row[$localKey] ?? null, $rows),
            static fn (mixed $v): bool => $v !== null
        )));

        if ($ids === []) {
            foreach ($rows as &$row) {
                $row[$as] = $type === 'many' ? [] : null;
            }

            return $rows;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s IN (%s)',
            $this->connection->driver() === 'mysql' ? "`$relatedTable`" : $relatedTable,
            $this->connection->driver() === 'mysql' ? "`$foreignKey`" : $foreignKey,
            $placeholders
        );

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($ids);
        $related = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($related as $relatedRow) {
            $grouped[$relatedRow[$foreignKey]][] = $relatedRow;
        }

        foreach ($rows as &$row) {
            $key = $row[$localKey] ?? null;
            $matches = $grouped[$key] ?? [];

            $row[$as] = $type === 'many' ? $matches : ($matches[0] ?? null);
        }

        return $rows;
    }
}
