# Academic protocol

## Hipotesis tecnicas sugeridas

1. Un nucleo con menor complejidad estructural reduce tiempo de onboarding tecnico.
2. La estrategia PDO + QueryBuilder reduce overhead de abstraccion frente a ORM pesado en escenarios CRUD simples.
3. Pipeline de seguridad integrado mejora trazabilidad de incidentes sin dependencia de componentes externos.

## Variables

### Independientes

- framework evaluado;
- escenario de carga;
- tipo de operacion (read/write/auth).

### Dependientes

- latencia;
- memoria;
- tiempo de bootstrap;
- queries por operacion;
- defectos detectados por tests de regresion.

## Metodologia

1. Definir escenarios equivalentes.
2. Ejecutar benchmarks con repeticion controlada.
3. Registrar resultados en formato estructurado.
4. Aplicar analisis estadistico descriptivo.
5. Interpretar tradeoffs de arquitectura.

## Productos publicables potenciales

- reporte comparativo multi-framework para APIs REST JSON;
- estudio sobre costo de seguridad integrada en frameworks ligeros;
- estudio sobre mantenibilidad y acoplamiento en nucleos PHP minimalistas.
