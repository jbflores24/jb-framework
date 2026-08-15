# Ciclo de vida de ServiceProvider

## Estado

Planeado

## Alcance actual

No existe lifecycle formal de providers.

## Comportamiento implementado hoy

Los servicios se registran directamente en Application::registerSupportServices.

## Objetivo futuro

Definir lifecycle estandar:

1. register
2. boot
3. optional terminate

Con esto se busca modularizar el arranque y facilitar extensiones de terceros.
