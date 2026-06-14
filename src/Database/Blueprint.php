<?php

declare(strict_types=1);

namespace Jb\Database;

class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    /** @var list<string> */
    private array $unique = [];

    /** @var list<array{columns: list<string>, name: string}> */
    private array $compoundUnique = [];

    public function __construct(private readonly string $driver)
    {
    }

    /**
     * Add an auto-incrementing id column.
     */
    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->column($name, match ($this->driver) {
            'pgsql' => 'BIGSERIAL PRIMARY KEY',
            'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            default => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
        }, true);
    }

    /**
     * Add a variable length string column.
     */
    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->column($name, "VARCHAR($length)");
    }

    /**
     * Add an integer column.
     */
    public function integer(string $name): ColumnDefinition
    {
        return $this->column($name, 'INTEGER');
    }

    /**
     * Add a boolean column.
     */
    public function boolean(string $name): ColumnDefinition
    {
        return $this->column($name, $this->driver === 'mysql' ? 'TINYINT(1)' : 'BOOLEAN');
    }

    /**
     * Add a text column.
     */
    public function text(string $name): ColumnDefinition
    {
        return $this->column($name, 'TEXT');
    }

    /**
     * Add a timestamp column.
     */
    public function timestamp(string $name): ColumnDefinition
    {
        return $this->column($name, $this->driver === 'pgsql' ? 'TIMESTAMP(0)' : 'DATETIME');
    }

    /**
     * Add created_at and updated_at timestamp columns.
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
    }

    /**
     * Declare a compound UNIQUE constraint across multiple columns.
     *
     * @param list<string> $columns
     */
    public function unique(array $columns, string $name): void
    {
        $this->compoundUnique[] = ['columns' => $columns, 'name' => $name];
    }

    /**
     * Compile columns into SQL fragments.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        $columns = array_map(fn (ColumnDefinition $column): string => $column->toSql(), $this->columns);

        foreach ($this->unique as $column) {
            $columns[] = 'UNIQUE (' . $this->wrap($column) . ')';
        }

        foreach ($this->compoundUnique as $constraint) {
            $wrapped = implode(', ', array_map(fn (string $col): string => $this->wrap($col), $constraint['columns']));
            $columns[] = 'CONSTRAINT ' . $this->wrap($constraint['name']) . ' UNIQUE (' . $wrapped . ')';
        }

        return $columns;
    }

    private function column(string $name, string $type, bool $primary = false): ColumnDefinition
    {
        $column = new ColumnDefinition($name, $type, $this->driver, $primary, function (string $name): void {
            $this->unique[] = $name;
        });
        $this->columns[] = $column;

        return $column;
    }

    private function wrap(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}