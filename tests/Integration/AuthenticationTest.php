<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Auth\AuthService;
use Jb\Auth\JWT;
use Jb\Core\HttpException;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive JWT authentication testing.
 */
class AuthenticationTest extends TestCase
{
    private AuthService $authService;
    private JWT $jwt;
    private string $secret = 'test-secret-key-that-is-long-enough';

    protected function setUp(): void
    {
        $this->jwt = new JWT($this->secret);
        $this->authService = new AuthService($this->jwt, 3600, 2592000);
    }

    public function test_generate_tokens_returns_access_and_refresh(): void
    {
        $tokens = $this->authService->generateTokens([
            'sub' => 1,
            'email' => 'user@example.com',
            'permissions' => ['users.read'],
        ]);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertArrayHasKey('expires_in', $tokens);
        $this->assertSame(3600, $tokens['expires_in']);
        $this->assertIsString($tokens['access_token']);
        $this->assertIsString($tokens['refresh_token']);
    }

    public function test_validate_access_token_succeeds(): void
    {
        $tokens = $this->authService->generateTokens([
            'sub' => 1,
            'email' => 'user@example.com',
            'permissions' => ['users.read'],
        ]);

        $claims = $this->authService->validateAccessToken($tokens['access_token']);

        $this->assertSame(1, $claims['sub']);
        $this->assertSame('user@example.com', $claims['email']);
        $this->assertSame(['users.read'], $claims['permissions']);
        $this->assertSame('access', $claims['type']);
    }

    public function test_validate_access_token_rejects_refresh_token(): void
    {
        $tokens = $this->authService->generateTokens([
            'sub' => 1,
            'email' => 'user@example.com',
        ]);

        $this->expectException(HttpException::class);
        $this->authService->validateAccessToken($tokens['refresh_token']);
    }

    public function test_refresh_access_token_succeeds(): void
    {
        $tokens = $this->authService->generateTokens([
            'sub' => 1,
            'email' => 'user@example.com',
        ]);

        $refreshed = $this->authService->refreshAccessToken($tokens['refresh_token']);

        $this->assertArrayHasKey('access_token', $refreshed);
        $this->assertArrayHasKey('expires_in', $refreshed);
        $this->assertNotSame($tokens['access_token'], $refreshed['access_token']);

        $claims = $this->authService->validateAccessToken($refreshed['access_token']);
        $this->assertSame(1, $claims['sub']);
        $this->assertSame('access', $claims['type']);
    }

    public function test_refresh_access_token_rejects_access_token(): void
    {
        $tokens = $this->authService->generateTokens([
            'sub' => 1,
            'email' => 'user@example.com',
        ]);

        $this->expectException(HttpException::class);
        $this->authService->refreshAccessToken($tokens['access_token']);
    }

    public function test_refresh_access_token_fails_with_expired_token(): void
    {
        // Create service with very short TTL
        $jwt = new JWT($this->secret);
        $authService = new AuthService($jwt, 3600, 1); // 1 second TTL for refresh

        $tokens = $authService->generateTokens(['sub' => 1]);

        // Wait for refresh token to expire
        sleep(2);

        $this->expectException(HttpException::class);
        $authService->refreshAccessToken($tokens['refresh_token']);
    }

    public function test_extract_bearer_token_succeeds(): void
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';
        $extracted = AuthService::extractBearerToken("Bearer $token");

        $this->assertSame($token, $extracted);
    }

    public function test_extract_bearer_token_is_case_insensitive(): void
    {
        $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...';
        $extracted = AuthService::extractBearerToken("bearer $token");

        $this->assertSame($token, $extracted);
    }

    public function test_extract_bearer_token_fails_with_missing_token(): void
    {
        $this->expectException(HttpException::class);
        AuthService::extractBearerToken('');
    }

    public function test_extract_bearer_token_fails_with_invalid_format(): void
    {
        $this->expectException(HttpException::class);
        AuthService::extractBearerToken('Basic xyz123');
    }

    public function test_jwt_decode_includes_iat_and_exp(): void
    {
        $token = $this->jwt->encode(['sub' => 1], 3600);
        $claims = $this->jwt->decode($token);

        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertIsInt($claims['iat']);
        $this->assertIsInt($claims['exp']);
        $this->assertGreaterThan($claims['iat'], $claims['exp']);
    }

    public function test_jwt_decode_fails_with_invalid_signature(): void
    {
        $token = $this->jwt->encode(['sub' => 1], 3600);
        $tampered = $token . 'x';

        $this->expectException(HttpException::class);
        $this->jwt->decode($tampered);
    }

    public function test_jwt_decode_fails_with_expired_token(): void
    {
        $jwt = new JWT($this->secret);
        $token = $jwt->encode(['sub' => 1], -1); // Already expired

        $this->expectException(HttpException::class);
        $jwt->decode($token);
    }

    public function test_access_token_includes_type_field(): void
    {
        $tokens = $this->authService->generateTokens(['sub' => 1]);
        $claims = $this->jwt->decode($tokens['access_token']);

        $this->assertArrayHasKey('type', $claims);
        $this->assertSame('access', $claims['type']);
    }

    public function test_refresh_token_includes_type_field(): void
    {
        $tokens = $this->authService->generateTokens(['sub' => 1]);
        $claims = $this->jwt->decode($tokens['refresh_token']);

        $this->assertArrayHasKey('type', $claims);
        $this->assertSame('refresh', $claims['type']);
    }

    public function test_multiple_users_have_different_tokens(): void
    {
        $tokens1 = $this->authService->generateTokens(['sub' => 1]);
        $tokens2 = $this->authService->generateTokens(['sub' => 2]);

        $this->assertNotSame($tokens1['access_token'], $tokens2['access_token']);
        $this->assertNotSame($tokens1['refresh_token'], $tokens2['refresh_token']);

        $claims1 = $this->authService->validateAccessToken($tokens1['access_token']);
        $claims2 = $this->authService->validateAccessToken($tokens2['access_token']);

        $this->assertSame(1, $claims1['sub']);
        $this->assertSame(2, $claims2['sub']);
    }

    public function test_claims_are_preserved_in_token(): void
    {
        $originalClaims = [
            'sub' => 42,
            'email' => 'test@example.com',
            'permissions' => ['read', 'write'],
            'role' => 'admin',
            'custom_field' => 'custom_value',
        ];

        $tokens = $this->authService->generateTokens($originalClaims);
        $retrievedClaims = $this->authService->validateAccessToken($tokens['access_token']);

        foreach ($originalClaims as $key => $value) {
            $this->assertArrayHasKey($key, $retrievedClaims);
            $this->assertSame($value, $retrievedClaims[$key]);
        }
    }
}
