# Technical debt

## Deuda tecnica identificada

1. Componentes file-based en rutas criticas
- cache
- rate limiting
- logging

2. Concentracion de orquestacion en Application.

3. Ausencia de provider lifecycle formal.

4. Ausencia de event bus interno.

5. Warning pendiente en suite de tests.

## Riesgo

- escalabilidad limitada en alta concurrencia;
- incremento de acoplamiento al crecer funcionalidades;
- mayor costo de evolucion del bootstrap.

## Priorizacion sugerida

1. Resolver warning pendiente de tests.
2. Formalizar release/versionado.
3. Introducir providers.
4. Evaluar backend distribuido para piezas file-based.
5. Diseñar capa de eventos.
