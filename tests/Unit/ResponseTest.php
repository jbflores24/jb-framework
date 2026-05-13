<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Core\Response;
use Jb\Tests\BaseTestCase;

final class ResponseTest extends BaseTestCase
{
    public function testSuccessFactoryBuildsStandardPayload(): void
    {
        $response = Response::success(['ok' => true], 'Todo bien', ['page' => 1]);
        $payload = $this->responsePayload($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('Todo bien', $payload['message']);
        $this->assertSame(['ok' => true], $payload['data']);
        $this->assertSame(['page' => 1], $payload['meta']);
        $this->assertSame(200, $this->responseStatus($response));
    }

    public function testErrorFactoryBuildsStandardPayload(): void
    {
        $response = Response::error('Error de validacion', 422, ['email' => ['invalido']]);
        $payload = $this->responsePayload($response);

        $this->assertSame('error', $payload['status']);
        $this->assertSame('Error de validacion', $payload['message']);
        $this->assertSame(['email' => ['invalido']], $payload['errors']);
        $this->assertSame(422, $this->responseStatus($response));
    }

    public function testSendOutputsJsonPayload(): void
    {
        $response = Response::success(['id' => 10]);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('success', $decoded['status']);
        $this->assertSame(10, $decoded['data']['id']);
    }
}
