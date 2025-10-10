<?php
/**
 * Enhanced Security Configuration
 *
 * Comprehensive security settings for:
 * - IP filtering and access control
 * - Session security
 * - Password policies
 * - Encryption settings
 * - Security headers (CSP, HSTS, etc.)
 * - Audit logging
 * - File upload restrictions
 * - Authentication policies
 * - API security
 * - Security monitoring
 *
 * This extends the base config/security.php with advanced features.
 *
 * @category   Configuration
 * @package    VapeshedTransfer
 * @subpackage Security
 * @version    1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | IP Filtering Configuration
    |--------------------------------------------------------------------------
    |
    | Configure IP-based access control with whitelist/blacklist support.
    |
    */
    'ip_filtering' => [
        'enabled' => false,
        'mode' => 'blacklist', // 'whitelist' or 'blacklist'
        'storage_path' => __DIR__ . '/../storage/security/ip_rules.json',
        
        // Routes that bypass IP filtering
        'bypass_routes' => [
            '/health',
            '/ping',
            '/api/webhook/vend'
        ],
        
        // Whitelist (only used in whitelist mode)
        'whitelist' => [
            '127.0.0.1',
            '::1',
            // Add your office/trusted IPs here
        ],
        
        // Blacklist (only used in blacklist mode)
        'blacklist' => [
            // Add malicious IPs here
        ],
        
        // Auto-ban settings
        'auto_ban' => [
            'enabled' => true,
            'failed_attempts' => 10,
            'time_window' => 600, // 10 minutes
            'ban_duration' => 3600 // 1 hour
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Secure session handling configuration.
    |
    */
    'session' => [
        'name' => 'VSHED_TRANSFER_SESSION',
        'lifetime' => 7200, // 2 hours
        'path' => '/',
        'domain' => '',
        'secure' => true, // HTTPS only
        'httponly' => true,
        'samesite' => 'Strict', // 'Strict', 'Lax', or 'None'
        'regenerate_on_login' => true,
        'regenerate_interval' => 1800, // 30 minutes
        
        // Session storage
        'handler' => 'files', // 'files', 'database', or 'redis'
        'path_storage' => __DIR__ . '/../storage/sessions',
        
        // Redis session storage
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 1
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policies
    |--------------------------------------------------------------------------
    |
    | Password strength and validation rules.
    |
    */
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special' => true,
        'special_chars' => '!@#$%^&*()-_=+[]{}|;:,.<>?',
        
        // Password history
        'prevent_reuse' => true,
        'history_count' => 5,
        
        // Password expiration
        'expire_after' => 90, // days
        'warn_before_expiry' => 14, // days
        
        // Hashing
        'algorithm' => PASSWORD_BCRYPT,
        'cost' => 12
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Settings
    |--------------------------------------------------------------------------
    |
    | Data encryption configuration.
    |
    */
    'encryption' => [
        'cipher' => 'AES-256-CBC',
        'key' => getenv('APP_KEY') ?: null, // Set in .env
        'derive_key' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | HTTP security headers configuration.
    |
    */
    'headers' => [
        'enabled' => true,
        
        // HSTS
        'hsts' => [
            'enabled' => true,
            'max_age' => 31536000, // 1 year
            'include_subdomains' => true,
            'preload' => true
        ],
        
        // CSP
        'csp' => [
            'enabled' => true,
            'report_only' => false,
            'report_uri' => '/api/csp-report',
            
            'directives' => [
                'default-src' => ["'self'"],
                'script-src' => [
                    "'self'",
                    "'unsafe-inline'", // Remove in production
                    'https://cdn.jsdelivr.net',
                    'https://cdnjs.cloudflare.com'
                ],
                'style-src' => [
                    "'self'",
                    "'unsafe-inline'",
                    'https://cdn.jsdelivr.net',
                    'https://cdnjs.cloudflare.com'
                ],
                'img-src' => ["'self'", 'data:', 'https:'],
                'font-src' => ["'self'", 'data:', 'https://cdn.jsdelivr.net'],
                'connect-src' => ["'self'", 'https://staff.vapeshed.co.nz'],
                'frame-src' => ["'none'"],
                'object-src' => ["'none'"],
                'base-uri' => ["'self'"],
                'form-action' => ["'self'"],
                'frame-ancestors' => ["'self'"],
                'upgrade-insecure-requests' => true
            ]
        ],
        
        // Other headers
        'x_frame_options' => 'SAMEORIGIN',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        
        'permissions_policy' => [
            'geolocation' => [],
            'microphone' => [],
            'camera' => [],
            'payment' => [],
            'usb' => [],
            'magnetometer' => [],
            'gyroscope' => [],
            'accelerometer' => []
        ],
        
        'cross_origin_opener_policy' => 'same-origin',
        'cross_origin_embedder_policy' => 'require-corp',
        'cross_origin_resource_policy' => 'same-origin'
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Security event logging configuration.
    |
    */
    'audit' => [
        'enabled' => true,
        'storage_path' => __DIR__ . '/../storage/logs/audit',
        'database_logging' => true,
        
        // Events to log
        'log_events' => [
            'auth' => true,
            'authz' => true,
            'data' => true,
            'config' => true,
            'security' => true,
            'admin' => true,
            'api' => true,
            'export' => true,
            'transfer' => true
        ],
        
        // Minimum severity to log
        'min_severity' => 'info',
        
        // Log retention
        'retention_days' => 90,
        'auto_cleanup' => true,
        'cleanup_schedule' => 'daily' // 'daily', 'weekly', 'monthly'
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Restrictions
    |--------------------------------------------------------------------------
    |
    | Security settings for file uploads.
    |
    */
    'uploads' => [
        'enabled' => true,
        'max_size' => 10485760, // 10MB
        'storage_path' => __DIR__ . '/../storage/uploads',
        
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ],
        
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif',
            'pdf',
            'csv', 'xls', 'xlsx'
        ],
        
        'scan_for_viruses' => false, // Requires ClamAV
        'quarantine_suspicious' => true
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | General authentication configuration.
    |
    */
    'auth' => [
        'lockout' => [
            'enabled' => true,
            'max_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'reset_after' => 1800 // 30 minutes
        ],
        
        'two_factor' => [
            'enabled' => false,
            'required_for_admin' => true,
            'methods' => ['totp', 'email']
        ],
        
        'remember_me' => [
            'enabled' => true,
            'duration' => 2592000 // 30 days
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security Settings
    |--------------------------------------------------------------------------
    |
    | API-specific security settings.
    |
    */
    'api' => [
        'require_authentication' => true,
        'token_lifetime' => 3600, // 1 hour
        'refresh_token_lifetime' => 2592000, // 30 days
        
        'throttle' => [
            'enabled' => true,
            'requests' => 100,
            'window' => 60 // 1 minute
        ],
        
        'allowed_ips' => [
            // Restrict API access to specific IPs (empty = all allowed)
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring & Alerting
    |--------------------------------------------------------------------------
    |
    | Real-time security monitoring and alerting.
    |
    */
    'monitoring' => [
        'enabled' => true,
        
        'alerts' => [
            'failed_logins' => [
                'threshold' => 10,
                'window' => 300, // 5 minutes
                'action' => 'log' // 'email', 'slack', 'log'
            ],
            
            'suspicious_activity' => [
                'threshold' => 50,
                'window' => 3600, // 1 hour
                'action' => 'log'
            ],
            
            'critical_errors' => [
                'action' => 'log'
            ],
            
            'rate_limit_exceeded' => [
                'threshold' => 5,
                'window' => 600, // 10 minutes
                'action' => 'log'
            ]
        ],
        
        'notification_recipients' => [
            'security@vapeshed.co.nz'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-Origin Resource Sharing for API endpoints.
    |
    */
    'cors' => [
        'enabled' => true,
        'allowed_origins' => [
            'https://staff.vapeshed.co.nz',
            'https://www.vapeshed.co.nz'
        ],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-Token'],
        'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'],
        'allow_credentials' => true,
        'max_age' => 86400 // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Input Validation Rules
    |--------------------------------------------------------------------------
    |
    | Global input validation rules and sanitization settings.
    |
    */
    'input_validation' => [
        'strict_mode' => true,
        'strip_tags_by_default' => true,
        'max_input_vars' => 1000,
        'max_string_length' => 10000,
        
        'blacklisted_patterns' => [
            // SQL injection patterns
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bDELETE\b|\bUPDATE\b|\bDROP\b)/i',
            // XSS patterns
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i'
        ]
    ]
];
