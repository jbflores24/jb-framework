<?php

declare(strict_types=1);

namespace Jb\Core;

use Closure;

class Router
{
    /** @var list<array{method: string, path: string, handler: mixed, middleware: array<int, mixed>}> */
    private array $routes = [];

    /**
     * Register a GET route.
     */
    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a route for any supported HTTP verb.
     */
    public function add(string $method, string $path, mixed $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '/' . trim($path, '/'),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Dispatch the request through route middleware and handler.
     */
    public function dispatch(Request $request, Container $container): Response
    {
        foreach ($this->routes as $route) {
            $params = $this->match($route['method'], $route['path'], $request);
            if ($params === null) {
                continue;
            }

            $request = $request->withRouteParams($params);
            return $this->runPipeline($route['middleware'], $request, $container, function (Request $request) use ($route, $container): Response {
                return $this->call($route['handler'], $request, $container);
            });
        }

        throw new HttpException('Ruta no encontrada.', 404);
    }

    /** @return array<string, string>|null */
    private function match(string $method, string $path, Request $request): ?array
    {
        if ($method !== $request->method()) {
            return null;
        }

        $keys = [];
        $pattern = preg_replace_callback('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', function (array $match) use (&$keys): string {
            $keys[] = $match[1];
            return '([^/]+)';
        }, $path);

        if (!preg_match('#^' . $pattern . '$#', $request->path(), $matches)) {
            return null;
        }

        array_shift($matches);

        return array_combine($keys, array_map('urldecode', $matches)) ?: [];
    }

    private function runPipeline(array $middleware, Request $request, Container $container, Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            fn (Closure $next, mixed $layer): Closure => fn (Request $request): Response => $this->callMiddleware($layer, $request, $next, $container),
            $destination
        );

        return $pipeline($request);
    }

    private function callMiddleware(mixed $middleware, Request $request, Closure $next, Container $container): Response
    {
        if (is_string($middleware)) {
            $middleware = [$container->get($middleware), 'handle'];
        }

        if (is_object($middleware) && method_exists($middleware, 'handle')) {
            $middleware = [$middleware, 'handle'];
        }

        $result = is_callable($middleware)
            ? $middleware($request, $next)
            : throw new HttpException('Middleware no ejecutable.', 500);

        return $result instanceof Response ? $result : Response::success($result);
    }

    private function call(mixed $handler, Request $request, Container $container): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler[0] = $container->get($handler[0]);
        }

        $result = is_callable($handler)
            ? $handler($request)
            : throw new HttpException('Handler no ejecutable.', 500);

        return $result instanceof Response ? $result : Response::success($result);
    }
}
