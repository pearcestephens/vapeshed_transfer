<?php
/**
 * Vend Database Configuration
 * 
 * Configuration for connecting to the Vend production database.
 * Uses environment variables for security.
 * 
 * @package Unified\Config
 * @version 1.0.0
 * @date 2025-10-08
 */

// Helper function for environment variables with defaults
if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}

// Robust credential resolution - use bootstrap constants if available
// Production credentials: jcepnzzkmj / wprKh9Jq63 @ 127.0.0.1
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$user = defined('DB_USER') ? DB_USER : 'jcepnzzkmj';
$pass = defined('DB_PASS') ? DB_PASS : 'wprKh9Jq63';
$db   = defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj';
$port = 3306;

return [
    // Primary Vend Database Connection
    'connection' => [
        'host' => $host,
        'port' => (int)$port,
        'database' => $db,
        'username' => $user,
        'password' => $pass,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
    
    // Connection Pool Settings
    'pool' => [
        'min_connections' => 2,
        'max_connections' => 10,
        'idle_timeout' => 300, // 5 minutes
        'max_lifetime' => 3600, // 1 hour
    ],
    
    // Performance Settings
    'performance' => [
        'query_timeout' => 30, // seconds
        'connection_timeout' => 5, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],
    
    // Cache Settings
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5 minutes for inventory data
        'prefix' => 'vend:',
        'driver' => 'file', // or 'redis' in production
    ],
    
    // Security Settings
    'security' => [
        'ssl' => env('VEND_DB_SSL', false),
        'verify_certificate' => env('VEND_DB_SSL_VERIFY', true),
        'read_only' => env('VEND_DB_READONLY', true), // Safety: read-only by default
    ],
    
    // Logging
    'logging' => [
        'enabled' => true,
        'slow_query_threshold' => 1000, // milliseconds
        'log_queries' => env('VEND_LOG_QUERIES', false),
    ],
    
    // Table Names (Vend Schema)
    'tables' => [
        'products' => 'vend_products',
        'inventory' => 'vend_inventory',
        'sales' => 'vend_sales',
        'sales_line_items' => 'vend_sales_line_items',
        'outlets' => 'vend_outlets',
        'product_types' => 'vend_product_types',
        'brands' => 'vend_brands',
        'suppliers' => 'vend_suppliers',
        'tags' => 'vend_tags',
        'customers' => 'vend_customers',
        'registers' => 'vend_registers',
    ],
    
    // Data Sync Settings
    'sync' => [
        'batch_size' => 1000,
        'sync_interval' => 300, // 5 minutes
        'full_sync_interval' => 86400, // 24 hours
    ],
    
    // Health Check
    'health_check' => [
        'enabled' => true,
        'interval' => 60, // seconds
        'query' => 'SELECT 1',
        'timeout' => 5, // seconds
    ],
];
