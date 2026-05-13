<?php

declare(strict_types=1);

namespace Jb\Core;

class Response
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        private readonly mixed $payload,
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    /**
     * Create a successful JSON response.
     *
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data = null, string $message = 'OK', array $meta = []): self
    {
        return new self([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /**
     * Create an error JSON response.
     *
     * @param array<string, mixed> $errors
     */
    public static function error(string $message, int $status = 400, array $errors = []): self
    {
        return new self([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
