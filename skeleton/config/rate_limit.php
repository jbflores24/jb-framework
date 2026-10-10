<?php

declare(strict_types=1);

return [
    'path' => 'storage/rate_limit',
    'max_attempts' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 120),
    'window_seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
];
