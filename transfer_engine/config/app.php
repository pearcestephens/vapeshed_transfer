<?php
/**
 * Application Configuration
 */
return [
    'name' => 'Vapeshed Transfer Engine',
    'version' => '2.0.0',
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'debug' => getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'development',
    'timezone' => 'Pacific/Auckland',
    
    'session' => [
        'lifetime' => 7200, // 2 hours
        'cookie_name' => 'transfer_engine_session'
    ]
];
