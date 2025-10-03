#!/usr/bin/env php
<?php
/**
 * Simple Migration Runner (Standalone)
 * 
 * Runs all SQL migrations in database/migrations/ directory
 * Focuses on the core Phase M14-M18 tables only.
 * 
 * Usage: php bin/run_migrations.php
 */

// Database credentials with fallback chain
$host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
$user = getenv('DB_USER') ?: (defined('DB_USERNAME') ? DB_USERNAME : (defined('DB_USER') ? DB_USER : 'jcepnzzkmj'));
$pass = getenv('DB_PASS') ?: (defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : 'wprKh9Jq63'));
$db   = getenv('DB_NAME') ?: (defined('DB_DATABASE') ? DB_DATABASE : (defined('DB_NAME') ? DB_NAME : 'jcepnzzkmj'));

// ANSI colors
const CLR_GREEN  = "\033[32m";
const CLR_RED    = "\033[31m";
const CLR_YELLOW = "\033[33m";
const CLR_RESET  = "\033[0m";

echo "\n" . str_repeat("=", 70) . "\n";
echo "  MIGRATION RUNNER - Core Phase M14-M18 Tables\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $mysqli = new mysqli($host, $user, $pass, $db);
    
    if ($mysqli->connect_error) {
        echo CLR_RED . "✗ Database connection failed: " . $mysqli->connect_error . CLR_RESET . "\n\n";
        exit(1);
    }
    
    echo CLR_GREEN . "✓ Connected to database\n" . CLR_RESET;
    echo "  Host: $host\n";
    echo "  Database: $db\n\n";
    
    // Define core migrations for M14-M18 phases
    $core_migrations = [
        '20251003_0001_create_proposal_log.sql',
        '20251003_0006_create_drift_metrics.sql',
        '20251003_0007_create_cooloff_log.sql',
        '20251003_0008_create_action_audit.sql'
    ];
    
    $migrations_dir = __DIR__ . '/../database/migrations/';
    
    if (!is_dir($migrations_dir)) {
        echo CLR_RED . "✗ Migrations directory not found: $migrations_dir\n" . CLR_RESET;
        exit(1);
    }
    
    echo "Running " . count($core_migrations) . " core migrations...\n\n";
    
    $success_count = 0;
    $failure_count = 0;
    
    foreach ($core_migrations as $migration_file) {
        $full_path = $migrations_dir . $migration_file;
        
        if (!file_exists($full_path)) {
            echo CLR_YELLOW . "! Migration not found: $migration_file\n" . CLR_RESET;
            continue;
        }
        
        echo "  Running: $migration_file\n";
        
        $sql = file_get_contents($full_path);
        
        // Remove comments and split by semicolons
        $sql = preg_replace('/--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement)) continue;
            
            if ($mysqli->query($statement)) {
                // Success - check if table was created
                if (preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
                    $table_name = $matches[1];
                    echo "    " . CLR_GREEN . "✓" . CLR_RESET . " Created table: $table_name\n";
                }
            } else {
                // Check if error is "table already exists"
                if ($mysqli->errno === 1050) {
                    if (preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
                        $table_name = $matches[1];
                        echo "    " . CLR_YELLOW . "!" . CLR_RESET . " Table already exists: $table_name\n";
                    }
                } else {
                    echo "    " . CLR_RED . "✗" . CLR_RESET . " Error: " . $mysqli->error . "\n";
                    $failure_count++;
                }
            }
        }
        
        $success_count++;
        echo "\n";
    }
    
    // Summary
    echo str_repeat("=", 70) . "\n";
    if ($failure_count === 0) {
        echo CLR_GREEN . "  ✓ ALL MIGRATIONS COMPLETED\n" . CLR_RESET;
        echo str_repeat("=", 70) . "\n\n";
        echo "Successfully processed $success_count migration files.\n";
        echo "Core database tables are ready for Phase M14-M18.\n\n";
        echo "Next step: Run validation with 'php bin/simple_validation.php'\n\n";
        exit(0);
    } else {
        echo CLR_RED . "  ✗ SOME MIGRATIONS FAILED\n" . CLR_RESET;
        echo str_repeat("=", 70) . "\n\n";
        echo "Processed: $success_count migrations\n";
        echo "Failures: $failure_count\n\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo CLR_RED . "✗ Error: " . $e->getMessage() . CLR_RESET . "\n\n";
    exit(1);
}
