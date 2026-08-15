# Módulo Providers

## Estado

Planeado

## Proposito esperado

Desacoplar el registro de servicios del arranque principal mediante unidades de inicialización por módulo.

## Alcance actual

No hay carpeta `src/Providers` ni un ciclo de vida formal de providers.

## Situacion implementada

`Application` registra bindings de forma directa durante el arranque.

## Riesgos actuales

- crecimiento de responsabilidades en `Application`;
- mayor fricción para extender módulos sin tocar el núcleo.

## Direccion recomendada

- definir una base para `ServiceProvider`;
- separar `register` y `boot`;
- declarar orden de carga y políticas de sobrescritura.
