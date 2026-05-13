# Configuración

La configuración de un proyecto generado se organiza en archivos PHP dentro del directorio `config/`. Cada archivo corresponde a un módulo del framework. Los valores se leen desde variables de entorno del archivo `.env` con valores por defecto en caso de que la variable no exista.

---

## Variables de entorno (`.env`)

El archivo `.env` no debe incluirse en el control de versiones. Se debe copiar desde `.env.example` y completar con los valores del entorno específico.

```ini
# Aplicación
APP_NAME=mi_api
APP_ENV=production      # development | production
APP_DEBUG=false
APP_URL=http://localhost

# Base de datos
DB_DRIVER=mysql         # mysql | pgsql | sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=mi_api
DB_USER=root
DB_PASS=secret
DB_CHARSET=utf8mb4
DB_PATH=storage/database.sqlite   # solo para sqlite

# JWT
JWT_SECRET=clave-secreta-larga-y-aleatoria
JWT_TTL=3600            # segundos de validez del token
JWT_REFRESH_TTL=1209600 # segundos de validez del token de refresco

# Rate limiting
RATE_LIMIT_MAX=120      # máximo de peticiones permitidas por ventana
RATE_LIMIT_WINDOW=60    # ventana de tiempo en segundos

# Correo
MAIL_FROM_ADDRESS=noreply@midominio.com
MAIL_FROM_NAME=Mi API
```

---

## `config/database.php`

Configura la conexión de base de datos.

```php
return [
    'driver'   => $_ENV['DB_DRIVER']  ?? 'mysql',   // mysql | pgsql | sqlite
    'host'     => $_ENV['DB_HOST']    ?? 'localhost',
    'port'     => $_ENV['DB_PORT']    ?? '3306',
    'name'     => $_ENV['DB_NAME']    ?? '',
    'username' => $_ENV['DB_USER']    ?? 'root',
    'password' => $_ENV['DB_PASS']    ?? '',
    'charset'  => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'path'     => $_ENV['DB_PATH']    ?? 'storage/database.sqlite',
];
```

**Notas:**

- `driver` determina el DSN que se construye. Para `sqlite` se usa `path` y se ignoran `host`, `port`, `name`, `username` y `password`.
- Para MySQL en producción se recomienda usar `utf8mb4` y configurar `STRICT_TRANS_TABLES` en el servidor.
- El singleton PDO se inicializa al primer uso y se reutiliza durante el ciclo de vida de la petición.

---

## `config/auth.php`

Configura JWT.

```php
return [
    'jwt_secret'      => $_ENV['JWT_SECRET']      ?? 'change-me',
    'jwt_ttl'         => (int)($_ENV['JWT_TTL']         ?? 3600),
    'jwt_refresh_ttl' => (int)($_ENV['JWT_REFRESH_TTL'] ?? 1209600),
];
```

**Notas:**

- `jwt_secret` debe ser una cadena aleatoria larga (mínimo 32 caracteres). Cambiarla invalida todos los tokens existentes.
- `jwt_ttl` es el tiempo de vida del token de acceso en segundos. Por defecto 1 hora.
- `jwt_refresh_ttl` es el tiempo de vida del token de refresco. Por defecto 14 días.

**Generar un secreto seguro:**

```bash
php -r "echo bin2hex(random_bytes(32));"
```

---

## `config/rate_limit.php`

Controla el límite de peticiones por IP.

```php
return [
    'path'           => 'storage/rate_limit',
    'max_attempts'   => (int)($_ENV['RATE_LIMIT_MAX']    ?? 120),
    'window_seconds' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
];
```

**Notas:**

- Con la configuración por defecto se permiten 120 peticiones por minuto por IP.
- Los contadores se almacenan en archivos en `storage/rate_limit/`. No requiere Redis ni Memcached.
- Cuando se supera el límite, el middleware responde con `429 Too Many Requests`.
- Para APIs públicas con mucho tráfico se recomienda delegar el rate limiting al servidor web o a un proxy inverso.

---

## `config/cache.php`

Configura el sistema de caché en archivo.

```php
return [
    'path' => 'storage/cache',
    'ttl'  => 3600,
];
```

**Notas:**

- `ttl` es el tiempo de vida por defecto en segundos para los ítems de caché.
- Se puede pasar un `ttl` distinto al guardar un ítem específico.
- Para invalidar toda la caché: `php jb cache:clear`.

**Uso básico en código:**

```php
$cache = new \Jb\Cache\FileCache($config);

$cache->set('clave', $valor, 300);  // guardar 5 minutos
$valor = $cache->get('clave');       // recuperar (null si expiró)
$cache->delete('clave');             // eliminar
```

---

## `config/logging.php`

Configura el sistema de logs.

```php
return [
    'path' => 'storage/logs/app.log',
];
```

**Uso básico en código:**

```php
$logger = new \Jb\Logging\Logger($config);

$logger->info('Usuario autenticado', ['user_id' => 42]);
$logger->warning('Rate limit próximo al límite', ['ip' => '1.2.3.4']);
$logger->error('Fallo al conectar a la base de datos', ['driver' => 'mysql']);
```

Los niveles disponibles son: `debug`, `info`, `warning`, `error`.

Para limpiar logs: `php jb logs:clear`.

---

## `config/mail.php`

Configura el remitente de correo.

```php
return [
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
    'from_name'    => $_ENV['MAIL_FROM_NAME']    ?? 'JB API',
];
```

**Notas:**

- El `Mailer` usa la función `mail()` de PHP como transporte básico. Para producción se recomienda configurar un relay SMTP en el servidor o reemplazar el `Mailer` por una librería dedicada (PHPMailer, Symfony Mailer).
- El módulo de mail está disponible pero es intencional que sea mínimo: el framework es para APIs, no para sistemas transaccionales de correo.

---

## `config/security.php`

Configura el módulo de detección de amenazas.

```php
return [
    'enabled'              => true,
    'score_threshold'      => 100,   // puntuación para bloqueo automático
    'block_duration'       => 3600,  // segundos de bloqueo
    'cleanup_interval'     => 86400, // segundos entre limpiezas
    'log_all_requests'     => false,
    'whitelist_ips'        => [],
    'blacklist_ips'        => [],
];
```

**Notas:**

- Cada detector acumula puntos de riesgo por IP. Cuando la suma supera `score_threshold`, la IP queda bloqueada durante `block_duration` segundos.
- Las IPs en `whitelist_ips` nunca son bloqueadas.
- Las IPs en `blacklist_ips` son rechazadas inmediatamente con `403 Forbidden`.
- El panel de administración en `/api/security/` permite gestionar estas listas en tiempo de ejecución sin reiniciar el servidor.

---

## `config/app.php`

Configuración general de la aplicación.

```php
return [
    'name'     => $_ENV['APP_NAME']  ?? 'JB API',
    'env'      => $_ENV['APP_ENV']   ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => $_ENV['APP_URL']   ?? 'http://localhost',
    'timezone' => 'America/Mexico_City',
];
```

**Notas:**

- En producción `debug` debe ser `false` para evitar exponer trazas de error en las respuestas JSON.
- `timezone` se aplica con `date_default_timezone_set()` durante el bootstrap.

---

## Orden de carga de configuración

El sistema de `Config` carga los archivos de `config/` bajo demanda y los cachea en memoria durante la petición. No hay archivo de configuración central que importe a los demás; cada módulo accede a su archivo directamente:

```php
$config = Config::get('database');  // carga config/database.php
$driver = Config::get('database.driver');  // acceso con notación de punto
```
