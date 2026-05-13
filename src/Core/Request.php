<?php

declare(strict_types=1);

namespace Jb\Core;

class Request
{
    /** @param array<string, mixed> $routeParams */
    public function __construct(
        private readonly array $server,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
        private array $routeParams = [],
        private ?string $pathOverride = null,
        private array $attributes = []
    ) {
    }

    /**
     * Build a request instance from PHP globals.
     */
    public static function capture(): self
    {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $body = is_array($json) ? $json : $_POST;

        return new self($_SERVER, $_GET, $body, self::detectHeaders());
    }

    /**
     * Return the HTTP method in uppercase.
     */
    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * Return the normalized request path without query string.
     */
    public function path(): string
    {
        if ($this->pathOverride !== null) {
            return $this->pathOverride;
        }

        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return '/' . trim($path, '/');
    }

    /**
     * Read an input value from route, body, or query data.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Return all decoded JSON or form body values.
     *
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * Return the value of one header.
     */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * Read one server value.
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Return a request attribute.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Return a copy of the request with one additional attribute.
     */
    public function withAttribute(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;

        return $clone;
    }

    /**
     * Store route parameters matched by the router.
     *
     * @param array<string, string> $params
     */
    public function withRouteParams(array $params): self
    {
        $clone = clone $this;
        $clone->routeParams = $params;

        return $clone;
    }

    /**
     * Return a copy of the request with a normalized path.
     */
    public function withPath(string $path): self
    {
        $clone = clone $this;
        $clone->pathOverride = '/' . trim($path, '/');

        return $clone;
    }

    /** @return array<string, string> */
    private static function detectHeaders(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = (string) $value;
        }

        return $normalized;
    }
}
