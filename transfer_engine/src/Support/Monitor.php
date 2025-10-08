<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * Monitor.php - Application Monitoring and Alerting
 * 
 * System health monitoring with threshold-based alerting.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class Monitor
{
    private Logger $logger;
    private array $thresholds;
    private array $alertHistory = [];
    
    /**
     * Create monitor instance
     * 
     * @param Logger|null $logger Logger instance
     * @param array $thresholds Alert thresholds
     */
    public function __construct(?Logger $logger = null, array $thresholds = [])
    {
        $this->logger = $logger ?? new Logger('monitor');
        $this->thresholds = array_merge($this->getDefaultThresholds(), $thresholds);
    }
    
    /**
     * Get default monitoring thresholds
     * 
     * @return array Default thresholds
     */
    private function getDefaultThresholds(): array
    {
        return [
            'memory_usage_percent' => 90,
            'disk_usage_percent' => 85,
            'cpu_load_1min' => 8.0,
            'error_rate_per_min' => 10,
            'slow_query_count' => 100,
            'queue_backlog' => 1000,
            'response_time_ms' => 2000,
        ];
    }
    
    /**
     * Check system health and alert on issues
     * 
     * @return array Health status and alerts
     */
    public function checkHealth(): array
    {
        $checks = [
            'memory' => $this->checkMemory(),
            'disk' => $this->checkDisk(),
            'load' => $this->checkLoad(),
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
        ];
        
        $alerts = [];
        $overallStatus = 'healthy';
        
        foreach ($checks as $name => $check) {
            if ($check['status'] === 'critical') {
                $overallStatus = 'critical';
                $alerts[] = $check['alert'];
                $this->triggerAlert($name, 'critical', $check['alert']);
            } elseif ($check['status'] === 'warning' && $overallStatus !== 'critical') {
                $overallStatus = 'warning';
                $alerts[] = $check['alert'];
                $this->triggerAlert($name, 'warning', $check['alert']);
            }
        }
        
        return [
            'status' => $overallStatus,
            'checks' => $checks,
            'alerts' => $alerts,
            'timestamp' => time(),
        ];
    }
    
    /**
     * Check memory usage
     * 
     * @return array Check result
     */
    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        $limitBytes = $this->parseMemoryLimit($limit);
        
        if ($limitBytes <= 0) {
            return ['status' => 'healthy', 'message' => 'Memory limit unlimited'];
        }
        
        $percent = ($usage / $limitBytes) * 100;
        $threshold = $this->thresholds['memory_usage_percent'];
        
        if ($percent >= $threshold) {
            return [
                'status' => 'critical',
                'message' => sprintf('Memory usage at %.1f%%', $percent),
                'alert' => sprintf('Memory usage critical: %.1f%% (threshold: %d%%)', $percent, $threshold),
                'value' => $percent,
            ];
        } elseif ($percent >= ($threshold * 0.8)) {
            return [
                'status' => 'warning',
                'message' => sprintf('Memory usage at %.1f%%', $percent),
                'alert' => sprintf('Memory usage warning: %.1f%% (threshold: %d%%)', $percent, $threshold),
                'value' => $percent,
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => sprintf('Memory usage at %.1f%%', $percent),
            'value' => $percent,
        ];
    }
    
    /**
     * Check disk usage
     * 
     * @return array Check result
     */
    private function checkDisk(): array
    {
        if (!defined('STORAGE_PATH') || !is_dir(STORAGE_PATH)) {
            return ['status' => 'healthy', 'message' => 'Storage path not configured'];
        }
        
        $free = @disk_free_space(STORAGE_PATH);
        $total = @disk_total_space(STORAGE_PATH);
        
        if ($free === false || $total === false) {
            return ['status' => 'warning', 'message' => 'Unable to check disk space'];
        }
        
        $used = $total - $free;
        $percent = ($used / $total) * 100;
        $threshold = $this->thresholds['disk_usage_percent'];
        
        if ($percent >= $threshold) {
            return [
                'status' => 'critical',
                'message' => sprintf('Disk usage at %.1f%%', $percent),
                'alert' => sprintf('Disk usage critical: %.1f%% (threshold: %d%%)', $percent, $threshold),
                'value' => $percent,
            ];
        } elseif ($percent >= ($threshold * 0.9)) {
            return [
                'status' => 'warning',
                'message' => sprintf('Disk usage at %.1f%%', $percent),
                'alert' => sprintf('Disk usage warning: %.1f%% (threshold: %d%%)', $percent, $threshold),
                'value' => $percent,
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => sprintf('Disk usage at %.1f%%', $percent),
            'value' => $percent,
        ];
    }
    
    /**
     * Check system load
     * 
     * @return array Check result
     */
    private function checkLoad(): array
    {
        if (!function_exists('sys_getloadavg')) {
            return ['status' => 'healthy', 'message' => 'Load average not available'];
        }
        
        $load = sys_getloadavg();
        $load1 = $load[0] ?? 0;
        $threshold = $this->thresholds['cpu_load_1min'];
        
        if ($load1 >= $threshold) {
            return [
                'status' => 'critical',
                'message' => sprintf('Load average: %.2f', $load1),
                'alert' => sprintf('System load critical: %.2f (threshold: %.1f)', $load1, $threshold),
                'value' => $load1,
            ];
        } elseif ($load1 >= ($threshold * 0.75)) {
            return [
                'status' => 'warning',
                'message' => sprintf('Load average: %.2f', $load1),
                'alert' => sprintf('System load warning: %.2f (threshold: %.1f)', $load1, $threshold),
                'value' => $load1,
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => sprintf('Load average: %.2f', $load1),
            'value' => $load1,
        ];
    }
    
    /**
     * Check database health
     * 
     * @return array Check result
     */
    private function checkDatabase(): array
    {
        try {
            $db = Pdo::instance();
            
            // Check slow queries
            $stmt = $db->query("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $slowQueries = (int) ($result['Value'] ?? 0);
            
            $threshold = $this->thresholds['slow_query_count'];
            
            if ($slowQueries >= $threshold) {
                return [
                    'status' => 'warning',
                    'message' => "Slow queries: $slowQueries",
                    'alert' => sprintf('High slow query count: %d (threshold: %d)', $slowQueries, $threshold),
                    'value' => $slowQueries,
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => "Database operational, slow queries: $slowQueries",
                'value' => $slowQueries,
            ];
            
        } catch (\Throwable $e) {
            return [
                'status' => 'critical',
                'message' => 'Database connection failed',
                'alert' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check queue health
     * 
     * @return array Check result
     */
    private function checkQueue(): array
    {
        try {
            $db = Pdo::instance();
            
            $stmt = $db->query("
                SELECT COUNT(*) as backlog
                FROM queue_jobs
                WHERE status IN ('pending', 'processing')
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $backlog = (int) ($result['backlog'] ?? 0);
            
            $threshold = $this->thresholds['queue_backlog'];
            
            if ($backlog >= $threshold) {
                return [
                    'status' => 'warning',
                    'message' => "Queue backlog: $backlog jobs",
                    'alert' => sprintf('High queue backlog: %d jobs (threshold: %d)', $backlog, $threshold),
                    'value' => $backlog,
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => "Queue backlog: $backlog jobs",
                'value' => $backlog,
            ];
            
        } catch (\Throwable $e) {
            return [
                'status' => 'healthy',
                'message' => 'Queue check skipped (table may not exist)',
            ];
        }
    }
    
    /**
     * Trigger alert (log and potentially notify)
     * 
     * @param string $check Check name
     * @param string $severity Severity level
     * @param string $message Alert message
     */
    private function triggerAlert(string $check, string $severity, string $message): void
    {
        $alertKey = $check . '_' . $severity;
        $now = time();
        
        // Rate limit alerts (don't spam same alert within 5 minutes)
        if (isset($this->alertHistory[$alertKey])) {
            $lastAlert = $this->alertHistory[$alertKey];
            if (($now - $lastAlert) < 300) {
                return; // Suppress duplicate alert
            }
        }
        
        $this->alertHistory[$alertKey] = $now;
        
        $level = $severity === 'critical' ? 'critical' : 'warn';
        $this->logger->log($level, $message, [
            'neuro' => [
                'namespace' => 'unified',
                'system' => 'vapeshed_transfer',
                'component' => 'monitor',
                'alert_type' => 'threshold',
            ],
            'check' => $check,
            'severity' => $severity,
            'threshold_exceeded' => true,
        ]);
        
        // TODO: Implement external alerting (email, Slack, PagerDuty, etc.)
    }
    
    /**
     * Parse memory limit string to bytes
     * 
     * @param string $limit Memory limit
     * @return int Bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $unit = strtoupper(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        return match($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int) $limit,
        };
    }
    
    /**
     * Create monitor from config
     * 
     * @return self
     */
    public static function fromConfig(): self
    {
        $thresholds = [];
        
        if (class_exists('Unified\Support\Config')) {
            // Load custom thresholds from config if available
            $thresholds = [
                'memory_usage_percent' => (int) Config::get('neuro.unified.monitoring.memory_threshold', 90),
                'disk_usage_percent' => (int) Config::get('neuro.unified.monitoring.disk_threshold', 85),
                'cpu_load_1min' => (float) Config::get('neuro.unified.monitoring.load_threshold', 8.0),
            ];
        }
        
        $logger = Logger::fromConfig('monitor');
        
        return new self($logger, $thresholds);
    }
}
