<?php

declare(strict_types=1);

namespace Jb\Rate\Middleware;

use Closure;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Rate\RateLimiter;

class RateLimitMiddleware {
    private RateLimiter $limiter;
    private int $requestsPerMinute;
    private int $requestsPerMinuteAuth;

    public function __construct(
        RateLimiter $limiter,
        int $requestsPerMinute = 100,
        int $requestsPerMinuteAuth = 50
    ) {
        $this->limiter = $limiter;
        $this->requestsPerMinute = $requestsPerMinute;
        $this->requestsPerMinuteAuth = $requestsPerMinuteAuth;
    }

    public function handle(Request $request, Closure $next): Response {
        // Obtener IP del cliente
        $clientIp = $this->getClientIp($request);

        // Obtener ID del usuario autenticado (si existe)
        $auth = $request->attribute('auth', []);
        $userId = isset($auth['sub']) ? (string)$auth['sub'] : null;

        // Generar identificador (prioriza usuario si está autenticado)
        $identifier = RateLimiter::getIdentifier($clientIp, $userId);

        // Determinar límite según si está autenticado
        $limit = $userId !== null ? $this->requestsPerMinuteAuth : $this->requestsPerMinute;

        // Verificar límite de rate
        $result = $this->limiter->check($identifier, $limit);

        // Si se excedió el límite
        if (!$result['allowed']) {
            throw new HttpException(
                'Too many requests. Please try again later.',
                429,
                'RATE_LIMIT_EXCEEDED'
            );
        }

        // Procesar request
        $response = $next($request);

        // Agregar headers de rate limit
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$result['limit'])
            ->withHeader('X-RateLimit-Remaining', (string)$result['remaining'])
            ->withHeader('X-RateLimit-Reset', (string)$result['resetAt']);
    }

    /**
     * Obtener IP del cliente (con soporte para proxies)
     *
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string {
        // X-Forwarded-For (puede contener múltiples IPs)
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

        // REMOTE_ADDR
        return $request->server('REMOTE_ADDR') ?? '127.0.0.1';
    }

    /**
     * Validar formato de IP
     *
     * @param string $ip
     * @return bool
     */
    private function isValidIp(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
