<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * DatabaseProfiler.php - Query Performance Profiler
 * 
 * Profile database queries for performance monitoring and optimization.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class DatabaseProfiler
{
    private static array $queries = [];
    private static bool $enabled = false;
    private static float $slowQueryThreshold = 1.0; // seconds
    
    /**
     * Enable profiling
     * 
     * @param float $slowQueryThreshold Threshold for slow query logging (seconds)
     */
    public static function enable(float $slowQueryThreshold = 1.0): void
    {
        self::$enabled = true;
        self::$slowQueryThreshold = $slowQueryThreshold;
    }
    
    /**
     * Disable profiling
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }
    
    /**
     * Check if profiling is enabled
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
    
    /**
     * Start profiling a query
     * 
     * @param string $sql SQL query
     * @param array $bindings Query bindings
     * @return string Query ID
     */
    public static function start(string $sql, array $bindings = []): string
    {
        if (!self::$enabled) {
            return '';
        }
        
        $queryId = uniqid('query_', true);
        
        self::$queries[$queryId] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ];
        
        return $queryId;
    }
    
    /**
     * End profiling a query
     * 
     * @param string $queryId Query ID from start()
     * @param int $rowCount Number of rows affected/returned
     */
    public static function end(string $queryId, int $rowCount = 0): void
    {
        if (!self::$enabled || !isset(self::$queries[$queryId])) {
            return;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        self::$queries[$queryId]['end_time'] = $endTime;
        self::$queries[$queryId]['end_memory'] = $endMemory;
        self::$queries[$queryId]['duration'] = $endTime - self::$queries[$queryId]['start_time'];
        self::$queries[$queryId]['memory_delta'] = $endMemory - self::$queries[$queryId]['start_memory'];
        self::$queries[$queryId]['row_count'] = $rowCount;
        
        // Log slow queries
        if (self::$queries[$queryId]['duration'] >= self::$slowQueryThreshold) {
            self::logSlowQuery($queryId);
        }
    }
    
    /**
     * Get all profiled queries
     * 
     * @return array Queries
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }
    
    /**
     * Get query statistics
     * 
     * @return array Statistics
     */
    public static function getStats(): array
    {
        $totalQueries = count(self::$queries);
        
        if ($totalQueries === 0) {
            return [
                'total_queries' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'max_time' => 0,
                'min_time' => 0,
                'slow_queries' => 0,
            ];
        }
        
        $times = array_column(self::$queries, 'duration');
        $totalTime = array_sum($times);
        $slowQueries = count(array_filter($times, fn($t) => $t >= self::$slowQueryThreshold));
        
        return [
            'total_queries' => $totalQueries,
            'total_time' => round($totalTime, 4),
            'avg_time' => round($totalTime / $totalQueries, 4),
            'max_time' => round(max($times), 4),
            'min_time' => round(min($times), 4),
            'slow_queries' => $slowQueries,
            'slow_query_threshold' => self::$slowQueryThreshold,
        ];
    }
    
    /**
     * Get slow queries
     * 
     * @return array Slow queries
     */
    public static function getSlowQueries(): array
    {
        return array_filter(
            self::$queries,
            fn($q) => isset($q['duration']) && $q['duration'] >= self::$slowQueryThreshold
        );
    }
    
    /**
     * Clear all profiled queries
     */
    public static function clear(): void
    {
        self::$queries = [];
    }
    
    /**
     * Log slow query
     * 
     * @param string $queryId Query ID
     */
    private static function logSlowQuery(string $queryId): void
    {
        $query = self::$queries[$queryId];
        
        $logger = new Logger('slow_queries');
        $logger->warn('Slow query detected', [
            'neuro' => [
                'namespace' => 'unified',
                'system' => 'vapeshed_transfer',
                'component' => 'database',
                'profiler' => 'query_performance',
            ],
            'sql' => $query['sql'],
            'duration' => round($query['duration'], 4),
            'row_count' => $query['row_count'] ?? 0,
            'memory_delta_mb' => round(($query['memory_delta'] ?? 0) / 1024 / 1024, 2),
            'caller' => self::formatCaller($query['backtrace'] ?? []),
            'threshold' => self::$slowQueryThreshold,
        ]);
    }
    
    /**
     * Format caller from backtrace
     * 
     * @param array $backtrace Backtrace
     * @return string Formatted caller
     */
    private static function formatCaller(array $backtrace): string
    {
        foreach ($backtrace as $trace) {
            if (isset($trace['file']) && isset($trace['line'])) {
                $file = basename($trace['file']);
                return sprintf('%s:%d', $file, $trace['line']);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get queries by execution time (sorted descending)
     * 
     * @param int $limit Number of queries to return
     * @return array Top queries
     */
    public static function getTopQueries(int $limit = 10): array
    {
        $queries = self::$queries;
        
        usort($queries, function($a, $b) {
            $durationA = $a['duration'] ?? 0;
            $durationB = $b['duration'] ?? 0;
            return $durationB <=> $durationA;
        });
        
        return array_slice($queries, 0, $limit);
    }
    
    /**
     * Export profile data for analysis
     * 
     * @return array Export data
     */
    public static function export(): array
    {
        return [
            'stats' => self::getStats(),
            'queries' => self::$queries,
            'slow_queries' => self::getSlowQueries(),
            'top_queries' => self::getTopQueries(),
            'timestamp' => time(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
    }
}
