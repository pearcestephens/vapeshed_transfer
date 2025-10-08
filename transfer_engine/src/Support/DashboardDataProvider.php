<?php
/**
 * DashboardDataProvider.php - Real-time Dashboard Data Aggregation Service
 * 
 * Aggregates data from multiple sources to provide unified dashboard views
 * with real-time updates, caching, and performance optimization.
 * 
 * Features:
 * - Multi-source data aggregation (metrics, health, alerts, performance)
 * - Real-time data updates with SSE support
 * - Intelligent caching with TTL
 * - Dashboard widget data providers
 * - KPI calculations
 * - Trend indicators
 * - Comparison periods
 * - Data transformation and formatting
 * - Performance optimization
 * - Concurrent request handling
 * 
 * @package VapeshedTransfer
 * @subpackage Support
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace Unified\Support;

use Unified\Support\Logger;
use Unified\Support\NeuroContext;
use Unified\Support\MetricsCollector;
use Unified\Support\HealthMonitor;
use Unified\Support\PerformanceProfiler;
use Unified\Support\AlertManager;
use Unified\Support\AnalyticsEngine;
use Unified\Support\CacheManager;

class DashboardDataProvider
{
    private Logger $logger;
    private MetricsCollector $metrics;
    private HealthMonitor $healthMonitor;
    private PerformanceProfiler $profiler;
    private AlertManager $alertManager;
    private AnalyticsEngine $analytics;
    private CacheManager $cache;
    private array $config;

    /**
     * Initialize DashboardDataProvider
     *
     * @param Logger $logger Logger instance
     * @param MetricsCollector $metrics Metrics collector
     * @param HealthMonitor $healthMonitor Health monitor
     * @param PerformanceProfiler $profiler Performance profiler
     * @param AlertManager $alertManager Alert manager
     * @param AnalyticsEngine $analytics Analytics engine
     * @param CacheManager $cache Cache manager
     * @param array $config Configuration options
     */
    public function __construct(
        Logger $logger,
        MetricsCollector $metrics,
        HealthMonitor $healthMonitor,
        PerformanceProfiler $profiler,
        AlertManager $alertManager,
        AnalyticsEngine $analytics,
        CacheManager $cache,
        array $config = []
    ) {
        $this->logger = $logger;
        $this->metrics = $metrics;
        $this->healthMonitor = $healthMonitor;
        $this->profiler = $profiler;
        $this->alertManager = $alertManager;
        $this->analytics = $analytics;
        $this->cache = $cache;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get complete dashboard data
     *
     * @param array $options Dashboard options (refresh, widgets, period)
     * @return array Complete dashboard data
     */
    public function getDashboard(array $options = []): array
    {
        $startTime = microtime(true);
        $refresh = $options['refresh'] ?? false;
        $cacheKey = 'dashboard:main:' . md5(json_encode($options));
        
        // Check cache unless refresh requested
        if (!$refresh && $cached = $this->cache->get($cacheKey)) {
            $this->logger->debug('Dashboard served from cache', NeuroContext::wrap('dashboard_provider', [
                'cache_key' => $cacheKey,
            ]));
            
            return $cached;
        }
        
        // Build dashboard data
        $data = [
            'overview' => $this->getOverview($options),
            'health' => $this->getHealthWidget($options),
            'performance' => $this->getPerformanceWidget($options),
            'alerts' => $this->getAlertsWidget($options),
            'metrics' => $this->getMetricsWidget($options),
            'recent_activity' => $this->getRecentActivity($options),
            'trends' => $this->getTrends($options),
            'generated_at' => date('c'),
            'generation_time_ms' => 0, // Will be updated below
        ];
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $data['generation_time_ms'] = $duration;
        
        // Cache for configured TTL
        $this->cache->set($cacheKey, $data, $this->config['cache_ttl']);
        
        $this->logger->info('Dashboard generated', NeuroContext::wrap('dashboard_provider', [
            'duration_ms' => $duration,
            'widgets' => count($data),
            'cached' => true,
        ]));
        
        return $data;
    }

    /**
     * Get overview widget data
     *
     * @param array $options Widget options
     * @return array Overview data
     */
    public function getOverview(array $options = []): array
    {
        $period = $options['period'] ?? '24h';
        
        // Get current status from all systems
        $health = $this->healthMonitor->check(true);
        $perfDashboard = $this->profiler->getDashboard($period);
        $alertStats = $this->alertManager->getStats(1);
        
        // Calculate KPIs
        $totalRequests = $perfDashboard['summary']['requests'] ?? 0;
        $avgResponseTime = $perfDashboard['summary']['avg_duration_ms'] ?? 0;
        $errorRate = isset($perfDashboard['summary']['errors'], $perfDashboard['summary']['requests'])
            ? ($perfDashboard['summary']['errors'] / max($perfDashboard['summary']['requests'], 1)) * 100
            : 0;
        
        return [
            'system_status' => $health['status'],
            'system_health_score' => $this->calculateHealthScore($health),
            'uptime_percent' => $health['uptime_percent'] ?? 0,
            'kpis' => [
                'total_requests' => [
                    'value' => $totalRequests,
                    'label' => 'Total Requests',
                    'icon' => 'activity',
                    'change' => $this->calculateChange('requests', $period),
                ],
                'avg_response_time' => [
                    'value' => $avgResponseTime,
                    'label' => 'Avg Response (ms)',
                    'icon' => 'clock',
                    'change' => $this->calculateChange('response_time', $period),
                ],
                'error_rate' => [
                    'value' => round($errorRate, 2),
                    'label' => 'Error Rate (%)',
                    'icon' => 'alert-triangle',
                    'change' => $this->calculateChange('error_rate', $period),
                    'severity' => $errorRate > 5 ? 'critical' : ($errorRate > 2 ? 'warning' : 'normal'),
                ],
                'active_alerts' => [
                    'value' => $alertStats['total'] ?? 0,
                    'label' => 'Active Alerts',
                    'icon' => 'bell',
                    'change' => null,
                ],
            ],
            'period' => $period,
        ];
    }

    /**
     * Get health widget data
     *
     * @param array $options Widget options
     * @return array Health widget data
     */
    public function getHealthWidget(array $options = []): array
    {
        $hours = $this->periodToHours($options['period'] ?? '24h');
        
        $currentHealth = $this->healthMonitor->check(true);
        $trends = $this->healthMonitor->getTrends($hours);
        
        return [
            'current_status' => $currentHealth['status'],
            'checks' => array_map(function($check) {
                return [
                    'name' => $check['name'],
                    'status' => $check['status'],
                    'message' => $check['message'] ?? null,
                    'last_check' => $check['last_check'] ?? null,
                ];
            }, $currentHealth['checks']),
            'uptime_percent' => $trends['uptime_percent'],
            'mtbf_minutes' => $trends['mtbf'],
            'mttr_minutes' => $trends['mttr'],
            'incidents' => [
                'degraded' => $trends['degraded_count'],
                'unhealthy' => $trends['unhealthy_count'],
                'critical' => $trends['critical_count'],
            ],
        ];
    }

    /**
     * Get performance widget data
     *
     * @param array $options Widget options
     * @return array Performance widget data
     */
    public function getPerformanceWidget(array $options = []): array
    {
        $period = $options['period'] ?? '24h';
        $dashboard = $this->profiler->getDashboard($period);
        
        return [
            'summary' => $dashboard['summary'],
            'timeline' => array_slice($dashboard['timeline'], -24), // Last 24 points
            'slow_requests' => array_slice($dashboard['slow_requests'], 0, 5),
            'slow_queries' => array_slice($dashboard['slow_queries'], 0, 5),
            'bottlenecks' => array_slice($dashboard['bottlenecks'], 0, 3),
        ];
    }

    /**
     * Get alerts widget data
     *
     * @param array $options Widget options
     * @return array Alerts widget data
     */
    public function getAlertsWidget(array $options = []): array
    {
        $days = $this->periodToDays($options['period'] ?? '24h');
        $stats = $this->alertManager->getStats($days);
        
        return [
            'total' => $stats['total'],
            'by_severity' => $stats['by_severity'],
            'recent' => $stats['recent'] ?? [],
            'timeline' => $stats['by_day'] ?? [],
        ];
    }

    /**
     * Get metrics widget data
     *
     * @param array $options Widget options
     * @return array Metrics widget data
     */
    public function getMetricsWidget(array $options = []): array
    {
        $period = $options['period'] ?? '24h';
        list($start, $end) = $this->parsePeriod($period);
        
        $metricNames = $options['metrics'] ?? [
            'transfer.success_rate',
            'transfer.total_items',
            'system.cpu_usage',
            'system.memory_usage',
        ];
        
        $metricsData = [];
        
        foreach ($metricNames as $name) {
            $stats = $this->metrics->getStats($name, $start, $end);
            $timeline = $this->metrics->query($name, $start, $end);
            
            if ($stats) {
                $metricsData[$name] = [
                    'name' => $name,
                    'current' => $stats['avg'],
                    'min' => $stats['min'],
                    'max' => $stats['max'],
                    'avg' => $stats['avg'],
                    'count' => $stats['count'],
                    'timeline' => array_slice($timeline['points'] ?? [], -24),
                ];
            }
        }
        
        return [
            'metrics' => $metricsData,
            'period' => $period,
        ];
    }

    /**
     * Get recent activity data
     *
     * @param array $options Widget options
     * @return array Recent activity data
     */
    public function getRecentActivity(array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        
        // Collect recent events from multiple sources
        $activities = [];
        
        // Recent alerts
        $alertStats = $this->alertManager->getStats(1);
        foreach (($alertStats['recent'] ?? []) as $alert) {
            $activities[] = [
                'type' => 'alert',
                'severity' => $alert['severity'] ?? 'info',
                'message' => $alert['message'] ?? 'Alert triggered',
                'timestamp' => $alert['timestamp'] ?? time(),
            ];
        }
        
        // Recent performance issues
        $perfDashboard = $this->profiler->getDashboard('1h');
        foreach (array_slice($perfDashboard['slow_requests'] ?? [], 0, 3) as $req) {
            $activities[] = [
                'type' => 'performance',
                'severity' => 'warning',
                'message' => "Slow request: {$req['endpoint']} ({$req['duration_ms']}ms)",
                'timestamp' => $req['timestamp'] ?? time(),
            ];
        }
        
        // Sort by timestamp descending
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        
        return [
            'activities' => array_slice($activities, 0, $limit),
            'total' => count($activities),
        ];
    }

    /**
     * Get trends data
     *
     * @param array $options Widget options
     * @return array Trends data
     */
    public function getTrends(array $options = []): array
    {
        $period = $options['period'] ?? '7d';
        list($start, $end) = $this->parsePeriod($period);
        
        // Get metrics for trend analysis
        $metricNames = [
            'transfer.success_rate',
            'transfer.total_items',
            'system.response_time',
        ];
        
        $trends = [];
        
        foreach ($metricNames as $name) {
            $result = $this->metrics->query($name, $start, $end);
            
            if (!empty($result['points'])) {
                $data = array_map(function($point) {
                    return [
                        'timestamp' => $point['timestamp'],
                        'value' => $point['value'],
                    ];
                }, $result['points']);
                
                $trendAnalysis = $this->analytics->analyzeTrend($data, AnalyticsEngine::TREND_LINEAR);
                
                $trends[$name] = [
                    'name' => $name,
                    'direction' => $trendAnalysis['direction'],
                    'strength' => $trendAnalysis['strength'],
                    'slope' => $trendAnalysis['slope'] ?? 0,
                    'r_squared' => $trendAnalysis['r_squared'] ?? 0,
                ];
            }
        }
        
        return [
            'trends' => $trends,
            'period' => $period,
        ];
    }

    /**
     * Get widget data by name
     *
     * @param string $widgetName Widget identifier
     * @param array $options Widget options
     * @return array Widget data
     */
    public function getWidget(string $widgetName, array $options = []): array
    {
        $startTime = microtime(true);
        
        $data = match($widgetName) {
            'overview' => $this->getOverview($options),
            'health' => $this->getHealthWidget($options),
            'performance' => $this->getPerformanceWidget($options),
            'alerts' => $this->getAlertsWidget($options),
            'metrics' => $this->getMetricsWidget($options),
            'activity' => $this->getRecentActivity($options),
            'trends' => $this->getTrends($options),
            default => throw new \InvalidArgumentException("Unknown widget: {$widgetName}"),
        };
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->debug('Widget data generated', NeuroContext::wrap('dashboard_provider', [
            'widget' => $widgetName,
            'duration_ms' => $duration,
        ]));
        
        return array_merge($data, [
            'widget_name' => $widgetName,
            'generated_at' => date('c'),
            'generation_time_ms' => $duration,
        ]);
    }

    /**
     * Stream dashboard updates (SSE compatible)
     *
     * @param array $options Stream options
     * @return \Generator Generator yielding updates
     */
    public function streamUpdates(array $options = []): \Generator
    {
        $interval = $options['interval'] ?? 5; // seconds
        $widgets = $options['widgets'] ?? ['overview', 'health', 'performance'];
        
        while (true) {
            $updates = [];
            
            foreach ($widgets as $widget) {
                try {
                    $updates[$widget] = $this->getWidget($widget, ['refresh' => true]);
                } catch (\Exception $e) {
                    $this->logger->error('Widget update failed', NeuroContext::wrap('dashboard_provider', [
                        'widget' => $widget,
                        'error' => $e->getMessage(),
                    ]));
                }
            }
            
            yield [
                'event' => 'dashboard_update',
                'data' => json_encode([
                    'widgets' => $updates,
                    'timestamp' => time(),
                ]),
            ];
            
            sleep($interval);
        }
    }

    /**
     * Calculate health score from health check results
     *
     * @param array $health Health check results
     * @return int Health score (0-100)
     */
    private function calculateHealthScore(array $health): int
    {
        $checks = $health['checks'] ?? [];
        if (empty($checks)) {
            return 0;
        }
        
        $weights = [
            'healthy' => 100,
            'degraded' => 60,
            'unhealthy' => 20,
            'critical' => 0,
        ];
        
        $totalWeight = 0;
        foreach ($checks as $check) {
            $status = $check['status'] ?? 'unknown';
            $totalWeight += $weights[$status] ?? 0;
        }
        
        return (int) round($totalWeight / count($checks));
    }

    /**
     * Calculate change percentage for a metric
     *
     * @param string $metric Metric name
     * @param string $period Period
     * @return array|null Change data or null
     */
    private function calculateChange(string $metric, string $period): ?array
    {
        list($start, $end) = $this->parsePeriod($period);
        $duration = $end - $start;
        
        // Get current period data
        $currentStart = $start;
        $currentEnd = $end;
        
        // Get previous period data (same duration)
        $previousStart = $start - $duration;
        $previousEnd = $start;
        
        // Map metric names
        $metricMap = [
            'requests' => 'http.requests',
            'response_time' => 'http.response_time',
            'error_rate' => 'http.error_rate',
        ];
        
        $metricName = $metricMap[$metric] ?? $metric;
        
        $currentStats = $this->metrics->getStats($metricName, $currentStart, $currentEnd);
        $previousStats = $this->metrics->getStats($metricName, $previousStart, $previousEnd);
        
        if (!$currentStats || !$previousStats) {
            return null;
        }
        
        $currentValue = $currentStats['avg'];
        $previousValue = $previousStats['avg'];
        
        if ($previousValue == 0) {
            return null;
        }
        
        $changePercent = (($currentValue - $previousValue) / $previousValue) * 100;
        
        return [
            'value' => round($changePercent, 1),
            'direction' => $changePercent > 0 ? 'up' : ($changePercent < 0 ? 'down' : 'flat'),
            'previous_value' => $previousValue,
        ];
    }

    /**
     * Parse period string to start/end timestamps
     *
     * @param string $period Period string (e.g., '24h', '7d', '1w')
     * @return array [start, end] timestamps
     */
    private function parsePeriod(string $period): array
    {
        $end = time();
        
        $value = (int) substr($period, 0, -1);
        $unit = substr($period, -1);
        
        $seconds = match($unit) {
            'h' => $value * 3600,
            'd' => $value * 86400,
            'w' => $value * 604800,
            'm' => $value * 2592000, // 30 days
            default => 86400, // default to 1 day
        };
        
        $start = $end - $seconds;
        
        return [$start, $end];
    }

    /**
     * Convert period to hours
     *
     * @param string $period Period string
     * @return int Hours
     */
    private function periodToHours(string $period): int
    {
        list($start, $end) = $this->parsePeriod($period);
        return (int) ceil(($end - $start) / 3600);
    }

    /**
     * Convert period to days
     *
     * @param string $period Period string
     * @return int Days
     */
    private function periodToDays(string $period): int
    {
        list($start, $end) = $this->parsePeriod($period);
        return (int) ceil(($end - $start) / 86400);
    }

    /**
     * Get default configuration
     *
     * @return array Default config
     */
    private function getDefaultConfig(): array
    {
        return [
            'cache_ttl' => 60, // 1 minute cache
            'default_period' => '24h',
            'max_timeline_points' => 100,
        ];
    }
}
