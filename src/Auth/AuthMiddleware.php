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
        if (!preg_match('/^Bearer\s+(.+)$/i', (string) $authorization, $matches)) {
            throw new HttpException('Token no proporcionado.', 401);
        }

        $jwt = new JWT((string) $this->config->get('auth.jwt_secret', 'change-me'));
        $claims = $jwt->decode($matches[1]);

        return $next($request->withAttribute('auth', $claims));
    }
}
