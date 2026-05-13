# Modulo Events

## Estado

Planeado

## Proposito esperado

Introducir un mecanismo de eventos para desacoplar reacciones del flujo principal.

## Alcance actual

No existe un dispatcher de eventos formal en src.

## Riesgos de no implementarlo

- integraciones cruzadas por acoplamiento directo;
- mayor carga de orquestacion en clases centrales.

## Direccion recomendada

- definir contrato EventDispatcher;
- listeners por modulo;
- politica clara de eventos de dominio vs infraestructura.
