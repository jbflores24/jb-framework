<?php

declare(strict_types=1);

namespace Jb\Database;

use PDO;

class QueryBuilder
{
    /** @var list<string> */
    private array $wheres = [];

    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table,
        private readonly string $driver = 'mysql'
    )
    {
    }

    /**
     * Add a where clause using a prepared placeholder.
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null && !in_array(strtoupper((string)$operator), ['IS', 'IS NOT', 'IS NULL', 'IS NOT NULL'], true)) {
            $value = $operator;
            $operator = '=';
        }

        $op = strtoupper((string)$operator);

        if ($value === null || in_array($op, ['IS NULL', 'IS NOT NULL'], true)) {
            if ($op === '=') {
                $op = 'IS NULL';
            } elseif ($op === '!=') {
                $op = 'IS NOT NULL';
            }
            $this->wheres[] = $this->wrap($column) . " " . $op;
            return $this;
        }

        if (in_array($op, ['IS', 'IS NOT'], true) && $value === null) {
            $op = $op . ' NULL';
            $this->wheres[] = $this->wrap($column) . " " . $op;
            return $this;
        }

        $placeholder = ':w_' . count($this->bindings);
        $this->wheres[] = $this->wrap($column) . " $operator $placeholder";
        $this->bindings[$placeholder] = $value;

        return $this;
    }

    /**
     * Add a WHERE IN clause.
     */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = '0 = 1';
            return $this;
        }

        $placeholders = [];
        foreach ($values as $index => $val) {
            $placeholder = ':wi_' . count($this->bindings) . '_' . $index;
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $val;
        }

        $this->wheres[] = $this->wrap($column) . ' IN (' . implode(', ', $placeholders) . ')';

        return $this;
    }

    /**
     * Add ordering to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $this->orders[] = $this->wrap($column) . ' ' . $direction;

        return $this;
    }

    /**
     * Limit the number of returned rows.
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * Return all matching rows.
     *
     * @return list<array<string, mixed>>
     */
    public function get(array $columns = ['*']): array
    {
        $statement = $this->pdo->prepare($this->selectSql($columns));
        $this->bind($statement);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Return the first matching row.
     *
     * @return array<string, mixed>|null
     */
    public function first(array $columns = ['*']): ?array
    {
        $this->limit(1);
        $rows = $this->get($columns);

        return $rows[0] ?? null;
    }

    /**
     * Insert one row and return the inserted id when available.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->wrap($this->table),
            implode(', ', array_map($this->wrap(...), $columns)),
            implode(', ', $placeholders)
        );

        $statement = $this->pdo->prepare($sql);
        $statement->execute($data);

        return $this->pdo->lastInsertId();
    }

    /**
     * Update matching rows.
     *
     * @param array<string, mixed> $data
     */
    public function update(array $data): int
    {
        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $placeholder = ':u_' . $column;
            $sets[] = $this->wrap($column) . " = $placeholder";
            $values[$placeholder] = $value;
        }

        $statement = $this->pdo->prepare('UPDATE ' . $this->wrap($this->table) . ' SET ' . implode(', ', $sets) . $this->whereSql());
        $this->bind($statement, $values);
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * Delete matching rows.
     */
    public function delete(): int
    {
        $statement = $this->pdo->prepare('DELETE FROM ' . $this->wrap($this->table) . $this->whereSql());
        $this->bind($statement);
        $statement->execute();

        return $statement->rowCount();
    }

    private function selectSql(array $columns): string
    {
        $select = $columns === ['*'] ? '*' : implode(', ', array_map($this->wrap(...), $columns));
        $sql = 'SELECT ' . $select . ' FROM ' . $this->wrap($this->table) . $this->whereSql();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit . ' OFFSET ' . ($this->offset ?? 0);
        }

        return $sql;
    }

    private function whereSql(): string
    {
        return $this->wheres === [] ? '' : ' WHERE ' . implode(' AND ', $this->wheres);
    }

    /** @param array<string, mixed> $extra */
    private function bind(\PDOStatement $statement, array $extra = []): void
    {
        foreach (array_merge($extra, $this->bindings) as $key => $value) {
            $statement->bindValue($key, $value);
        }
    }

    private function wrap(string $identifier): string
    {
        if ($identifier === '*') {
            return $identifier;
        }

        return $this->driver === 'mysql'
            ? '`' . str_replace('`', '``', $identifier) . '`'
            : '"' . str_replace('"', '""', $identifier) . '"';
    }
}
