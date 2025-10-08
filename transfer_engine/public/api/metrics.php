<?php
declare(strict_types=1);
/**
 * Metrics API Endpoint
 * Real-time system and application metrics for monitoring dashboards.
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Unified\Support\Api;
use Unified\Support\Config;
use Unified\Support\Pdo;
use Unified\Support\Cache;

Api::initJson();
Api::applySecurityHeaders();
Api::applyCors('GET, OPTIONS');
Api::handleOptionsPreflight();
Api::requireMethod('GET');
Api::enforceGetRateLimit('metrics');

$cache = Cache::fromConfig();

// Check cache first (metrics expensive to compute)
$cacheKey = 'metrics_snapshot';
$cacheTtl = (int) Config::get('neuro.unified.metrics_cache_ttl', 30); // 30 seconds default

$metrics = $cache->get($cacheKey);

if ($metrics === null) {
    $metrics = computeMetrics();
    $cache->set($cacheKey, $metrics, $cacheTtl);
}

Api::ok($metrics);

/**
 * Compute comprehensive system and application metrics
 * 
 * @return array Metrics data
 */
function computeMetrics(): array
{
    $db = Pdo::instance();
    $metrics = [
        'timestamp' => time(),
        'system' => getSystemMetrics(),
        'database' => getDatabaseMetrics($db),
        'application' => getApplicationMetrics($db),
        'queue' => getQueueMetrics($db),
    ];
    
    return $metrics;
}

/**
 * Get system-level metrics
 * 
 * @return array System metrics
 */
function getSystemMetrics(): array
{
    $metrics = [
        'memory' => [
            'usage_bytes' => memory_get_usage(true),
            'usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_bytes' => memory_get_peak_usage(true),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit'),
        ],
        'php' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ],
    ];
    
    // System load if available
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $metrics['load'] = [
            '1min' => round($load[0] ?? 0, 2),
            '5min' => round($load[1] ?? 0, 2),
            '15min' => round($load[2] ?? 0, 2),
        ];
    }
    
    // Disk space
    if (defined('STORAGE_PATH') && is_dir(STORAGE_PATH)) {
        $free = @disk_free_space(STORAGE_PATH);
        $total = @disk_total_space(STORAGE_PATH);
        
        if ($free !== false && $total !== false) {
            $used = $total - $free;
            $metrics['disk'] = [
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                'used_gb' => round($used / 1024 / 1024 / 1024, 2),
                'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                'used_percent' => round(($used / $total) * 100, 2),
            ];
        }
    }
    
    return $metrics;
}

/**
 * Get database metrics
 * 
 * @param PDO $db Database connection
 * @return array Database metrics
 */
function getDatabaseMetrics(\PDO $db): array
{
    $metrics = [
        'connection' => 'active',
    ];
    
    try {
        // Database size (MySQL/MariaDB specific)
        $stmt = $db->query("
            SELECT 
                SUM(data_length + index_length) as size_bytes,
                COUNT(*) as table_count
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE()
        ");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            $metrics['size_mb'] = round(($result['size_bytes'] ?? 0) / 1024 / 1024, 2);
            $metrics['table_count'] = (int) ($result['table_count'] ?? 0);
        }
        
        // Connection stats
        $stmt = $db->query("SHOW STATUS WHERE Variable_name IN (
            'Threads_connected', 'Threads_running', 'Max_used_connections', 
            'Questions', 'Slow_queries', 'Uptime'
        )");
        $stats = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        
        $metrics['connections'] = [
            'current' => (int) ($stats['Threads_connected'] ?? 0),
            'running' => (int) ($stats['Threads_running'] ?? 0),
            'max_used' => (int) ($stats['Max_used_connections'] ?? 0),
        ];
        
        $metrics['queries'] = [
            'total' => (int) ($stats['Questions'] ?? 0),
            'slow' => (int) ($stats['Slow_queries'] ?? 0),
        ];
        
        $metrics['uptime_seconds'] = (int) ($stats['Uptime'] ?? 0);
        
    } catch (\Throwable $e) {
        $metrics['error'] = 'Failed to retrieve database metrics';
    }
    
    return $metrics;
}

/**
 * Get application-specific metrics
 * 
 * @param PDO $db Database connection
 * @return array Application metrics
 */
function getApplicationMetrics(\PDO $db): array
{
    $metrics = [];
    
    try {
        // Transfer executions (last 24 hours)
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM transfer_executions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $transfers = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $metrics['transfers_24h'] = [
            'total' => (int) ($transfers['total'] ?? 0),
            'completed' => (int) ($transfers['completed'] ?? 0),
            'failed' => (int) ($transfers['failed'] ?? 0),
            'pending' => (int) ($transfers['pending'] ?? 0),
        ];
        
        // Price changes (last 24 hours)
        $stmt = $db->query("
            SELECT COUNT(*) as total
            FROM claude_price_changes
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $priceChanges = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $metrics['price_changes_24h'] = (int) ($priceChanges['total'] ?? 0);
        
        // Neural insights (last 24 hours)
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                SUM(CASE WHEN severity = 'warning' THEN 1 ELSE 0 END) as warning,
                SUM(CASE WHEN acknowledged = 1 THEN 1 ELSE 0 END) as acknowledged
            FROM cis_neural_insights
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $insights = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $metrics['insights_24h'] = [
            'total' => (int) ($insights['total'] ?? 0),
            'critical' => (int) ($insights['critical'] ?? 0),
            'warning' => (int) ($insights['warning'] ?? 0),
            'acknowledged' => (int) ($insights['acknowledged'] ?? 0),
        ];
        
    } catch (\Throwable $e) {
        $metrics['error'] = 'Failed to retrieve application metrics';
    }
    
    return $metrics;
}

/**
 * Get queue metrics
 * 
 * @param PDO $db Database connection
 * @return array Queue metrics
 */
function getQueueMetrics(\PDO $db): array
{
    $metrics = [];
    
    try {
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(CASE 
                    WHEN status = 'completed' AND completed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) 
                    ELSE NULL 
                END) as avg_processing_time
            FROM queue_jobs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $queue = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $metrics['jobs_24h'] = [
            'total' => (int) ($queue['total'] ?? 0),
            'pending' => (int) ($queue['pending'] ?? 0),
            'processing' => (int) ($queue['processing'] ?? 0),
            'completed' => (int) ($queue['completed'] ?? 0),
            'failed' => (int) ($queue['failed'] ?? 0),
            'avg_processing_time_seconds' => $queue['avg_processing_time'] !== null 
                ? round((float) $queue['avg_processing_time'], 2) 
                : null,
        ];
        
    } catch (\Throwable $e) {
        $metrics['error'] = 'Failed to retrieve queue metrics';
    }
    
    return $metrics;
}
