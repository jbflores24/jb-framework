<?php

declare(strict_types=1);

namespace Jb\Core;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 500,
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Return the HTTP status code that should be sent to the client.
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Return additional structured data for logs or JSON responses.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
