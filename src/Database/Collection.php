<?php

declare(strict_types=1);

namespace Jb\Database;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Lightweight collection wrapper for arrays of associative arrays
 * (typically rows returned by QueryBuilder/BaseRepository).
 *
 * @implements ArrayAccess<int, array<string, mixed>>
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(private array $items = [])
    {
    }

    /** @param list<array<string, mixed>> $items */
    public static function make(array $items): self
    {
        return new self($items);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function first(): ?array
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?array
    {
        return $this->items === [] ? null : $this->items[array_key_last($this->items)];
    }

    /**
     * Apply a callback to each item and return a new Collection.
     *
     * @param callable(array<string, mixed>, int): mixed $callback
     */
    public function map(callable $callback): self
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[] = $callback($item, $key);
        }

        return new self($result);
    }

    /**
     * Filter items using a callback, preserving keys reset to a list.
     *
     * @param callable(array<string, mixed>, int): bool $callback
     */
    public function filter(callable $callback): self
    {
        return new self(array_values(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH)));
    }

    /**
     * Extract a single column's values, optionally keyed by another column.
     *
     * @return array<int|string, mixed>
     */
    public function pluck(string $value, ?string $key = null): array
    {
        $result = [];
        foreach ($this->items as $item) {
            if ($key !== null) {
                $result[$item[$key]] = $item[$value] ?? null;
            } else {
                $result[] = $item[$value] ?? null;
            }
        }

        return $result;
    }

    /**
     * Group items by the value of a given column.
     *
     * @return array<int|string, list<array<string, mixed>>>
     */
    public function groupBy(string $column): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $key = $item[$column] ?? '';
            $result[$key][] = $item;
        }

        return $result;
    }

    /**
     * Index items by the value of a given column (last one wins on duplicates).
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function keyBy(string $column): array
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[$item[$column]] = $item;
        }

        return $result;
    }

    /**
     * Reduce items to a single value.
     *
     * @param callable(mixed, array<string, mixed>): mixed $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Sum a numeric column.
     */
    public function sum(string $column): float|int
    {
        return array_sum($this->pluck($column));
    }

    /**
     * Average of a numeric column, rounded to given precision.
     */
    public function avg(string $column, int $precision = 2): float
    {
        $values = array_filter($this->pluck($column), static fn (mixed $v): bool => $v !== null);

        if ($values === []) {
            return 0.0;
        }

        return round((float) array_sum($values) / count($values), $precision);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}