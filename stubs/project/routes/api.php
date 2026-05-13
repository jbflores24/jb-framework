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
