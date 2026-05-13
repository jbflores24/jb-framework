<?php

declare(strict_types=1);

return [
    'name' => $_ENV['APP_NAME'] ?? 'JB API',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => $_ENV['APP_DEBUG'] ?? false,
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'base_route' => $_ENV['APP_BASE_ROUTE'] ?? '/api',
    'routes_cache' => [
        'enabled' => filter_var($_ENV['ROUTE_CACHE_ENABLED'] ?? ((($_ENV['APP_ENV'] ?? 'production') === 'production') ? 'true' : 'false'), FILTER_VALIDATE_BOOL),
        'path' => $_ENV['ROUTE_CACHE_PATH'] ?? 'storage/cache/routes.json',
    ],
    'cors' => [
        'allowed_origins' => $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*',
    ],
];
