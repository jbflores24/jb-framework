# Middleware lifecycle

## Implementado

- Middlewares se ejecutan como pipeline alrededor del handler.
- Cada middleware puede:
  - permitir paso al siguiente;
  - interrumpir flujo con error/control de acceso;
  - enriquecer Request.

## Orden operativo

El orden de declaracion en ruta define el orden de ejecucion.

## Recomendacion

Mantener middlewares de seguridad/autorizacion antes de middlewares de logica de dominio.
