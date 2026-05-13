<?php

declare(strict_types=1);

namespace Jb\Auth;

use Jb\Core\HttpException;
use Jb\Logging\DistributedLogger;

/**
 * Handle JWT token generation, validation, and refresh logic.
 */
class AuthService
{
    // Token types
    private const TYPE_ACCESS = 'access';
    private const TYPE_REFRESH = 'refresh';

    // Default TTLs
    private const DEFAULT_ACCESS_TTL = 3600;      // 1 hour
    private const DEFAULT_REFRESH_TTL = 2592000;  // 30 days

    public function __construct(
        private readonly JWT $jwt,
        private readonly int $accessTtl = self::DEFAULT_ACCESS_TTL,
        private readonly int $refreshTtl = self::DEFAULT_REFRESH_TTL,
        private readonly ?TokenRevocationList $revocationList = null,
        private readonly ?DistributedLogger $distributedLogger = null,
    ) {
    }

    /**
     * Generate access and refresh tokens for a user.
     *
     * @param array<string, mixed> $claims User claims (sub, email, permissions, etc)
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function generateTokens(array $claims): array
    {
        $accessToken = $this->jwt->encode(
            array_merge(['type' => self::TYPE_ACCESS], $claims),
            $this->accessTtl
        );

        $refreshToken = $this->jwt->encode(
            array_merge(['type' => self::TYPE_REFRESH, 'sub' => $claims['sub'] ?? null], []),
            $this->refreshTtl
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->accessTtl,
        ];
    }

    /**
     * Validate an access token and return claims.
     *
     * @return array<string, mixed>
     */
    public function validateAccessToken(string $token): array
    {
        if ($this->revocationList !== null && $this->revocationList->isRevoked($token)) {
            throw new HttpException('Token revocado.', 401, 'TOKEN_REVOKED');
        }

        $claims = $this->jwt->decode($token);

        if (($claims['type'] ?? null) !== self::TYPE_ACCESS) {
            throw new HttpException('Token de acceso inválido.', 401, 'INVALID_TOKEN');
        }

        return $claims;
    }

    /**
     * Validate a refresh token and return new access token.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        if ($this->revocationList !== null && $this->revocationList->isRevoked($refreshToken)) {
            throw new HttpException('Refresh token revocado.', 401, 'TOKEN_REVOKED');
        }

        $claims = $this->jwt->decode($refreshToken);

        if (($claims['type'] ?? null) !== self::TYPE_REFRESH) {
            throw new HttpException('Refresh token inválido.', 401, 'INVALID_REFRESH_TOKEN');
        }

        $sub = $claims['sub'] ?? null;
        if ($sub === null) {
            throw new HttpException('Refresh token corrupto.', 401, 'INVALID_REFRESH_TOKEN');
        }

        $accessToken = $this->jwt->encode(
            ['type' => self::TYPE_ACCESS, 'sub' => $sub, 'refreshed_at' => time()],
            $this->accessTtl
        );

        return [
            'access_token' => $accessToken,
            'expires_in' => $this->accessTtl,
        ];
    }

    /**
     * Revoke a token until its expiration timestamp.
     */
    public function revokeToken(string $token): void
    {
        if ($this->revocationList === null) {
            return;
        }

        $claims = $this->jwt->decode($token);
        $expiresAt = isset($claims['exp']) ? (int) $claims['exp'] : time();
        $this->revocationList->revoke($token, $expiresAt, [
            'token_type' => $claims['type'] ?? 'unknown',
            'user_id' => $claims['sub'] ?? null,
            'trace_id' => $claims['trace_id'] ?? null,
        ]);
        $this->revocationList->cleanup();

        if ($this->distributedLogger !== null) {
            $this->distributedLogger->logAuthentication([
                'trace_id' => $claims['trace_id'] ?? 'unknown',
                'action' => 'TOKEN_REVOKE',
                'user_id' => $claims['sub'] ?? null,
                'email' => $claims['email'] ?? null,
                'success' => true,
                'reason' => null,
            ]);
        }
    }

    /**
     * Extract bearer token from Authorization header.
     *
     * @throws HttpException If token is missing or malformed
     */
    public static function extractBearerToken(string $authorization): string
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new HttpException('Token de autenticación requerido.', 401, 'MISSING_TOKEN');
        }

        return $matches[1];
    }
}
