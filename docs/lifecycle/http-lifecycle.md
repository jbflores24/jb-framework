# HTTP lifecycle

## Implementado

1. Entrada por public/index.php del proyecto.
2. Bootstrap via Application.
3. Registro/carga de rutas.
4. Dispatch de Router.
5. Ejecucion de pipeline de middlewares.
6. Ejecucion de handler.
7. Envio de Response JSON.
8. Manejo centralizado de excepciones.

## Puntos de extension

- middlewares por ruta;
- bindings de Container;
- servicios de Auth/Security/Rate.
