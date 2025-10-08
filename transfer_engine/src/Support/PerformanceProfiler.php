<?php
/**
 * PerformanceProfiler.php - Enterprise Performance Profiling Dashboard
 * 
 * Comprehensive performance monitoring and profiling with request tracking,
 * query analysis, resource usage monitoring, and bottleneck detection.
 * 
 * Features:
 * - Request performance tracking
 * - Query performance profiling
 * - Memory usage monitoring
 * - CPU usage tracking
 * - Bottleneck detection
 * - Performance alerts
 * - Historical trending
 * - Export & reporting
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace Unified\Support;

use Unified\Support\Logger;
use Unified\Support\NeuroContext;
use Unified\Support\Cache;
use Unified\Support\CacheManager;
use Unified\Support\AlertManager;

class PerformanceProfiler
{
    private Logger $logger;
    private Cache|CacheManager $cache;
    private ?AlertManager $alertManager;
    private array $config;
    private array $requestTimers = [];
    private array $queries = [];
    private float $requestStartTime;
    private int $requestStartMemory;

    // Performance thresholds
    private const THRESHOLD_SLOW_REQUEST = 1000; // ms
    private const THRESHOLD_SLOW_QUERY = 100;    // ms
    private const THRESHOLD_MEMORY_HIGH = 50 * 1024 * 1024; // 50MB
    private const THRESHOLD_CPU_HIGH = 80; // percent

    /**
     * Initialize PerformanceProfiler
     *
     * @param Logger $logger Logger instance
     * @param Cache|CacheManager $cache Cache instance
     * @param AlertManager|null $alertManager Optional alert manager
     * @param array $config Configuration options
     */
    public function __construct(
        Logger $logger,
        Cache|CacheManager $cache,
        ?AlertManager $alertManager = null,
        array $config = []
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->alertManager = $alertManager;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->requestStartTime = microtime(true);
        $this->requestStartMemory = memory_get_usage(true);
    }

    /**
     * Start profiling segment
     *
     * @param string $name Segment name
     * @return void
     */
    public function start(string $name): void
    {
        $this->requestTimers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * Stop profiling segment
     *
     * @param string $name Segment name
     * @return array Segment metrics
     */
    public function stop(string $name): array
    {
        if (!isset($this->requestTimers[$name])) {
            return [
                'error' => 'Timer not started: ' . $name,
            ];
        }
        
        $timer = $this->requestTimers[$name];
        $duration = (microtime(true) - $timer['start']) * 1000; // ms
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];
        
        $metrics = [
            'name' => $name,
            'duration_ms' => round($duration, 2),
            'memory_used' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
        ];
        
        $this->requestTimers[$name]['metrics'] = $metrics;
        
        return $metrics;
    }

    /**
     * Record query execution
     *
     * @param string $query SQL query
     * @param float $duration Query duration in milliseconds
     * @param array $context Additional context
     * @return void
     */
    public function recordQuery(string $query, float $duration, array $context = []): void
    {
        $this->queries[] = [
            'query' => $query,
            'duration_ms' => round($duration, 2),
            'context' => $context,
            'timestamp' => microtime(true),
            'is_slow' => $duration >= self::THRESHOLD_SLOW_QUERY,
        ];
        
        // Alert on slow query
        if ($duration >= self::THRESHOLD_SLOW_QUERY && $this->alertManager) {
            $this->alertManager->warning(
                'Slow Query Detected',
                "Query took {$duration}ms to execute",
                [
                    'query' => substr($query, 0, 200),
                    'duration_ms' => $duration,
                    'threshold_ms' => self::THRESHOLD_SLOW_QUERY,
                ]
            );
        }
    }

    /**
     * Start tracking a request (alias/extended API)
     *
     * @param array $context Request context (method, uri, headers, etc.)
     * @return string Request ID
     */
    public function startRequest(array $context = []): string
    {
        $requestId = uniqid('req_', true);
        $this->start($requestId);
        $this->requestTimers[$requestId]['context'] = $context;
        return $requestId;
    }

    /**
     * Add query to request tracking (alias for recordQuery)
     *
     * @param string $requestId Request ID
     * @param string $query SQL query
     * @param float $duration Duration in milliseconds
     * @return void
     */
    public function addQuery(string $requestId, string $query, float $duration): void
    {
        $this->recordQuery($query, $duration, ['request_id' => $requestId]);
    }

    /**
     * End request tracking
     *
     * @param string $requestId Request ID
     * @param int $statusCode HTTP status code
     * @param array $response Response data
     * @return array Request metrics
     */
    public function endRequest(string $requestId, int $statusCode = 200, array $response = []): array
    {
        $metrics = $this->stop($requestId);
        $metrics['status_code'] = $statusCode;
        $metrics['response'] = $response;
        return $metrics;
    }

    /**
     * Get current request metrics
     *
     * @return array Request performance metrics
     */
    public function getRequestMetrics(): array
    {
        $duration = (microtime(true) - $this->requestStartTime) * 1000;
        $memoryUsed = memory_get_usage(true) - $this->requestStartMemory;
        $memoryPeak = memory_get_peak_usage(true);
        
        $metrics = [
            'request' => [
                'duration_ms' => round($duration, 2),
                'is_slow' => $duration >= self::THRESHOLD_SLOW_REQUEST,
                'memory_used' => $memoryUsed,
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
                'memory_peak' => $memoryPeak,
                'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            ],
            'timers' => array_map(
                fn($timer) => $timer['metrics'] ?? null,
                $this->requestTimers
            ),
            'queries' => [
                'total' => count($this->queries),
                'slow_queries' => count(array_filter($this->queries, fn($q) => $q['is_slow'])),
                'total_duration_ms' => round(array_sum(array_column($this->queries, 'duration_ms')), 2),
                'queries' => $this->queries,
            ],
            'system' => $this->getSystemMetrics(),
        ];
        
        // Record metrics to cache
        $this->recordMetrics($metrics);
        
        // Check for performance issues
        $this->checkPerformanceIssues($metrics);
        
        return $metrics;
    }

    /**
     * Get system resource metrics
     *
     * @return array System metrics
     */
    public function getSystemMetrics(): array
    {
        $loadAvg = sys_getloadavg();
        
        return [
            'memory' => [
                'current' => memory_get_usage(true),
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak' => memory_get_peak_usage(true),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit' => ini_get('memory_limit'),
            ],
            'cpu' => [
                'load_1m' => $loadAvg[0] ?? null,
                'load_5m' => $loadAvg[1] ?? null,
                'load_15m' => $loadAvg[2] ?? null,
            ],
            'opcache' => $this->getOpcacheStats(),
        ];
    }

    /**
     * Get OPcache statistics
     *
     * @return array|null OPcache stats or null if disabled
     */
    private function getOpcacheStats(): ?array
    {
        if (!function_exists('opcache_get_status')) {
            return null;
        }
        
        $status = opcache_get_status(false);
        
        if (!$status) {
            return null;
        }
        
        return [
            'enabled' => $status['opcache_enabled'] ?? false,
            'cache_full' => $status['cache_full'] ?? false,
            'memory_used_mb' => round(($status['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 2),
            'memory_free_mb' => round(($status['memory_usage']['free_memory'] ?? 0) / 1024 / 1024, 2),
            'hit_rate' => round((($status['opcache_statistics']['hits'] ?? 0) / max(1, ($status['opcache_statistics']['hits'] ?? 0) + ($status['opcache_statistics']['misses'] ?? 0))) * 100, 2),
        ];
    }

    /**
     * Get performance dashboard data
     *
     * @param string $timeRange Time range (1h, 6h, 24h, 7d)
     * @return array Dashboard data
     */
    public function getDashboard(string $timeRange = '1h'): array
    {
        $seconds = $this->parseTimeRange($timeRange);
        $cutoff = time() - $seconds;
        
        $history = $this->getMetricsHistory($cutoff);
        
        return [
            'summary' => $this->calculateSummary($history),
            'timeline' => $this->buildTimeline($history),
            'slow_requests' => $this->getSlowRequests($history),
            'slow_queries' => $this->getSlowQueries($history),
            'bottlenecks' => $this->detectBottlenecks($history),
            'alerts' => $this->getPerformanceAlerts($cutoff),
            'time_range' => $timeRange,
        ];
    }

    /**
     * Record metrics to cache
     *
     * @param array $metrics Metrics to record
     * @return void
     */
    private function recordMetrics(array $metrics): void
    {
        $timestamp = time();
        $key = 'perf_metrics:' . $timestamp;
        
        $this->cache->set($key, [
            'timestamp' => $timestamp,
            'metrics' => $metrics,
        ], 86400); // 24 hours
        
        // Update history index
        $historyKey = 'perf_history:' . date('Y-m-d');
        $history = $this->cache->get($historyKey, []);
        $history[] = $timestamp;
        
        // Keep last 10000 entries per day
        if (count($history) > 10000) {
            $history = array_slice($history, -10000);
        }
        
        $this->cache->set($historyKey, $history, 86400);
    }

    /**
     * Get metrics history
     *
     * @param int $since Unix timestamp
     * @return array Historical metrics
     */
    private function getMetricsHistory(int $since): array
    {
        $days = (int)ceil((time() - $since) / 86400);
        $history = [];
        
        for ($i = 0; $i <= $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $historyKey = 'perf_history:' . $date;
            $timestamps = $this->cache->get($historyKey, []);
            
            foreach ($timestamps as $timestamp) {
                if ($timestamp < $since) {
                    continue;
                }
                
                $key = 'perf_metrics:' . $timestamp;
                $data = $this->cache->get($key);
                
                if ($data) {
                    $history[] = $data;
                }
            }
        }
        
        return $history;
    }

    /**
     * Calculate summary statistics
     *
     * @param array $history Metrics history
     * @return array Summary stats
     */
    private function calculateSummary(array $history): array
    {
        if (empty($history)) {
            return [
                'requests' => 0,
                'avg_duration_ms' => 0,
                'max_duration_ms' => 0,
                'slow_requests' => 0,
                'total_queries' => 0,
                'slow_queries' => 0,
            ];
        }
        
        $durations = [];
        $slowRequests = 0;
        $totalQueries = 0;
        $slowQueries = 0;
        
        foreach ($history as $entry) {
            $metrics = $entry['metrics'] ?? [];
            $duration = $metrics['request']['duration_ms'] ?? 0;
            
            $durations[] = $duration;
            
            if ($duration >= self::THRESHOLD_SLOW_REQUEST) {
                $slowRequests++;
            }
            
            $totalQueries += $metrics['queries']['total'] ?? 0;
            $slowQueries += $metrics['queries']['slow_queries'] ?? 0;
        }
        
        return [
            'requests' => count($history),
            'avg_duration_ms' => round(array_sum($durations) / max(1, count($durations)), 2),
            'median_duration_ms' => $this->calculateMedian($durations),
            'p95_duration_ms' => $this->calculatePercentile($durations, 95),
            'max_duration_ms' => !empty($durations) ? max($durations) : 0,
            'slow_requests' => $slowRequests,
            'slow_request_rate' => round(($slowRequests / max(1, count($history))) * 100, 2),
            'total_queries' => $totalQueries,
            'slow_queries' => $slowQueries,
            'slow_query_rate' => round(($slowQueries / max(1, $totalQueries)) * 100, 2),
        ];
    }

    /**
     * Build timeline data
     *
     * @param array $history Metrics history
     * @return array Timeline data points
     */
    private function buildTimeline(array $history): array
    {
        $timeline = [];
        
        foreach ($history as $entry) {
            $timestamp = $entry['timestamp'] ?? time();
            $metrics = $entry['metrics'] ?? [];
            
            $timeline[] = [
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp),
                'duration_ms' => $metrics['request']['duration_ms'] ?? 0,
                'memory_mb' => $metrics['request']['memory_peak_mb'] ?? 0,
                'queries' => $metrics['queries']['total'] ?? 0,
            ];
        }
        
        return $timeline;
    }

    /**
     * Get slow requests
     *
     * @param array $history Metrics history
     * @return array Slow requests
     */
    private function getSlowRequests(array $history): array
    {
        $slowRequests = [];
        
        foreach ($history as $entry) {
            $metrics = $entry['metrics'] ?? [];
            
            if ($metrics['request']['is_slow'] ?? false) {
                $slowRequests[] = [
                    'timestamp' => $entry['timestamp'],
                    'datetime' => date('Y-m-d H:i:s', $entry['timestamp']),
                    'duration_ms' => $metrics['request']['duration_ms'],
                    'memory_mb' => $metrics['request']['memory_peak_mb'],
                    'queries' => $metrics['queries']['total'],
                ];
            }
        }
        
        // Sort by duration (slowest first)
        usort($slowRequests, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
        
        return array_slice($slowRequests, 0, 20); // Top 20
    }

    /**
     * Get slow queries
     *
     * @param array $history Metrics history
     * @return array Slow queries
     */
    private function getSlowQueries(array $history): array
    {
        $slowQueries = [];
        
        foreach ($history as $entry) {
            $metrics = $entry['metrics'] ?? [];
            $queries = $metrics['queries']['queries'] ?? [];
            
            foreach ($queries as $query) {
                if ($query['is_slow'] ?? false) {
                    $slowQueries[] = [
                        'timestamp' => $entry['timestamp'],
                        'datetime' => date('Y-m-d H:i:s', $entry['timestamp']),
                        'query' => substr($query['query'], 0, 200),
                        'duration_ms' => $query['duration_ms'],
                    ];
                }
            }
        }
        
        // Sort by duration (slowest first)
        usort($slowQueries, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
        
        return array_slice($slowQueries, 0, 20); // Top 20
    }

    /**
     * Detect performance bottlenecks
     *
     * @param array $history Metrics history
     * @return array Detected bottlenecks
     */
    private function detectBottlenecks(array $history): array
    {
        $bottlenecks = [];
        
        // Check for high query count
        $avgQueries = 0;
        $queryCount = 0;
        
        foreach ($history as $entry) {
            $queries = $entry['metrics']['queries']['total'] ?? 0;
            $avgQueries += $queries;
            $queryCount++;
        }
        
        $avgQueries = $avgQueries / max(1, $queryCount);
        
        if ($avgQueries > 50) {
            $bottlenecks[] = [
                'type' => 'high_query_count',
                'severity' => 'warning',
                'message' => "Average query count is high: {$avgQueries} queries per request",
                'recommendation' => 'Consider query optimization, caching, or eager loading',
            ];
        }
        
        // Check for memory issues
        foreach ($history as $entry) {
            $memoryMb = $entry['metrics']['request']['memory_peak_mb'] ?? 0;
            
            if ($memoryMb > 100) {
                $bottlenecks[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'error',
                    'message' => "High memory usage detected: {$memoryMb}MB",
                    'recommendation' => 'Review memory usage patterns, check for memory leaks',
                    'timestamp' => $entry['timestamp'],
                ];
                break; // Only report once
            }
        }
        
        return $bottlenecks;
    }

    /**
     * Check for performance issues and trigger alerts
     *
     * @param array $metrics Current metrics
     * @return void
     */
    private function checkPerformanceIssues(array $metrics): void
    {
        if (!$this->alertManager) {
            return;
        }
        
        $request = $metrics['request'] ?? [];
        
        // Slow request alert
        if ($request['is_slow'] ?? false) {
            $this->alertManager->warning(
                'Slow Request Detected',
                "Request took {$request['duration_ms']}ms to complete",
                [
                    'duration_ms' => $request['duration_ms'],
                    'threshold_ms' => self::THRESHOLD_SLOW_REQUEST,
                    'memory_mb' => $request['memory_peak_mb'],
                ]
            );
        }
        
        // High memory alert
        if (($request['memory_peak'] ?? 0) > self::THRESHOLD_MEMORY_HIGH) {
            $this->alertManager->error(
                'High Memory Usage',
                "Request used {$request['memory_peak_mb']}MB of memory",
                [
                    'memory_mb' => $request['memory_peak_mb'],
                    'threshold_mb' => round(self::THRESHOLD_MEMORY_HIGH / 1024 / 1024, 2),
                ]
            );
        }
    }

    /**
     * Get performance alerts
     *
     * @param int $since Unix timestamp
     * @return array Recent alerts
     */
    private function getPerformanceAlerts(int $since): array
    {
        // This would typically query the alert history
        // For now, return empty array
        return [];
    }

    /**
     * Parse time range string to seconds
     *
     * @param string $timeRange Time range string
     * @return int Seconds
     */
    private function parseTimeRange(string $timeRange): int
    {
        return match($timeRange) {
            '5m' => 300,
            '15m' => 900,
            '1h' => 3600,
            '6h' => 21600,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000,
            default => 3600,
        };
    }

    /**
     * Calculate median of array
     *
     * @param array $values Numeric values
     * @return float Median
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $count = count($values);
        $middle = (int)floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }

    /**
     * Calculate percentile of array
     *
     * @param array $values Numeric values
     * @param int $percentile Percentile (0-100)
     * @return float Percentile value
     */
    private function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = (int)ceil((count($values) * $percentile) / 100) - 1;
        $index = max(0, min($index, count($values) - 1));
        
        return $values[$index];
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'record_all_requests' => false, // Record only slow requests by default
        ];
    }
}
