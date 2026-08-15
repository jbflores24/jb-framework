# Modulo Database

## Proposito

Proveer acceso a datos multi-driver con bajo acoplamiento.

## Responsabilidades

- inicialización de la conexión PDO;
- construcción de consultas con `QueryBuilder`;
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

## `BaseRepository` y carga de relaciones

`BaseRepository` incluye tres métodos protegidos para trabajar con relaciones entre tablas sin necesidad de un ORM completo:

| Método | Propósito | Tipo de relación |
|---|---|---|
| `hasMany($relatedTable, $foreignKey, $id)` | Devuelve una `Collection` con las filas relacionadas. | uno a muchos |
| `belongsTo($relatedTable, $localKeyColumn, $value, $relatedPrimaryKey)` | Devuelve una sola fila relacionada o `null`. | muchos a uno |
| `eagerLoad($rows, $relatedTable, $localKey, $foreignKey, $as, $type)` | Carga por lotes una relación sobre un arreglo de filas, evitando consultas N+1. | uno o muchos |

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

## `Collection`

`Jb\Database\Collection` es una envoltura ligera sobre arreglos de filas asociativas que implementa `ArrayAccess`, `Countable` e `IteratorAggregate`. Provee métodos encadenables inspirados en colecciones funcionales:

| Método | Retorno | Descripción |
|---|---|---|
| `make($items)` | `Collection` | Fábrica estática. |
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
| `sum($column)` | `float\|int` | Suma los valores de una columna numérica. |
| `avg($column, $precision?)` | `float` | Promedio de una columna numérica. |

Los métodos `hasMany` y `map` de `BaseRepository` y `QueryBuilder` devuelven instancias de `Collection`.

## Dependencias

- `Config` para parámetros de conexión.
- `PDO` como infraestructura base.

## Flujo interno

`Config` selecciona el controlador -> `Connection` crea `PDO` -> `QueryBuilder` construye SQL según el controlador -> repositorios y servicios consumen resultados. `BaseRepository` aprovecha `Collection` para los resultados de relaciones.

## Extensibilidad

- agregar drivers adicionales;
- evolución a una capa `Grammar` dedicada (planeado).

## Riesgos tecnicos

- diferencia de dialectos SQL en escenarios complejos;
- ausencia de una `Grammar` formal todavía.
