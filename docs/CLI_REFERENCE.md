# CLI Reference

JB Framework ships with a CLI for project creation, code generation, migrations, maintenance and reporting.

## Command format

```bash
php bin/jb <command> [arguments]
```

In a generated project:

```bash
php jb <command> [arguments]
```

## Project commands

### `new <name>`

Create a new project.

```bash
php bin/jb new mi_api
```

Creates the project structure, copies `.env.example` to `.env` and adds a local `jb` launcher.

### `serve`

Start the local PHP built-in server on `127.0.0.1:8000`.

```bash
php jb serve
```

### `env`

Print a small set of useful configuration values.

```bash
php jb env
```

## Generators

### `make:controller <Name>`

Creates `app/Controllers/<Name>Controller.php`.

```bash
php jb make:controller Cliente
```

### `make:model <TableName>`

Creates `app/Repositories/<SingularName>Repository.php` from database metadata.

```bash
php jb make:model estudiantes
```

Notes:
- Uses `information_schema` metadata.
- Supports MySQL and PostgreSQL.
- Generates `@property` annotations for detected columns.

### `make:migration <Name>`

Creates a timestamped migration file in `database/migrations/`.

```bash
php jb make:migration create_clientes_table
```

### `make:seeder <Name>`

Creates `database/seeders/<Name>Seeder.php`.

```bash
php jb make:seeder Cliente
```

### `make:middleware <Name>`

Creates `app/Middleware/<Name>Middleware.php`.

```bash
php jb make:middleware Audit
```

### `make:test <Name>`

Creates `tests/Unit/<Name>Test.php`.

```bash
php jb make:test Cliente
```

### `make:service <Name>`

Creates `app/Services/<Name>Service.php`.

```bash
php jb make:service Cliente
```

### `make:crud <Name>`

Creates controller, model, migration, seeder and REST routes.

```bash
php jb make:crud Cliente
```

It registers these routes in `routes/api.php`:

- `GET /api/clientes`
- `GET /api/clientes/{id}`
- `POST /api/clientes`
- `PUT /api/clientes/{id}`
- `DELETE /api/clientes/{id}`

### `make:scaffold <Name>`

Extends `make:crud` with tests and security-related project entries.

```bash
php jb make:scaffold Producto
```

It creates:
- `tests/Unit/ProductoUnitTest.php`
- `tests/Integration/ProductoScaffoldTest.php`

It also appends the scaffold route block, registers the security permission entry and writes the audit log entry.

### `stub:publish`

Copy the framework stubs into the current project so they can be customized locally.

```bash
php jb stub:publish
```

## Database commands

### `migrate`

Run pending migrations.

```bash
php jb migrate
```

### `migrate:rollback`

Rollback the last batch of migrations.

```bash
php jb migrate:rollback
```

### `migrate:fresh`

Drop and re-run all migrations. This removes data.

```bash
php jb migrate:fresh
```

### `migrate:status`

Show the status of every migration.

```bash
php jb migrate:status
```

### `seed [Name]`

Run one seeder or all seeders.

```bash
php jb seed
php jb seed Producto
php jb seed ProductoSeeder
```

## Maintenance commands

### `cache:clear`

Clear `storage/cache/`.

```bash
php jb cache:clear
```

### `logs:clear`

Clear `storage/logs/`.

```bash
php jb logs:clear
```

## Quality and docs

### `test`

Run the project test suite.

```bash
php jb test
```

This delegates to `composer test`.

### `docs:generate`

Generate `docs/swagger.yaml` from `routes/api.php`.

```bash
php jb docs:generate
```

## Operational tools

### `alerts:check-silence [hours]`

Check for silent tanks and generate alerts when needed.

```bash
php jb alerts:check-silence 24
```

### `producer:erase <producer_id>`

Delete a producer and its related data.

```bash
php jb producer:erase 15
```

### `db:backup`

Create a database backup under `storage/backups/`.

```bash
php jb db:backup
```

### `report:generate <producer_id> [days]`

Generate a CSV report for a producer.

```bash
php jb report:generate 15 7
```

## Help

```bash
php jb help
```

Print the list of supported commands.