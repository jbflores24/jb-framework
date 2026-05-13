<?php

declare(strict_types=1);

namespace Jb\Logging\Middleware;

use Closure;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Logging\DistributedLogger;

class LoggingMiddleware {
    public function __construct(private readonly DistributedLogger $logger)
    {
    }

    public function handle(Request $request, Closure $next): Response {
        $startTime = microtime(true);
        $clientIp = $this->getClientIp($request);
        $auth = $request->attribute('auth', []);
        $userId = isset($auth['sub']) ? (string)$auth['sub'] : null;

        // Procesar request
        $response = $next($request);

        // Calcular duración
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Registrar acceso
        $this->logger->logAccess([
            'trace_id' => $request->attribute('trace_id', 'unknown'),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status' => 200, // Por defecto éxito
            'user_id' => $userId,
            'client_ip' => $clientIp,
            'duration_ms' => $duration,
            'user_agent' => $request->header('user-agent'),
        ]);

        return $response;
    }

    /**
     * Obtener IP del cliente
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
