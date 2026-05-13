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
    public static function error(
        string $message,
        int $status = 400,
        array $errors = [],
        string $code = 'ERROR',
        ?string $traceId = null
    ): self
    {
        $payload = [
            'status' => 'error',
            'code' => $code,
            'message' => $message,
            'errors' => $errors,
        ];

        if ($traceId !== null) {
            $payload['trace_id'] = $traceId;
        }

        return new self($payload, $status);
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

        // JSON_UNESCAPED_UNICODE: permite caracteres UTF-8 sin escape
        // JSON_UNESCAPED_SLASHES: permite "/" sin escape
        // JSON_THROW_ON_ERROR: lanza excepción si falla en lugar de retornar false
        echo json_encode(
            $this->payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Agregar un header a la respuesta (retorna nueva instancia)
     *
     * @param string $name Nombre del header
     * @param string $value Valor del header
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = array_merge($this->headers, [$name => $value]);
        return new self($this->payload, $this->status, $headers);
    }

    /**
     * Obtener headers para testing
     *
     * @return array<string, mixed>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Obtener payload para testing
     *
     * @return mixed
     */
    public function data(): mixed
    {
        return $this->payload;
    }
}
