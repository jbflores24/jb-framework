<?php

declare(strict_types=1);

namespace Jb\Tests\Integration;

use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use PHPUnit\Framework\TestCase;

/**
 * Test standard error response format.
 */
class ErrorStandardizationTest extends TestCase
{
    public function test_error_response_includes_code_and_trace_id(): void
    {
        $response = Response::error(
            'Resource not found',
            404,
            [],
            'NOT_FOUND',
            'trace-123-abc'
        );

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('error', $decoded['status']);
        $this->assertSame('NOT_FOUND', $decoded['code']);
        $this->assertSame('Resource not found', $decoded['message']);
        $this->assertSame('trace-123-abc', $decoded['trace_id']);
    }

    public function test_error_response_includes_errors_array(): void
    {
        $response = Response::error(
            'Validation failed',
            422,
            ['email' => 'Invalid email', 'age' => 'Must be positive'],
            'VALIDATION_ERROR'
        );

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertSame('VALIDATION_ERROR', $decoded['code']);
        $this->assertArrayHasKey('errors', $decoded);
        $this->assertSame('Invalid email', $decoded['errors']['email']);
        $this->assertSame('Must be positive', $decoded['errors']['age']);
    }

    public function test_request_has_unique_trace_id(): void
    {
        $request1 = Request::capture();
        $request2 = Request::capture();

        $this->assertNotEmpty($request1->traceId());
        $this->assertNotEmpty($request2->traceId());
        $this->assertNotSame($request1->traceId(), $request2->traceId());
    }

    public function test_trace_id_format(): void
    {
        $request = Request::capture();
        $traceId = $request->traceId();

        // Format: microtime-randomhex
        // Should contain hyphen
        $this->assertStringContainsString('-', $traceId);

        // Should not be "unknown"
        $this->assertNotSame('unknown', $traceId);

        // Should be at least 10 characters
        $this->assertGreaterThan(10, strlen($traceId));
    }

    public function test_http_exception_with_error_code(): void
    {
        $exception = new HttpException(
            'Unauthorized access',
            401,
            'UNAUTHORIZED',
            ['user_id' => 'invalid']
        );

        $this->assertSame(401, $exception->statusCode());
        $this->assertSame('UNAUTHORIZED', $exception->errorCode());
        $this->assertSame(['user_id' => 'invalid'], $exception->context());
    }

    public function test_error_response_without_trace_id(): void
    {
        // When trace_id is null, it should not be included in the response
        $response = Response::error(
            'Bad request',
            400,
            [],
            'BAD_REQUEST',
            null
        );

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertArrayNotHasKey('trace_id', $decoded);
    }

    public function test_success_response_does_not_include_code(): void
    {
        $response = Response::success(['id' => 1], 'Created');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertArrayNotHasKey('code', $decoded);
        $this->assertSame('success', $decoded['status']);
    }
}
