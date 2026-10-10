<?php

declare(strict_types=1);

/*
 * Se ejecuta después de `composer create-project` y de `composer install`.
 * Es idempotente: solo prepara lo que falte y nunca sobrescribe un .env existente.
 */

$root = dirname(__DIR__);
$envPath = $root . '/.env';
$examplePath = $root . '/.env.example';

if (!is_file($envPath) && is_file($examplePath)) {
    $env = (string) file_get_contents($examplePath);
    $secret = bin2hex(random_bytes(32));
    $env = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $secret, $env) ?? $env;
    file_put_contents($envPath, $env);
    echo "Archivo .env creado con un JWT_SECRET nuevo.\n";
}

foreach (['storage/logs', 'storage/cache', 'storage/rate_limit', 'database/migrations', 'app'] as $dir) {
    $path = $root . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}
