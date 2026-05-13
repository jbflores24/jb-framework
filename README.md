# JB Framework

Framework PHP 8.2 minimalista para el desarrollo de APIs REST. Orientado a proyectos donde se prioriza el control total del código, la seguridad y la simplicidad operativa sobre la conveniencia de frameworks de propósito general.

**Autor:** José Braulio Flores Martínez  
**Licencia:** MIT  
**Requiere:** PHP 8.2+ · Composer 2+

---

## Para qué sirve

JB Framework está diseñado exclusivamente para construir **APIs REST que responden JSON**. No gestiona vistas HTML, sesiones de navegador ni renderizado de plantillas. Su propósito es ofrecer una base sólida, auditable y ligera para servicios backend.

---

## Qué incluye

### Núcleo HTTP
- Router con soporte de métodos GET, POST, PUT, PATCH, DELETE
- Objetos `Request` y `Response` propios
- Contenedor de inyección de dependencias simple
- Sistema de configuración basado en archivos PHP
- Manejo centralizado de excepciones HTTP

### Base de datos
- Conexión PDO con soporte para MySQL, PostgreSQL y SQLite
- `QueryBuilder` fluido para construir consultas sin SQL directo
- `BaseRepository` como clase base para repositorios de entidades
- Sistema de migraciones versionadas con registro de estado
- Seeders para población de datos

### Autenticación y autorización
- JWT con firma HMAC-SHA256
- `AuthMiddleware` para proteger rutas
- `PermissionMiddleware` para control por permisos

### Seguridad
- Módulo de detección de amenazas con 9 detectores especializados (inyección, bots, path traversal, payload sospechoso, etc.)
- Motor de puntuación de riesgo por IP
- Listas blancas y negras
- Servicio CSRF
- Panel REST de administración de seguridad
- Rate limiting configurable

### Utilidades
- Validador de datos de entrada con reglas encadenables
- Logger con niveles (debug, info, warning, error)
- Caché en archivo con interfaz extensible
- Mailer básico (SMTP vía `mail()`)

### CLI (`bin/jb`)
- Creación de proyectos nuevos con estructura completa
- Generadores de código: controlador, modelo, migración, seeder, middleware, test
- `make:crud` y `make:scaffold` para scaffolding completo con pruebas
- Publicación de stubs editables
- Ejecución de migraciones, seeders y limpieza de caché/logs
- Generación de documentación OpenAPI básica

### Pruebas
- Suite PHPUnit 11 propia del framework
- Tests unitarios e integración con SQLite en memoria
- Proyectos generados incluyen tests de scaffolding listos para ejecutar

---

## Qué NO incluye

Estas características están fuera del alcance del framework por diseño:

| Característica | Alternativa sugerida |
|---|---|
| Motor de plantillas (Blade, Twig, etc.) | No aplica — el framework es solo para JSON |
| ORM tipo Active Record (Eloquent, etc.) | Se usa QueryBuilder + BaseRepository |
| Sistema de colas y workers | Implementar con Redis/RabbitMQ según el proyecto |
| WebSockets | No soportado |
| Sistema de eventos y listeners | No incluido |
| Carga y almacenamiento de archivos | No incluido |
| Autenticación OAuth2 / social login | No incluido |
| Panel de administración con UI | No incluido (solo API REST de administración) |
| Multi-tenancy | No incluido |
| Internacionalización (i18n) | No incluida |

---

## Requisitos

- PHP 8.2 o superior con extensiones: `pdo`, `pdo_mysql` (o `pdo_sqlite`), `json`, `mbstring`
- Composer 2+
- Servidor web con soporte de reescritura de URL (Apache con `mod_rewrite` o Nginx)

---

## Instalación

### Clonar el repositorio del framework

```bash
git clone https://github.com/brauliofm/jb-framework.git jb
cd jb
composer install
```

### Verificar que todo funciona

```bash
composer test
```

Resultado esperado:

```
OK (24 tests, 62 assertions)
```

---

## Crear un proyecto nuevo

```bash
php bin/jb new mi_api
cd mi_api
composer install
```

Levantar servidor de desarrollo:

```bash
php jb serve
# Escuchando en http://127.0.0.1:8000
```

### Generar un recurso completo

```bash
php jb make:scaffold Producto
php jb migrate
php jb seed Producto
```

Esto crea el controlador, modelo, migración, seeder, rutas REST y pruebas automáticamente.

---

## Estructura de un proyecto generado

```
mi_api/
├── app/
│   ├── Controllers/
│   └── Models/
├── database/
│   ├── migrations/
│   └── seeders/
├── public/
│   ├── index.php       ← punto de entrada
│   └── .htaccess
├── routes/
│   └── api.php
├── config/
│   ├── app.php
│   ├── database.php
│   └── ...
├── storage/
│   ├── cache/
│   └── logs/
├── tests/
├── .env
└── composer.json
```

---

## Documentación

| Archivo | Contenido |
|---|---|
| [docs/QUICKSTART.md](docs/QUICKSTART.md) | De cero a API funcional paso a paso |
| [docs/PROJECT_STRUCTURE.md](docs/PROJECT_STRUCTURE.md) | Estructura completa con descripción de cada componente |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Arquitectura interna, capas y flujo HTTP |
| [docs/CLI_REFERENCE.md](docs/CLI_REFERENCE.md) | Referencia completa de comandos CLI |
| [docs/EXAMPLES.md](docs/EXAMPLES.md) | Ejemplos de uso con salidas esperadas |
| [docs/CONFIGURATION.md](docs/CONFIGURATION.md) | Configuración detallada de cada módulo |
| [docs/TESTING.md](docs/TESTING.md) | Guía de pruebas: ejecución y escritura |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Convenciones y flujo para contribuir |

---

## Proyecto de ejemplo

El directorio `examples/demo_api/` contiene un proyecto generado con el CLI y configurado con SQLite, tres recursos scaffoldeados (Ping, Cliente, Producto) y pruebas listas para ejecutar.

```bash
cd examples/demo_api
composer install
php jb migrate
php jb test
```

---

## Licencia

MIT — libre para uso personal y comercial.
