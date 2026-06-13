<?php

declare(strict_types=1);

namespace Jb\Core;

use Closure;

class Router
{
    /** @var list<array{method: string, path: string, handler: mixed, middleware: array<int, mixed>}> */
    private array $routes = [];

    /** @var array<string, int> */
    private array $compiledStaticRoutes = [];

    /** @var list<array{index: int, method: string, path: string, pattern: string, keys: array<int, string>}> */
    private array $compiledDynamicRoutes = [];

    private bool $routeCacheEnabled = false;

    private ?string $routeCachePath = null;

    /** @var array{loaded_from_cache: bool, compiled: bool} */
    private array $cacheStatus = [
        'loaded_from_cache' => false,
        'compiled' => false,
    ];

    public function configureCache(bool $enabled, ?string $path = null): void
    {
        $this->routeCacheEnabled = $enabled;
        $this->routeCachePath = $enabled ? $path : null;
        $this->compiledStaticRoutes = [];
        $this->compiledDynamicRoutes = [];
        $this->cacheStatus = [
            'loaded_from_cache' => false,
            'compiled' => false,
        ];
    }

    /**
     * @return array{loaded_from_cache: bool, compiled: bool}
     */
    public function cacheStatus(): array
    {
        return $this->cacheStatus;
    }

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

        $this->compiledStaticRoutes = [];
        $this->compiledDynamicRoutes = [];
        $this->cacheStatus = [
            'loaded_from_cache' => false,
            'compiled' => false,
        ];
    }

    /**
     * Dispatch the request through route middleware and handler.
     */
    public function dispatch(Request $request, Container $container): Response
    {
        $this->compiledRoutes();

        $staticRouteIndex = $this->compiledStaticRoutes[$this->staticRouteKey($request->method(), $request->path())] ?? null;
        if ($staticRouteIndex !== null) {
            $route = $this->routes[$staticRouteIndex];

            return $this->runPipeline($route['middleware'], $request, $container, function (Request $request) use ($route, $container): Response {
                return $this->call($route['handler'], $request, $container);
            });
        }

        foreach ($this->compiledDynamicRoutes as $compiledRoute) {
            $params = $this->matchCompiled($compiledRoute, $request);
            if ($params === null) {
                continue;
            }

            $route = $this->routes[$compiledRoute['index']];

            $request = $request->withRouteParams($params);
            return $this->runPipeline($route['middleware'], $request, $container, function (Request $request) use ($route, $container): Response {
                return $this->call($route['handler'], $request, $container);
            });
        }

        throw new HttpException('Ruta no encontrada.', 404);
    }

    /** @return array<string, string>|null */
    private function matchCompiled(array $route, Request $request): ?array
    {
        if ($route['method'] !== $request->method()) {
            return null;
        }

        if (!preg_match($route['pattern'], $request->path(), $matches)) {
            return null;
        }

        array_shift($matches);

        return array_combine($route['keys'], array_map('urldecode', $matches)) ?: [];
    }

    private function compiledRoutes(): void
    {
        if ($this->compiledStaticRoutes !== [] || $this->compiledDynamicRoutes !== []) {
            return;
        }

        $signature = $this->routeSignature();
        $cached = $this->loadCompiledRoutesFromCache($signature);
        if ($cached !== null) {
            $this->compiledStaticRoutes = $cached['static'];
            $this->compiledDynamicRoutes = $cached['dynamic'];
            $this->cacheStatus = [
                'loaded_from_cache' => true,
                'compiled' => false,
            ];

            return;
        }

        foreach ($this->routes as $index => $route) {
            if (!str_contains($route['path'], '{')) {
                $this->compiledStaticRoutes[$this->staticRouteKey($route['method'], $route['path'])] = $index;
                continue;
            }

            $this->compiledDynamicRoutes[] = $this->compileRoute($route, $index);
        }

        $this->persistCompiledRoutes($signature, $this->compiledStaticRoutes, $this->compiledDynamicRoutes);
        $this->cacheStatus = [
            'loaded_from_cache' => false,
            'compiled' => true,
        ];
    }

    /** @param array{method: string, path: string, handler: mixed, middleware: array<int, mixed>} $route
     *  @return array{index: int, method: string, path: string, pattern: string, keys: array<int, string>}
     */
    private function compileRoute(array $route, int $index): array
    {
        $keys = [];
        $pattern = preg_replace_callback('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', function (array $match) use (&$keys): string {
            $keys[] = $match[1];
            return '([^/]+)';
        }, $route['path']);

        return [
            'index' => $index,
            'method' => $route['method'],
            'path' => $route['path'],
            'pattern' => '#^' . $pattern . '$#',
            'keys' => $keys,
        ];
    }

    private function routeSignature(): string
    {
        $normalized = array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'path' => $route['path'],
            ],
            $this->routes
        );

        return hash('sha256', (string) json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /** @return array{static: array<string, int>, dynamic: list<array{index: int, method: string, path: string, pattern: string, keys: array<int, string>}>}|null */
    private function loadCompiledRoutesFromCache(string $signature): ?array
    {
        if (!$this->routeCacheEnabled || $this->routeCachePath === null || !is_file($this->routeCachePath)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($this->routeCachePath), true);
        if (!is_array($payload)
            || ($payload['signature'] ?? null) !== $signature
            || !isset($payload['routes']['static'], $payload['routes']['dynamic'])
            || !is_array($payload['routes']['static'])
            || !is_array($payload['routes']['dynamic'])) {
            return null;
        }

        return [
            'static' => $payload['routes']['static'],
            'dynamic' => $payload['routes']['dynamic'],
        ];
    }

    /**
     * @param array<string, int> $compiledStaticRoutes
     * @param list<array{index: int, method: string, path: string, pattern: string, keys: array<int, string>}> $compiledDynamicRoutes
     */
    private function persistCompiledRoutes(string $signature, array $compiledStaticRoutes, array $compiledDynamicRoutes): void
    {
        if (!$this->routeCacheEnabled || $this->routeCachePath === null) {
            return;
        }

        $directory = dirname($this->routeCachePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->routeCachePath, (string) json_encode([
            'signature' => $signature,
            'routes' => [
                'static' => $compiledStaticRoutes,
                'dynamic' => $compiledDynamicRoutes,
            ],
            'generated_at' => date('c'),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function staticRouteKey(string $method, string $path): string
    {
        return $method . ' ' . $path;
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
