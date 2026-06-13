<?php

declare(strict_types=1);

namespace Jb\Security\services;

use Jb\Security\config\SecurityConfig;

class CsrfService
{
    public function __construct(private readonly SecurityConfig $config)
    {
    }

    /**
     * Return whether CSRF validation is enabled for security admin actions.
     */
    public function enabled(): bool
    {
        return (bool) $this->config->get('csrf_enabled', false);
    }

    /**
     * Generate a deterministic CSRF token for a user id.
     */
    public function token(int|string $userId): string
    {
        return hash_hmac('sha256', (string) $userId, (string) $this->config->get('csrf_secret', 'change-me'));
    }

    /**
     * Validate a provided CSRF token.
     */
    public function valid(int|string $userId, ?string $token): bool
    {
        return !$this->enabled() || ($token !== null && hash_equals($this->token($userId), $token));
    }
}
