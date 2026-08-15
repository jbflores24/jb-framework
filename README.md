# JB Framework

JB Framework es un framework PHP 8.2 ligero para APIs REST JSON.
Se enfoca en código claro, seguridad por defecto y un núcleo pequeño y fácil de auditar.

## Qué incluye

- Núcleo HTTP con Router, Request, Response, Container y HttpException
- Capa de base de datos con QueryBuilder, BaseRepository, migraciones y seeders
- Autenticación JWT y middleware de permisos
- Módulo de seguridad con detección de amenazas, puntuación, listas de permitidos y bloqueados
- Caché en archivo, registro, correo, limitación de solicitudes y utilidades de validación
- CLI para crear proyectos, generar estructuras, ejecutar migraciones, seeders y documentación
- Suite de pruebas PHPUnit 11 con cobertura unitaria e integral

## Qué no incluye

- Plantillas HTML
- ORM como Eloquent
- Colas, jobs o workers
- WebSockets
- Sistema de eventos integrado
- Capa de almacenamiento para cargas de archivos
- OAuth o inicio de sesión social
- Interfaz administrativa
- Multitenencia
- Internacionalización

## Requisitos

- PHP 8.2 o superior
- Composer 2 o superior
- Extensión PDO y un controlador de base de datos compatible
- Un servidor web con soporte para reescritura de URLs

## Instalación del framework

```bash
git clone https://github.com/jbflores24/jb-framework.git jb
cd jb
composer install
composer test
```

## Crear un proyecto nuevo

```bash
php bin/jb new mi_api
cd mi_api
composer install
```

Luego inicia el servidor local:

```bash
php jb serve
```

## Generar un recurso

```bash
php jb make:scaffold Producto
php jb migrate
php jb seed Producto
php jb test
```

El comando make:scaffold crea el controlador, el modelo, la migración, el seeder, la prueba unitaria, la prueba de integración, el bloque de rutas, el permiso de seguridad y la entrada de auditoría.

## Documentación

- [Inicio rápido](docs/QUICKSTART.md)
- [Referencia CLI](docs/CLI_REFERENCE.md)
- [Estructura del proyecto](docs/PROJECT_STRUCTURE.md)
- [Configuración](docs/CONFIGURATION.md)
- [Pruebas](docs/TESTING.md)
- [Despliegue](docs/DEPLOYMENT.md)
- [Rendimiento](docs/PERFORMANCE.md)
- [Hoja de ruta](docs/ROADMAP.md)
- [Comunidad](docs/COMMUNITY.md)
- [Índice interno](docs/INDEX.md)

## Reproducibilidad del estudio

- [jbtest](https://github.com/jbflores24/jbtest): entorno de prueba usado para medir el comportamiento del framework.
- [pruebas_jb](https://github.com/jbflores24/pruebas_jb): herramientas, metodología y trazabilidad del estudio.
- [research-2026-v1](https://github.com/jbflores24/jb-framework/tree/research-2026-v1): referencia estable de esta documentación y de los artefactos asociados.

## Proyecto de ejemplo

El directorio `examples/demo_api/` contiene una estructura de ejemplo ligera para referencia.

Para crear un proyecto ejecutable, usa la CLI desde la raíz del framework:

```bash
php bin/jb new mi_api
cd mi_api
composer install
```

Licencia MIT.