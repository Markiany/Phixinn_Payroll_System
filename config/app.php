<?php

return [
    'name'     => $_ENV['APP_NAME'] ?? 'Attendance & Payroll System',
    'env'      => $_ENV['APP_ENV'] ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'      => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Manila',
    'key'      => $_ENV['APP_KEY'] ?? '',
];
