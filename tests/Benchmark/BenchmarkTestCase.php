<?php

declare(strict_types=1);

namespace Jb\Tests\Benchmark;

use PHPUnit\Framework\TestCase;

abstract class BenchmarkTestCase extends TestCase
{
    /**
     * @return array{iterations:int,total_ms:float,average_ms:float}
     */
    protected function benchmark(callable $callback, int $iterations): array
    {
        $start = hrtime(true);

        for ($index = 0; $index < $iterations; $index++) {
            $callback();
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        return [
            'iterations' => $iterations,
            'total_ms' => $elapsedMs,
            'average_ms' => $elapsedMs / max($iterations, 1),
        ];
    }

    protected function printMetric(string $label, array $metric): void
    {
        fwrite(
            STDOUT,
            sprintf(
                "%s => iterations=%d total=%.4fms avg=%.6fms%s",
                $label,
                $metric['iterations'],
                $metric['total_ms'],
                $metric['average_ms'],
                PHP_EOL
            )
        );
    }
}
