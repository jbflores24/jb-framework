<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Database\Connection;
use Jb\Tests\BaseTestCase;
use PDO;
use RuntimeException;

final class DatabaseTest extends BaseTestCase
{
    public function testConnectionWithSqliteMemoryAndTransactionCommit(): void
    {
        $config = $this->makeConfig($this->createTempPath('db-'), [], [
            'driver' => 'sqlite',
            'path' => ':memory:',
        ]);

        $connection = Connection::init($config);
        $pdo = $connection->pdo();

        $pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL)');

        $connection->transaction(static function (PDO $pdo): void {
            $statement = $pdo->prepare('INSERT INTO usuarios (nombre) VALUES (:nombre)');
            $statement->execute(['nombre' => 'Luis']);
        });

        $count = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testTransactionRollbackOnException(): void
    {
        $config = $this->makeConfig($this->createTempPath('db-rollback-'), [], [
            'driver' => 'sqlite',
            'path' => ':memory:',
        ]);

        $connection = Connection::init($config);
        $pdo = $connection->pdo();
        $pdo->exec('CREATE TABLE eventos (id INTEGER PRIMARY KEY AUTOINCREMENT, descripcion TEXT NOT NULL)');

        try {
            $connection->transaction(static function (PDO $pdo): void {
                $statement = $pdo->prepare('INSERT INTO eventos (descripcion) VALUES (:descripcion)');
                $statement->execute(['descripcion' => 'inicio']);

                throw new RuntimeException('fallo forzado');
            });
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException) {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
            $this->assertSame(0, $count);
        }
    }
}
