#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Database Performance Analysis Tool
 * 
 * Analyze database performance, suggest indexes, identify slow queries.
 * 
 * Usage:
 *   php bin/db_analyze.php tables           - List all tables with stats
 *   php bin/db_analyze.php indexes <table>  - Show indexes for table
 *   php bin/db_analyze.php slow             - Show slow query log
 *   php bin/db_analyze.php analyze <table>  - Analyze table
 *   php bin/db_analyze.php optimize <table> - Optimize table
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\Pdo;
use Unified\Support\Logger;

$logger = new Logger('db_analyze');
$db = Pdo::instance();

// Parse command
$command = $argv[1] ?? 'help';
$arg = $argv[2] ?? null;

switch ($command) {
    case 'tables':
        echo "📊 Database Tables:\n\n";
        
        $stmt = $db->query("
            SELECT 
                table_name,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
                table_rows,
                ROUND(data_length / 1024 / 1024, 2) AS data_mb,
                ROUND(index_length / 1024 / 1024, 2) AS index_mb,
                engine,
                table_collation
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
        ");
        
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo sprintf(
            "%-40s %10s %12s %10s %10s %10s\n",
            "Table", "Rows", "Total (MB)", "Data (MB)", "Index (MB)", "Engine"
        );
        echo str_repeat("-", 100) . "\n";
        
        foreach ($tables as $table) {
            echo sprintf(
                "%-40s %10s %12s %10s %10s %10s\n",
                $table['table_name'],
                number_format((int)$table['table_rows']),
                $table['size_mb'],
                $table['data_mb'],
                $table['index_mb'],
                $table['engine']
            );
        }
        
        $totalSize = array_sum(array_column($tables, 'size_mb'));
        echo "\n";
        echo "Total database size: " . round($totalSize, 2) . " MB\n";
        echo "Total tables: " . count($tables) . "\n";
        break;
        
    case 'indexes':
        if ($arg === null) {
            echo "❌ Error: Table name required\n";
            echo "Usage: php bin/db_analyze.php indexes <table>\n";
            exit(1);
        }
        
        echo "📇 Indexes for table: $arg\n\n";
        
        $stmt = $db->prepare("SHOW INDEX FROM " . $arg);
        $stmt->execute();
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($indexes)) {
            echo "❌ No indexes found\n";
            exit(0);
        }
        
        echo sprintf(
            "%-20s %-15s %-20s %-10s %-10s\n",
            "Key Name", "Type", "Column", "Unique", "Cardinality"
        );
        echo str_repeat("-", 80) . "\n";
        
        foreach ($indexes as $index) {
            echo sprintf(
                "%-20s %-15s %-20s %-10s %-10s\n",
                $index['Key_name'],
                $index['Index_type'],
                $index['Column_name'],
                $index['Non_unique'] == 0 ? 'YES' : 'NO',
                $index['Cardinality'] ?? 'N/A'
            );
        }
        break;
        
    case 'slow':
        echo "🐌 Slow Query Analysis:\n\n";
        
        // Get slow query stats from MySQL
        $stmt = $db->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $slowCount = $result['Value'] ?? 0;
        
        echo "Total slow queries: $slowCount\n\n";
        
        // Check if slow query log is enabled
        $stmt = $db->query("SHOW VARIABLES LIKE 'slow_query_log'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $logEnabled = $result['Value'] ?? 'OFF';
        
        echo "Slow query log: $logEnabled\n";
        
        if ($logEnabled === 'OFF') {
            echo "\n⚠️  Slow query log is disabled. Enable it with:\n";
            echo "   SET GLOBAL slow_query_log = 'ON';\n";
            echo "   SET GLOBAL long_query_time = 2;\n";
        }
        
        // Check for queries from our profiler log
        $logFile = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs/slow_queries.log' : null;
        
        if ($logFile && is_file($logFile)) {
            echo "\n📄 Recent slow queries from profiler:\n\n";
            
            $lines = file($logFile);
            $recentLines = array_slice($lines, -10);
            
            foreach ($recentLines as $line) {
                $data = json_decode($line, true);
                if ($data && isset($data['context']['sql'])) {
                    echo "Duration: " . $data['context']['duration'] . "s\n";
                    echo "SQL: " . substr($data['context']['sql'], 0, 100) . "...\n";
                    echo "Rows: " . ($data['context']['row_count'] ?? 'N/A') . "\n";
                    echo "---\n";
                }
            }
        }
        break;
        
    case 'analyze':
        if ($arg === null) {
            echo "❌ Error: Table name required\n";
            echo "Usage: php bin/db_analyze.php analyze <table>\n";
            exit(1);
        }
        
        echo "🔍 Analyzing table: $arg\n";
        
        $stmt = $db->prepare("ANALYZE TABLE " . $arg);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            echo "Status: " . $result['Msg_text'] . "\n";
        }
        
        $logger->info("Analyzed table: $arg");
        echo "✅ Analysis complete\n";
        break;
        
    case 'optimize':
        if ($arg === null) {
            echo "❌ Error: Table name required\n";
            echo "Usage: php bin/db_analyze.php optimize <table>\n";
            exit(1);
        }
        
        echo "⚡ Optimizing table: $arg\n";
        
        $stmt = $db->prepare("OPTIMIZE TABLE " . $arg);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $result) {
            echo "Status: " . $result['Msg_text'] . "\n";
        }
        
        $logger->info("Optimized table: $arg");
        echo "✅ Optimization complete\n";
        break;
        
    case 'unused-indexes':
        echo "🔍 Finding potentially unused indexes...\n\n";
        
        // This requires sys schema (available in MySQL 5.7+)
        try {
            $stmt = $db->query("
                SELECT 
                    object_schema AS db,
                    object_name AS table_name,
                    index_name
                FROM sys.schema_unused_indexes
                WHERE object_schema = DATABASE()
            ");
            
            $unused = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($unused)) {
                echo "✅ No unused indexes found\n";
            } else {
                echo "Found " . count($unused) . " potentially unused indexes:\n\n";
                
                foreach ($unused as $index) {
                    echo "  Table: {$index['table_name']}\n";
                    echo "  Index: {$index['index_name']}\n";
                    echo "  ---\n";
                }
                
                echo "\n⚠️  Review these carefully before dropping!\n";
            }
        } catch (\PDOException $e) {
            echo "❌ Error: sys schema not available or insufficient permissions\n";
        }
        break;
        
    case 'fragmentation':
        echo "📊 Table Fragmentation Analysis:\n\n";
        
        $stmt = $db->query("
            SELECT 
                table_name,
                ROUND(data_length / 1024 / 1024, 2) AS data_mb,
                ROUND(data_free / 1024 / 1024, 2) AS free_mb,
                ROUND((data_free / data_length) * 100, 2) AS fragmentation_pct
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            AND data_free > 0
            AND engine = 'InnoDB'
            ORDER BY data_free DESC
            LIMIT 20
        ");
        
        $fragmented = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($fragmented)) {
            echo "✅ No significant fragmentation found\n";
        } else {
            echo sprintf(
                "%-40s %12s %12s %15s\n",
                "Table", "Data (MB)", "Free (MB)", "Fragmentation %"
            );
            echo str_repeat("-", 85) . "\n";
            
            foreach ($fragmented as $table) {
                echo sprintf(
                    "%-40s %12s %12s %15s\n",
                    $table['table_name'],
                    $table['data_mb'],
                    $table['free_mb'],
                    $table['fragmentation_pct']
                );
            }
            
            echo "\n💡 Run OPTIMIZE TABLE on fragmented tables to reclaim space\n";
        }
        break;
        
    case 'help':
    default:
        echo "Database Performance Analysis Tool\n\n";
        echo "Usage:\n";
        echo "  php bin/db_analyze.php tables              - List all tables with stats\n";
        echo "  php bin/db_analyze.php indexes <table>     - Show indexes for table\n";
        echo "  php bin/db_analyze.php slow                - Show slow query analysis\n";
        echo "  php bin/db_analyze.php analyze <table>     - Analyze table\n";
        echo "  php bin/db_analyze.php optimize <table>    - Optimize table\n";
        echo "  php bin/db_analyze.php unused-indexes      - Find unused indexes\n";
        echo "  php bin/db_analyze.php fragmentation       - Show table fragmentation\n";
        echo "  php bin/db_analyze.php help                - Show this help\n";
        break;
}

exit(0);
