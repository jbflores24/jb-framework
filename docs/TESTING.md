# Guía de pruebas

JB Framework incluye su propia suite de pruebas con PHPUnit 11 y un mecanismo para que los proyectos generados incluyan sus propias pruebas desde el primer día.

---

## Pruebas del framework

### Ejecutar toda la suite

```bash
cd jb/
composer test
```

**Salida esperada:**

```
PHPUnit 11.x.x

........................

Time: 00:00.215, Memory: 12.00 MB

OK (24 tests, 62 assertions)
```

### Ejecutar solo pruebas unitarias

```bash
composer test-unit
```

### Ejecutar solo pruebas de integración

```bash
composer test-integration
```

---

## Organización de las pruebas del framework

```
tests/
├── BaseTestCase.php            ← clase base con helpers comunes
├── Unit/
│   ├── ConfigTest.php          ← lectura y acceso con punto
│   ├── JWTTest.php             ← generación, validación y expiración
│   ├── QueryBuilderTest.php    ← construcción de queries SQL
│   ├── RequestTest.php         ← parsing de método, URL y body
│   ├── ResponseTest.php        ← serialización JSON y códigos HTTP
│   ├── RouterTest.php          ← registro y resolución de rutas
│   └── ValidatorTest.php       ← reglas de validación y mensajes
└── Integration/
    ├── AuthTest.php            ← flujo completo JWT en middleware
    ├── DatabaseTest.php        ← operaciones CRUD con SQLite en memoria
    └── MigrationTest.php       ← ejecución y rollback de migraciones
```

Las pruebas de integración usan **SQLite en memoria** (`:memory:`). No requieren ninguna base de datos externa instalada.

---

## `BaseTestCase`

La clase base proporciona helpers para las pruebas:

```php
// Crear un directorio temporal (se limpia automáticamente en tearDown)
$path = $this->createTempPath('prefijo-');

// Escribir un archivo en el path temporal
$this->writeFile($path . '/config/app.php', '<?php return [];');

// Construir una instancia de Config con valores inyectados
$config = $this->makeConfig($basePath, app: ['debug' => true], database: ['driver' => 'sqlite']);
```

---

## Escribir pruebas en proyectos generados

Cuando se usa `make:scaffold Recurso`, se generan automáticamente:

- `tests/Unit/RecursoTest.php`
- `tests/Integration/RecursoIntegrationTest.php`

### Estructura de una prueba unitaria generada

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductoTest extends TestCase
{
    public function test_instancia_correcta(): void
    {
        $this->assertTrue(true); // reemplazar con assertions reales
    }
}
```

### Estructura de una prueba de integración generada

```php
<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Jb\Database\Connection;

class ProductoIntegrationTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        // base de datos SQLite en memoria para pruebas aisladas
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE productos (id INTEGER PRIMARY KEY, nombre TEXT)');
    }

    public function test_insertar_producto(): void
    {
        $this->pdo->exec("INSERT INTO productos (nombre) VALUES ('Café')");
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM productos');
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }
}
```

---

## Configuración PHPUnit (`phpunit.xml`)

```xml
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Las suites permiten ejecutar solo un tipo de prueba cuando se necesita rapidez:

```bash
php vendor/bin/phpunit --testsuite Unit
php vendor/bin/phpunit --testsuite Integration
```

---

## Convenciones para pruebas

- Nombre de método: `test_{qué_se_prueba}` en snake_case.
- Una sola responsabilidad por prueba.
- No usar bases de datos externas en pruebas unitarias.
- Usar SQLite en memoria para pruebas de integración que requieren base de datos.
- Limpiar estado global entre pruebas (setUp / tearDown).
- No hacer pruebas contra servicios externos (correo, APIs de terceros) en la suite principal.

---

## Pruebas en el proyecto de ejemplo

```bash
cd examples/demo_api
php jb test
# OK (3 tests, 3 assertions)
```

El proyecto de ejemplo incluye un test de humo (Ping) y las pruebas generadas por `make:scaffold`.

---

## Agregar una prueba de integración con migración real

```php
protected function setUp(): void
{
    $pdo = new \PDO('sqlite::memory:');
    Connection::setInstance($pdo);

    $migrator = new Migrator($config);
    $migrator->run();
}

public function test_crear_y_recuperar_recurso(): void
{
    $repo = new ProductoRepository($config);
    $id   = $repo->insert(['nombre' => 'Mesa', 'precio' => 250]);
    $item = $repo->find($id);

    $this->assertSame('Mesa', $item['nombre']);
}
```
