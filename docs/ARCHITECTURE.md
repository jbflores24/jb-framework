# Arquitectura

# Arquitectura interna

Este documento describe las decisiones de diseño, la organización por capas y el flujo de ejecución del framework.

---

## Principios de diseño

- **Sin magia implícita**: cada comportamiento es explícito y rastreable desde el código.
- **Dependencias mínimas**: el framework no requiere ningún paquete de terceros en producción.
- **Solo JSON**: no hay motor de plantillas ni renderizado HTML; todas las respuestas son `application/json`.
- **Tipado estricto**: todos los archivos PHP usan `declare(strict_types=1)`.
- **Testeable desde el núcleo**: el diseño permite inyectar dependencias en cualquier nivel.

---

## Capas del framework

```
┌─────────────────────────────────────────────────┐
│                   CLI (Console)                 │
│        new / make:* / migrate / seed / test     │
├─────────────────────────────────────────────────┤
│                   Core HTTP                     │
│     Router → Middleware → Controller → Response │
├──────────────────┬──────────────────────────────┤
│    Auth / JWT    │   Security Module            │
├──────────────────┼──────────────────────────────┤
│    Database      │   Validation / Cache / Log   │
│  PDO · QB · Repo │                              │
└──────────────────┴──────────────────────────────┘
```

### Core (`src/Core/`)

Contiene los componentes fundamentales del ciclo de vida HTTP:

- **`Application`**: inicializa configuración, contenedor y router; captura excepciones globales.
- **`Router`**: registro de rutas con soporte de grupos, prefijos y pilas de middleware. Resuelve por método + patrón de URL con soporte de parámetros dinámicos (`/api/productos/{id}`).
- **`Request`**: encapsula `$_SERVER`, `$_GET`, `$_POST` y el cuerpo JSON. Proporciona acceso tipado a parámetros.
- **`Response`**: construye y envía respuestas JSON con código HTTP y cabeceras CORS.
- **`Container`**: contenedor de inyección de dependencias ligero. Registra singletons y fábricas.
- **`Config`**: carga archivos PHP de `config/` en caché de memoria. Acceso con notación de punto (`config('database.driver')`).
- **`HttpException`**: excepción tipada con código HTTP que el núcleo convierte automáticamente en respuesta JSON.

### Database (`src/Database/`)

- **`Connection`**: singleton PDO. Inicializa la conexión una vez y la reutiliza. Soporta MySQL, PostgreSQL y SQLite.
- **`QueryBuilder`**: API fluida para construir SELECT, INSERT, UPDATE, DELETE sin SQL directo. Usa prepared statements internamente.
- **`BaseRepository`**: clase base de repositorios. Provee `find()`, `findAll()`, `insert()`, `update()`, `delete()` sobre `QueryBuilder`.
- **`Blueprint`** y **`ColumnDefinition`**: API fluida para definir columnas en migraciones.
- **`Migration`**: contrato base con métodos `up()` y `down()`.
- **`Migrator`**: lee archivos de `database/migrations/`, los ordena por timestamp y los ejecuta. Registra el estado en la tabla `jb_migrations`.
- **`Seeder`**: contrato base con método `run()`.

### Auth (`src/Auth/`)

- **`JWT`**: generación y validación de tokens con firma HMAC-SHA256. Sin dependencias externas.
- **`AuthMiddleware`**: extrae el token del header `Authorization: Bearer`, lo valida y carga el payload en el request.
- **`PermissionMiddleware`**: verifica que el payload del token incluya el permiso requerido para la ruta.

### Security (`src/Security/`)

Módulo de detección de amenazas y administración de seguridad. Funciona como middleware y como panel REST independiente.

**Detectores** (heredan de `AbstractDetector`):

| Detector | Qué detecta |
|---|---|
| `InjectionDetector` | SQL injection, XSS, command injection |
| `BotDetector` | User-agents de bots y scrapers conocidos |
| `PathDetector` | Path traversal (`../`) |
| `PayloadDetector` | Payloads anormalmente grandes o con patrones sospechosos |
| `LoginDetector` | Fuerza bruta en endpoints de login |
| `MethodDetector` | Métodos HTTP no permitidos |
| `NotFoundDetector` | Escaneo de rutas inexistentes |
| `RateLimitDetector` | Exceso de peticiones por IP |
| `SessionDetector` | Manipulación de sesión |

**Servicios:**

- `ScoringEngine`: acumula puntuación de riesgo por IP. Cuando supera el umbral configurable, activa un bloqueo automático.
- `SecurityManager`: orquesta detección, puntuación y bloqueo en una sola llamada.
- `CsrfService`: generación y validación de tokens CSRF para formularios.
- `CleanupService`: limpieza periódica de registros de auditoría y bloqueos expirados.

**Panel REST** (`/api/security/`): endpoints para consultar logs, gestionar lista negra/blanca y revisar puntuaciones.

### Console (`src/Console/`)

- **`ConsoleApplication`**: dispatcher central. Lee `$argv[1]` y delega al método correspondiente.
- **`Generator`**: genera artefactos de código leyendo stubs y reemplazando placeholders.
- **`ProjectBuilder`**: crea la estructura completa de un proyecto nuevo, incluyendo el `composer.json` del proyecto con referencia path al framework.

---

## Flujo HTTP de una petición

```
Petición HTTP
	│
	▼
public/index.php
	│  carga autoload, instancia Application
	▼
Application::run()
	│  inicializa Config, Container, Router
	│  incluye routes/api.php
	▼
Router::dispatch()
	│  empareja método + URL
	│  ejecuta pila de middleware (FIFO)
	▼
Middleware(s)
	│  Auth, Permission, RateLimit, Security, ...
	│  cada uno puede interrumpir con HttpException
	▼
Controller::método()
	│  usa modelos/repositorios
	│  construye array de datos
	▼
Response::json()
	│  serializa a JSON, escribe cabeceras
	▼
Respuesta HTTP (application/json)
```

En caso de excepción en cualquier punto, `Application` la captura y emite una respuesta JSON con el código y mensaje apropiados.

---

## Sistema de generación de código

Los stubs son archivos de texto con placeholders que el `Generator` sustituye al crear artefactos:

| Placeholder | Valor |
|---|---|
| `{{ClassName}}` | Nombre en PascalCase (`Producto`) |
| `{{className}}` | Nombre en camelCase (`producto`) |
| `{{class_name}}` | Nombre en snake_case (`producto`) |
| `{{table_name}}` | Tabla en snake_case plural (`productos`) |
| `{{timestamp}}` | Timestamp de migración (`2026_05_12_143022`) |
| `{{namespace}}` | Namespace del proyecto |

Los stubs pueden publicarse al proyecto con `stub:publish` para editarlos localmente sin modificar el framework.

---

## Contenedor de inyección de dependencias

El `Container` es intencional y deliberadamente simple. Soporta:

- **Singleton**: registra una instancia única reutilizable.
- **Fábrica**: registra un closure que se ejecuta en cada solicitud.
- **Resolución**: `$container->get(Clase::class)`.

No hace resolución automática de constructores. Las dependencias se registran explícitamente en el bootstrap de la aplicación.

---

## Persistencia de migraciones

El `Migrator` mantiene la tabla `jb_migrations` con columnas:

| Columna | Descripción |
|---|---|
| `migration` | Nombre del archivo de migración |
| `batch` | Número de lote de ejecución |
| `executed_at` | Timestamp de ejecución |

`migrate:rollback` revierte el último lote completo. `migrate:fresh` elimina la tabla y la recrea.
