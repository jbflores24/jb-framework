# Referencia CLI

JB Framework incluye una CLI para crear proyectos, generar código, ejecutar migraciones, dar mantenimiento y producir reportes.

## Command format

```bash
php bin/jb <command> [arguments]
```

In a generated project:

```bash
php jb <command> [arguments]
```

## Comandos de proyecto

### `new <name>`

Crear un proyecto nuevo desde una copia local del framework.

```bash
php bin/jb new mi_api
```

Crea la estructura del proyecto, copia `.env.example` a `.env` y agrega un lanzador local `jb`. Para la forma recomendada, que no requiere clonar el framework, usa Composer:

```bash
composer create-project jbflores24/skeleton mi_api
```

Este comando también genera el `.env` con un `JWT_SECRET` aleatorio después de instalar las dependencias.

### `serve`

Inicia el servidor integrado de PHP en `127.0.0.1:8000`.

```bash
php jb serve
```

### `env`

Imprime un conjunto pequeño de valores útiles de configuración.

```bash
php jb env
```

## Generadores

### `make:controller <Name>`

Crea `app/Controllers/<Name>Controller.php`.

```bash
php jb make:controller Cliente
```

### `make:model <TableName>`

Crea `app/Repositories/<SingularName>Repository.php` a partir de metadatos de la base de datos.

```bash
php jb make:model estudiantes
```

Notas:
- Usa metadatos de `information_schema`.
- Soporta MySQL y PostgreSQL.
- Genera anotaciones `@property` para las columnas detectadas.

### `make:migration <Name>`

Crea un archivo de migración con marca de tiempo en `database/migrations/`.

```bash
php jb make:migration create_clientes_table
```

### `make:seeder <Name>`

Crea `database/seeders/<Name>Seeder.php`.

```bash
php jb make:seeder Cliente
```

### `make:middleware <Name>`

Crea `app/Middleware/<Name>Middleware.php`.

```bash
php jb make:middleware Audit
```

### `make:test <Name>`

Crea `tests/Unit/<Name>Test.php`.

```bash
php jb make:test Cliente
```

### `make:service <Name>`

Crea `app/Services/<Name>Service.php`.

```bash
php jb make:service Cliente
```

### `make:crud <Name>`

Crea controlador, modelo, migración, seeder y rutas REST.

```bash
php jb make:crud Cliente
```

Registra estas rutas en `routes/api.php`:

- `GET /api/clientes`
- `GET /api/clientes/{id}`
- `POST /api/clientes`
- `PUT /api/clientes/{id}`
- `DELETE /api/clientes/{id}`

### `make:scaffold <Name>`

Extiende `make:crud` con pruebas y entradas del proyecto relacionadas con seguridad.

```bash
php jb make:scaffold Producto
```

Crea:
- `tests/Unit/ProductoUnitTest.php`
- `tests/Integration/ProductoScaffoldTest.php`

También agrega el bloque de rutas del scaffold, registra la entrada de permiso de seguridad y escribe la entrada de auditoría.

### `stub:publish`

Copiar los stubs del framework al proyecto actual para personalizarlos de forma local.

```bash
php jb stub:publish
```

## Comandos de base de datos

### `migrate`

Ejecutar las migraciones pendientes.

```bash
php jb migrate
```

### `migrate:rollback`

Revertir el último lote de migraciones.

```bash
php jb migrate:rollback
```

### `migrate:fresh`

Eliminar y volver a ejecutar todas las migraciones. Esto elimina datos.

```bash
php jb migrate:fresh
```

### `migrate:status`

Mostrar el estado de cada migración.

```bash
php jb migrate:status
```

### `seed [Name]`

Ejecutar un seeder o todos los seeders.

```bash
php jb seed
php jb seed Producto
php jb seed ProductoSeeder
```

## Comandos de mantenimiento

### `cache:clear`

Limpiar `storage/cache/`.

```bash
php jb cache:clear
```

### `logs:clear`

Limpiar `storage/logs/`.

```bash
php jb logs:clear
```

## Calidad y documentación

### `test`

Ejecutar la suite de pruebas del proyecto.

```bash
php jb test
```

Esto delega en `composer test`.

### `docs:generate`

Generar `docs/swagger.yaml` a partir de `routes/api.php`.

```bash
php jb docs:generate
```

## Herramientas operativas

### `alerts:check-silence [hours]`

Comprobar estanques en silencio y generar alertas cuando sea necesario.

```bash
php jb alerts:check-silence 24
```

### `producer:erase <producer_id>`

Eliminar un productor y sus datos relacionados.

```bash
php jb producer:erase 15
```

### `db:backup`

Crear un respaldo de la base de datos en `storage/backups/`.

```bash
php jb db:backup
```

### `report:generate <producer_id> [days]`

Generar un reporte CSV para un productor.

```bash
php jb report:generate 15 7
```

## Ayuda

```bash
php jb help
```

Imprimir la lista de comandos disponibles.