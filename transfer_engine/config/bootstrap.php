<?php
/**
 * Bootstrap Configuration for Enterprise Transfer Engine
 * Integrates with existing CIS database configuration
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

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
define('DB_USER', 'jcepnzzkmj');
define('DB_PASS', 'wprKh9Jq63');

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
    define('DB_USER', 'jcepnzzkmj');
    define('DB_PASS', 'update_this_password');
}

// Set timezone
date_default_timezone_set('Pacific/Auckland');

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);