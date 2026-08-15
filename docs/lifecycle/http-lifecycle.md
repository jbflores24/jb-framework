# HTTP lifecycle

## Implementado

1. Entrada por public/index.php del proyecto.
2. Arranque mediante `Application`.
3. Registro/carga de rutas.
4. Dispatch de Router.
5. Ejecución del pipeline de middlewares.
6. Ejecución del handler.
7. Envío de `Response` JSON.
8. Manejo centralizado de excepciones.

## Puntos de extension

- middlewares por ruta;
- bindings de Container;
- servicios de Auth/Security/Rate.
