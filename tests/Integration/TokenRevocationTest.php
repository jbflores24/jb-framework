<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\App\Controllers\AuthController;
use Jb\Auth\AuthMiddleware;
use Jb\Auth\AuthService;
use Jb\Auth\JWT;
use Jb\Auth\TokenRevocationList;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Tests\BaseTestCase;

final class TokenRevocationTest extends BaseTestCase
{
    private string $secret = 'test-secret-key-that-is-long-enough';

    public function testRevokeAccessTokenBlocksValidation(): void
    {
        $path = $this->createTempPath('revoked-') . '/revoked_tokens.json';
        $authService = new AuthService(
            new JWT($this->secret),
            3600,
            2592000,
            new TokenRevocationList($path)
        );

        $tokens = $authService->generateTokens(['sub' => 1001]);
        $authService->revokeToken($tokens['access_token']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Token revocado');
        $authService->validateAccessToken($tokens['access_token']);
    }

    public function testRevokeRefreshTokenBlocksRefresh(): void
    {
        $path = $this->createTempPath('revoked-') . '/revoked_tokens.json';
        $authService = new AuthService(
            new JWT($this->secret),
            3600,
            2592000,
            new TokenRevocationList($path)
        );

        $tokens = $authService->generateTokens(['sub' => 1002]);
        $authService->revokeToken($tokens['refresh_token']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Refresh token revocado');
        $authService->refreshAccessToken($tokens['refresh_token']);
    }

    public function testLogoutRevokesAccessAndRefreshToken(): void
    {
        $path = $this->createTempPath('revoked-') . '/revoked_tokens.json';
        $authService = new AuthService(
            new JWT($this->secret),
            3600,
            2592000,
            new TokenRevocationList($path)
        );
        $controller = new AuthController($authService);

        $tokens = $authService->generateTokens(['sub' => 1003]);

        $request = new Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/auth/logout'],
            [],
            ['refresh_token' => $tokens['refresh_token']],
            ['authorization' => 'Bearer ' . $tokens['access_token']]
        );

        $response = $controller->logout($request);
        $payload = $this->responsePayload($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(2, $payload['data']['revoked_tokens']);

        try {
            $authService->validateAccessToken($tokens['access_token']);
            $this->fail('Expected access token to be revoked.');
        } catch (HttpException $e) {
            $this->assertSame('TOKEN_REVOKED', $e->errorCode());
        }

        try {
            $authService->refreshAccessToken($tokens['refresh_token']);
            $this->fail('Expected refresh token to be revoked.');
        } catch (HttpException $e) {
            $this->assertSame('TOKEN_REVOKED', $e->errorCode());
        }
    }

    public function testAuthMiddlewareRejectsRevokedToken(): void
    {
        $tempBase = $this->createTempPath('authmw-');
        $revokedPath = $tempBase . '/storage/auth/revoked_tokens.json';
        $config = $this->makeConfig($tempBase);
        $config->set('auth.jwt_secret', $this->secret);
        $config->set('auth.revoked_tokens_path', $revokedPath);

        $authService = new AuthService(
            new JWT($this->secret),
            3600,
            2592000,
            new TokenRevocationList($revokedPath)
        );

        $tokens = $authService->generateTokens(['sub' => 1004, 'permissions' => ['usuarios.read']]);
        $authService->revokeToken($tokens['access_token']);

        $middleware = new AuthMiddleware($config);
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/private'],
            [],
            [],
            ['authorization' => 'Bearer ' . $tokens['access_token']]
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Token revocado');

        $middleware->handle($request, static fn (Request $request): Response => Response::success([
            'sub' => $request->attribute('auth')['sub'] ?? null,
        ]));
    }

    public function testRevocationListExposesStatsAndActiveEntries(): void
    {
        $path = $this->createTempPath('revoked-') . '/revoked_tokens.json';
        $revocationList = new TokenRevocationList($path);
        $authService = new AuthService(
            new JWT($this->secret),
            3600,
            2592000,
            $revocationList
        );

        $tokens = $authService->generateTokens(['sub' => 999]);
        $authService->revokeToken($tokens['access_token']);

        $active = $revocationList->active();
        $stats = $revocationList->stats();

        $this->assertCount(1, $active);
        $this->assertSame(999, $active[0]['user_id']);
        $this->assertSame('access', $active[0]['token_type']);
        $this->assertSame(1, $stats['active_total']);
        $this->assertSame(1, $stats['by_type']['access']);
    }
}
