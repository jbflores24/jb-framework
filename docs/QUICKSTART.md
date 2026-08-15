# Inicio rápido

Esta guía lleva de cero a una API REST funcional con JB Framework.

## 1. Instalar el framework

```bash
git clone https://github.com/jbflores24/jb-framework.git jb
cd jb
composer install
composer test
```

## 2. Crear un proyecto nuevo

```bash
php bin/jb new mi_api
cd mi_api
composer install
```

El comando crea la estructura base, copia `.env.example` a `.env` y deja un lanzador local llamado `jb`.

## 3. Revisar la configuracion

```bash
php jb env
```

Este comando muestra valores útiles de `app`, `database` y `security`.

## 4. Generar un recurso completo

```bash
php jb make:scaffold Producto
```

El scaffold crea estos archivos:

| Archivo | Descripción |
|---|---|
| `app/Controllers/ProductoController.php` | Controlador REST |
| `app/Models/Producto.php` | Modelo del recurso |
| `database/migrations/{timestamp}_create_productos_table.php` | Migracion |
| `database/seeders/ProductoSeeder.php` | Seeder |
| `tests/Unit/ProductoUnitTest.php` | Prueba unitaria |
| `tests/Integration/ProductoScaffoldTest.php` | Prueba de integración |

También registra las rutas REST en `routes/api.php` y agrega una entrada de auditoría de scaffold.

## 5. Ejecutar migraciones y seeders

```bash
php jb migrate
php jb seed Producto
```

Si necesitas ver el estado de las migraciones:

```bash
php jb migrate:status
```

## 6. Probar la aplicacion

```bash
php jb test
php jb serve
```

## 7. Generar documentacion OpenAPI

```bash
php jb docs:generate
```

El archivo generado se guarda en `docs/swagger.yaml`.

## 8. Crear otros artefactos

```bash
php jb make:controller Cliente
php jb make:model estudiantes
php jb make:migration create_clientes_table
php jb make:seeder Cliente
php jb make:middleware Audit
php jb make:test Cliente
php jb make:service Cliente
```

## Siguiente lectura

- [Referencia CLI](CLI_REFERENCE.md)
- [Configuración](CONFIGURATION.md)
- [Pruebas](TESTING.md)