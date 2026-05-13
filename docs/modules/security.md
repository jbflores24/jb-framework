# Modulo Security

## Proposito

Detectar patrones de riesgo y reforzar seguridad operacional en runtime.

## Responsabilidades

- evaluacion de requests con detectores especializados;
- scoring de riesgo;
- bloqueo y registro de eventos;
- endpoints de administracion de seguridad.

## Clases principales

- SecurityMiddleware
- SecurityManager
- ScoringEngine
- detectores en src/Security/detectors
- SecurityAdminController

## Dependencias

- Core pipeline;
- Logging;
- persistencia de datos de seguridad.

## Flujo interno

Request -> SecurityMiddleware -> SecurityManager -> detectores -> scoring -> allow o block -> log/auditoria.

## Extensibilidad

- nuevos detectores;
- ajuste de umbrales y politicas.

## Riesgos tecnicos

- sobrecarga de falsos positivos;
- costos de mantenimiento si el catalogo de detectores crece sin gobernanza.
