# ADR 0002 - PDO + QueryBuilder en lugar de ORM pesado

## Estado

Aceptado

## Contexto

Se requeria un mecanismo de persistencia multi-driver con menor complejidad operativa que un ORM completo, manteniendo control explicito de SQL y costos en runtime.

## Decision

Se adopta PDO como base de conexion y QueryBuilder propio como capa de construccion de queries.

## Alternativas consideradas

1. Integrar ORM completo de terceros.
2. Escribir SQL crudo en toda la aplicacion sin abstraccion.

## Consecuencias

### Ventajas

- Menor acoplamiento con paquetes externos.
- Mejor trazabilidad de consultas.
- Soporte multi-driver mantenible con menor overhead.

### Desventajas

- Menos capacidades avanzadas out-of-the-box que un ORM grande.
- Algunas convenciones deben resolverse a nivel de repositorio/aplicacion.
