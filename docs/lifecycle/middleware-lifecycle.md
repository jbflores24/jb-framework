# Ciclo de vida de middlewares

## Implementado

- Los middlewares se ejecutan como un pipeline alrededor del handler.
- Cada middleware puede:
  - permitir el paso al siguiente;
  - interrumpir el flujo con un error o control de acceso;
  - enriquecer la `Request`.

## Orden operativo

El orden de declaración en la ruta define el orden de ejecución.

## Recomendacion

Mantener los middlewares de seguridad/autorización antes de los middlewares de lógica de dominio.
