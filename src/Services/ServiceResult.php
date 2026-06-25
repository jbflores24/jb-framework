<?php

declare(strict_types=1);

namespace Jb\Services;

/**
 * Objeto tipado que reemplaza el patrón "OK: mensaje" / "ERROR: mensaje".
 *
 * Uso:
 *   if ($result->ok()) { $data = $result->data(); }
 *   else { $msg = $result->message(); $code = $result->errorCode(); }
 */
final class ServiceResult
{
    private function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly mixed $data,
        private readonly string $errorCode,
    ) {}

    // ── Constructores estáticos ──────────────────────────────────────────────

    public static function success(string $message = 'OK', mixed $data = null): self
    {
        return new self(true, $message, $data, '');
    }

    public static function fail(string $message, string $errorCode = 'SERVICE_ERROR', mixed $data = null): self
    {
        return new self(false, $message, $data, $errorCode);
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function ok(): bool
    {
        return $this->success;
    }

    public function failed(): bool
    {
        return !$this->success;
    }

    public function message(): string
    {
        return $this->message;
    }

    /** @return mixed */
    public function data(): mixed
    {
        return $this->data;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Lanza HttpException si el resultado es fallo.
     * Útil en controladores: $result->throwIfFailed(422);
     */
    public function throwIfFailed(int $httpStatus = 422): void
    {
        if ($this->failed()) {
            throw new \Jb\Core\HttpException(
                $this->message,
                $httpStatus,
                $this->errorCode
            );
        }
    }
}