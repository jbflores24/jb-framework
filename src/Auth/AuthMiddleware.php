<?php

declare(strict_types=1);

namespace Jb\Auth;

use Closure;
use Jb\Core\Config;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;

class AuthMiddleware
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Validate a bearer JWT and attach claims to the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('authorization', '');
        $token = AuthService::extractBearerToken($authorization);

        $jwt = new JWT((string) $this->config->get('auth.jwt_secret', 'change-me'));
        $revokedPath = $this->resolveStoragePath((string) $this->config->get(
            'auth.revoked_tokens_path',
            'storage/auth/revoked_tokens.json'
        ));
        $authService = new AuthService($jwt, 3600, 2592000, new TokenRevocationList($revokedPath));
        
        try {
            $claims = $authService->validateAccessToken($token);
        } catch (HttpException $e) {
            // Re-throw with proper error code
            throw new HttpException($e->getMessage(), $e->statusCode(), 'UNAUTHORIZED', $e->context());
        }

        return $next($request->withAttribute('auth', $claims));
    }

    private function resolveStoragePath(string $path): string
    {
        if ($path === '') {
            return $this->config->basePath() . DIRECTORY_SEPARATOR . 'storage/auth/revoked_tokens.json';
        }

        if (preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return $this->config->basePath() . DIRECTORY_SEPARATOR . $path;
    }
}
