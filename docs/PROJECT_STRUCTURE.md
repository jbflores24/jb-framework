# Estructura del proyecto

Esta página describe la organización de directorios y archivos del framework y de los proyectos que genera el CLI.

---

## Estructura del framework (`jb/`)

```
jb/
├── bin/
│   └── jb                      ← ejecutable CLI (PHP)
├── src/
│   ├── Auth/
│   │   ├── JWT.php             ← generación y validación de tokens
│   │   ├── AuthMiddleware.php  ← protección de rutas por token
│   │   └── PermissionMiddleware.php ← control por permisos
│   ├── Cache/
│   │   ├── CacheInterface.php
│   │   └── FileCache.php       ← caché en sistema de archivos
│   ├── Console/
│   │   ├── ConsoleApplication.php ← dispatcher de comandos CLI
│   │   ├── Generator.php       ← generación de artefactos desde stubs
│   │   └── ProjectBuilder.php  ← creación de proyectos nuevos
│   ├── Core/
│   │   ├── Application.php     ← bootstrap y ciclo de vida HTTP
│   │   ├── Config.php          ← lectura de archivos de configuración
│   │   ├── Container.php       ← contenedor de inyección de dependencias
│   │   ├── HttpException.php   ← excepciones HTTP tipadas
│   │   ├── Request.php         ← abstracción de la solicitud HTTP
│   │   ├── Response.php        ← construcción de respuestas JSON
│   │   └── Router.php          ← registro y resolución de rutas
│   ├── Database/
│   │   ├── Connection.php      ← singleton PDO multi-driver
│   │   ├── QueryBuilder.php    ← construcción fluida de consultas SQL
│   │   ├── BaseRepository.php  ← CRUD base para repositorios
│   │   ├── Blueprint.php       ← definición de columnas en migraciones
│   │   ├── ColumnDefinition.php ← fluent API de columnas
│   │   ├── Migration.php       ← contrato base de migración
│   │   ├── Migrator.php        ← ejecución y registro de migraciones
│   │   └── Seeder.php          ← contrato base de seeder
│   ├── Logging/
│   │   ├── LoggerInterface.php
│   │   └── Logger.php          ← escritura en archivos de log
│   ├── Mail/
│   │   └── Mailer.php          ← envío básico por SMTP/mail()
│   ├── RateLimit/
│   │   ├── RateLimiter.php     ← control de peticiones por IP y ventana
│   │   └── RateLimitMiddleware.php
│   ├── Security/
│   │   ├── SecurityMiddleware.php     ← integración con el pipeline HTTP
│   │   ├── SecurityRoutes.php         ← rutas del panel de administración
│   │   ├── config/
│   │   │   └── SecurityConfig.php    ← umbrales y parámetros del módulo
│   │   ├── Controllers/
│   │   │   └── SecurityAdminController.php ← panel REST de seguridad
│   │   ├── detectors/                ← detectores de amenazas (9 clases)
│   │   │   ├── AbstractDetector.php
│   │   │   ├── BotDetector.php
│   │   │   ├── InjectionDetector.php
│   │   │   ├── LoginDetector.php
│   │   │   ├── MethodDetector.php
│   │   │   ├── NotFoundDetector.php
│   │   │   ├── PathDetector.php
│   │   │   ├── PayloadDetector.php
│   │   │   ├── RateLimitDetector.php
│   │   │   └── SessionDetector.php
│   │   ├── models/                   ← modelos de tablas security_*
│   │   │   ├── AuditModel.php
│   │   │   ├── BlacklistModel.php
│   │   │   ├── BlockModel.php
│   │   │   ├── LogModel.php
│   │   │   ├── ScoreModel.php
│   │   │   ├── SecurityModel.php
│   │   │   └── WhitelistModel.php
│   │   ├── services/                 ← lógica de negocio del módulo
│   │   │   ├── CleanupService.php
│   │   │   ├── CsrfService.php
│   │   │   ├── ScoringEngine.php
│   │   │   └── SecurityManager.php
│   │   └── utils/
│   │       └── SecurityRequest.php
│   └── Validation/
│       └── Validator.php             ← validación de datos de entrada
├── stubs/
│   ├── controller.stub         ← plantilla de controlador
│   ├── middleware.stub
│   ├── migration.stub
│   ├── model.stub
│   ├── seeder.stub
│   ├── test.stub
│   ├── project/                ← archivos base de un proyecto generado
│   │   ├── config/
│   │   ├── database/migrations/
│   │   ├── public/
│   │   └── routes/
│   └── scaffold/               ← plantillas de scaffolding con CRUD+test
│       ├── controller.stub
│       ├── migration.stub
│       ├── model.stub
│       └── test.stub
├── tests/
│   ├── BaseTestCase.php        ← base con helpers de prueba
│   ├── Unit/                   ← pruebas unitarias por módulo
│   └── Integration/            ← pruebas de integración con SQLite
├── examples/
│   └── demo_api/               ← proyecto de referencia generado con CLI
├── docs/                       ← esta documentación
├── composer.json
├── phpunit.xml
├── CONTRIBUTING.md
└── README.md
```

---

## Estructura de un proyecto generado

Cuando se ejecuta `php bin/jb new nombre_proyecto`, se crea la siguiente estructura:

```
nombre_proyecto/
├── app/
│   ├── Controllers/            ← controladores de la API
│   └── Models/                 ← modelos de entidades
├── database/
│   ├── migrations/             ← archivos de migración con timestamp
│   └── seeders/                ← seeders de datos
├── public/
│   ├── index.php               ← único punto de entrada HTTP
│   └── .htaccess               ← reglas de reescritura para Apache
├── routes/
│   └── api.php                 ← registro de todas las rutas
├── config/
│   ├── app.php                 ← configuración general
│   ├── auth.php                ← parámetros JWT
│   ├── cache.php               ← configuración de caché
│   ├── database.php            ← driver, host, credenciales
│   ├── logging.php             ← nivel y ruta de logs
│   ├── mail.php                ← SMTP y remitente
│   ├── rate_limit.php          ← ventana y límite de peticiones
│   └── security.php            ← umbales del módulo de seguridad
├── storage/
│   ├── cache/                  ← archivos de caché
│   └── logs/                   ← archivos de log
├── tests/
│   ├── Unit/
│   └── Integration/
├── stubs/                      ← stubs locales si se ejecutó stub:publish
├── .env                        ← variables de entorno (no versionar)
├── .env.example                ← plantilla de variables
├── composer.json
├── phpunit.xml
└── jb                          ← enlace al CLI para uso local
```

---

## Convenciones de nombres

| Tipo | Convención | Ejemplo |
|---|---|---|
| Clases | PascalCase | `ProductoController` |
| Métodos | camelCase | `findBySlug()` |
| Tablas | snake_case plural | `productos` |
| Archivos de migración | `{timestamp}_create_{tabla}_table.php` | `2026_05_12_000000_create_productos_table.php` |
| Archivos de seeder | `{Nombre}Seeder.php` | `ProductoSeeder.php` |
| Rutas en `api.php` | kebab-case | `/api/mis-productos` |

---

## Namespaces

| Directorio en el framework | Namespace |
|---|---|
| `src/` | `Jb\` |
| `tests/` | `Jb\Tests\` |

En proyectos generados el namespace de `app/` se configura en el `composer.json` del proyecto según el nombre elegido.
