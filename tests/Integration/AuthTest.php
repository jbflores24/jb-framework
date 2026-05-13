<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Auth\AuthMiddleware;
use Jb\Auth\AuthService;
use Jb\Auth\JWT;
use Jb\Auth\PermissionMiddleware;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Tests\BaseTestCase;

final class AuthTest extends BaseTestCase
{
    public function testAuthMiddlewareInjectsJwtClaimsIntoRequest(): void
    {
        $config = $this->makeConfig($this->createTempPath('auth-'));
        $config->set('auth.jwt_secret', 'secret-test');

        $jwt = new JWT('secret-test');
        $authService = new AuthService($jwt);
        $tokens = $authService->generateTokens(['sub' => 15, 'permissions' => ['usuarios.read']]);

        $middleware = new AuthMiddleware($config);
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/perfil'],
            [],
            [],
            ['authorization' => 'Bearer ' . $tokens['access_token']]
        );

        $response = $middleware->handle($request, static function (Request $request): Response {
            $auth = $request->attribute('auth', []);

            return Response::success(['sub' => $auth['sub'] ?? null]);
        });

        $payload = $this->responsePayload($response);

        $this->assertSame(15, $payload['data']['sub']);
    }

    public function testPermissionMiddlewareAllowsAuthorizedPermission(): void
    {
        $middleware = PermissionMiddleware::require('usuarios.read');
        $request = (new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/usuarios'], [], [], []))
            ->withAttribute('auth', ['permissions' => ['usuarios.read', 'usuarios.write']]);

        $response = $middleware->handle($request, static fn (Request $request): Response => Response::success([
            'permissions' => $request->attribute('auth')['permissions'] ?? [],
        ]));

        $payload = $this->responsePayload($response);
        $this->assertSame(['usuarios.read', 'usuarios.write'], $payload['data']['permissions']);
    }

    public function testPermissionMiddlewareRejectsMissingPermission(): void
    {
        $middleware = PermissionMiddleware::require('usuarios.delete');
        $request = (new Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/usuarios'], [], [], []))
            ->withAttribute('auth', ['permissions' => ['usuarios.read']]);

        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, static fn (): Response => Response::success());
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->statusCode());
            throw $exception;
        }
    }
}
