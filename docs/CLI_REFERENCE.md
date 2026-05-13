# Referencia CLI

Comando base:

```bash
php bin/jb <comando>
```

En proyectos generados:

```bash
php jb <comando>
```

## Proyecto

- `new <nombre>`: crea proyecto API nuevo.
- `serve`: levanta servidor PHP embebido (`127.0.0.1:8000`).
- `env`: imprime claves de configuración relevantes.

## Generadores

- `make:controller <Nombre>`
- `make:model <Nombre>`
- `make:migration <Nombre>`
- `make:seeder <Nombre>`
- `make:middleware <Nombre>`
# Referencia del CLI

JB Framework incluye una interfaz de línea de comandos para crear proyectos, generar artefactos de código, gestionar la base de datos y realizar operaciones de mantenimiento.

**En el framework:**

```bash
php bin/jb <comando> [argumentos]
```

**En un proyecto generado:**

```bash
php jb <comando> [argumentos]
```

---

## Ayuda

```bash
php jb help
```

Muestra la lista de todos los comandos disponibles con una descripción breve.

---

## Gestión de proyectos

### `new <nombre>`

Crea un proyecto API nuevo con la estructura completa.

```bash
php bin/jb new mi_api
php bin/jb new proyectos/mi_api   # dentro de un subdirectorio
```

Genera: estructura de directorios, `composer.json`, `.env`, `.htaccess`, rutas base, configuraciones y un archivo `jb` local para usar sin prefijo `bin/`.

### `serve`

Levanta el servidor de desarrollo PHP embebido.

```bash
php jb serve
# Servidor iniciado en http://127.0.0.1:8000
```

Solo para desarrollo local. No usar en producción.

### `env`

Muestra las variables de entorno activas relevantes para el framework.

```bash
php jb env
```

---

## Generadores de código

Todos los generadores reciben el nombre en **PascalCase** y crean el archivo correspondiente en el directorio apropiado dentro de `app/`.

### `make:controller <Nombre>`

```bash
php jb make:controller Cliente
# → app/Controllers/ClienteController.php
```

Genera un controlador vacío con los métodos `index`, `show`, `store`, `update` y `destroy`.

### `make:model <Nombre>`

```bash
php jb make:model Cliente
# → app/Models/Cliente.php
```

Genera un modelo que extiende `BaseRepository` con la tabla y campos configurables.

### `make:migration <Nombre>`

```bash
php jb make:migration create_clientes_table
# → database/migrations/2026_05_12_143022_create_clientes_table.php
```

El timestamp se genera automáticamente. La migración incluye los métodos `up()` y `down()`.

### `make:seeder <Nombre>`

```bash
php jb make:seeder Cliente
# → database/seeders/ClienteSeeder.php
```

### `make:middleware <Nombre>`

```bash
php jb make:middleware Audit
# → app/Middleware/AuditMiddleware.php
```

### `make:test <Nombre>`

```bash
php jb make:test Cliente
# → tests/Unit/ClienteTest.php
```

### `make:crud <Nombre>`

Genera controlador, modelo, migración, seeder y registra las rutas REST, sin pruebas.

```bash
php jb make:crud Cliente
```

Rutas que agrega en `routes/api.php`:

```
GET    /api/clientes
GET    /api/clientes/{id}
POST   /api/clientes
PUT    /api/clientes/{id}
DELETE /api/clientes/{id}
```

### `make:scaffold <Nombre>`

Extiende `make:crud` con pruebas unitarias, prueba de integración, registro de permiso de seguridad y entrada de auditoría.

```bash
php jb make:scaffold Producto
```

Artefactos generados:

| Archivo | Descripción |
|---|---|
| `app/Controllers/ProductoController.php` | Controlador REST completo |
| `app/Models/Producto.php` | Modelo con repositorio |
| `database/migrations/{ts}_create_productos_table.php` | Migración |
| `database/seeders/ProductoSeeder.php` | Seeder |
| `tests/Unit/ProductoTest.php` | Test unitario |
| `tests/Integration/ProductoIntegrationTest.php` | Test de integración |
| Rutas en `routes/api.php` | 5 rutas REST |

### `stub:publish`

Copia los stubs del framework al directorio `stubs/` del proyecto para que puedan editarse localmente.

```bash
php jb stub:publish
# Stubs publicados en stubs/
```

Una vez publicados, los generadores usarán los stubs locales en lugar de los del framework.

---

## Base de datos

### `migrate`

Aplica todas las migraciones pendientes en orden cronológico.

```bash
php jb migrate
# Aplicando: 2026_05_12_000000_create_security_tables ... OK
# Aplicando: 2026_05_12_143022_create_productos_table ... OK
```

### `migrate:status`

Muestra el estado de cada migración (ejecutada / pendiente).

```bash
php jb migrate:status
```

### `migrate:rollback`

Revierte el último lote de migraciones ejecutadas.

```bash
php jb migrate:rollback
```

### `migrate:fresh`

Revierte todas las migraciones y las vuelve a aplicar desde cero. **Borra todos los datos.**

```bash
php jb migrate:fresh
```

### `seed [Nombre]`

Ejecuta uno o todos los seeders.

```bash
php jb seed              # ejecuta todos los seeders en database/seeders/
php jb seed Producto     # ejecuta ProductoSeeder
php jb seed ProductoSeeder  # también válido
```

---

## Mantenimiento

### `cache:clear`

Elimina todos los archivos en `storage/cache/`.

```bash
php jb cache:clear
# Caché limpiada.
```

### `logs:clear`

Elimina todos los archivos en `storage/logs/`.

```bash
php jb logs:clear
# Logs eliminados.
```

---

## Calidad y documentación

### `test`

Ejecuta la suite de pruebas del proyecto con PHPUnit.

```bash
php jb test
# OK (N tests, N assertions)
```

Equivalente a `composer test` dentro del proyecto.

### `docs:generate`

Analiza `routes/api.php` y genera un archivo `docs/swagger.yaml` con la especificación OpenAPI básica.

```bash
php jb docs:generate
# Documentación generada en docs/swagger.yaml
```
