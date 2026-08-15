# Metodología de benchmark

## Objetivo

Medir el comportamiento técnico del framework con criterios repetibles y comparables.

## Alcance actual

Implementado:

- benchmarks internos para `Router`, `QueryBuilder` y `JWT` en `tests/Benchmark`.

Planeado:

- comparativos sistemáticos contra frameworks externos.
- batería de medición con infraestructura dedicada.

## Metricas

1. Latencia media por solicitud u operación.
2. Uso de memoria por flujo.
3. Tiempo de bootstrap.
4. Tiempo de generación con la CLI.
5. Complejidad ciclomatica por modulo.
6. Número de consultas por endpoint representativo.

## Reglas de medicion

- misma máquina y versión de PHP para comparaciones directas;
- calentamiento previo;
- n iteraciones por escenario;
- reporte de promedio y dispersión;
- guardar el contexto del entorno junto al resultado.

## Comparativos externos

Estado: planeado.

Objetivos sugeridos:

- Laravel
- Slim
- CodeIgniter

La comparación debe limitarse a escenarios equivalentes para evitar sesgos.
