<?php

declare(strict_types=1);

namespace Jb\Tests\Benchmark;

use Jb\Auth\JWT;

final class JwtBenchmarkTest extends BenchmarkTestCase
{
    public function testMeasuresJwtEncodeAndDecodePerformance(): void
    {
        $jwt = new JWT(str_repeat('s', 64));

        $encodeMetric = $this->benchmark(function () use ($jwt): void {
            $jwt->encode(['sub' => 7, 'role' => 'admin'], 3600);
        }, 5000);

        $token = $jwt->encode(['sub' => 7, 'role' => 'admin'], 3600);
        $decodeMetric = $this->benchmark(function () use ($jwt, $token): void {
            $jwt->decode($token);
        }, 5000);

        $this->printMetric('jwt.encode', $encodeMetric);
        $this->printMetric('jwt.decode', $decodeMetric);

        $this->assertGreaterThan(0.0, $encodeMetric['average_ms']);
        $this->assertGreaterThan(0.0, $decodeMetric['average_ms']);
    }
}
