<?php

declare(strict_types=1);

namespace Jb\Database;

class ColumnDefinition
{
    private bool $nullable = false;

    private mixed $default = null;

    private bool $hasDefault = false;

    public function __construct(
        private readonly string $name,
        private readonly string $type,
        private readonly string $driver,
        private readonly bool $primary,
        private readonly mixed $uniqueCallback
    ) {
    }

    /**
     * Mark the column as nullable.
     */
    public function nullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    /**
     * Add a default value.
     */
    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    /**
     * Add a unique constraint for this column.
     */
    public function unique(): self
    {
        ($this->uniqueCallback)($this->name);

        return $this;
    }

    /**
     * Compile the column to SQL.
     */
    public function toSql(): string
    {
        $sql = $this->wrap($this->name) . ' ' . $this->type;

        if (!$this->primary) {
            $sql .= $this->nullable ? ' NULL' : ' NOT NULL';
        }

        if ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($this->default);
        }

        return $sql;
    }

    private function formatDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value === null) {
            return 'NULL';
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    private function wrap(string $identifier): string
    {
        return $this->driver === 'mysql'
            ? '`' . str_replace('`', '``', $identifier) . '`'
            : '"' . str_replace('"', '""', $identifier) . '"';
    }
}
