<?php
/**
 * Analytics Database Migration - Quick Deploy Version
 * 
 * Run this once to create the analytics tables
 */

echo "=================================\n";
echo "Analytics Database Migration\n";
echo "=================================\n\n";

// Get database credentials
echo "Enter database host [localhost]: ";
$dbHost = trim(fgets(STDIN));
$dbHost = $dbHost ?: 'localhost';

echo "Enter database name [jcepnzzkmj]: ";
$dbName = trim(fgets(STDIN));
$dbName = $dbName ?: 'jcepnzzkmj';

echo "Enter database user [jcepnzzkmj]: ";
$dbUser = trim(fgets(STDIN));
$dbUser = $dbUser ?: 'jcepnzzkmj';

echo "Enter database password: ";
$dbPass = trim(fgets(STDIN));

echo "\nConnecting to database...\n";

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "✓ Connected successfully\n\n";
} catch (PDOException $e) {
    die("✗ Connection failed: " . $e->getMessage() . "\n");
}

// Create tables
$tables = [
    'transfer_metrics' => "CREATE TABLE IF NOT EXISTS transfer_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT NULL,
        source_outlet_id INT NULL,
        destination_outlet_id INT NULL,
        total_items INT DEFAULT 0,
        total_quantity INT DEFAULT 0,
        status VARCHAR(50) DEFAULT 'pending',
        processing_time_ms INT DEFAULT 0,
        api_calls_made INT DEFAULT 0,
        cost_calculated DECIMAL(10,2) DEFAULT 0.00,
        created_at DATETIME NOT NULL,
        metadata JSON NULL,
        INDEX idx_transfer_id (transfer_id),
        INDEX idx_source_outlet (source_outlet_id),
        INDEX idx_destination_outlet (destination_outlet_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_date_status (created_at, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'api_usage_metrics' => "CREATE TABLE IF NOT EXISTS api_usage_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) DEFAULT 'GET',
        provider VARCHAR(50) DEFAULT 'vend',
        response_time_ms INT DEFAULT 0,
        status_code INT DEFAULT 200,
        success TINYINT(1) DEFAULT 1,
        error_message TEXT NULL,
        rate_limit_remaining INT NULL,
        created_at DATETIME NOT NULL,
        metadata JSON NULL,
        INDEX idx_endpoint (endpoint),
        INDEX idx_provider (provider),
        INDEX idx_success (success),
        INDEX idx_created_at (created_at),
        INDEX idx_endpoint_provider (endpoint, provider),
        INDEX idx_date_provider (created_at, provider)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'performance_metrics' => "CREATE TABLE IF NOT EXISTS performance_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metric_type VARCHAR(50) NOT NULL,
        metric_value DECIMAL(12,4) DEFAULT 0,
        operation VARCHAR(100) NULL,
        query_text TEXT NULL,
        execution_time_ms INT DEFAULT 0,
        memory_usage_mb DECIMAL(8,2) DEFAULT 0,
        created_at DATETIME NOT NULL,
        metadata JSON NULL,
        INDEX idx_metric_type (metric_type),
        INDEX idx_created_at (created_at),
        INDEX idx_execution_time (execution_time_ms),
        INDEX idx_type_date (metric_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'scheduled_reports' => "CREATE TABLE IF NOT EXISTS scheduled_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        report_type VARCHAR(50) DEFAULT 'full',
        format VARCHAR(20) DEFAULT 'pdf',
        frequency VARCHAR(20) DEFAULT 'weekly',
        recipients JSON NOT NULL,
        filters JSON NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME NOT NULL,
        next_run_at DATETIME NULL,
        last_run_at DATETIME NULL,
        INDEX idx_is_active (is_active),
        INDEX idx_next_run (next_run_at),
        INDEX idx_frequency (frequency)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

echo "Creating tables...\n";
foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        echo "✓ {$name}\n";
    } catch (PDOException $e) {
        echo "✗ {$name}: " . $e->getMessage() . "\n";
    }
}

echo "\n✓ Migration complete!\n";
echo "\nTables created: " . count($tables) . "\n";
echo "Access dashboard at: /admin/analytics/dashboard\n\n";
