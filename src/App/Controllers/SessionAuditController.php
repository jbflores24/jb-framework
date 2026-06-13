<?php

declare(strict_types=1);

namespace Jb\App\Controllers;

use Jb\Auth\TokenRevocationList;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Logging\DistributedLogger;

/**
 * Expose audit APIs for revoked sessions and auth security signals.
 */
class SessionAuditController
{
    public function __construct(
        private readonly TokenRevocationList $revocationList,
        private readonly ?DistributedLogger $distributedLogger = null,
    ) {
    }

    public function revokedSessions(Request $request): Response
    {
        $limit = max(1, min(200, (int) $request->input('limit', 50)));
        $sessions = $this->revocationList->active($limit);

        return Response::success([
            'sessions' => $sessions,
            'count' => count($sessions),
        ], 'Sesiones revocadas activas');
    }

    public function revocationStats(Request $request): Response
    {
        $hours = max(1, min(168, (int) $request->input('hours', 24)));
        $stats = $this->revocationList->stats();
        $criticalAuthAlerts = [];

        if ($this->distributedLogger !== null) {
            $critical = $this->distributedLogger->getCriticalEvents($hours);
            $criticalAuthAlerts = array_values(array_filter(
                $critical,
                static fn (array $event): bool => (($event['alert_type'] ?? '') === 'AUTH_FAILURE')
            ));
        }

        return Response::success([
            'revocations' => $stats,
            'critical_auth_alerts' => $criticalAuthAlerts,
            'critical_auth_alerts_count' => count($criticalAuthAlerts),
            'window_hours' => $hours,
        ], 'Estadísticas de revocación y autenticación');
    }
}
