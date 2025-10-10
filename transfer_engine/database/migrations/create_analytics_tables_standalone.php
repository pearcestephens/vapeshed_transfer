<?php
/**
 * Analytics Database Migration (Standalone)
 *
 * Creates the database schema for the analytics system
 * Uses direct PDO connection without requiring bootstrap
 *
 * @version 1.0.0
 */

// Try to load from CIS config first
$cisConfigPath = '/home/master/applications/jcepnzzkmj/public_html/assets/functions/config.php';
if (file_exists($cisConfigPath)) {
    echo "Loading CIS database config from: {$cisConfigPath}\n";
    require_once $cisConfigPath;
    
    // Try to use existing global $pdo
    global $pdo;
    if ($pdo instanceof PDO) {
        $db = $pdo;
        echo "✓ Using existing CIS database connection\n\n";
    } elseif (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        // Create new connection using defined constants
        echo "✓ Creating new connection using CIS constants\n\n";
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
        }
    } else {
        die("ERROR: CIS config loaded but database constants not defined\n");
    }
} else {
    die("ERROR: CIS config not found at: {$cisConfigPath}\n");
}

echo "Starting Analytics Database Migration...\n\n";

try {
    // Create transfer_metrics table
    echo "Creating transfer_metrics table... ";
    $sql = "CREATE TABLE IF NOT EXISTS transfer_metrics (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
      COMMENT='Stores metrics for transfer operations'";

    $db->exec($sql);
    echo "✓ Created\n";

    // Create api_usage_metrics table
    echo "Creating api_usage_metrics table... ";
    $sql = "CREATE TABLE IF NOT EXISTS api_usage_metrics (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
      COMMENT='Tracks API endpoint usage and performance'";

    $db->exec($sql);
    echo "✓ Created\n";

    // Create performance_metrics table
    echo "Creating performance_metrics table... ";
    $sql = "CREATE TABLE IF NOT EXISTS performance_metrics (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
      COMMENT='Stores system and query performance metrics'";

    $db->exec($sql);
    echo "✓ Created\n";

    // Create scheduled_reports table
    echo "Creating scheduled_reports table... ";
    $sql = "CREATE TABLE IF NOT EXISTS scheduled_reports (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
      COMMENT='Configuration for scheduled analytics reports'";

    $db->exec($sql);
    echo "✓ Created\n";

    // Create views
    echo "\nCreating database views...\n";

    // Transfer statistics view
    echo "Creating v_transfer_statistics view... ";
    $sql = "CREATE OR REPLACE VIEW v_transfer_statistics AS
    SELECT
        DATE(created_at) as report_date,
        COUNT(*) as total_transfers,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_transfers,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_transfers,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transfers,
        ROUND((SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100), 2) as success_rate,
        AVG(processing_time_ms) as avg_processing_time,
        SUM(total_items) as total_items,
        SUM(total_quantity) as total_quantity,
        SUM(api_calls_made) as total_api_calls,
        SUM(cost_calculated) as total_cost
    FROM transfer_metrics
    GROUP BY DATE(created_at)
    ORDER BY report_date DESC";

    $db->exec($sql);
    echo "✓ Created\n";

    // API performance view
    echo "Creating v_api_performance view... ";
    $sql = "CREATE OR REPLACE VIEW v_api_performance AS
    SELECT
        endpoint,
        provider,
        DATE(created_at) as report_date,
        COUNT(*) as total_calls,
        AVG(response_time_ms) as avg_response_time,
        MAX(response_time_ms) as max_response_time,
        MIN(response_time_ms) as min_response_time,
        SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_calls,
        SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_calls,
        ROUND((SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) / COUNT(*) * 100), 2) as error_rate
    FROM api_usage_metrics
    GROUP BY endpoint, provider, DATE(created_at)
    ORDER BY report_date DESC, total_calls DESC";

    $db->exec($sql);
    echo "✓ Created\n";

    // Slow queries view
    echo "Creating v_slow_queries view... ";
    $sql = "CREATE OR REPLACE VIEW v_slow_queries AS
    SELECT
        query_text,
        COUNT(*) as execution_count,
        AVG(execution_time_ms) as avg_time,
        MAX(execution_time_ms) as max_time,
        MIN(execution_time_ms) as min_time,
        AVG(memory_usage_mb) as avg_memory,
        DATE(MAX(created_at)) as last_seen
    FROM performance_metrics
    WHERE metric_type = 'query'
        AND execution_time_ms >= 1000
        AND query_text IS NOT NULL
    GROUP BY query_text
    ORDER BY avg_time DESC";

    $db->exec($sql);
    echo "✓ Created\n";

    // Insert sample data
    echo "\nInserting sample data...\n";

    // Sample transfer metrics (5 records)
    echo "Inserting sample transfer metrics... ";
    $stmt = $db->prepare("INSERT INTO transfer_metrics (
        transfer_id, source_outlet_id, destination_outlet_id,
        total_items, total_quantity, status, processing_time_ms,
        api_calls_made, cost_calculated, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $sampleTransfers = [
        [1001, 1, 2, 15, 45, 'completed', 2450, 3, 2.50, date('Y-m-d H:i:s', strtotime('-5 days'))],
        [1002, 2, 3, 8, 24, 'completed', 1890, 2, 1.75, date('Y-m-d H:i:s', strtotime('-4 days'))],
        [1003, 1, 3, 22, 66, 'failed', 3200, 4, 0.00, date('Y-m-d H:i:s', strtotime('-3 days'))],
        [1004, 3, 1, 12, 36, 'completed', 2100, 3, 2.00, date('Y-m-d H:i:s', strtotime('-2 days'))],
        [1005, 2, 1, 18, 54, 'pending', 0, 0, 0.00, date('Y-m-d H:i:s', strtotime('-1 day'))]
    ];

    foreach ($sampleTransfers as $transfer) {
        $stmt->execute($transfer);
    }
    echo "✓ Inserted " . count($sampleTransfers) . " records\n";

    // Sample API usage metrics (5 records)
    echo "Inserting sample API usage metrics... ";
    $stmt = $db->prepare("INSERT INTO api_usage_metrics (
        endpoint, method, provider, response_time_ms,
        status_code, success, error_message, rate_limit_remaining, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $sampleApiUsage = [
        ['/api/2.0/products', 'GET', 'vend', 450, 200, 1, null, 9950, date('Y-m-d H:i:s', strtotime('-6 hours'))],
        ['/api/2.0/consignments', 'POST', 'vend', 890, 201, 1, null, 9949, date('Y-m-d H:i:s', strtotime('-5 hours'))],
        ['/api/2.0/outlets', 'GET', 'vend', 320, 200, 1, null, 9948, date('Y-m-d H:i:s', strtotime('-4 hours'))],
        ['/api/2.0/products', 'GET', 'vend', 1200, 500, 0, 'Internal Server Error', 9947, date('Y-m-d H:i:s', strtotime('-3 hours'))],
        ['/api/2.0/inventory', 'PUT', 'vend', 650, 200, 1, null, 9946, date('Y-m-d H:i:s', strtotime('-2 hours'))]
    ];

    foreach ($sampleApiUsage as $usage) {
        $stmt->execute($usage);
    }
    echo "✓ Inserted " . count($sampleApiUsage) . " records\n";

    // Sample performance metrics (4 records)
    echo "Inserting sample performance metrics... ";
    $stmt = $db->prepare("INSERT INTO performance_metrics (
        metric_type, metric_value, operation, query_text,
        execution_time_ms, memory_usage_mb, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $samplePerformance = [
        ['query', 2500.00, 'SELECT', 'SELECT * FROM transfers WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)', 2500, 15.5, date('Y-m-d H:i:s', strtotime('-8 hours'))],
        ['query', 1200.00, 'UPDATE', 'UPDATE inventory SET quantity = quantity - 1 WHERE product_id IN (SELECT ...)', 1200, 8.2, date('Y-m-d H:i:s', strtotime('-6 hours'))],
        ['api_call', 890.00, 'vend_consignment_create', null, 890, 5.1, date('Y-m-d H:i:s', strtotime('-4 hours'))],
        ['query', 3400.00, 'SELECT', 'SELECT t.*, o1.name as source, o2.name as dest FROM transfers t JOIN outlets o1 JOIN outlets o2', 3400, 22.8, date('Y-m-d H:i:s', strtotime('-2 hours'))]
    ];

    foreach ($samplePerformance as $perf) {
        $stmt->execute($perf);
    }
    echo "✓ Inserted " . count($samplePerformance) . " records\n";

    // Sample scheduled report (1 record)
    echo "Inserting sample scheduled report... ";
    $stmt = $db->prepare("INSERT INTO scheduled_reports (
        name, report_type, format, frequency, recipients,
        filters, is_active, created_at, next_run_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

    $stmt->execute([
        'Weekly Transfer Summary',
        'transfer_summary',
        'pdf',
        'weekly',
        json_encode(['admin@vapeshed.co.nz']),
        '{}',
        1,
        date('Y-m-d H:i:s', strtotime('next monday 9:00'))
    ]);
    echo "✓ Inserted 1 record\n";

    // Verify tables and counts
    echo "\nVerifying migration...\n";

    $tables = [
        'transfer_metrics',
        'api_usage_metrics',
        'performance_metrics',
        'scheduled_reports'
    ];

    foreach ($tables as $table) {
        $result = $db->query("SELECT COUNT(*) as count FROM {$table}")->fetch();
        echo "  {$table}: " . $result['count'] . " records\n";
    }

    echo "\n✓ Analytics Migration Completed Successfully!\n\n";

    echo "Summary:\n";
    echo "--------\n";
    echo "✓ Created 4 tables\n";
    echo "✓ Created 3 views\n";
    echo "✓ Inserted sample data\n";
    echo "✓ Added indexes for performance\n";
    echo "\nThe analytics system is ready to use.\n";
    echo "Access the dashboard at: /admin/analytics/dashboard\n\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nMigration failed. Please check the error and try again.\n";
    exit(1);
}
