<?php

declare(strict_types=1);

namespace Jb\RateLimit;

use Jb\Core\HttpException;
use Jb\Core\Request;

class RateLimiter
{
    public function __construct(
        private readonly string $path,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds
    ) {
    }

    /**
     * Consume one request attempt for the client IP.
     */
    public function hit(Request $request): void
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $file = $this->file($request);
        $now = time();
        $state = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $resetAt = (int) ($state['reset_at'] ?? ($now + $this->windowSeconds));
        $attempts = $resetAt <= $now ? 0 : (int) ($state['attempts'] ?? 0);
        $resetAt = $resetAt <= $now ? $now + $this->windowSeconds : $resetAt;

        if ($attempts >= $this->maxAttempts) {
            throw new HttpException('Demasiadas solicitudes.', 429, ['retry_after' => $resetAt - $now]);
        }

        file_put_contents($file, json_encode([
            'attempts' => $attempts + 1,
            'reset_at' => $resetAt,
        ], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private function file(Request $request): string
    {
        $ip = $request->server('REMOTE_ADDR', 'unknown');

        return $this->path . DIRECTORY_SEPARATOR . hash('sha256', (string) $ip) . '.json';
    }
}
