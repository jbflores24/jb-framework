# Diagramas conceptuales

## 1. Ciclo HTTP

```mermaid
flowchart TD
    A[HTTP Request] --> B[public/index.php]
    B --> C[Application bootstrap]
    C --> D[Router dispatch]
    D --> E[Middleware pipeline]
    E --> F[Controller or Handler]
    F --> G[Response JSON]
```

## 2. Middleware pipeline

```text
Request
  -> SecurityMiddleware
  -> AuthMiddleware (si aplica)
  -> PermissionMiddleware (si aplica)
  -> RateLimitMiddleware (si aplica)
  -> Handler
  -> Response
```

## 3. Flujo CLI

```mermaid
flowchart TD
    A[bin/jb] --> B[ConsoleApplication]
    B --> C{Command}
    C -->|new| D[ProjectBuilder]
    C -->|make:*| E[Generator]
    C -->|migrate/seed| F[Database tools]
    D --> G[Archivos generados]
    E --> G
    F --> H[DB state]
```

## 4. Container bindings

```text
Application::bootstrap
  -> Config
  -> Router
  -> Connection
  -> Logger
  -> Cache
  -> RateLimiter
  -> Mailer
```

## 5. Flujo de autenticacion

```text
Login endpoint
  -> AuthService (emision de tokens)
  -> JWT::encode
  -> access_token + refresh_token
```

## 6. Flujo JWT

```text
Authorization: Bearer <token>
  -> AuthMiddleware
  -> JWT::decode
  -> claims al Request
  -> PermissionMiddleware valida permisos
```

## 7. Arquitectura multi-driver

```text
Config database.driver
  -> Connection (PDO)
  -> QueryBuilder(table, driver)
  -> SQL con quoting segun driver
```

## 8. Relacion QueryBuilder y Grammar

### Implementado

```text
QueryBuilder
  -> estrategia interna por driver (quote y diferencias SQL basicas)
```

### Planeado

```text
QueryBuilder
  -> Grammar interface
      -> MySqlGrammar
      -> PgSqlGrammar
      -> SqliteGrammar
```

## 9. Arquitectura del modulo Security

```mermaid
flowchart TD
    A[Request] --> B[SecurityMiddleware]
    B --> C[SecurityManager]
    C --> D[Detectors]
    D --> E[ScoringEngine]
    E --> F{Threshold}
    F -->|low| G[Allow]
    F -->|high| H[Block/Alert]
    H --> I[Security logs/admin endpoints]
```
