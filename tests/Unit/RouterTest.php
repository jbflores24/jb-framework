<?php

declare(strict_types=1);

namespace Jb\Tests\Unit;

use Jb\Core\Container;
use Jb\Core\HttpException;
use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Core\Router;
use Jb\Tests\BaseTestCase;

final class RouterTest extends BaseTestCase
{
    public function testDispatchesRouteWithParameters(): void
    {
        $router = new Router();
        $container = new Container();

        $router->get('/usuarios/{id}', fn (Request $request): array => [
            'id' => $request->input('id'),
        ]);

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/usuarios/42'],
            [],
            [],
            []
        );

        $response = $router->dispatch($request, $container);
        $payload = $this->responsePayload($response);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('42', $payload['data']['id']);
    }

    public function testExecutesMiddlewarePipelineInOrder(): void
    {
        $router = new Router();
        $container = new Container();
        $events = [];

        $middleware = function (Request $request, callable $next) use (&$events): Response {
            $events[] = 'before';
            $response = $next($request);
            $events[] = 'after';

            return $response;
        };

        $router->get('/ping', function () use (&$events): Response {
            $events[] = 'handler';

            return Response::success(['pong' => true]);
        }, [$middleware]);

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/ping'],
            [],
            [],
            []
        );

        $router->dispatch($request, $container);

        $this->assertSame(['before', 'handler', 'after'], $events);
    }

    public function testThrowsNotFoundWhenRouteDoesNotExist(): void
    {
        $router = new Router();
        $container = new Container();

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/no-existe'],
            [],
            [],
            []
        );

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Ruta no encontrada.');

        try {
            $router->dispatch($request, $container);
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->statusCode());
            throw $exception;
        }
    }

    public function testPersistsAndReusesCompiledRouteCache(): void
    {
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jb-router-cache-' . uniqid('', true) . '.json';

        $router = new Router();
        $router->configureCache(true, $cacheFile);
        $container = new Container();

        $router->get('/articulos/{slug}', fn (Request $request): array => [
            'slug' => $request->input('slug'),
        ]);

        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/articulos/hola-mundo'],
            [],
            [],
            []
        );

        $payload = $this->responsePayload($router->dispatch($request, $container));

        $this->assertSame('hola-mundo', $payload['data']['slug']);
        $this->assertFileExists($cacheFile);
        $this->assertSame([
            'loaded_from_cache' => false,
            'compiled' => true,
        ], $router->cacheStatus());

        $secondRouter = new Router();
        $secondRouter->configureCache(true, $cacheFile);
        $secondRouter->get('/articulos/{slug}', fn (Request $request): array => [
            'slug' => $request->input('slug'),
        ]);

        $secondPayload = $this->responsePayload($secondRouter->dispatch($request, $container));

        $this->assertSame('hola-mundo', $secondPayload['data']['slug']);
        $this->assertSame([
            'loaded_from_cache' => true,
            'compiled' => false,
        ], $secondRouter->cacheStatus());

        @unlink($cacheFile);
    }
}
