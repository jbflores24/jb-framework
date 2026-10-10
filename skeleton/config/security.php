<?php

declare(strict_types=1);

return [
    'enabled' => ($_ENV['SECURITY_ENABLED'] ?? 'true') === 'true',
    'learning_mode' => ($_ENV['SECURITY_LEARNING_MODE'] ?? 'false') === 'true',
    'fail_open' => ($_ENV['SECURITY_FAIL_OPEN'] ?? 'true') === 'true',
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'excluded_paths' => ['/health'],
    'excluded_extensions' => ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.ico', '.svg'],
    'allow_empty_user_agent' => true,
    'max_payload_bytes' => 1048576,
    'rate_window_seconds' => 60,
    'rate_max_requests' => 120,
    'login_max_failed' => 5,
    'not_found_max' => 30,
    'block_default_minutes' => 60,
    'block_high_minutes' => 240,
    'block_critical_minutes' => 1440,
    'csrf_enabled' => false,
    'csrf_secret' => $_ENV['JWT_SECRET'] ?? 'change-me',
    'permissions' => [],
];
