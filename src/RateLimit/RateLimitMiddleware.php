<?php

declare(strict_types=1);

namespace Jb\RateLimit;

use Closure;
use Jb\Core\Request;
use Jb\Core\Response;

class RateLimitMiddleware
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    /**
     * Apply rate limiting before reaching the route handler.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->limiter->hit($request);

        return $next($request);
    }
}
