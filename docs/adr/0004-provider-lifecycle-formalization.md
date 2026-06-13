# ADR 0004 - Formalizacion del lifecycle de Providers

## Estado

Planeado

## Contexto

Actualmente el registro de servicios se concentra en Application. A medida que crecen modulos y bindings, se vuelve necesario desacoplar el bootstrapping en unidades mas pequenas y versionables.

## Decision

Introducir un modelo formal de Providers para registrar servicios por modulo con lifecycle controlado.

## Alternativas consideradas

1. Mantener toda la composicion de servicios dentro de Application.
2. Resolver dependencias de forma implicita por reflection y autowiring completo.

## Consecuencias

### Ventajas

- Mejor separacion de responsabilidades.
- Bootstrap mas modular y mantenible.
- Menor riesgo de clase orquestadora sobredimensionada.

### Desventajas

- Requiere definir contratos y orden de carga.
- Incrementa el numero de piezas de infraestructura del framework.
