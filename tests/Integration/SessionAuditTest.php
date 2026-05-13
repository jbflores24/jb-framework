<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\App\Controllers\SessionAuditController;
use Jb\Auth\AuthService;
use Jb\Auth\JWT;
use Jb\Auth\TokenRevocationList;
use Jb\Core\Request;
use Jb\Logging\DistributedLogger;
use Jb\Tests\BaseTestCase;

final class SessionAuditTest extends BaseTestCase
{
    private string $secret = 'test-secret-key-that-is-long-enough';

    public function testRevokedSessionsReturnsActiveListWithMetadata(): void
    {
        $base = $this->createTempPath('audit-');
        $revokedPath = $base . '/storage/auth/revoked_tokens.json';

        $revocationList = new TokenRevocationList($revokedPath);
        $authService = new AuthService(new JWT($this->secret), 3600, 2592000, $revocationList);

        $tokens = $authService->generateTokens(['sub' => 501]);
        $authService->revokeToken($tokens['access_token']);

        $controller = new SessionAuditController($revocationList);
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/audit/revoked-sessions'],
            ['limit' => '10'],
            [],
            []
        );

        $response = $controller->revokedSessions($request);
        $payload = $this->responsePayload($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(1, $payload['data']['count']);
        $this->assertSame(501, $payload['data']['sessions'][0]['user_id']);
        $this->assertSame('access', $payload['data']['sessions'][0]['token_type']);
        $this->assertArrayHasKey('hash', $payload['data']['sessions'][0]);
    }

    public function testRevocationStatsIncludesByTypeAndExpiringWindow(): void
    {
        $base = $this->createTempPath('audit-');
        $revokedPath = $base . '/storage/auth/revoked_tokens.json';

        $revocationList = new TokenRevocationList($revokedPath);
        $authService = new AuthService(new JWT($this->secret), 3600, 2592000, $revocationList);

        $tokens = $authService->generateTokens(['sub' => 777]);
        $authService->revokeToken($tokens['access_token']);
        $authService->revokeToken($tokens['refresh_token']);

        $controller = new SessionAuditController($revocationList);
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/audit/revocation-stats'],
            ['hours' => '24'],
            [],
            []
        );

        $response = $controller->revocationStats($request);
        $payload = $this->responsePayload($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(2, $payload['data']['revocations']['active_total']);
        $this->assertSame(1, $payload['data']['revocations']['by_type']['access']);
        $this->assertSame(1, $payload['data']['revocations']['by_type']['refresh']);
        $this->assertSame(24, $payload['data']['window_hours']);
    }

    public function testRevocationStatsIncludesCriticalAuthAlertsWhenLoggerProvided(): void
    {
        $base = $this->createTempPath('audit-');
        $revokedPath = $base . '/storage/auth/revoked_tokens.json';
        $logsDir = $base . '/storage/logs';
        $alertsDir = $base . '/storage/alerts';

        $revocationList = new TokenRevocationList($revokedPath);
        $logger = new DistributedLogger($logsDir, $alertsDir);

        $logger->logAuthentication([
            'trace_id' => 'trace-auth-fail',
            'action' => 'LOGIN',
            'success' => false,
            'reason' => 'Invalid credentials',
        ]);

        $controller = new SessionAuditController($revocationList, $logger);
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/audit/revocation-stats'],
            ['hours' => '24'],
            [],
            []
        );

        $response = $controller->revocationStats($request);
        $payload = $this->responsePayload($response);

        $this->assertSame('success', $payload['status']);
        $this->assertGreaterThanOrEqual(1, $payload['data']['critical_auth_alerts_count']);
        $this->assertSame('AUTH_FAILURE', $payload['data']['critical_auth_alerts'][0]['alert_type']);
    }
}
