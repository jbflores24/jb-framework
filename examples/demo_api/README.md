# Demo API (Proyecto de ejemplo)

Proyecto generado con `php bin/jb new examples/demo_api`.

## Inicio rápido

```bash
composer install
php jb env
php jb make:scaffold Producto
php jb migrate
php jb seed Producto
php jb docs:generate
php jb test
```

## Estructura relevante

- `app/`: controladores, modelos, middleware y repositorios
- `config/`: configuración de app, base de datos, auth y seguridad
- `database/migrations`: migraciones PHP
- `database/seeders`: seeders
- `routes/api.php`: rutas HTTP
- `public/index.php`: front controller
- `docs/swagger.yaml`: generado por `docs:generate`

## Observaciones

- El proyecto referencia el framework por repositorio `path` en `composer.json`.
- No requiere frameworks externos MVC.
