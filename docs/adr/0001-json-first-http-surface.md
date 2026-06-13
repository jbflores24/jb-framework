# ADR 0001 - Superficie HTTP centrada en JSON

## Estado

Aceptado

## Contexto

El framework necesitaba una frontera de responsabilidad clara para evitar mezclar API backend y rendering de vistas, lo cual incrementa complejidad y ambiguedad de alcance.

## Decision

El nucleo HTTP se orienta a respuestas JSON como comportamiento principal del framework.

## Alternativas consideradas

1. Soportar HTML server-side como parte central.
2. Mantener JSON y HTML como objetivos equivalentes desde el inicio.

## Consecuencias

### Ventajas

- Menor superficie de mantenimiento.
- Flujo API mas predecible.
- Documentacion y testing mas enfocados.

### Desventajas

- No cubre casos de UI server-side sin extensiones.
- Puede requerir integracion externa para frontends acoplados al backend.
