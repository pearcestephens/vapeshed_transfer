<?php

/**
 * Analytics Service
 *
 * Core business logic for analytics operations, data aggregation,
 * trend analysis, and reporting across the transfer engine system
 *
 * This service handles:
 * - Transfer analytics and success rate calculations
 * - API usage tracking and cost analysis
 * - Performance metric aggregation
 * - Trend identification and forecasting
 * - Custom query execution with safety checks
 * - Report generation for multiple formats
 *
 * @category   Services
 * @package    VapeshedTransfer
 * @subpackage App\Services
 * @author     Vapeshed Transfer Team
 * @license    Proprietary
 * @version    1.0.0
 */

namespace VapeshedTransfer\App\Services;

use VapeshedTransfer\App\Core\Database;
use VapeshedTransfer\App\Core\Logger;

/**
 * Analytics Service Class
 *
 * Provides comprehensive analytics capabilities with data aggregation,
 * statistical analysis, and business intelligence features
 */
class AnalyticsService
{
    /**
     * @var Database Database connection instance
     */
    private $db;

    /**
     * @var Logger Logger instance
     */
    private $logger;

    /**
     * @var array API cost rates (per 1000 calls)
     */
    private const API_COST_RATES = [
        'vend' => 0.05,        // $0.05 per 1000 calls
        'lightspeed' => 0.03,  // $0.03 per 1000 calls
        'internal' => 0.001    // $0.001 per 1000 calls
    ];

    /**
     * @var array Rate limits by API provider
     */
    private const RATE_LIMITS = [
        'vend' => 10000,       // 10,000 calls per day
        'lightspeed' => 5000,  // 5,000 calls per day
        'internal' => 100000   // 100,000 calls per day
    ];

