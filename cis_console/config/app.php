<?php
declare(strict_types=1);

return [
    'env' => getenv('APP_ENV') ?: 'dev',
    'browse_mode' => strtolower(getenv('BROWSE_MODE') ?: 'on') === 'on',
    'paths' => [
        'storage' => __DIR__ . '/../storage',
        'views' => __DIR__ . '/../resources/views',
        'assets' => __DIR__ . '/../public/assets',
    ],
];
