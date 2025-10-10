<?php

/**
 * Sections 11 & 12 Configuration
 * 
 * Configuration for Web Traffic Monitoring and API Testing Suite
 * 
 * @package     CIS Staff Portal
 * @subpackage  Config
 * @version     1.0.0
 * @author      Ecigdis Limited Engineering Team
 * @copyright   2025 Ecigdis Limited
 */

return [
    
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    
    'features' => [
        'traffic_monitor' => env('TRAFFIC_MONITOR_ENABLED', true),
        'performance_analytics' => env('PERFORMANCE_ANALYTICS_ENABLED', true),
        'error_tracking' => env('ERROR_TRACKING_ENABLED', true),
        'health_checks' => env('HEALTH_CHECKS_ENABLED', true),
        'api_testing' => env('API_TESTING_ENABLED', true),
        'webhook_lab' => env('WEBHOOK_LAB_ENABLED', true),
        'vend_tester' => env('VEND_TESTER_ENABLED', true),
        'sync_tester' => env('SYNC_TESTER_ENABLED', true),
        'queue_tester' => env('QUEUE_TESTER_ENABLED', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Traffic Monitoring
    |--------------------------------------------------------------------------
    */
    
    'traffic' => [
        'visitor_window' => 300, // 5 minutes in seconds
        'rps_window' => 3600, // 1 hour for RPS chart
        'auto_refresh' => 10, // seconds
        'live_feed_limit' => 50, // max items in live feed
        'endpoint_health_threshold' => 500, // ms response time threshold
        'ddos_burst_threshold' => 100, // requests per minute from single IP
        'ddos_sustained_threshold' => 500, // requests per 5 minutes
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Performance Analytics
    |--------------------------------------------------------------------------
    */
    
    'performance' => [
        'slow_query_threshold' => 1000, // ms
        'page_load_budget' => 2500, // ms
        'api_response_budget' => 500, // ms
        'memory_limit_warning' => 80, // percentage
        'percentiles' => [50, 95, 99],
        'retention_days' => 90,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Error Tracking
    |--------------------------------------------------------------------------
    */
    
    'errors' => [
        'grouping_keys' => ['type', 'file', 'line'],
        'max_stack_depth' => 10,
        'auto_resolve_days' => 30,
        'notification_threshold' => 10, // occurrences before alert
        'exclude_patterns' => [
            '/vendor/',
            '/node_modules/',
            '/.git/',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    */
    
    'health' => [
        'components' => [
            'ssl' => [
                'enabled' => true,
                'url' => env('APP_URL', 'https://staff.vapeshed.co.nz'),
                'days_before_expiry_warning' => 30,
            ],
            'database' => [
                'enabled' => true,
                'timeout' => 5, // seconds
            ],
            'phpfpm' => [
                'enabled' => true,
                'status_url' => env('PHP_FPM_STATUS_URL', 'http://localhost/php-fpm-status'),
            ],
            'queue' => [
                'enabled' => true,
                'max_pending_jobs' => 1000,
            ],
            'disk' => [
                'enabled' => true,
                'warning_threshold' => 80, // percentage
                'critical_threshold' => 90, // percentage
            ],
            'vend_api' => [
                'enabled' => true,
                'url' => env('VEND_API_URL', 'https://api.vendhq.com/api/2.0'),
                'timeout' => 10, // seconds
            ],
        ],
        'cache_ttl' => 60, // seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Testing
    |--------------------------------------------------------------------------
    */
    
    'api_testing' => [
        'webhook' => [
            'timeout' => 30, // seconds
            'max_payload_size' => 1048576, // 1MB
            'allowed_domains' => [
                'staff.vapeshed.co.nz',
                'webhook.site',
                'requestbin.com',
            ],
        ],
        'vend' => [
            'api_url' => env('VEND_API_URL', 'https://api.vendhq.com/api/2.0'),
            'api_token' => env('VEND_API_TOKEN'),
            'domain_prefix' => env('VEND_DOMAIN_PREFIX'),
            'rate_limit' => 10, // requests per minute
            'timeout' => 30, // seconds
            'endpoints' => [
                'GET /products',
                'GET /products/{id}',
                'POST /consignments',
                'GET /consignments',
                'GET /consignments/{id}',
                'GET /outlets',
                'GET /sales',
                'POST /webhooks',
                'GET /webhooks',
            ],
        ],
        'sync' => [
            'dry_run_default' => true,
            'timeout' => 300, // seconds for full pipeline
            'tests' => [
                'transfer_to_consignment',
                'po_to_consignment',
                'stock_sync',
                'webhook_trigger',
                'full_pipeline',
            ],
        ],
        'queue' => [
            'connection' => env('QUEUE_CONNECTION', 'database'),
            'test_queue' => 'testing',
            'stress_job_count' => 100,
            'timeout' => 60, // seconds per job
        ],
        'endpoints' => [
            'suites' => [
                'transfer' => 9,
                'po' => 9,
                'inventory' => 5,
                'webhook' => 3,
            ],
            'timeout' => 10, // seconds per test
            'stop_on_fail' => false,
        ],
        'history' => [
            'max_per_user' => 50,
            'retention_days' => 30,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    
    'logging' => [
        'apache_error_log' => env('APACHE_ERROR_LOG_PATH', '/var/log/apache2/error.log'),
        'phpfpm_log' => env('PHP_FPM_LOG_PATH', '/var/log/php-fpm/error.log'),
        'tail_lines' => 200,
        'snapshot_dir' => env('LOG_SNAPSHOT_DIR', '/var/log/cis/snapshots'),
        'max_snapshots' => 10,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    
    'security' => [
        'require_admin' => true,
        'csrf_protection' => true,
        'rate_limit' => [
            'enabled' => true,
            'max_requests' => 60, // per minute
            'by_ip' => true,
            'by_user' => true,
        ],
        'allowed_ips' => env('ALLOWED_IPS', '*'), // Comma-separated or '*' for all
    ],
    
    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    */
    
    'urls' => [
        'base' => env('APP_URL', 'https://staff.vapeshed.co.nz'),
        'traffic' => '/admin/traffic',
        'performance' => '/admin/performance',
        'errors' => '/admin/errors',
        'health' => '/admin/health',
        'api_test' => '/admin/api-test',
    ],
    
];