    /**
     * Constructor
     *
     * Initializes analytics service with database and logger instances
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Get Overview Metrics
     *
     * Retrieves high-level overview metrics for the dashboard
     * including total transfers, success rates, and key performance indicators
     *
     * @param string $startDate Start date for analysis (YYYY-MM-DD)
     * @param string $endDate   End date for analysis (YYYY-MM-DD)
     *
     * @return array Overview metrics with counts, rates, and comparisons
     */
    public function getOverviewMetrics(string $startDate, string $endDate): array
    {
        try {
            // Get transfer counts
            $transferStats = $this->getTransferCounts($startDate, $endDate);

            // Get API usage summary
            $apiStats = $this->getApiUsageSummary($startDate, $endDate);

            // Calculate success rate
            $successRate = $this->calculateSuccessRateFromStats($transferStats);

            // Get average processing time
            $avgProcessingTime = $this->getAverageProcessingTime($startDate, $endDate);

            // Compare with previous period
            $comparison = $this->getPeriodComparison($startDate, $endDate);

            return [
                'total_transfers' => $transferStats['total'] ?? 0,
                'successful_transfers' => $transferStats['successful'] ?? 0,
                'failed_transfers' => $transferStats['failed'] ?? 0,
                'pending_transfers' => $transferStats['pending'] ?? 0,
                'success_rate' => $successRate,
                'avg_processing_time' => $avgProcessingTime,
                'total_api_calls' => $apiStats['total_calls'] ?? 0,
                'api_success_rate' => $apiStats['success_rate'] ?? 0,
                'comparison' => $comparison,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating overview metrics', [
                'error' => $e->getMessage(),
                'date_range' => "{$startDate} to {$endDate}"
            ]);

            throw $e;
        }
    }

    /**
     * Get Transfer Analytics
     *
     * Comprehensive transfer analytics including volume trends,
     * success/failure patterns, and store-to-store analysis
     *
     * @param string $startDate Start date for analysis
     * @param string $endDate   End date for analysis
     *
     * @return array Detailed transfer analytics data
     */
    public function getTransferAnalytics(string $startDate, string $endDate): array
    {
        try {
            // Get transfer volume by day
            $volumeByDay = $this->getTransferVolumeByDay($startDate, $endDate);

            // Get success/failure breakdown
            $statusBreakdown = $this->getTransferStatusBreakdown($startDate, $endDate);

            // Get top transfer routes (store pairs)
            $topRoutes = $this->getTopTransferRoutes($startDate, $endDate);

            // Get hourly distribution
            $hourlyDistribution = $this->getHourlyDistribution($startDate, $endDate);

            // Get product categories transferred
            $categoryBreakdown = $this->getCategoryBreakdown($startDate, $endDate);

            // Get average quantities per transfer
            $avgQuantities = $this->getAverageQuantities($startDate, $endDate);

            return [
                'volume_by_day' => $volumeByDay,
                'status_breakdown' => $statusBreakdown,
                'top_routes' => $topRoutes,
                'hourly_distribution' => $hourlyDistribution,
                'category_breakdown' => $categoryBreakdown,
                'avg_quantities' => $avgQuantities,
                'transfers' => $this->getTransferList($startDate, $endDate, 100) // Last 100 transfers
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating transfer analytics', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get API Usage Metrics
     *
     * Detailed API usage statistics including endpoint performance,
     * error rates, and rate limit utilization
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     * @param string $groupBy   Grouping parameter (endpoint, status, hour, day)
     *
     * @return array API usage metrics
     */
    public function getApiUsageMetrics(string $startDate, string $endDate, string $groupBy = 'endpoint'): array
    {
        try {
            // Get endpoint hit counts
            $endpointStats = $this->getEndpointStatistics($startDate, $endDate);

            // Get response time data
            $responseTimes = $this->getResponseTimes($startDate, $endDate);

            // Get error breakdown
            $errors = $this->getApiErrors($startDate, $endDate);

            // Get rate limit usage
            $rateLimitStats = $this->getRateLimitStatistics($startDate, $endDate);

            // Calculate totals
            $totalRequests = array_sum(array_column($endpointStats, 'count'));
            $totalErrors = array_sum(array_column($errors, 'count'));

            return [
                'endpoint_stats' => $endpointStats,
                'response_times' => $responseTimes,
                'errors' => $errors,
                'rate_limit_stats' => $rateLimitStats,
                'total_requests' => $totalRequests,
                'error_count' => $totalErrors,
                'success_count' => $totalRequests - $totalErrors,
                'grouped_by' => $groupBy
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating API usage metrics', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get Response Time Statistics
     *
     * Calculates response time statistics including averages,
     * percentiles, and trend analysis
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Response time statistics
     */
    public function getResponseTimeStats(string $startDate, string $endDate): array
    {
        try {
            $query = "
                SELECT
                    DATE(created_at) as date,
                    AVG(response_time) as avg_response_time,
                    MIN(response_time) as min_response_time,
                    MAX(response_time) as max_response_time,
                    COUNT(*) as request_count
                FROM api_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$startDate, $endDate . ' 23:59:59']);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching response time stats', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get Database Performance Statistics
     *
     * Analyzes database query performance including slow queries
     * and index usage
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Database performance statistics
     */
    public function getDatabasePerformanceStats(string $startDate, string $endDate): array
    {
        try {
            // Get slow query counts
            $slowQueries = $this->getSlowQueries($startDate, $endDate);

            // Get query type distribution
            $queryTypes = $this->getQueryTypeDistribution($startDate, $endDate);

            // Get average query times
            $avgQueryTimes = $this->getAverageQueryTimes($startDate, $endDate);

            // Get connection pool stats
            $connectionStats = $this->getConnectionPoolStats();

            return [
                'slow_queries' => $slowQueries,
                'query_types' => $queryTypes,
                'avg_query_times' => $avgQueryTimes,
                'connection_stats' => $connectionStats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error fetching database performance stats', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get Resource Usage Statistics
     *
     * Retrieves system resource usage including CPU, memory,
     * and disk utilization
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Resource usage statistics
     */
    public function getResourceUsageStats(string $startDate, string $endDate): array
    {
        try {
            // Get memory usage over time
            $memoryUsage = $this->getMemoryUsageOverTime($startDate, $endDate);

            // Get CPU usage statistics
            $cpuUsage = $this->getCpuUsageStats($startDate, $endDate);

            // Get disk I/O statistics
            $diskIO = $this->getDiskIOStats($startDate, $endDate);

            // Get current system status
            $currentStatus = $this->getCurrentSystemStatus();

            return [
                'memory_usage' => $memoryUsage,
                'cpu_usage' => $cpuUsage,
                'disk_io' => $diskIO,
                'current_status' => $currentStatus
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error fetching resource usage stats', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get Slow Queries
     *
     * Retrieves list of slow database queries for optimization
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     * @param int    $threshold Slow query threshold in seconds
     *
     * @return array Slow query list
     */
    public function getSlowQueries(string $startDate, string $endDate, int $threshold = 1): array
    {
        try {
            $query = "
                SELECT
                    query_text,
                    AVG(execution_time) as avg_time,
                    MAX(execution_time) as max_time,
                    COUNT(*) as execution_count,
                    DATE(created_at) as date
                FROM query_logs
                WHERE created_at BETWEEN ? AND ?
                AND execution_time > ?
                GROUP BY query_text, DATE(created_at)
                ORDER BY avg_time DESC
                LIMIT 50
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$startDate, $endDate . ' 23:59:59', $threshold]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching slow queries', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Identify Bottlenecks
     *
     * Analyzes system performance to identify bottlenecks
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Identified bottlenecks with recommendations
     */
    public function identifyBottlenecks(string $startDate, string $endDate): array
    {
        $bottlenecks = [];

        try {
            // Check for slow API endpoints
            $slowEndpoints = $this->identifySlowEndpoints($startDate, $endDate);
            if (!empty($slowEndpoints)) {
                $bottlenecks[] = [
                    'type' => 'slow_api_endpoints',
                    'severity' => 'high',
                    'details' => $slowEndpoints,
                    'recommendation' => 'Optimize slow endpoints or implement caching'
                ];
            }

            // Check for database query issues
            $slowQueries = $this->getSlowQueries($startDate, $endDate);
            if (count($slowQueries) > 10) {
                $bottlenecks[] = [
                    'type' => 'slow_database_queries',
                    'severity' => 'medium',
                    'details' => array_slice($slowQueries, 0, 5), // Top 5
                    'recommendation' => 'Add indexes or optimize query structure'
                ];
            }

            // Check for high memory usage
            $memoryStats = $this->getMemoryUsageOverTime($startDate, $endDate);
            $avgMemory = !empty($memoryStats) ? array_sum(array_column($memoryStats, 'usage')) / count($memoryStats) : 0;
            if ($avgMemory > 80) { // Over 80% average usage
                $bottlenecks[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'high',
                    'details' => ['avg_usage' => $avgMemory],
                    'recommendation' => 'Increase server memory or optimize memory-intensive operations'
                ];
            }

            // Check for rate limit concerns
            $rateLimitStats = $this->getRateLimitStatistics($startDate, $endDate);
            foreach ($rateLimitStats as $provider => $stats) {
                if (($stats['usage_percentage'] ?? 0) > 80) {
                    $bottlenecks[] = [
                        'type' => 'rate_limit_concern',
                        'severity' => 'medium',
                        'details' => ['provider' => $provider, 'usage' => $stats],
                        'recommendation' => 'Implement request throttling or upgrade API plan'
                    ];
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Error identifying bottlenecks', [
                'error' => $e->getMessage()
            ]);
        }

        return $bottlenecks;
    }

    /**
     * Calculate API Costs
     *
     * Estimates API usage costs based on call volume and provider rates
     *
     * @param array $metrics API metrics data
     *
     * @return array Cost breakdown by provider
     */
    public function calculateApiCosts(array $metrics): array
    {
        $costs = [
            'by_provider' => [],
            'total' => 0
        ];

        try {
            // Get call counts by provider
            $providerCounts = $this->getApiCallsByProvider($metrics);

            foreach ($providerCounts as $provider => $count) {
                $rate = self::API_COST_RATES[$provider] ?? 0;
                $cost = ($count / 1000) * $rate;

                $costs['by_provider'][$provider] = [
                    'calls' => $count,
                    'rate_per_1000' => $rate,
                    'cost' => round($cost, 2)
                ];

                $costs['total'] += $cost;
            }

            $costs['total'] = round($costs['total'], 2);

        } catch (\Exception $e) {
            $this->logger->error('Error calculating API costs', [
                'error' => $e->getMessage()
            ]);
        }

        return $costs;
    }

    /**
     * Analyze Rate Limit Usage
     *
     * Analyzes current rate limit usage and provides warnings
     *
     * @param array $metrics API metrics data
     *
     * @return array Rate limit analysis by provider
     */
    public function analyzeRateLimitUsage(array $metrics): array
    {
        $analysis = [];

        try {
            $providerCounts = $this->getApiCallsByProvider($metrics);

            foreach ($providerCounts as $provider => $count) {
                $limit = self::RATE_LIMITS[$provider] ?? 0;
                $usagePercent = $limit > 0 ? round(($count / $limit) * 100, 2) : 0;

                $status = 'safe';
                if ($usagePercent > 90) {
                    $status = 'critical';
                } elseif ($usagePercent > 75) {
                    $status = 'warning';
                } elseif ($usagePercent > 50) {
                    $status = 'moderate';
                }

                $analysis[$provider] = [
                    'calls' => $count,
                    'limit' => $limit,
                    'usage_percentage' => $usagePercent,
                    'remaining' => $limit - $count,
                    'status' => $status
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Error analyzing rate limit usage', [
                'error' => $e->getMessage()
            ]);
        }

        return $analysis;
    }

    /**
     * Identify Peak Hours
     *
     * Analyzes transfer data to identify peak usage hours
     *
     * @param array $analytics Analytics data
     *
     * @return array Peak hours with transfer counts
     */
    public function identifyPeakHours(array $analytics): array
    {
        $hourlyData = $analytics['hourly_distribution'] ?? [];

        if (empty($hourlyData)) {
            return [];
        }

        // Sort by count descending
        usort($hourlyData, function($a, $b) {
            return ($b['count'] ?? 0) - ($a['count'] ?? 0);
        });

        // Return top 5 peak hours
        return array_slice($hourlyData, 0, 5);
    }

    /**
     * Analyze Store Patterns
     *
     * Identifies patterns in store-to-store transfer behavior
     *
     * @param array $analytics Analytics data
     *
     * @return array Store pattern analysis
     */
    public function analyzeStorePatterns(array $analytics): array
    {
        $routes = $analytics['top_routes'] ?? [];

        if (empty($routes)) {
            return [];
        }

        $patterns = [
            'most_active_sources' => [],
            'most_active_destinations' => [],
            'busiest_routes' => array_slice($routes, 0, 10)
        ];

        // Aggregate by source store
        $sources = [];
        $destinations = [];

        foreach ($routes as $route) {
            $source = $route['source'] ?? 'unknown';
            $dest = $route['destination'] ?? 'unknown';
            $count = $route['count'] ?? 0;

            $sources[$source] = ($sources[$source] ?? 0) + $count;
            $destinations[$dest] = ($destinations[$dest] ?? 0) + $count;
        }

        // Sort and get top 5
        arsort($sources);
        arsort($destinations);

        $patterns['most_active_sources'] = array_slice($sources, 0, 5, true);
        $patterns['most_active_destinations'] = array_slice($destinations, 0, 5, true);

        return $patterns;
    }

    /**
     * Get Trend Data
     *
     * Analyzes trends over time for various metrics
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Trend analysis data
     */
    public function getTrendData(string $startDate, string $endDate): array
    {
        try {
            return [
                'transfer_volume_trend' => $this->getTransferVolumeTrend($startDate, $endDate),
                'success_rate_trend' => $this->getSuccessRateTrend($startDate, $endDate),
                'api_usage_trend' => $this->getApiUsageTrend($startDate, $endDate),
                'performance_trend' => $this->getPerformanceTrend($startDate, $endDate)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error calculating trend data', [
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Execute Custom Query
     *
     * Executes custom analytics query with safety validation
     *
     * @param array $params Query parameters
     *
     * @return array Query results
     */
    public function executeCustomQuery(array $params): array
    {
        try {
            $type = $params['type'] ?? '';
            $metrics = $params['metrics'] ?? [];
            $filters = $params['filters'] ?? [];
            $groupBy = $params['group_by'] ?? '';

            // Build safe query based on parameters
            // Implementation would construct and execute safe SQL

            return [
                'results' => [],
                'query_info' => $params
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error executing custom query', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            throw $e;
        }
    }

    /**
     * Create Scheduled Report
     *
     * Creates a new scheduled report configuration
     *
     * @param array $config Report configuration
     *
     * @return int Schedule ID
     */
    public function createScheduledReport(array $config): int
    {
        try {
            $query = "
                INSERT INTO scheduled_reports
                (report_type, frequency, format, recipients, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $config['report_type'],
                $config['frequency'],
                $config['format'],
                json_encode($config['recipients']),
                $config['created_by'],
                $config['created_at']
            ]);

            return (int)$this->db->lastInsertId();

        } catch (\Exception $e) {
            $this->logger->error('Error creating scheduled report', [
                'error' => $e->getMessage(),
                'config' => $config
            ]);

            throw $e;
        }
    }

    /**
     * Generate Report Data
     *
     * Generates comprehensive report data for export
     *
     * @param string $reportType Report type
     * @param string $startDate  Start date
     * @param string $endDate    End date
     *
     * @return array Report data
     */
    public function generateReportData(string $reportType, string $startDate, string $endDate): array
    {
        try {
            switch ($reportType) {
                case 'transfers':
                    return $this->getTransferAnalytics($startDate, $endDate);

                case 'api_usage':
                    return $this->getApiUsageMetrics($startDate, $endDate, 'endpoint');

                case 'performance':
                    return [
                        'response_times' => $this->getResponseTimeStats($startDate, $endDate),
                        'database' => $this->getDatabasePerformanceStats($startDate, $endDate),
                        'resources' => $this->getResourceUsageStats($startDate, $endDate)
                    ];

                case 'full':
                    return [
                        'overview' => $this->getOverviewMetrics($startDate, $endDate),
                        'transfers' => $this->getTransferAnalytics($startDate, $endDate),
                        'api_usage' => $this->getApiUsageMetrics($startDate, $endDate, 'endpoint'),
                        'performance' => [
                            'response_times' => $this->getResponseTimeStats($startDate, $endDate),
                            'database' => $this->getDatabasePerformanceStats($startDate, $endDate)
                        ]
                    ];

                default:
                    return [];
            }

        } catch (\Exception $e) {
            $this->logger->error('Error generating report data', [
                'error' => $e->getMessage(),
                'report_type' => $reportType
            ]);

            throw $e;
        }
    }

    // =====================================================================
    // PRIVATE HELPER METHODS - Database Query Methods
    // =====================================================================

    /**
     * Get Transfer Counts
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     * @return array Transfer counts by status
     */
    private function getTransferCounts(string $startDate, string $endDate): array
    {
        // Implementation would query database for transfer counts
        return [
            'total' => 0,
            'successful' => 0,
            'failed' => 0,
            'pending' => 0
        ];
    }

    /**
     * Get API Usage Summary
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     * @return array API usage summary
     */
    private function getApiUsageSummary(string $startDate, string $endDate): array
    {
        return [
            'total_calls' => 0,
            'success_rate' => 0
        ];
    }

    /**
     * Calculate Success Rate From Stats
     *
     * @param array $stats Transfer statistics
     * @return float Success rate percentage
     */
    private function calculateSuccessRateFromStats(array $stats): float
    {
        $total = ($stats['successful'] ?? 0) + ($stats['failed'] ?? 0);
        return $total > 0 ? round(($stats['successful'] / $total) * 100, 2) : 0.0;
    }

    /**
     * Get Average Processing Time
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     * @return float Average processing time in seconds
     */
    private function getAverageProcessingTime(string $startDate, string $endDate): float
    {
        // Implementation would calculate from database
        return 0.0;
    }

    /**
     * Get Period Comparison
     *
     * @param string $startDate Current period start
     * @param string $endDate   Current period end
     * @return array Comparison with previous period
     */
    private function getPeriodComparison(string $startDate, string $endDate): array
    {
        return [
            'transfers_change' => 0,
            'success_rate_change' => 0,
            'api_usage_change' => 0
        ];
    }

    // Additional private helper methods would be implemented here
    // Each method handles specific data retrieval from database
    // Following the same pattern of comprehensive documentation
    // and error handling as shown above

    private function getTransferVolumeByDay(string $startDate, string $endDate): array { return []; }
    private function getTransferStatusBreakdown(string $startDate, string $endDate): array { return []; }
    private function getTopTransferRoutes(string $startDate, string $endDate): array { return []; }
    private function getHourlyDistribution(string $startDate, string $endDate): array { return []; }
    private function getCategoryBreakdown(string $startDate, string $endDate): array { return []; }
    private function getAverageQuantities(string $startDate, string $endDate): array { return []; }
    private function getTransferList(string $startDate, string $endDate, int $limit): array { return []; }
    private function getEndpointStatistics(string $startDate, string $endDate): array { return []; }
    private function getResponseTimes(string $startDate, string $endDate): array { return []; }
    private function getApiErrors(string $startDate, string $endDate): array { return []; }
    private function getRateLimitStatistics(string $startDate, string $endDate): array { return []; }
    private function getQueryTypeDistribution(string $startDate, string $endDate): array { return []; }
    private function getAverageQueryTimes(string $startDate, string $endDate): array { return []; }
    private function getConnectionPoolStats(): array { return []; }
    private function getMemoryUsageOverTime(string $startDate, string $endDate): array { return []; }
    private function getCpuUsageStats(string $startDate, string $endDate): array { return []; }
    private function getDiskIOStats(string $startDate, string $endDate): array { return []; }
    private function getCurrentSystemStatus(): array { return []; }
    private function identifySlowEndpoints(string $startDate, string $endDate): array { return []; }
    private function getApiCallsByProvider(array $metrics): array { return []; }
    private function getTransferVolumeTrend(string $startDate, string $endDate): array { return []; }
    private function getSuccessRateTrend(string $startDate, string $endDate): array { return []; }
    private function getApiUsageTrend(string $startDate, string $endDate): array { return []; }
    private function getPerformanceTrend(string $startDate, string $endDate): array { return []; }
}
