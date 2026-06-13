# CLI lifecycle

## Implementado

1. Entrada por bin/jb.
2. Delegacion a ConsoleApplication.
3. Resolucion de comando.
4. Ejecucion de Generator, ProjectBuilder o comandos operativos.
5. Salida y codigo de retorno.

## Comandos relevantes

- new
- make:*
- migrate / rollback / fresh
- seed
- test
- docs:generate
