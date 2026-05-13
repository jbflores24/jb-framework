<?php

declare(strict_types=1);

use Jb\Core\Request;
use Jb\Core\Response;
use Jb\Security\SecurityRoutes;

/** @var \Jb\Core\Router $router */
$router->get('/health', function (Request $request): Response {
    return Response::success([
        'service' => 'jb',
        'path' => $request->path(),
    ], 'API disponible.');
});

SecurityRoutes::register($router);

// JB scaffold: Cliente
$router->get('/clientes', [App\Controllers\ClienteController::class, 'index']);
$router->get('/clientes/{id}', [App\Controllers\ClienteController::class, 'show']);
$router->post('/clientes', [App\Controllers\ClienteController::class, 'store']);
$router->put('/clientes/{id}', [App\Controllers\ClienteController::class, 'update']);
$router->delete('/clientes/{id}', [App\Controllers\ClienteController::class, 'destroy']);

// JB scaffold: Producto
$router->get('/productos', [App\Controllers\ProductoController::class, 'index']);
$router->get('/productos/{id}', [App\Controllers\ProductoController::class, 'show']);
$router->post('/productos', [App\Controllers\ProductoController::class, 'store']);
$router->put('/productos/{id}', [App\Controllers\ProductoController::class, 'update']);
$router->delete('/productos/{id}', [App\Controllers\ProductoController::class, 'destroy']);
