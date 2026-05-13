<?php

declare(strict_types=1);

namespace Jb\Tests\Benchmark;

use Jb\Database\QueryBuilder;
use PDO;

final class QueryBuilderBenchmarkTest extends BenchmarkTestCase
{
    public function testMeasuresQueryBuilderSelectPerformance(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, published INTEGER)');

        $statement = $pdo->prepare('INSERT INTO posts (title, published) VALUES (:title, :published)');
        for ($index = 1; $index <= 200; $index++) {
            $statement->execute([
                'title' => 'Post ' . $index,
                'published' => $index % 2,
            ]);
        }

        $metric = $this->benchmark(function () use ($pdo): void {
            $builder = new QueryBuilder($pdo, 'posts', 'sqlite');
            $builder
                ->where('published', 1)
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get(['id', 'title']);
        }, 1000);

        $this->printMetric('query_builder.select', $metric);

        $this->assertGreaterThan(0.0, $metric['average_ms']);
    }
}
