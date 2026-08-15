# Modulo Core

## Proposito

Orquestar el arranque, el enrutamiento, el ciclo solicitud/respuesta y la resolución de dependencias.

## Responsabilidades

- carga de configuracion;
- manejo global de excepciones HTTP;
- dispatch de rutas y pipeline de middlewares;
- normalizacion de respuestas JSON.

## Clases principales

- Application
- Config
- Container
- Router
- Request
- Response
- HttpException

## Dependencias

Core depende de componentes transversales para registrar bindings durante el arranque.

## Flujo interno

Application inicializa Config y Container, registra servicios, ejecuta Router y captura excepciones.

## Extensibilidad

Actualmente mediante bindings en Container y middlewares.

## Riesgos tecnicos

- crecimiento de responsabilidades en Application;
- necesidad de formalizar provider lifecycle.
