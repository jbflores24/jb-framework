<?php

declare(strict_types=1);

return [
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@example.com',
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'JB API',
];
