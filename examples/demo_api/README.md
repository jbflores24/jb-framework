# Demo API (Ejemplo ligero)

Este directorio es una referencia minima de estructura para APIs con JB Framework.

## Alcance

- Es un ejemplo de codigo, no un proyecto instalable.
- No incluye `vendor/`, `composer.json` ni lockfiles.
- Su objetivo es mostrar organizacion de carpetas y rutas base.

## Estructura incluida

- `app/`: namespace de la aplicacion de ejemplo
- `routes/`: definicion de rutas API
- `public/`: front controller (`index.php`)

## Como crear un proyecto real

Para generar una API ejecutable usa el CLI del framework desde la raiz:

```bash
php bin/jb new mi_api
cd mi_api
composer install
```
