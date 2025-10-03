#!/usr/bin/env php
<?php
/**
 * Simple System Validation (Standalone)
 * 
 * Validates database tables without loading the full bootstrap
 * to avoid Pdo redeclaration conflicts.
 * 
 * Usage: php bin/simple_validation.php
 */

// Direct database connection with fallback chain
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
echo "  SIMPLE SYSTEM VALIDATION (Standalone)\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $mysqli = new mysqli($host, $user, $pass, $db);
    
    if ($mysqli->connect_error) {
        echo CLR_RED . "✗ Database connection failed: " . $mysqli->connect_error . CLR_RESET . "\n\n";
        exit(1);
    }
    
    echo CLR_GREEN . "✓ Database connection successful\n" . CLR_RESET;
    echo "  Host: $host\n";
    echo "  Database: $db\n\n";
    
    // Check required tables
    echo "[1/3] Checking Required Tables...\n";
    
    $required_tables = [
        'proposal_log',
        'drift_metrics',
        'cooloff_log',
        'action_audit'
    ];
    
    $failures = [];
    
    foreach ($required_tables as $table) {
        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "  " . CLR_GREEN . "✓" . CLR_RESET . " Table '$table' exists\n";
        } else {
            echo "  " . CLR_RED . "✗" . CLR_RESET . " Table '$table' MISSING\n";
            $failures[] = "Missing table: $table";
        }
    }
    echo "\n";
    
    // Check table structures
    echo "[2/3] Checking Table Structures...\n";
    
    // proposal_log columns
    $pp_cols = $mysqli->query("SHOW COLUMNS FROM proposal_log");
    if ($pp_cols) {
        $col_count = $pp_cols->num_rows;
        echo "  " . CLR_GREEN . "✓" . CLR_RESET . " proposal_log has $col_count columns\n";
    }
    
    // cooloff_log columns
    $cl_cols = $mysqli->query("SHOW COLUMNS FROM cooloff_log");
    if ($cl_cols) {
        $col_count = $cl_cols->num_rows;
        echo "  " . CLR_GREEN . "✓" . CLR_RESET . " cooloff_log has $col_count columns\n";
    }
    
    // action_audit columns
    $aa_cols = $mysqli->query("SHOW COLUMNS FROM action_audit");
    if ($aa_cols) {
        $col_count = $aa_cols->num_rows;
        echo "  " . CLR_GREEN . "✓" . CLR_RESET . " action_audit has $col_count columns\n";
    }
    echo "\n";
    
    // Check data counts
    echo "[3/3] Checking Data Counts...\n";
    
    $pp_count = $mysqli->query("SELECT COUNT(*) as cnt FROM proposal_log")->fetch_assoc()['cnt'];
    echo "  " . CLR_GREEN . "✓" . CLR_RESET . " proposal_log: $pp_count records\n";
    
    $cl_count = $mysqli->query("SELECT COUNT(*) as cnt FROM cooloff_log")->fetch_assoc()['cnt'];
    echo "  " . CLR_GREEN . "✓" . CLR_RESET . " cooloff_log: $cl_count records\n";
    
    $aa_count = $mysqli->query("SELECT COUNT(*) as cnt FROM action_audit")->fetch_assoc()['cnt'];
    echo "  " . CLR_GREEN . "✓" . CLR_RESET . " action_audit: $aa_count records\n";
    
    $dm_count = $mysqli->query("SELECT COUNT(*) as cnt FROM drift_metrics")->fetch_assoc()['cnt'];
    echo "  " . CLR_GREEN . "✓" . CLR_RESET . " drift_metrics: $dm_count records\n";
    echo "\n";
    
    // Summary
    echo str_repeat("=", 70) . "\n";
    if (empty($failures)) {
        echo CLR_GREEN . "  ✓ ALL VALIDATION CHECKS PASSED\n" . CLR_RESET;
        echo str_repeat("=", 70) . "\n\n";
        echo "Database tables are present and accessible.\n";
        echo "Total records: " . ($pp_count + $cl_count + $aa_count + $dm_count) . "\n\n";
        exit(0);
    } else {
        echo CLR_RED . "  ✗ VALIDATION FAILURES\n" . CLR_RESET;
        echo str_repeat("=", 70) . "\n\n";
        foreach ($failures as $i => $failure) {
            echo "  " . ($i + 1) . ". $failure\n";
        }
        echo "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo CLR_RED . "✗ Error: " . $e->getMessage() . CLR_RESET . "\n\n";
    exit(1);
}
