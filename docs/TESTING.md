# Guía de pruebas

JB Framework incluye una suite de pruebas PHPUnit 11 para el framework en sí y también genera archivos de prueba para los proyectos creados con scaffold.

## Ejecutar la suite

```bash
composer test
```

La suite actual del repositorio termina con:

```bash
OK (13 tests, 29 assertions)
```

Para ejecutar solo un subconjunto:

```bash
composer test-unit
composer test-integration
```

## Estructura de pruebas

```text
tests/
|-- BaseTestCase.php
|-- Benchmark/
|-- Integration/
`-- Unit/
```

El directorio `Benchmark/` está reservado para verificaciones de benchmark.

## Clase base de pruebas

`tests/BaseTestCase.php` es una clase base compartida para las pruebas del framework. Actualmente extiende `PHPUnit\Framework\TestCase` y todavía no agrega ayudantes extra.

## Cobertura actual

La suite cubre actualmente las áreas principales del framework, entre ellas:

- Objetos HTTP del núcleo
- Configuración
- Base de datos y repositorios
- Autenticación y JWT
- Enrutamiento
- Validación
- Verificaciones de seguridad y arranque

## Escribir pruebas

Usa una sola responsabilidad por prueba y mantén las aserciones enfocadas.

Ejemplo de prueba unitaria:

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

Ejemplo de prueba de integración con SQLite en memoria:

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

## Proyectos con scaffold

Cuando ejecutas `make:scaffold <Name>`, el proyecto generado incluye:

- `tests/Unit/<Name>UnitTest.php`
- `tests/Integration/<Name>ScaffoldTest.php`

## Configuración de PHPUnit

El repositorio usa `phpunit.xml` con estas suites:

- `Unit`
- `Integration`
- `Benchmark`

La suite de integración usa SQLite en memoria (`:memory:`) por defecto, así que no requiere un servidor externo de base de datos.

## Buenas prácticas

- Mantén las pruebas unitarias aisladas.
- Usa SQLite en memoria para las pruebas de integración cuando sea posible.
- Limpia el estado compartido en `tearDown()` cuando una prueba cambie la configuración global.
- Evita servicios externos en la suite principal.