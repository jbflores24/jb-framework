<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Core\Request;
use Jb\Tests\BaseTestCase;

final class RequestTest extends BaseTestCase
{
    public function testNormalizesMethodAndPath(): void
    {
        $request = new Request(
            ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/api/usuarios?pagina=2'],
            ['pagina' => '2'],
            [],
            []
        );

        $this->assertSame('POST', $request->method());
        $this->assertSame('/api/usuarios', $request->path());
    }

    public function testInputUsesRouteBodyThenQueryPriority(): void
    {
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items/3'],
            ['id' => '1'],
            ['id' => '2'],
            [],
            ['id' => '3']
        );

        $this->assertSame('3', $request->input('id'));
    }

    public function testWithAttributeAndWithPathReturnClonedInstances(): void
    {
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items'],
            [],
            [],
            ['x-trace' => 'abc']
        );

        $withAttribute = $request->withAttribute('usuario', 99);
        $withPath = $request->withPath('/otra-ruta');

        $this->assertNull($request->attribute('usuario'));
        $this->assertSame(99, $withAttribute->attribute('usuario'));
        $this->assertSame('/otra-ruta', $withPath->path());
        $this->assertSame('abc', $request->header('X-Trace'));
    }
}
