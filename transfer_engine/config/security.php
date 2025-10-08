<?php
/**
 * Security configuration for admin endpoints.
 */
return [
    'rate_limits' => [
        'default' => [
            'per_minute' => (int)\Unified\Support\Env::get('ADMIN_RATE_LIMIT_PER_MIN', 120),
            'burst' => (int)\Unified\Support\Env::get('GET_RL_BURST', 30),
        ],
        'admin/health/phpinfo' => [
            'per_minute' => (int)\Unified\Support\Env::get('ADMIN_RATE_LIMIT_PER_MIN', 60),
            'burst' => 5,
        ],
        'admin/logs/apache-error-tail' => [
            'per_minute' => 4,
            'burst' => 1,
        ],
        'admin/api-lab/queue' => [
            'per_minute' => 12,
            'burst' => 3,
        ],
    ],
    'csrf' => [
        'required' => \Unified\Support\Env::get('CSRF_REQUIRED', 'false') === 'true',
        'token_key' => '_csrf',
    ],
    'hmac' => [
        'enabled' => false,
        'header' => 'X-Signature',
        'secret_env' => 'HMAC_SECRET',
    ],
    'sse' => [
        'heartbeat_interval' => (int)\Unified\Support\Env::get('SSE_HEARTBEAT_INTERVAL', 10),
        'retry_ms' => (int)\Unified\Support\Env::get('SSE_RETRY_MS', 4000),
    ],
];
