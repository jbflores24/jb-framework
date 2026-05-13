# Ejemplos

Este directorio contiene proyectos de referencia generados con el CLI de JB Framework.

## demo_api

Ruta: `examples/demo_api`

Objetivo:

- mostrar estructura real de un proyecto generado
- servir como base de pruebas E2E del CLI
- facilitar onboarding de nuevos contribuidores

Pasos sugeridos:

```bash
cd examples/demo_api
composer install
php jb make:scaffold Producto
php jb migrate
php jb docs:generate
php jb test
```
