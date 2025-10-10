<?php
/**
 * Bootstrap Configuration for Enterprise Transfer Engine
 * Integrates with existing CIS database configuration
 */

// Check if main bootstrap already loaded
if (!defined('ROOT_PATH')) {
    // Define base path
    define('BASE_PATH', dirname(__DIR__));
    define('ROOT_PATH', BASE_PATH); // Alias for compatibility
    define('APP_ROOT', BASE_PATH);  // Another alias for compatibility
    
    // Define all path constants
    define('APP_PATH', BASE_PATH . '/app');
    define('CONFIG_PATH', BASE_PATH . '/config');
    define('SRC_PATH', BASE_PATH . '/src');
    define('PUBLIC_PATH', BASE_PATH . '/public');
    define('STORAGE_PATH', BASE_PATH . '/storage');
    define('VIEWS_PATH', PUBLIC_PATH . '/views');
    define('MODULES_PATH', PUBLIC_PATH . '/modules');
    
    // Define log paths
    define('LOG_PATH', STORAGE_PATH . '/logs/transfer_engine.log');
    define('LOG_LEVEL', 'info');
    
    // Define database configuration flag
    define('DB_CONFIGURED', true);
    
    // Define business constants (warehouse configuration)
    // These can be overridden by environment variables or config
    if (!defined('WAREHOUSE_ID')) {
        define('WAREHOUSE_ID', getenv('WAREHOUSE_ID') ?: '1'); // Default warehouse outlet ID
    }
    if (!defined('WAREHOUSE_WEB_OUTLET_ID')) {
        define('WAREHOUSE_WEB_OUTLET_ID', getenv('WAREHOUSE_WEB_OUTLET_ID') ?: '020b2c2a-4671-11f0-e200-8e55f1689700');
    }
    if (!defined('CSRF_TOKEN_NAME')) {
        define('CSRF_TOKEN_NAME', '_csrf_token'); // CSRF token form field name
    }
} else {
    // Already loaded via app/bootstrap.php
    if (!defined('BASE_PATH')) {
        define('BASE_PATH', ROOT_PATH);
    }
    if (!defined('APP_ROOT')) {
        define('APP_ROOT', ROOT_PATH);
    }
    if (!defined('LOG_PATH')) {
        define('LOG_PATH', STORAGE_PATH . '/logs/transfer_engine.log');
    }
    if (!defined('LOG_LEVEL')) {
        define('LOG_LEVEL', 'info');
    }
    if (!defined('DB_CONFIGURED')) {
        define('DB_CONFIGURED', true);
    }
    if (!defined('WAREHOUSE_ID')) {
        define('WAREHOUSE_ID', getenv('WAREHOUSE_ID') ?: '1');
    }
    if (!defined('WAREHOUSE_WEB_OUTLET_ID')) {
        define('WAREHOUSE_WEB_OUTLET_ID', getenv('WAREHOUSE_WEB_OUTLET_ID') ?: '020b2c2a-4671-11f0-e200-8e55f1689700');
    }
    if (!defined('CSRF_TOKEN_NAME')) {
        define('CSRF_TOKEN_NAME', '_csrf_token');
    }
}

// SPL Autoloader for App namespace (primary)
spl_autoload_register(function ($class) {
    // Handle App namespace
    if (strpos($class, 'App\\') === 0) {
        $classPath = str_replace('App\\', '', $class);
        $classPath = str_replace('\\', '/', $classPath);
        
        $file = BASE_PATH . '/app/' . $classPath . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// SPL Autoloader for Unified namespace
spl_autoload_register(function ($class) {
    // Only handle Unified namespace
    if (strpos($class, 'Unified\\') !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $classPath = str_replace('Unified\\', '', $class);
    $classPath = str_replace('\\', '/', $classPath);
    
    // Try src/ directory
    $file = BASE_PATH . '/src/' . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
    
    // Try app/ directory (for backwards compatibility)
    $file = BASE_PATH . '/app/' . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});

// Helper functions
if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string {
        $storagePath = BASE_PATH . '/storage';
        if (!is_dir($storagePath)) {
            @mkdir($storagePath, 0775, true);
        }
        return $storagePath . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string {
        return BASE_PATH . '/config' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

// Database Configuration - Production Credentials
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'jcepnzzkmj');
define('DB_DATABASE', 'jcepnzzkmj'); // Alias for Database class compatibility
define('DB_USER', 'jcepnzzkmj');
define('DB_USERNAME', 'jcepnzzkmj'); // Alias for Database class compatibility
define('DB_PASS', 'wprKh9Jq63');
define('DB_PASSWORD', 'wprKh9Jq63'); // Alias for Database class compatibility
define('DB_PORT', 3306); // MySQL default port

// Alternatively, try to include existing CIS configuration
$possibleConfigs = [
    dirname(__FILE__) . '/../../../../../../app.php',
    dirname(__FILE__) . '/../../../app.php',
    dirname(__FILE__) . '/app.php'
];

foreach ($possibleConfigs as $configFile) {
    if (file_exists($configFile)) {
        require_once $configFile;
        break;
    }
}

// Ensure we have database constants defined
if (!defined('DB_HOST')) {
    // Fallback configuration - update these with your actual values
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'jcepnzzkmj');
    define('DB_DATABASE', 'jcepnzzkmj'); // Alias for Database class compatibility
    define('DB_USER', 'jcepnzzkmj');
    define('DB_USERNAME', 'jcepnzzkmj'); // Alias for Database class compatibility
    define('DB_PASS', 'update_this_password');
    define('DB_PASSWORD', 'update_this_password'); // Alias for Database class compatibility
    define('DB_PORT', 3306); // MySQL default port
}

// Set timezone
date_default_timezone_set('Pacific/Auckland');

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize session and CSRF token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure CSRF token exists in session
if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}