# Ejemplos y resultados esperados

Ejemplos prácticos de uso del framework con las salidas reales que se pueden esperar en cada caso.

---

## 1. Crear un proyecto nuevo

```bash
php bin/jb new blog_api
```

**Salida esperada:**

```
Proyecto [blog_api] creado.

Próximos pasos:
  cd blog_api
  composer install
  php jb serve
```

**Estructura generada:**

```
blog_api/
├── app/Controllers/
├── app/Models/
├── database/migrations/
│   └── 2026_05_12_000000_create_security_tables.php
├── database/seeders/
├── public/index.php
├── public/.htaccess
├── routes/api.php
├── config/
├── storage/cache/
├── storage/logs/
├── tests/
├── .env
├── .env.example
├── composer.json
└── jb
```

---

## 2. Generar un recurso con scaffolding

```bash
cd blog_api
composer install
php jb make:scaffold Articulo
```

**Salida esperada:**

```
Creado: app/Controllers/ArticuloController.php
Creado: app/Models/Articulo.php
Creado: database/migrations/2026_05_12_143022_create_articulos_table.php
Creado: database/seeders/ArticuloSeeder.php
Creado: tests/Unit/ArticuloTest.php
Creado: tests/Integration/ArticuloIntegrationTest.php
Rutas registradas en routes/api.php
```

**Rutas agregadas en `routes/api.php`:**

```php
$router->get('/api/articulos', [ArticuloController::class, 'index']);
$router->get('/api/articulos/{id}', [ArticuloController::class, 'show']);
$router->post('/api/articulos', [ArticuloController::class, 'store']);
$router->put('/api/articulos/{id}', [ArticuloController::class, 'update']);
$router->delete('/api/articulos/{id}', [ArticuloController::class, 'destroy']);
```

---

## 3. Ejecutar migraciones

```bash
php jb migrate
```

**Salida esperada:**

```
Aplicando: 2026_05_12_000000_create_security_tables ... OK
Aplicando: 2026_05_12_143022_create_articulos_table ... OK
Migraciones completadas.
```

```bash
php jb migrate:status
```

**Salida esperada:**

```
[✔] 2026_05_12_000000_create_security_tables  (lote 1)
[✔] 2026_05_12_143022_create_articulos_table  (lote 1)
```

---

## 4. Ejecutar seeders

```bash
php jb seed Articulo
```

**Salida esperada:**

```
Seeder ArticuloSeeder ejecutado correctamente.
```

```bash
php jb seed
```

**Salida esperada:**

```
Seeder ArticuloSeeder ejecutado correctamente.
Todos los seeders ejecutados.
```

---

## 5. Llamadas a la API con curl

### Listar recursos

```bash
curl -s http://127.0.0.1:8000/api/articulos
```

**Respuesta esperada:**

```json
{
    "data": [
        {
            "id": 1,
            "titulo": "Artículo de ejemplo",
            "contenido": "Lorem ipsum...",
            "created_at": "2026-05-12 14:30:00"
        }
    ],
    "status": 200
}
```

### Obtener un recurso por ID

```bash
curl -s http://127.0.0.1:8000/api/articulos/1
```

**Respuesta esperada:**

```json
{
    "data": {
        "id": 1,
        "titulo": "Artículo de ejemplo",
        "contenido": "Lorem ipsum...",
        "created_at": "2026-05-12 14:30:00"
    },
    "status": 200
}
```

### Crear un recurso

```bash
curl -s -X POST http://127.0.0.1:8000/api/articulos \
     -H "Content-Type: application/json" \
     -d '{"titulo": "Nuevo artículo", "contenido": "Contenido del artículo"}'
```

**Respuesta esperada:**

```json
{
    "data": {
        "id": 2,
        "titulo": "Nuevo artículo",
        "contenido": "Contenido del artículo"
    },
    "status": 201
}
```

### Recurso no encontrado

```bash
curl -s http://127.0.0.1:8000/api/articulos/999
```

**Respuesta esperada:**

```json
{
    "error": "Not Found",
    "status": 404
}
```

---

## 6. Autenticación con JWT

### Obtener token

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email": "admin@ejemplo.com", "password": "secreto"}'
```

**Respuesta esperada:**

```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600
}
```

### Usar el token en una ruta protegida

```bash
curl -s http://127.0.0.1:8000/api/articulos \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
```

### Token inválido o expirado

```json
{
    "error": "Unauthorized",
    "status": 401
}
```

---

## 7. Validación de datos

Cuando el controlador usa el `Validator` y los datos no cumplen las reglas:

```bash
curl -s -X POST http://127.0.0.1:8000/api/articulos \
     -H "Content-Type: application/json" \
     -d '{}'
```

**Respuesta esperada:**

```json
{
    "errors": {
        "titulo": ["El campo titulo es obligatorio."],
        "contenido": ["El campo contenido es obligatorio."]
    },
    "status": 422
}
```

---

## 8. Rate limiting

Cuando una IP supera el límite de peticiones configurado:

```json
{
    "error": "Too Many Requests",
    "status": 429
}
```

---

## 9. Rollback y fresh de migraciones

```bash
php jb migrate:rollback
```

**Salida esperada:**

```
Revirtiendo: 2026_05_12_143022_create_articulos_table ... OK
Revirtiendo: 2026_05_12_000000_create_security_tables ... OK
Rollback completado.
```

```bash
php jb migrate:fresh
```

**Salida esperada:**

```
Revirtiendo todas las migraciones...
Aplicando todas las migraciones...
Aplicando: 2026_05_12_000000_create_security_tables ... OK
Aplicando: 2026_05_12_143022_create_articulos_table ... OK
Fresh completado.
```

---

## 10. Ejecutar pruebas

```bash
php jb test
```

**Salida esperada (proyecto con scaffold de Articulo):**

```
PHPUnit 11.x.x

..........

Time: 00:00.123, Memory: 8.00 MB

OK (10 tests, 24 assertions)
```

---

## 11. QueryBuilder — uso en un repositorio

Ejemplo de uso del `QueryBuilder` dentro de un modelo/repositorio:

```php
// Obtener artículos activos paginados
$articulos = $this->query()
    ->select(['id', 'titulo', 'created_at'])
    ->where('activo', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(0)
    ->get();

// Insertar
$id = $this->query()
    ->insert([
        'titulo'    => 'Nuevo',
        'contenido' => 'Texto',
        'activo'    => 1,
    ]);

// Actualizar
$this->query()
    ->where('id', $id)
    ->update(['activo' => 0]);

// Eliminar
$this->query()
    ->where('id', $id)
    ->delete();
```

---

## 12. Proyecto de ejemplo incluido

El repositorio incluye `examples/demo_api/`, un proyecto generado con el CLI y configurado con SQLite.

```bash
cd examples/demo_api
composer install
php jb migrate
php jb seed
php jb test
# OK (3 tests, 3 assertions)
```
