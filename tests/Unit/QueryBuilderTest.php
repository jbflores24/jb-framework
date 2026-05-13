<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Database\QueryBuilder;
use Jb\Tests\BaseTestCase;
use PDO;

final class QueryBuilderTest extends BaseTestCase
{
    public function testInsertSelectUpdateAndDeleteWithSqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, email TEXT NOT NULL)');

        $query = new QueryBuilder($pdo, 'usuarios', 'sqlite');
        $id = $query->insert(['nombre' => 'Ana', 'email' => 'ana@example.com']);

        $this->assertSame('1', $id);

        $row = (new QueryBuilder($pdo, 'usuarios', 'sqlite'))
            ->where('id', 1)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Ana', $row['nombre']);

        $updated = (new QueryBuilder($pdo, 'usuarios', 'sqlite'))
            ->where('id', 1)
            ->update(['nombre' => 'Ana Maria']);

        $this->assertSame(1, $updated);

        $deleted = (new QueryBuilder($pdo, 'usuarios', 'sqlite'))
            ->where('id', 1)
            ->delete();

        $this->assertSame(1, $deleted);
    }

    public function testOrderByAndLimit(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL)');
        $pdo->exec("INSERT INTO items (nombre) VALUES ('B'), ('A'), ('C')");

        $rows = (new QueryBuilder($pdo, 'items', 'sqlite'))
            ->orderBy('nombre', 'asc')
            ->limit(2)
            ->get(['id', 'nombre']);

        $this->assertCount(2, $rows);
        $this->assertSame('A', $rows[0]['nombre']);
        $this->assertSame('B', $rows[1]['nombre']);
    }
}
