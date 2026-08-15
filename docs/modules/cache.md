# Módulo Cache

## Proposito

Proveer caché simple por archivo como comportamiento operativo por defecto del framework.

## Responsabilidades

- lectura/escritura de valores serializados;
- expiración por TTL;
- limpieza de entradas.

## Clases principales

- CacheInterface
- FileCache

## Dependencias

- sistema de archivos local.

## Flujo interno

put/get/forget/clear sobre archivos cache con hashing de claves.

## Extensibilidad

CacheInterface permite migrar a backends distribuidos.

## Riesgos tecnicos

- E/S síncrona;
- limitaciones de concurrencia en alta carga.
