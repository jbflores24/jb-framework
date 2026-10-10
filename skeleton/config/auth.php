<?php

declare(strict_types=1);

return [
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'change-me',
    'jwt_ttl' => (int) ($_ENV['JWT_TTL'] ?? 3600),
    'jwt_refresh_ttl' => (int) ($_ENV['JWT_REFRESH_TTL'] ?? 1209600),
];
