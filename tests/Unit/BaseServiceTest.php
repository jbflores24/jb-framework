<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jb\Tests\BaseTestCase;
use Jb\Services\ServiceResult;
use Jb\Services\BaseService;
use Jb\Database\Connection;

/**
 * Suite: ServiceResult + BaseService (con PDO en memoria SQLite).
 */
class BaseServiceTest extends BaseTestCase
{
    // ── ServiceResult ────────────────────────────────────────────────────────

    public function test_ok_result_is_successful(): void
    {
        $r = ServiceResult::success('Todo bien', ['id' => 1]);

        $this->assertTrue($r->ok());
        $this->assertFalse($r->failed());
        $this->assertSame('Todo bien', $r->message());
        $this->assertSame(['id' => 1], $r->data());
        $this->assertSame('', $r->errorCode());
    }

    public function test_fail_result_is_failed(): void
    {
        $r = ServiceResult::fail('Algo salió mal', 'VALIDATION_ERROR');

        $this->assertFalse($r->ok());
        $this->assertTrue($r->failed());
        $this->assertSame('Algo salió mal', $r->message());
        $this->assertSame('VALIDATION_ERROR', $r->errorCode());
        $this->assertNull($r->data());
    }

    public function test_throw_if_failed_throws_http_exception(): void
    {
        $this->expectException(\Jb\Core\HttpException::class);

        ServiceResult::fail('No permitido', 'FORBIDDEN')->throwIfFailed(403);
    }

    public function test_throw_if_failed_does_not_throw_on_success(): void
    {
        $this->expectNotToPerformAssertions();

        ServiceResult::success('OK')->throwIfFailed(422);
    }

    // ── BaseService (transacción) ────────────────────────────────────────────

    private function makeService(): BaseService
    {
        // SQLite en memoria — no necesita servidor
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Stub de Connection que expone el PDO y driver
        $conn = new class($pdo) extends Connection {
            public function __construct(private \PDO $p)
            {
                // no llamamos al padre para no leer .env
            }
            public function pdo(): \PDO { return $this->p; }
            public function driver(): string { return 'sqlite'; }
        };

        // Implementación concreta anónima de BaseService
        return new class($conn) extends BaseService {
            public function runOk(): ServiceResult
            {
                return $this->transaction(fn() => $this->ok('Commit OK', 42));
            }

            public function runFail(): ServiceResult
            {
                return $this->transaction(fn() => $this->fail('Fallo negocio', 'BIZ_ERROR'));
            }

            public function runException(): ServiceResult
            {
                return $this->transaction(function (): ServiceResult {
                    throw new \RuntimeException('Excepción inesperada');
                });
            }

            public function runAttemptOk(): ServiceResult
            {
                return $this->attempt(fn() => $this->ok('Attempt OK'));
            }

            public function runAttemptException(): ServiceResult
            {
                return $this->attempt(function (): ServiceResult {
                    throw new \RuntimeException('Error en attempt');
                });
            }
        };
    }

    public function test_transaction_commits_on_ok(): void
    {
        $r = $this->makeService()->runOk();

        $this->assertTrue($r->ok());
        $this->assertSame('Commit OK', $r->message());
        $this->assertSame(42, $r->data());
    }

    public function test_transaction_rollbacks_on_fail_result(): void
    {
        $r = $this->makeService()->runFail();

        $this->assertTrue($r->failed());
        $this->assertSame('BIZ_ERROR', $r->errorCode());
    }

    public function test_transaction_rollbacks_on_exception(): void
    {
        $r = $this->makeService()->runException();

        $this->assertTrue($r->failed());
        $this->assertSame('TRANSACTION_ERROR', $r->errorCode());
        $this->assertStringContainsString('Excepción inesperada', $r->message());
    }

    public function test_attempt_returns_ok_result(): void
    {
        $r = $this->makeService()->runAttemptOk();

        $this->assertTrue($r->ok());
    }

    public function test_attempt_catches_exception(): void
    {
        $r = $this->makeService()->runAttemptException();

        $this->assertTrue($r->failed());
        $this->assertSame('UNEXPECTED_ERROR', $r->errorCode());
    }
}