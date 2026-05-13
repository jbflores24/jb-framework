# ADR 0003 - Arquitectura con seguridad como capacidad central

## Estado

Aceptado

## Contexto

Las APIs expuestas requieren controles de seguridad consistentes en autenticacion, autorizacion, validacion de entrada y deteccion de patrones maliciosos.

## Decision

Se integra seguridad en el flujo principal mediante middlewares y modulo dedicado de deteccion con scoring y administracion.

## Alternativas consideradas

1. Tratar seguridad como paquete externo opcional.
2. Delegar toda la seguridad a infraestructura externa.

## Consecuencias

### Ventajas

- Controles de seguridad disponibles desde etapas tempranas.
- Menor riesgo de omisiones en proyectos generados.
- Mayor trazabilidad de eventos de seguridad.

### Desventajas

- Mayor superficie de codigo a mantener en el propio framework.
- Riesgo de complejidad creciente en modulo Security si no se gobierna bien el alcance.
