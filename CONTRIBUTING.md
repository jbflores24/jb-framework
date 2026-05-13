# Contribuir a JB Framework

## Requisitos

- PHP 8.2+
- Composer 2+
- Entorno con SQLite recomendado para pruebas

## Flujo recomendado

1. Crear una rama descriptiva.
2. Implementar cambios pequeños y cohesionados.
3. Ejecutar pruebas antes de enviar cambios.
4. Actualizar documentación cuando cambie comportamiento público.

## Convenciones

- `declare(strict_types=1);` en archivos PHP nuevos.
- Tipado explícito en parámetros y retornos.
- Nombres de clases en PascalCase.
- Tests en `tests/Unit` y `tests/Integration`.
- Evitar dependencias externas innecesarias.

## Verificación local

```bash
composer install
composer test
```

## CLI y stubs

Si cambias el generador o stubs:

1. Prueba al menos `make:crud` y `make:scaffold` en un proyecto generado.
2. Verifica que `docs:generate` siga creando `docs/swagger.yaml`.
3. Si alteras plantillas, valida `stub:publish`.

## Pull request

Incluye siempre:

- Resumen breve de cambios.
- Riesgos o impactos conocidos.
- Evidencia de pruebas ejecutadas.
