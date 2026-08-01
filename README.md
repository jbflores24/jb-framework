# JB Framework

JB Framework is a lightweight PHP 8.2 framework for JSON REST APIs.
It focuses on clear code, security by default, and a small core that is easy to audit.

## What it includes

- HTTP core with Router, Request, Response, Container and HttpException
- PDO database layer with QueryBuilder, BaseRepository, migrations and seeders
- JWT authentication and permission middleware
- Security module with threat detection, scoring, allowlists and denylists
- File cache, logger, mailer, rate limiting and validation utilities
- CLI for project creation, scaffolding, migrations, seeders and docs generation
- PHPUnit 11 test suite with unit and integration coverage

## What it does not include

- HTML templating
- ORM such as Eloquent
- Queues, jobs or workers
- WebSockets
- Built-in event system
- File upload storage layer
- OAuth or social login
- Admin UI
- Multi-tenancy
- Internationalization

## Requirements

- PHP 8.2 or newer
- Composer 2 or newer
- PDO extension and a supported database driver
- A web server with URL rewriting support

## Install the framework

```bash
git clone https://github.com/jbflores24/jb-framework.git jb
cd jb
composer install
composer test
```

## Create a new project

```bash
php bin/jb new mi_api
cd mi_api
composer install
```

Then start the local server:

```bash
php jb serve
```

## Generate a resource

```bash
php jb make:scaffold Producto
php jb migrate
php jb seed Producto
php jb test
```

The scaffold command creates the controller, model, migration, seeder, unit test, integration test, route block, security permission and audit entry.

## Documentation

- [Quickstart](docs/QUICKSTART.md)
- [CLI reference](docs/CLI_REFERENCE.md)
- [Project structure](docs/PROJECT_STRUCTURE.md)
- [Configuration](docs/CONFIGURATION.md)
- [Testing](docs/TESTING.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Performance](docs/PERFORMANCE.md)
- [Roadmap](docs/ROADMAP.md)
- [Community](docs/COMMUNITY.md)
- [Internal index](docs/INDEX.md)

## Example project

The `examples/demo_api/` directory contains a lightweight example structure for reference.

To create a runnable project, use the CLI from the framework root:

```bash
php bin/jb new mi_api
cd mi_api
composer install
```

MIT License.