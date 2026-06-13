# Bootstrapping

## Implementado

Application realiza:

1. carga de .env y archivos config;
2. validaciones de seguridad de configuracion;
3. construccion de Container y Router;
4. registro de bindings de infraestructura;
5. configuracion de cache de rutas;
6. ejecucion de run.

## Riesgo principal

Concentracion de responsabilidades en una sola clase orquestadora.

## Planeado

Extraer bootstrapping por providers para reducir acoplamiento.
