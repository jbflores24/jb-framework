# Modulo Cache

## Proposito

Proveer cache simple por archivo como default operativo del framework.

## Responsabilidades

- lectura/escritura de valores serializados;
- expiracion por TTL;
- limpieza de entradas.

## Clases principales

- CacheInterface
- FileCache

## Dependencias

- filesystem local.

## Flujo interno

put/get/forget/clear sobre archivos cache con hashing de claves.

## Extensibilidad

CacheInterface permite migrar a backends distribuidos.

## Riesgos tecnicos

- I/O sincronico;
- limitaciones de concurrencia en alta carga.
