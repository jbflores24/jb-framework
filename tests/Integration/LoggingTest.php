<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Logging\DistributedLogger;
use Jb\Logging\Middleware\LoggingMiddleware;
use PHPUnit\Framework\TestCase;

class LoggingTest extends TestCase {
    private DistributedLogger $logger;
    private string $tempDir;
    private string $alertsDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/logging_test_' . time();
        $this->alertsDir = $this->tempDir . '/alerts';
        mkdir($this->tempDir);
        mkdir($this->alertsDir);

        $this->logger = new DistributedLogger($this->tempDir, $this->alertsDir);
    }

    protected function tearDown(): void {
        $this->logger->flush();
        $this->removePath($this->tempDir);
    }

    private function removePath(string $path): void {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->removePath($path . '/' . $item);
        }

        @rmdir($path);
    }

    public function test_log_access_creates_entry(): void {
        $this->logger->logAccess([
            'trace_id' => 'trace-123',
            'method' => 'GET',
            'endpoint' => '/api/usuarios',
            'status' => 200,
            'user_id' => 42,
            'client_ip' => '192.168.1.1',
            'duration_ms' => 125,
        ]);

        $logs = $this->logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('ACCESS', $logs[0]['type']);
        $this->assertEquals('trace-123', $logs[0]['trace_id']);
        $this->assertEquals(200, $logs[0]['status']);
    }

    public function test_log_authentication_success(): void {
        $this->logger->logAuthentication([
            'trace_id' => 'trace-456',
            'action' => 'LOGIN',
            'user_id' => 42,
            'email' => 'user@example.com',
            'client_ip' => '192.168.1.1',
            'success' => true,
        ]);

        $logs = $this->logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('AUTHENTICATION', $logs[0]['type']);
        $this->assertEquals('LOGIN', $logs[0]['action']);
        $this->assertTrue($logs[0]['success']);
    }

    public function test_log_authentication_failure_creates_alert(): void {
        $this->logger->logAuthentication([
            'trace_id' => 'trace-789',
            'action' => 'LOGIN',
            'email' => 'hacker@example.com',
            'client_ip' => '203.0.113.5',
            'success' => false,
            'reason' => 'Invalid credentials',
        ]);

        $logs = $this->logger->getLogs();
        $alerts = $this->logger->getAlerts();

        $this->assertCount(1, $logs);
        $this->assertCount(1, $alerts);
        $this->assertEquals('AUTH_FAILURE', $alerts[0]['alert_type']);
    }

    public function test_log_rate_limit_violation(): void {
        $this->logger->logRateLimitViolation([
            'trace_id' => 'trace-rl1',
            'identifier' => 'ip_203.0.113.5',
            'limit' => 100,
            'requests' => 101,
            'client_ip' => '203.0.113.5',
            'endpoint' => '/api/usuarios',
        ]);

        $logs = $this->logger->getLogs();
        $alerts = $this->logger->getAlerts();

        $this->assertCount(1, $logs);
        $this->assertCount(1, $alerts);
        $this->assertEquals('RATE_LIMIT', $logs[0]['type']);
        $this->assertEquals('RATE_LIMIT_VIOLATION', $alerts[0]['alert_type']);
    }

    public function test_log_error_with_5xx_creates_alert(): void {
        $this->logger->logError([
            'trace_id' => 'trace-err1',
            'status' => 500,
            'code' => 'INTERNAL_ERROR',
            'message' => 'Database connection failed',
            'endpoint' => '/api/usuarios',
            'method' => 'GET',
            'client_ip' => '192.168.1.1',
        ]);

        $logs = $this->logger->getLogs();
        $alerts = $this->logger->getAlerts();

        $this->assertCount(1, $logs);
        $this->assertCount(1, $alerts);
        $this->assertEquals('ERROR', $logs[0]['type']);
        $this->assertEquals(500, $logs[0]['status']);
        $this->assertEquals('SERVER_ERROR', $alerts[0]['alert_type']);
    }

    public function test_log_error_with_4xx_no_alert(): void {
        $this->logger->logError([
            'trace_id' => 'trace-err2',
            'status' => 404,
            'code' => 'NOT_FOUND',
            'message' => 'Resource not found',
            'endpoint' => '/api/notfound',
            'method' => 'GET',
            'client_ip' => '192.168.1.1',
        ]);

        $logs = $this->logger->getLogs();
        $alerts = $this->logger->getAlerts();

        $this->assertCount(1, $logs);
        $this->assertCount(0, $alerts);
        $this->assertEquals(404, $logs[0]['status']);
    }

    public function test_log_data_change(): void {
        $this->logger->logDataChange([
            'trace_id' => 'trace-dc1',
            'action' => 'UPDATE',
            'entity' => 'usuarios',
            'entity_id' => 42,
            'user_id' => 1,
            'changes' => ['email' => 'new@example.com', 'status' => 'active'],
            'client_ip' => '192.168.1.1',
        ]);

        $logs = $this->logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('DATA_CHANGE', $logs[0]['type']);
        $this->assertEquals('UPDATE', $logs[0]['action']);
        $this->assertEquals('usuarios', $logs[0]['entity']);
    }

    public function test_get_logs_empty_when_no_data(): void {
        $logs = $this->logger->getLogs();
        $this->assertCount(0, $logs);
    }

    public function test_filter_logs_by_type(): void {
        $this->logger->logAccess(['trace_id' => 't1', 'status' => 200]);
        $this->logger->logAuthentication(['trace_id' => 't2', 'success' => true]);
        $this->logger->logAccess(['trace_id' => 't3', 'status' => 404]);

        $filtered = $this->logger->filterLogs('today', ['type' => 'ACCESS']);
        $this->assertCount(2, $filtered);
    }

    public function test_filter_logs_by_user_id(): void {
        $this->logger->logAccess(['trace_id' => 't1', 'user_id' => 42, 'status' => 200]);
        $this->logger->logAccess(['trace_id' => 't2', 'user_id' => 99, 'status' => 200]);
        $this->logger->logAccess(['trace_id' => 't3', 'user_id' => 42, 'status' => 404]);

        $filtered = $this->logger->filterLogs('today', ['user_id' => 42]);
        $this->assertCount(2, $filtered);
    }

    public function test_get_alerts_by_date(): void {
        $this->logger->logAuthentication([
            'success' => false,
            'reason' => 'Invalid credentials',
        ]);

        $alerts = $this->logger->getAlerts('today');
        $this->assertCount(1, $alerts);
        $this->assertEquals('AUTH_FAILURE', $alerts[0]['alert_type']);
    }

    public function test_get_critical_events(): void {
        $this->logger->logAuthentication(['success' => false, 'reason' => 'Bad auth']);
        $this->logger->logError(['status' => 500, 'code' => 'ERROR']);

        $critical = $this->logger->getCriticalEvents(24);
        $this->assertGreaterThanOrEqual(2, count($critical));
    }

    public function test_cleanup_removes_old_logs(): void {
        // Crear archivo antiguo manualmente
        $oldDate = date('Y-m-d', time() - 86400);
        $oldFile = $this->tempDir . '/log_' . $oldDate . '.jsonl';
        file_put_contents($oldFile, '{}' . "\n");
        
        // Tocar archivo para antiguarlo
        touch($oldFile, time() - (8 * 86400));
        $this->assertTrue(file_exists($oldFile));

        // Cleanup retiene 7 días
        $this->logger->cleanup(7);

        // Archivo antiguo debe haber sido eliminado
        $this->assertFalse(file_exists($oldFile));
    }

    public function test_logging_middleware_records_access(): void {
        $middleware = new LoggingMiddleware($this->logger);
        $request = Request::capture()->withAttribute('trace_id', 'trace-mw1');
        $callbackCalled = false;

        $middleware->handle($request, function () use (&$callbackCalled) {
            $callbackCalled = true;
            return Response::success([]);
        });

        $this->assertTrue($callbackCalled);
        
        $logs = $this->logger->getLogs();
        $this->assertCount(1, $logs);
        $this->assertEquals('trace-mw1', $logs[0]['trace_id']);
    }

    public function test_logging_middleware_includes_user_id(): void {
        $middleware = new LoggingMiddleware($this->logger);
        $request = Request::capture()
            ->withAttribute('trace_id', 'trace-mw2')
            ->withAttribute('auth', ['sub' => 42]);

        $middleware->handle($request, fn() => Response::success([]));

        $logs = $this->logger->getLogs();
        $this->assertEquals(42, $logs[0]['user_id']);
    }

    public function test_logging_middleware_measures_duration(): void {
        $middleware = new LoggingMiddleware($this->logger);
        $request = Request::capture()->withAttribute('trace_id', 'trace-mw3');

        $middleware->handle($request, function () {
            usleep(10000); // 10ms
            return Response::success([]);
        });

        $logs = $this->logger->getLogs();
        $this->assertGreaterThan(0, $logs[0]['duration_ms']);
    }

    public function test_multiple_events_same_day(): void {
        $this->logger->logAccess(['trace_id' => 't1', 'status' => 200]);
        $this->logger->logAccess(['trace_id' => 't2', 'status' => 201]);
        $this->logger->logAuthentication(['trace_id' => 't3', 'success' => true]);

        $logs = $this->logger->getLogs();
        $this->assertCount(3, $logs);
    }

    public function test_log_includes_timestamp(): void {
        $before = time();
        $this->logger->logAccess(['trace_id' => 't1', 'status' => 200]);
        $after = time();

        $logs = $this->logger->getLogs();
        $this->assertGreaterThanOrEqual($before, $logs[0]['timestamp']);
        $this->assertLessThanOrEqual($after, $logs[0]['timestamp']);
    }

    public function test_flush_clears_all_data(): void {
        $this->logger->logAccess(['trace_id' => 't1', 'status' => 200]);
        $this->logger->logAccess(['trace_id' => 't2', 'status' => 201]);

        $this->assertCount(2, $this->logger->getLogs());

        $this->logger->flush();

        $this->assertCount(0, $this->logger->getLogs());
    }
}
