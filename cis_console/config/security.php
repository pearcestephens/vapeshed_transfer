<?php
declare(strict_types=1);

return [
    'admin_token' => getenv('ADMIN_TOKEN') ?: '',
    'rate_limit' => [
        'requests' => (int)(getenv('RATE_LIMIT_REQUESTS') ?: 30),
        'window' => (int)(getenv('RATE_LIMIT_WINDOW') ?: 60),
    ],
    'logs' => [
        'apache_error' => getenv('APACHE_ERROR_LOG') ?: '/var/log/apache2/error.log',
        'apache_access' => getenv('APACHE_ACCESS_LOG') ?: '/var/log/apache2/access.log',
        'snapshot_dir' => getenv('QUICK_DIAL_SNAPSHOT_DIR') ?: '/var/log/cis/snapshots',
    ],
];
