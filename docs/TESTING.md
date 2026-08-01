# Testing Guide

JB Framework includes a PHPUnit 11 test suite for the framework itself and also generates test files for scaffolded projects.

## Run the suite

```bash
composer test
```

The current repository suite finishes with:

```bash
OK (13 tests, 29 assertions)
```

To run only a subset:

```bash
composer test-unit
composer test-integration
```

## Test layout

```text
tests/
|-- BaseTestCase.php
|-- Benchmark/
|-- Integration/
`-- Unit/
```

The `Benchmark/` directory is reserved for benchmark checks.

## Base test case

`tests/BaseTestCase.php` is a shared base class for framework tests. It currently extends `PHPUnit\Framework\TestCase` and does not add extra helpers yet.

## Current coverage

The suite currently covers the main framework areas, including:

- Core HTTP objects
- Configuration
- Database and repositories
- Authentication and JWT
- Routing
- Validation
- Security and bootstrap checks

## Writing tests

Use one responsibility per test and keep the assertions focused.

Example unit test:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jb\Tests\BaseTestCase;

class ExampleTest extends BaseTestCase
{
    public function test_example(): void
    {
        $this->assertTrue(true);
    }
}
```

Example integration test with SQLite in memory:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jb\Tests\BaseTestCase;

class ExampleIntegrationTest extends BaseTestCase
{
    public function test_database_ready(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->assertInstanceOf(\PDO::class, $pdo);
    }
}
```

## Scaffolded projects

When you run `make:scaffold <Name>`, the generated project includes:

- `tests/Unit/<Name>UnitTest.php`
- `tests/Integration/<Name>ScaffoldTest.php`

## PHPUnit configuration

The repository uses `phpunit.xml` with these suites:

- `Unit`
- `Integration`
- `Benchmark`

The integration suite uses SQLite in memory (`:memory:`) by default, so it does not require an external database server.

## Good practices

- Keep unit tests isolated.
- Use SQLite in memory for integration tests when possible.
- Clean shared state in `tearDown()` when a test changes global configuration.
- Avoid external services in the main suite.