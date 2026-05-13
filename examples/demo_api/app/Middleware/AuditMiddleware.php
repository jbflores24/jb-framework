<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Jb\Core\Request;
use Jb\Core\Response;

class AuditMiddleware
{
    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
