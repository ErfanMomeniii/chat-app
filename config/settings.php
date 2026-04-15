<?php

declare(strict_types=1);

return [
    'db' => [
        'path' => dirname(__DIR__) . '/var/chat.db',
    ],
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
    'log_errors' => true,
];
