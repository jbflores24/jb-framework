<?php

declare(strict_types=1);

namespace Jb\Tests\Benchmark;

use Jb\Core\Container;
use Jb\Core\Request;
use Jb\Core\Router;

final class RouterBenchmarkTest extends BenchmarkTestCase
{
    public function testMeasuresRouterDispatchWithAndWithoutRouteCache(): void
    {
        $container = new Container();
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items/250'],
            [],
            [],
            []
        );

        $uncachedCold = $this->benchmark(function () use ($container, $request): void {
            $router = $this->buildRouter();
            $router->dispatch($request, $container);
        }, 100);

        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jb-router-bench-' . uniqid('', true) . '.json';
        $warmRouter = $this->buildRouter(true, $cacheFile);
        $warmRouter->dispatch($request, $container);

        $cachedCold = $this->benchmark(function () use ($container, $request, $cacheFile): void {
            $router = $this->buildRouter(true, $cacheFile);
            $router->dispatch($request, $container);
        }, 100);

        $cachedWarmRouter = $this->buildRouter(true, $cacheFile);
        $cachedWarmRouter->dispatch($request, $container);
        $cachedWarm = $this->benchmark(function () use ($container, $request, $cachedWarmRouter): void {
            $cachedWarmRouter->dispatch($request, $container);
        }, 5000);

        $this->printMetric('router.uncached_cold', $uncachedCold);
        $this->printMetric('router.cached_cold', $cachedCold);
        $this->printMetric('router.cached_warm', $cachedWarm);

        $this->assertGreaterThan(0.0, $uncachedCold['average_ms']);
        $this->assertGreaterThan(0.0, $cachedCold['average_ms']);
        $this->assertGreaterThan(0.0, $cachedWarm['average_ms']);
        $this->assertLessThan($uncachedCold['average_ms'], $cachedCold['average_ms']);
        $this->assertLessThan(2.0, $cachedWarm['average_ms']);

        @unlink($cacheFile);
    }

    private function buildRouter(bool $useCache = false, ?string $cacheFile = null): Router
    {
        $router = new Router();
        if ($useCache) {
            $router->configureCache(true, $cacheFile);
        }

        for ($index = 1; $index <= 500; $index++) {
            $router->get('/items/' . $index, static fn (): array => ['item' => $index]);
        }

        $router->get('/items/{id}', static fn (Request $request): array => [
            'id' => (int) $request->input('id'),
        ]);

        return $router;
    }
}
