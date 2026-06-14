<?php

declare(strict_types=1);

namespace Jb\RateLimit;

use Closure;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;

class RateLimitMiddleware
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly int $requestsPerMinute = 100,
        private readonly int $requestsPerMinuteAuth = 50
    ) {
    }

    /**
     * Apply rate limiting before reaching the route handler.
     *
     * Adds X-RateLimit-* headers to compliant responses and
     * throws an HttpException (429) when the limit is exceeded.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $this->getClientIp($request);

        // Determine authenticated user id if available.
        $auth = $request->attribute('auth', []);
        $userId = isset($auth['sub']) ? (string) $auth['sub'] : null;

        $identifier = RateLimiter::getIdentifier($clientIp, $userId);
        $limit = $userId !== null ? $this->requestsPerMinuteAuth : $this->requestsPerMinute;

        $result = $this->limiter->check($identifier, $limit);

        if (!$result['allowed']) {
            throw new HttpException(
                'Too many requests. Please try again later.',
                429,
                ['retry_after' => $result['resetAt'] - time()]
            );
        }

        /** @var Response $response */
        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $result['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $result['resetAt']);
    }

    /**
     * Extract the client IP, with proxy awareness.
     */
    private function getClientIp(Request $request): string
    {
        // X-Forwarded-For (may contain a comma-separated chain).
        $forwarded = $request->header('X-Forwarded-For');
        if ($forwarded !== null) {
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0]);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        // X-Client-IP
        $clientIp = $request->header('X-Client-IP');
        if ($clientIp !== null && $this->isValidIp($clientIp)) {
            return $clientIp;
        }

        // Fallback to REMOTE_ADDR.
        return $request->server('REMOTE_ADDR', '127.0.0.1');
    }

    /**
     * Validate an IP address format.
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}