# Architecture Overview

## 1. Vision general

JB Framework es un framework PHP 8.2 para APIs REST JSON con un enfoque deliberado en simplicidad operativa, bajo acoplamiento y trazabilidad del comportamiento en runtime.

El framework privilegia:

- flujo HTTP explicito;
- dependencias de produccion minimas;
- capa de seguridad integrada;
- capacidades de generacion por CLI para acelerar bootstrap de proyectos.

## 2. Objetivos arquitectonicos

1. Reducir complejidad accidental en el nucleo HTTP.
2. Mantener una frontera clara entre infraestructura y logica de aplicacion.
3. Permitir evolucion incremental por etapas sin reescrituras masivas.
4. Soportar multiples drivers de base de datos desde una sola interfaz de acceso.
5. Mantener testabilidad alta en componentes criticos.

## 3. Filosofia

- Explicit over implicit: cada paso relevante del ciclo de vida se puede rastrear en codigo.
- JSON first: el framework no persigue soporte de vistas HTML como objetivo central.
- Security by default: middlewares y verificaciones de seguridad forman parte del flujo principal.
- Evolucion por evidencia: decisiones y mejoras se sustentan en tests, benchmark y auditoria tecnica.

## 4. Capas del sistema

### 4.1 Capa de entrada

- HTTP entrypoint: public/index.php del proyecto generado.
- CLI entrypoint: bin/jb.

### 4.2 Capa Core

- Application
- Config
- Container
- Router
- Request
- Response
- HttpException

Responsabilidad: bootstrap, enrutamiento, resolucion de dependencias y normalizacion de respuestas.

### 4.3 Capa de capacidades transversales

- Auth
- Security
- Logging
- RateLimit/Rate
- Validation
- Cache
- Mail

Responsabilidad: seguridad, control de acceso, trazabilidad, limites de consumo y utilidades operativas.

### 4.4 Capa de persistencia

- Database/Connection
- QueryBuilder
- BaseRepository
- Migrator
- Seeder

Responsabilidad: acceso a datos, migraciones y datos semilla.

### 4.5 Capa de tooling

- ConsoleApplication
- Generator
- ProjectBuilder
- stubs

Responsabilidad: scaffolding y operaciones de mantenimiento de proyecto.

## 5. Modularidad y limites

### Implementado

- Modulos fisicos por dominio en src.
- Separacion entre Core, Database, Auth, Security y Console.
- Tests por categoria: Unit, Integration, Benchmark.

### Planeado

- Sistema formal de Providers para registro modular desacoplado.
- Capa de Events para extension basada en hooks.

## 6. Flujo de ejecucion

### HTTP

1. Application carga configuracion.
2. Application configura Container y bindings de infraestructura.
3. Router registra rutas desde archivo de rutas.
4. Request pasa por pipeline de middlewares.
5. Handler produce Response.
6. Response envia payload JSON.

### CLI

1. bin/jb delega en ConsoleApplication.
2. ConsoleApplication resuelve comando.
3. Generator o ProjectBuilder ejecutan accion.
4. Se persisten artefactos y se retorna codigo de salida.

## 7. Responsabilidades por modulo

Resumen ejecutivo:

- Core: orquestacion de ciclo de vida.
- Database: persistencia y migraciones.
- Auth: emision y validacion JWT.
- Security: deteccion y mitigacion de riesgo.
- Console: automatizacion de proyecto y codigo repetitivo.

El detalle modulo a modulo se documenta en docs/modules.

## 8. Convenciones

- strict_types en PHP.
- nombres de clases en PascalCase.
- separacion de tests por tipo.
- configuracion por archivos en config y variables de entorno.

## 9. Patrones identificados

### Implementado

- Dependency Injection Container.
- Front Controller.
- Middleware Pipeline.
- Repository Base + QueryBuilder.
- Template-based Code Generation.

### Parcialmente implementado

- Strategy-like behavior en QueryBuilder por driver.

### Planeado

- Provider pattern formal.
- Event dispatcher para extension interna.

## 10. Estrategias tecnicas

### Testing

- Unit tests para componentes atomicos.
- Integration tests para flujos end-to-end funcionales.
- Benchmarks para latencia y costo relativo de operaciones clave.

### Seguridad

- Validaciones de configuracion sensible.
- Headers y controles de seguridad en flujo HTTP.
- Modulo Security con detectores y scoring.
- Auth + Permission middlewares.

### Base de datos y multi-driver

- PDO como base de conectividad.
- QueryBuilder con diferencias de quoting por driver.
- Soporte activo para MySQL, PostgreSQL y SQLite.

## 11. Riesgos arquitectonicos actuales

1. Dependencia de componentes file-based para algunas capacidades operativas (cache, rate limiting, logging).
2. Ausencia de capa formal de eventos para extension desacoplada.
3. Acumulacion de responsabilidades en Application al crecer el framework.

## 12. Direccion de evolucion

- Consolidar provider lifecycle.
- Formalizar event system.
- Migrar componentes file-based criticos a backends escalables cuando el contexto operativo lo exija.
