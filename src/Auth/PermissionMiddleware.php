<?php

declare(strict_types=1);

namespace Jb\Auth;

use Closure;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;

class PermissionMiddleware
{
    public function __construct(private readonly string $permission)
    {
    }

    /**
     * Ensure the authenticated user has the required permission.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $request->attribute('auth', []);
        $permissions = is_array($auth) ? ($auth['permissions'] ?? []) : [];

        if (!is_array($permissions) || !in_array($this->permission, $permissions, true)) {
            throw new HttpException('Permiso insuficiente.', 403);
        }

        return $next($request);
    }

    /**
     * Create middleware for one permission.
     */
    public static function require(string $permission): self
    {
        return new self($permission);
    }
}
