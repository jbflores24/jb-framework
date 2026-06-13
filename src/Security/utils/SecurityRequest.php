<?php

declare(strict_types=1);

namespace Jb\Security\utils;

use Jb\Core\Request;

class SecurityRequest
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public readonly string $ip,
        public readonly string $method,
        public readonly string $path,
        public readonly ?string $userAgent,
        public readonly string $fingerprint,
        public readonly array $query,
        public readonly array $body,
        public readonly int $contentLength,
        public readonly ?int $userId
    ) {
    }

    /**
     * Build a security request DTO from the framework request.
     */
    public static function fromRequest(Request $request): self
    {
        $ip = (string) $request->server('REMOTE_ADDR', '127.0.0.1');
        $userAgent = $request->header('user-agent');
        $fingerprint = hash('sha256', $ip . '|' . ($userAgent ?? '') . '|' . ($request->header('accept-language') ?? ''));
        $auth = $request->attribute('auth', []);
        $userId = is_array($auth) && isset($auth['id']) ? (int) $auth['id'] : null;

        return new self(
            $ip,
            $request->method(),
            $request->path(),
            $userAgent,
            $fingerprint,
            $_GET,
            $request->body(),
            (int) $request->server('CONTENT_LENGTH', 0),
            $userId
        );
    }
}
