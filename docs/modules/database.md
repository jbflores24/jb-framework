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
- Collection
- Migrator
- Migration
- Seeder
- Blueprint
- ColumnDefinition

## BaseRepository — carga de relaciones

BaseRepository incluye tres metodos protegidos para trabajar con relaciones entre tablas sin necesidad de un ORM completo:

| Metodo | Proposito | Tipo de relacion |
|---|---|---|
| `hasMany($relatedTable, $foreignKey, $id)` | Devuelve una `Collection` con las filas relacionadas. | one-to-many |
| `belongsTo($relatedTable, $localKeyColumn, $value, $relatedPrimaryKey)` | Devuelve una sola fila relacionada o `null`. | many-to-one |
| `eagerLoad($rows, $relatedTable, $localKey, $foreignKey, $as, $type)` | Carga en lote una relacion sobre un arreglo de filas, evitando consultas N+1. | one o many |

Ejemplo de uso en un repositorio concreto:

```php
class EstudianteRepository extends BaseRepository
{
    public function findWithGrupo(int $id): ?array
    {
        $estudiante = $this->find($id);
        if ($estudiante === null) {
            return null;
        }

        $estudiante['grupo'] = $this->belongsTo('grupos', 'grupo_id', $estudiante['grupo_id']);

        return $estudiante;
    }

    public function allWithMaterias(): array
    {
        $rows = $this->all();

        return $this->eagerLoad($rows, 'materias', 'id', 'estudiante_id', 'materias', 'many');
    }
}
```

## Collection

`Jb\Database\Collection` es una envoltura ligera sobre arreglos de filas asociativas que implementa `ArrayAccess`, `Countable` e `IteratorAggregate`. Provee metodos encadenables inspirados en colecciones funcionales:

| Metodo | Retorno | Descripcion |
|---|---|---|
| `make($items)` | `Collection` | Fabrica estatica. |
| `toArray()` | `list<array>` | Convierte de vuelta a arreglo. |
| `count()` | `int` | Cantidad de elementos. |
| `isEmpty()` | `bool` | `true` si no hay elementos. |
| `first()` | `?array` | Primer elemento o `null`. |
| `last()` | `?array` | Ultimo elemento o `null`. |
| `map(callable)` | `Collection` | Aplica callback a cada elemento. |
| `filter(callable)` | `Collection` | Filtra elementos con callback. |
| `pluck($value, $key?)` | `array` | Extrae una columna, opcionalmente indexada. |
| `groupBy($column)` | `array` | Agrupa por el valor de una columna. |
| `keyBy($column)` | `array` | Indexa por columna (ultimo gana en duplicados). |
| `reduce(callable, $initial?)` | `mixed` | Reduce a un solo valor. |
| `sum($column)` | `float\|int` | Suma los valores de una columna numerica. |
| `avg($column, $precision?)` | `float` | Promedio de una columna numerica. |

Los metodos `hasMany` y `map` de BaseRepository y QueryBuilder devuelven instancias de `Collection`.

## Dependencias

- Config para parametros de conexion.
- PDO como infraestructura base.

## Flujo interno

Config selecciona driver -> Connection crea PDO -> QueryBuilder construye SQL segun driver -> repositorios y servicios consumen resultados. BaseRepository aprovecha Collection para los resultados de relaciones.

## Extensibilidad

- agregar drivers adicionales;
- evolucion a capa Grammar dedicada (planeado).

## Riesgos tecnicos

- diferencia de dialectos SQL en escenarios complejos;
- ausencia de grammar formal aun.
