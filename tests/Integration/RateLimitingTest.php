<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Core\Application;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Rate\Middleware\RateLimitMiddleware;
use Jb\Rate\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimitingTest extends TestCase {
    private RateLimiter $limiter;
    private string $tempDir;

    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/rate_limit_test_' . time();
        mkdir($this->tempDir);
        $this->limiter = new RateLimiter($this->tempDir, 5, 60);
    }

    protected function tearDown(): void {
        $this->limiter->flush();
        array_map('unlink', glob("$this->tempDir/*"));
        rmdir($this->tempDir);
    }

    public function test_check_within_limit_returns_allowed(): void {
        $result = $this->limiter->check('test_ip');

        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['remaining']);
        $this->assertEquals(1, $result['current']);
        $this->assertEquals(5, $result['limit']);
    }

    public function test_check_exceeding_limit_returns_not_allowed(): void {
        // Hacer 5 requests (límite)
        for ($i = 0; $i < 5; $i++) {
            $result = $this->limiter->check('test_ip');
            $this->assertTrue($result['allowed']);
        }

        // El sexto request debe ser rechazado
        $result = $this->limiter->check('test_ip');
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['remaining']);
        $this->assertEquals(6, $result['current']);
    }

    public function test_status_does_not_increment_counter(): void {
        $this->limiter->check('test_ip');

        // Status debería mostrar 1 request sin incrementar
        $status = $this->limiter->status('test_ip');
        $this->assertEquals(1, $status['count']);
        $this->assertTrue($status['allowed']);

        // Status de nuevo debería mostrar 1 (no incrementó)
        $status = $this->limiter->status('test_ip');
        $this->assertEquals(1, $status['count']);
    }

    public function test_different_identifiers_have_separate_limits(): void {
        // Hacer 5 requests con IP1
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check('ip_1.1.1.1');
        }

        // IP2 debería tener límite completo
        $result = $this->limiter->check('ip_2.2.2.2');
        $this->assertTrue($result['allowed']);
        $this->assertEquals(4, $result['remaining']);
    }

    public function test_reset_clears_counter(): void {
        // Hacer 5 requests
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check('test_ip');
        }

        // Siguiente request rechazado
        $result = $this->limiter->check('test_ip');
        $this->assertFalse($result['allowed']);

        // Reset
        $this->limiter->reset('test_ip');

        // Ahora debería estar permitido
        $result = $this->limiter->check('test_ip');
        $this->assertTrue($result['allowed']);
    }

    public function test_custom_limit_per_check(): void {
        // Check con límite personalizado de 3
        $result = $this->limiter->check('test_ip', 3);
        $this->assertTrue($result['allowed']);
        $this->assertEquals(3, $result['limit']);
        $this->assertEquals(2, $result['remaining']);
    }

    public function test_get_identifier_uses_user_id_if_available(): void {
        $identifier = RateLimiter::getIdentifier('1.1.1.1', '123');
        $this->assertEquals('user_123', $identifier);
    }

    public function test_get_identifier_uses_ip_if_no_user_id(): void {
        $identifier = RateLimiter::getIdentifier('1.1.1.1', null);
        $this->assertEquals('ip_1.1.1.1', $identifier);
    }

    public function test_rate_limit_middleware_allows_request_within_limit(): void {
        $request = Request::capture();
        $callbackCalled = false;

        $middleware = new RateLimitMiddleware($this->limiter, 5, 50);
        $response = $middleware->handle($request, function () use (&$callbackCalled) {
            $callbackCalled = true;
            return Response::success(['status' => 'ok']);
        });

        $this->assertTrue($callbackCalled);
        $this->assertEquals('success', $response->data()['status']);
    }

    public function test_rate_limit_middleware_includes_headers(): void {
        $request = Request::capture();

        $middleware = new RateLimitMiddleware($this->limiter, 5, 50);
        $response = $middleware->handle($request, fn() => Response::success([]));

        // Verificar headers
        $this->assertStringContainsString('5', (string)$response->headers()['X-RateLimit-Limit'] ?? '');
        $this->assertTrue(isset($response->headers()['X-RateLimit-Remaining']));
        $this->assertTrue(isset($response->headers()['X-RateLimit-Reset']));
    }

    public function test_rate_limit_middleware_rejects_request_exceeding_limit(): void {
        $middleware = new RateLimitMiddleware($this->limiter, 2, 50);

        // Hacer 2 requests (límite)
        for ($i = 0; $i < 2; $i++) {
            $request = Request::capture();
            try {
                $middleware->handle($request, fn() => Response::success([]));
            } catch (HttpException) {
                // Ignorar excepción
            }
        }

        // Tercer request debería ser rechazado
        $request = Request::capture();
        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, fn() => Response::success([]));
        } catch (HttpException $e) {
            $this->assertEquals(429, $e->statusCode());
            $this->assertEquals('RATE_LIMIT_EXCEEDED', $e->errorCode());
            throw $e;
        }
    }

    public function test_authenticated_users_have_lower_limit(): void {
        $middleware = new RateLimitMiddleware($this->limiter, 100, 3); // 100 anon, 3 auth

        // Crear request con claims de autenticación
        $request = Request::capture()->withAttribute('auth', ['sub' => 42]);

        // Hacer 3 requests autenticados
        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($request, fn() => Response::success([]));
        }

        // Cuarto request debería ser rechazado
        $this->expectException(HttpException::class);
        $middleware->handle($request, fn() => Response::success([]));
    }

    public function test_client_ip_extraction_from_remote_addr(): void {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';

        $middleware = new RateLimitMiddleware($this->limiter, 1, 50);
        $request = Request::capture();

        // Primer request debería pasar
        try {
            $middleware->handle($request, fn() => Response::success([]));
        } catch (HttpException) {
            // Ignorar
        }

        // Segundo request debería fallar (límite = 1)
        $this->expectException(HttpException::class);
        $middleware->handle($request, fn() => Response::success([]));
    }

    public function test_client_ip_extraction_from_x_forwarded_for(): void {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 198.51.100.178';

        $middleware = new RateLimitMiddleware($this->limiter, 1, 50);
        $request = Request::capture();

        // Primer request debería extraer primera IP
        try {
            $middleware->handle($request, fn() => Response::success([]));
        } catch (HttpException) {
            // Ignorar
        }

        // Segundo request debería fallar (límite = 1, misma IP)
        $this->expectException(HttpException::class);
        $middleware->handle($request, fn() => Response::success([]));
    }

    public function test_remaining_decrements_correctly(): void {
        $result1 = $this->limiter->check('test', 10);
        $this->assertEquals(9, $result1['remaining']);

        $result2 = $this->limiter->check('test', 10);
        $this->assertEquals(8, $result2['remaining']);

        $result3 = $this->limiter->check('test', 10);
        $this->assertEquals(7, $result3['remaining']);
    }

    public function test_reset_at_timestamp_is_valid(): void {
        $before = time() + 60;
        $result = $this->limiter->check('test');
        $after = time() + 60;

        $this->assertGreaterThanOrEqual($before, $result['resetAt']);
        $this->assertLessThanOrEqual($after, $result['resetAt']);
    }

    public function test_flush_clears_all_data(): void {
        $this->limiter->check('test1');
        $this->limiter->check('test2');
        $this->limiter->check('test3');

        $this->limiter->flush();

        // Todos los contadores deberían resetear
        $result1 = $this->limiter->check('test1');
        $result2 = $this->limiter->check('test2');

        $this->assertTrue($result1['allowed']);
        $this->assertTrue($result2['allowed']);
        $this->assertEquals(1, $result1['current']);
        $this->assertEquals(1, $result2['current']);
    }
}
