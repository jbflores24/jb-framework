<?php

declare(strict_types=1);

namespace Jb\Services;

use Jb\Database\Connection;
use Throwable;

/**
 * Clase base para servicios de dominio.
 *
 * Provee:
 *   - transaction(callable $fn): ServiceResult  → ejecuta $fn dentro de una TX
 *   - ok(...)  / fail(...)                      → atajos a ServiceResult
 *   - attempt(callable $fn): ServiceResult      → captura excepciones genéricas
 */
abstract class BaseService
{
    public function __construct(
        protected readonly Connection $connection
    ) {}

    // ── Helpers de resultado ─────────────────────────────────────────────────

    protected function ok(string $message = 'OK', mixed $data = null): ServiceResult
    {
        return ServiceResult::success($message, $data);
    }

    protected function fail(string $message, string $errorCode = 'SERVICE_ERROR', mixed $data = null): ServiceResult
    {
        return ServiceResult::fail($message, $errorCode, $data);
    }

    // ── Transacción ──────────────────────────────────────────────────────────

    /**
     * Ejecuta $fn dentro de una transacción PDO.
     * Si $fn lanza una excepción → rollback → retorna fail().
     * Si $fn retorna ServiceResult::fail() → rollback → retorna el mismo fallo.
     * Si $fn retorna ServiceResult::ok() → commit → retorna el resultado.
     *
     * @param callable(): ServiceResult $fn
     */
    protected function transaction(callable $fn): ServiceResult
    {
        $pdo = $this->connection->pdo();

        $pdo->beginTransaction();

        try {
            $result = $fn();

            if ($result->failed()) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                return $result;
            }

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->fail($e->getMessage(), 'TRANSACTION_ERROR');
        }
    }

    /**
     * Ejecuta $fn capturando cualquier excepción y convirtiéndola a fail().
     * Úsalo cuando NO necesitas transacción pero sí quieres manejo uniforme.
     *
     * @param callable(): ServiceResult $fn
     */
    protected function attempt(callable $fn): ServiceResult
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            return $this->fail($e->getMessage(), 'UNEXPECTED_ERROR');
        }
    }
}