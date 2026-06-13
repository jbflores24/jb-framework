# Modulo Database

## Proposito

Proveer acceso a datos multi-driver con bajo acoplamiento.

## Responsabilidades

- inicializacion de conexion PDO;
- construccion de queries con QueryBuilder;
- soporte de migraciones y seeders.

## Clases principales

- Connection
- QueryBuilder
- BaseRepository
- Migrator
- Migration
- Seeder
- Blueprint
- ColumnDefinition

## Dependencias

- Config para parametros de conexion.
- PDO como infraestructura base.

## Flujo interno

Config selecciona driver -> Connection crea PDO -> QueryBuilder construye SQL segun driver -> repositorios y servicios consumen resultados.

## Extensibilidad

- agregar drivers adicionales;
- evolucion a capa Grammar dedicada (planeado).

## Riesgos tecnicos

- diferencia de dialectos SQL en escenarios complejos;
- ausencia de grammar formal aun.
