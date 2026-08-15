# Protocolo académico

## Hipótesis técnicas sugeridas

1. Un núcleo con menor complejidad estructural reduce el tiempo de integración técnica.
2. La estrategia PDO + `QueryBuilder` reduce la sobrecarga de abstracción frente a un ORM pesado en escenarios CRUD simples.
3. Un pipeline de seguridad integrado mejora la trazabilidad de incidentes sin depender de componentes externos.

## Variables

### Independientes

- framework evaluado;
- escenario de carga;
- tipo de operación (lectura/escritura/autenticación).

### Dependientes

- latencia;
- memoria;
- tiempo de arranque;
- consultas por operación;
- defectos detectados por pruebas de regresión.

## Metodologia

1. Definir escenarios equivalentes.
2. Ejecutar benchmarks con repetición controlada.
3. Registrar resultados en formato estructurado.
4. Aplicar análisis estadístico descriptivo.
5. Interpretar compromisos de arquitectura.

## Productos publicables potenciales

- reporte comparativo entre varios frameworks para APIs REST JSON;
- estudio sobre el costo de seguridad integrada en frameworks ligeros;
- estudio sobre mantenibilidad y acoplamiento en núcleos PHP minimalistas.
