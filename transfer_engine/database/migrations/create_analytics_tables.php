<?php
/**
 * Analytics Database Migration
 *
 * Creates the database schema for the analytics system including:
 * - transfer_metrics: Transfer operation metrics and statistics
 * - api_usage_metrics: API endpoint performance and rate limits
 * - performance_metrics: System and query performance data
 * - scheduled_reports: Automated report configuration
 *
 * @category   Database
 * @package    VapeshedTransfer
 * @subpackage Migrations
 * @version    1.0.0
 */

// Load dependencies
$autoloadPath = __DIR__ . '/../../config/bootstrap.php';
if (!file_exists($autoloadPath)) {
    // Alternative: direct class loading
    require_once __DIR__ . '/../../app/Support/Db.php';
} else {
    require_once $autoloadPath;
}

use App\Support\Db;

/**
 * Run analytics migration
 */
function runAnalyticsMigration()
{
    $db = Db::getInstance();

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

        $db->execute($sql);
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

        $db->execute($sql);
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

        $db->execute($sql);
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

        $db->execute($sql);
        echo "✓ Created\n";

        // Create views for common queries
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

        $db->execute($sql);
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

        $db->execute($sql);
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

        $db->execute($sql);
        echo "✓ Created\n";

        // Insert sample data for testing
        echo "\nInserting sample data...\n";

        // Sample transfer metrics
        echo "Inserting sample transfer metrics... ";
        $sampleTransfers = [
            [
                'transfer_id' => 1001,
                'source_outlet_id' => 1,
                'destination_outlet_id' => 2,
                'total_items' => 15,
                'total_quantity' => 45,
                'status' => 'completed',
                'processing_time_ms' => 2450,
                'api_calls_made' => 3,
                'cost_calculated' => 2.50,
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ],
            [
                'transfer_id' => 1002,
                'source_outlet_id' => 2,
                'destination_outlet_id' => 3,
                'total_items' => 8,
                'total_quantity' => 24,
                'status' => 'completed',
                'processing_time_ms' => 1890,
                'api_calls_made' => 2,
                'cost_calculated' => 1.75,
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 days'))
            ],
            [
                'transfer_id' => 1003,
                'source_outlet_id' => 1,
                'destination_outlet_id' => 3,
                'total_items' => 22,
                'total_quantity' => 66,
                'status' => 'failed',
                'processing_time_ms' => 3200,
                'api_calls_made' => 4,
                'cost_calculated' => 0.00,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ],
            [
                'transfer_id' => 1004,
                'source_outlet_id' => 3,
                'destination_outlet_id' => 1,
                'total_items' => 12,
                'total_quantity' => 36,
                'status' => 'completed',
                'processing_time_ms' => 2100,
                'api_calls_made' => 3,
                'cost_calculated' => 2.00,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
            ],
            [
                'transfer_id' => 1005,
                'source_outlet_id' => 2,
                'destination_outlet_id' => 1,
                'total_items' => 18,
                'total_quantity' => 54,
                'status' => 'pending',
                'processing_time_ms' => 0,
                'api_calls_made' => 0,
                'cost_calculated' => 0.00,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];

        foreach ($sampleTransfers as $transfer) {
            $sql = "INSERT INTO transfer_metrics (
                transfer_id, source_outlet_id, destination_outlet_id,
                total_items, total_quantity, status, processing_time_ms,
                api_calls_made, cost_calculated, created_at
            ) VALUES (
                :transfer_id, :source_outlet_id, :destination_outlet_id,
                :total_items, :total_quantity, :status, :processing_time_ms,
                :api_calls_made, :cost_calculated, :created_at
            )";
            $db->execute($sql, [
                ':transfer_id' => $transfer['transfer_id'],
                ':source_outlet_id' => $transfer['source_outlet_id'],
                ':destination_outlet_id' => $transfer['destination_outlet_id'],
                ':total_items' => $transfer['total_items'],
                ':total_quantity' => $transfer['total_quantity'],
                ':status' => $transfer['status'],
                ':processing_time_ms' => $transfer['processing_time_ms'],
                ':api_calls_made' => $transfer['api_calls_made'],
                ':cost_calculated' => $transfer['cost_calculated'],
                ':created_at' => $transfer['created_at']
            ]);
        }
        echo "✓ Inserted " . count($sampleTransfers) . " records\n";

        // Sample API usage metrics
        echo "Inserting sample API usage metrics... ";
        $sampleApiUsage = [
            [
                'endpoint' => '/api/2.0/products',
                'method' => 'GET',
                'provider' => 'vend',
                'response_time_ms' => 450,
                'status_code' => 200,
                'success' => 1,
                'rate_limit_remaining' => 9950,
                'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
            ],
            [
                'endpoint' => '/api/2.0/consignments',
                'method' => 'POST',
                'provider' => 'vend',
                'response_time_ms' => 890,
                'status_code' => 201,
                'success' => 1,
                'rate_limit_remaining' => 9949,
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
            ],
            [
                'endpoint' => '/api/2.0/outlets',
                'method' => 'GET',
                'provider' => 'vend',
                'response_time_ms' => 320,
                'status_code' => 200,
                'success' => 1,
                'rate_limit_remaining' => 9948,
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
            ],
            [
                'endpoint' => '/api/2.0/products',
                'method' => 'GET',
                'provider' => 'vend',
                'response_time_ms' => 1200,
                'status_code' => 500,
                'success' => 0,
                'error_message' => 'Internal Server Error',
                'rate_limit_remaining' => 9947,
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours'))
            ],
            [
                'endpoint' => '/api/2.0/inventory',
                'method' => 'PUT',
                'provider' => 'vend',
                'response_time_ms' => 650,
                'status_code' => 200,
                'success' => 1,
                'rate_limit_remaining' => 9946,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ]
        ];

        foreach ($sampleApiUsage as $usage) {
            $sql = "INSERT INTO api_usage_metrics (
                endpoint, method, provider, response_time_ms,
                status_code, success, error_message, rate_limit_remaining, created_at
            ) VALUES (
                :endpoint, :method, :provider, :response_time_ms,
                :status_code, :success, :error_message, :rate_limit_remaining, :created_at
            )";
            $db->execute($sql, [
                ':endpoint' => $usage['endpoint'],
                ':method' => $usage['method'],
                ':provider' => $usage['provider'],
                ':response_time_ms' => $usage['response_time_ms'],
                ':status_code' => $usage['status_code'],
                ':success' => $usage['success'],
                ':error_message' => $usage['error_message'] ?? null,
                ':rate_limit_remaining' => $usage['rate_limit_remaining'],
                ':created_at' => $usage['created_at']
            ]);
        }
        echo "✓ Inserted " . count($sampleApiUsage) . " records\n";

        // Sample performance metrics
        echo "Inserting sample performance metrics... ";
        $samplePerformance = [
            [
                'metric_type' => 'query',
                'metric_value' => 2500.00,
                'operation' => 'SELECT',
                'query_text' => 'SELECT * FROM transfers WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)',
                'execution_time_ms' => 2500,
                'memory_usage_mb' => 15.5,
                'created_at' => date('Y-m-d H:i:s', strtotime('-8 hours'))
            ],
            [
                'metric_type' => 'query',
                'metric_value' => 1200.00,
                'operation' => 'UPDATE',
                'query_text' => 'UPDATE inventory SET quantity = quantity - 1 WHERE product_id IN (SELECT ...)',
                'execution_time_ms' => 1200,
                'memory_usage_mb' => 8.2,
                'created_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))
            ],
            [
                'metric_type' => 'api_call',
                'metric_value' => 890.00,
                'operation' => 'vend_consignment_create',
                'query_text' => null,
                'execution_time_ms' => 890,
                'memory_usage_mb' => 5.1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-4 hours'))
            ],
            [
                'metric_type' => 'query',
                'metric_value' => 3400.00,
                'operation' => 'SELECT',
                'query_text' => 'SELECT t.*, o1.name as source, o2.name as dest FROM transfers t JOIN outlets o1 JOIN outlets o2',
                'execution_time_ms' => 3400,
                'memory_usage_mb' => 22.8,
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ]
        ];

        foreach ($samplePerformance as $perf) {
            $sql = "INSERT INTO performance_metrics (
                metric_type, metric_value, operation, query_text,
                execution_time_ms, memory_usage_mb, created_at
            ) VALUES (
                :metric_type, :metric_value, :operation, :query_text,
                :execution_time_ms, :memory_usage_mb, :created_at
            )";
            $db->execute($sql, [
                ':metric_type' => $perf['metric_type'],
                ':metric_value' => $perf['metric_value'],
                ':operation' => $perf['operation'],
                ':query_text' => $perf['query_text'],
                ':execution_time_ms' => $perf['execution_time_ms'],
                ':memory_usage_mb' => $perf['memory_usage_mb'],
                ':created_at' => $perf['created_at']
            ]);
        }
        echo "✓ Inserted " . count($samplePerformance) . " records\n";

        // Sample scheduled report
        echo "Inserting sample scheduled report... ";
        $sql = "INSERT INTO scheduled_reports (
            name, report_type, format, frequency, recipients,
            filters, is_active, created_at, next_run_at
        ) VALUES (
            'Weekly Transfer Summary',
            'transfer_summary',
            'pdf',
            'weekly',
            :recipients,
            '{}',
            1,
            NOW(),
            :next_run
        )";
        $db->execute($sql, [
            ':recipients' => json_encode(['admin@vapeshed.co.nz']),
            ':next_run' => date('Y-m-d H:i:s', strtotime('next monday 9:00'))
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
            $result = $db->fetchOne("SELECT COUNT(*) as count FROM {$table}");
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
}

// Run migration if executed directly
if (php_sapi_name() === 'cli') {
    runAnalyticsMigration();
}
