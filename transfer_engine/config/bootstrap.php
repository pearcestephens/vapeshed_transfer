<?php
/**
 * Bootstrap Configuration for Enterprise Transfer Engine
 * Integrates with existing CIS database configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jcepnzzkmj');  // Your actual database name
define('DB_USER', 'jcepnzzkmj');  // Your database user
define('DB_PASS', 'your_db_password_here');  // Update with actual password

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