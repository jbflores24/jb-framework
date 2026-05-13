# Quickstart

## 1) Instalar framework

```bash
composer install
composer test
```

## 2) Crear un proyecto nuevo

```bash
php bin/jb new mi_api
cd mi_api
```

Esto crea:

- estructura base de API
- `.env` desde `.env.example`
- launcher local `php jb`

## 3) Instalar dependencias del proyecto generado

```bash
composer install
```

## 4) Generar un recurso completo

```bash
php jb make:scaffold Producto
```

Genera:

- `app/Controllers/ProductoController.php`
- `app/Models/Producto.php`
- migración en `database/migrations`
- `database/seeders/ProductoSeeder.php`
- tests unitarios e integración
- rutas REST en `routes/api.php`

## 5) Migrar y seed

```bash
php jb migrate
php jb seed Producto
```

## 6) Generar documentación OpenAPI

```bash
php jb docs:generate
```

Salida esperada: `docs/swagger.yaml`.

## 7) Ejecutar pruebas

```bash
php jb test
```

# Guía de inicio rápido

Esta guía lleva de cero a una API REST funcional en menos de diez minutos.

---

## Requisitos previos

- PHP 8.2+ con extensiones `pdo`, `pdo_sqlite` (o `pdo_mysql`), `json`, `mbstring`
- Composer 2+
- Apache con `mod_rewrite` habilitado, o Nginx configurado para reescritura

---

## Paso 1 — Instalar el framework

```bash
git clone https://github.com/jbflores24/jb-framework.git jb
cd jb
composer install
```

Verificar que el framework está operativo:

```bash
composer test
# OK (24 tests, 62 assertions)
```

---

## Paso 2 — Crear un proyecto nuevo

```bash
php bin/jb new mi_api
cd mi_api
```

El comando genera la estructura completa del proyecto, copia el archivo `.env` desde `.env.example` y crea un launcher local `jb`.

---

## Paso 3 — Instalar dependencias del proyecto

```bash
composer install
```

---

## Paso 4 — Configurar la base de datos

Editar `.env` con las credenciales correspondientes:

```ini
DB_DRIVER=sqlite
DB_DATABASE=storage/database.sqlite
```

Para MySQL:

```ini
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mi_api
DB_USERNAME=root
DB_PASSWORD=secret
```

---

## Paso 5 — Generar un recurso completo

```bash
php jb make:scaffold Producto
```

Esto genera en un solo comando:

| Archivo | Descripción |
|---|---|
| `app/Controllers/ProductoController.php` | Controlador REST con index, show, store, update, destroy |
| `app/Models/Producto.php` | Modelo con repositorio base |
| `database/migrations/{ts}_create_productos_table.php` | Migración con columnas básicas |
| `database/seeders/ProductoSeeder.php` | Seeder con datos de ejemplo |
| `tests/Unit/ProductoTest.php` | Prueba unitaria básica |
| `tests/Integration/ProductoIntegrationTest.php` | Prueba de integración con base de datos |

Las rutas se registran automáticamente en `routes/api.php`.

---

## Paso 6 — Ejecutar migraciones y seeders

```bash
php jb migrate
# Aplicando: create_productos_table ... OK

php jb seed Producto
# Seeder ProductoSeeder ejecutado correctamente.
```

---

## Paso 7 — Ejecutar las pruebas

```bash
php jb test
# OK (N tests, N assertions)
```

---

## Paso 8 — Levantar el servidor de desarrollo

```bash
php jb serve
# Servidor iniciado en http://127.0.0.1:8000
```

Probar el endpoint generado:

```bash
curl http://127.0.0.1:8000/api/productos
# {"data": [], "status": 200}
```

---

## Paso 9 — Proteger rutas con JWT

En `routes/api.php` agregar el middleware de autenticación:

```php
$router->middleware(['auth'])->group(function () use ($router) {
	$router->get('/api/productos', [ProductoController::class, 'index']);
	$router->post('/api/productos', [ProductoController::class, 'store']);
});
```

Obtener un token desde el endpoint de autenticación y enviarlo en el header:

```
Authorization: Bearer {token}
```

---

## Paso 10 — Generar documentación OpenAPI

```bash
php jb docs:generate
# Documentación generada en docs/swagger.yaml
```

---

## Siguientes pasos

- [Referencia completa del CLI](CLI_REFERENCE.md)
- [Estructura del proyecto](PROJECT_STRUCTURE.md)
- [Configuración detallada](CONFIGURATION.md)
- [Ejemplos con salidas esperadas](EXAMPLES.md)
