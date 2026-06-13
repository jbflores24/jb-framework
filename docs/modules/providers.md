# Modulo Providers

## Estado

Planeado

## Proposito esperado

Desacoplar registro de servicios del bootstrap principal mediante unidades de inicializacion por modulo.

## Alcance actual

No hay carpeta src/Providers ni lifecycle formal de providers.

## Situacion implementada

Application registra bindings de forma directa en bootstrap.

## Riesgos actuales

- crecimiento de responsabilidades en Application;
- mayor friccion para extender modulos sin tocar nucleo.

## Direccion recomendada

- definir ServiceProvider base;
- separar register y boot;
- declarar orden de carga y politicas de override.
