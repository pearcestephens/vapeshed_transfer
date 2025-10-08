#!/usr/bin/env php
<?php
/**
 * Comprehensive Test Suite for Phases 8, 9, and 10
 * 
 * Tests all components delivered in the last 3 phases:
 * - Phase 8: Integration & Advanced Tools
 * - Phase 9: Monitoring & Alerting
 * - Phase 10: Analytics & Reporting
 * 
 * @version 2.0.0
 */

require_once __DIR__ . '/../config/bootstrap.php';

use Unified\Support\Logger;
use Unified\Support\CacheManager;
use Unified\Support\MetricsCollector;
use Unified\Support\HealthMonitor;
use Unified\Support\PerformanceProfiler;
use Unified\Support\AlertManager;
use Unified\Support\LogAggregator;
use Unified\Support\AnalyticsEngine;
use Unified\Support\ReportGenerator;
use Unified\Support\DashboardDataProvider;
use Unified\Support\NotificationScheduler;
use Unified\Support\ApiDocumentationGenerator;

class ComprehensiveTestSuite
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Run all tests
     */
    public function runAll(): void
    {
        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║   COMPREHENSIVE TEST SUITE - PHASES 8, 9, 10            ║\n";
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        // Phase 8 Tests
        echo "┌─ PHASE 8: Integration & Advanced Tools ─────────────────┐\n";
        $this->testCacheManager();
        $this->testIntegrationHelpers();
        echo "└──────────────────────────────────────────────────────────┘\n\n";

        // Phase 9 Tests
        echo "┌─ PHASE 9: Monitoring & Alerting ────────────────────────┐\n";
        $this->testMetricsCollector();
        $this->testHealthMonitor();
        $this->testPerformanceProfiler();
        $this->testAlertManager();
        $this->testLogAggregator();
        echo "└──────────────────────────────────────────────────────────┘\n\n";

        // Phase 10 Tests
        echo "┌─ PHASE 10: Analytics & Reporting ───────────────────────┐\n";
        $this->testAnalyticsEngine();
        $this->testReportGenerator();
        $this->testDashboardDataProvider();
        $this->testNotificationScheduler();
        $this->testApiDocumentationGenerator();
        echo "└──────────────────────────────────────────────────────────┘\n\n";

        $this->printSummary();
    }

    /**
     * Test CacheManager (Phase 8)
     */
    private function testCacheManager(): void
    {
        echo "  Testing CacheManager...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);

            // Test 1: Set and get
            $cache->set('test_key', 'test_value', 60);
            $value = $cache->get('test_key');
            $this->assert('CacheManager: Set and get', $value === 'test_value');

            // Test 2: Delete
            $cache->delete('test_key');
            $value = $cache->get('test_key');
            $this->assert('CacheManager: Delete', $value === null);

            // Test 3: Increment
            $cache->set('counter', 0);
            $cache->increment('counter', 5);
            $value = $cache->get('counter');
            $this->assert('CacheManager: Increment', $value == 5);

            // Test 4: Tags
            $cache->tags(['test'])->set('tagged_key', 'tagged_value');
            $value = $cache->tags(['test'])->get('tagged_key');
            $this->assert('CacheManager: Tags', $value === 'tagged_value');

            // Test 5: Flush tags
            $cache->tags(['test'])->flush();
            $value = $cache->tags(['test'])->get('tagged_key');
            $this->assert('CacheManager: Flush tags', $value === null);

        } catch (\Exception $e) {
            $this->assert('CacheManager: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test Integration Helpers (Phase 8)
     */
    private function testIntegrationHelpers(): void
    {
        echo "  Testing Integration Helpers...\n";

        try {
            // Test helper functions exist
            $this->assert('Helper: storage_path exists', function_exists('storage_path'));
            $this->assert('Helper: config_path exists', function_exists('config_path'));
            $this->assert('Helper: base_path exists', function_exists('base_path'));

            // Test paths
            $storagePath = storage_path('test');
            $this->assert('Helper: storage_path returns string', is_string($storagePath));

        } catch (\Exception $e) {
            $this->assert('Integration Helpers: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test MetricsCollector (Phase 9)
     */
    private function testMetricsCollector(): void
    {
        echo "  Testing MetricsCollector...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $metrics = new MetricsCollector($logger, $cache);

            // Test 1: Record counter
            $metrics->counter('test.counter', 10, ['env' => 'test']);
            $metrics->flush();
            $this->assert('MetricsCollector: Counter recorded', true);

            // Test 2: Record gauge
            $metrics->gauge('test.gauge', 42.5, ['env' => 'test']);
            $metrics->flush();
            $this->assert('MetricsCollector: Gauge recorded', true);

            // Test 3: Record histogram
            $metrics->histogram('test.histogram', 100, ['env' => 'test']);
            $metrics->flush();
            $this->assert('MetricsCollector: Histogram recorded', true);

            // Test 4: Timer
            $timerId = $metrics->startTimer('test.timer', ['env' => 'test']);
            usleep(10000); // 10ms
            $metrics->stopTimer($timerId);
            $metrics->flush();
            $this->assert('MetricsCollector: Timer recorded', true);

            // Test 5: Query metrics
            $end = time();
            $start = $end - 3600;
            $result = $metrics->query('test.counter', $start, $end, ['env' => 'test']);
            $this->assert('MetricsCollector: Query returns array', is_array($result));

        } catch (\Exception $e) {
            $this->assert('MetricsCollector: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test HealthMonitor (Phase 9)
     */
    private function testHealthMonitor(): void
    {
        echo "  Testing HealthMonitor...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $health = new HealthMonitor($logger, $cache);

            // Test 1: Register check
            $health->registerCheck('test_check', function() {
                return ['status' => 'healthy', 'message' => 'Test check passed'];
            }); // No 3rd parameter - it expects callable|null, not string
            $this->assert('HealthMonitor: Register check', true);

            // Test 2: Run check
            $result = $health->check(false); // Don't run remediation
            $this->assert('HealthMonitor: Check returns array', is_array($result));
            $this->assert('HealthMonitor: Has status', isset($result['status']));
            $this->assert('HealthMonitor: Has checks', isset($result['checks']));

            // Test 3: Get trends
            $trends = $health->getTrends(24);
            $this->assert('HealthMonitor: Trends returns array', is_array($trends));
            $this->assert('HealthMonitor: Trends has uptime', isset($trends['uptime_percent']));

        } catch (\Exception $e) {
            $this->assert('HealthMonitor: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test PerformanceProfiler (Phase 9)
     */
    private function testPerformanceProfiler(): void
    {
        echo "  Testing PerformanceProfiler...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $profiler = new PerformanceProfiler($logger, $cache);

            // Test 1: Start request
            $requestId = $profiler->startRequest([
                'method' => 'GET',
                'uri' => '/api/test',
                'headers' => [],
            ]);
            $this->assert('PerformanceProfiler: Start request returns ID', !empty($requestId));

            // Test 2: Add query
            $profiler->addQuery($requestId, 'SELECT * FROM test', 5.2);
            $this->assert('PerformanceProfiler: Add query', true);

            // Test 3: End request
            usleep(20000); // 20ms
            $profiler->endRequest($requestId, 200, ['data' => 'test']);
            $this->assert('PerformanceProfiler: End request', true);

            // Test 4: Get dashboard
            $dashboard = $profiler->getDashboard('1h');
            $this->assert('PerformanceProfiler: Dashboard returns array', is_array($dashboard));
            $this->assert('PerformanceProfiler: Has summary', isset($dashboard['summary']));

        } catch (\Exception $e) {
            $this->assert('PerformanceProfiler: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test AlertManager (Phase 9)
     */
    private function testAlertManager(): void
    {
        echo "  Testing AlertManager...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $alert = new AlertManager($logger, $cache);

            // Test 1: Send alert
            $result = $alert->send([
                'severity' => 'info',
                'title' => 'Test Alert',
                'message' => 'This is a test alert',
            ]);
            $this->assert('AlertManager: Send alert', $result['success'] ?? false);

            // Test 2: Get stats
            $stats = $alert->getStats(1);
            $this->assert('AlertManager: Stats returns array', is_array($stats));
            $this->assert('AlertManager: Has total', isset($stats['total']));

        } catch (\Exception $e) {
            $this->assert('AlertManager: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test LogAggregator (Phase 9)
     */
    private function testLogAggregator(): void
    {
        echo "  Testing LogAggregator...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $logAggregator = new LogAggregator($logger, storage_path('logs'));

            // Test 1: Search logs
            $result = $logAggregator->search('test', [
                'level' => 'info',
                'limit' => 10,
            ]);
            $this->assert('LogAggregator: Search returns array', is_array($result));
            $this->assert('LogAggregator: Has entries', isset($result['entries']));

            // Test 2: Get statistics
            $stats = $logAggregator->getStatistics([
                'period' => '24h',
            ]);
            $this->assert('LogAggregator: Statistics returns array', is_array($stats));

        } catch (\Exception $e) {
            $this->assert('LogAggregator: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test AnalyticsEngine (Phase 10)
     */
    private function testAnalyticsEngine(): void
    {
        echo "  Testing AnalyticsEngine...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $metrics = new MetricsCollector($logger, $cache);
            $analytics = new AnalyticsEngine($logger, $metrics);

            // Test 1: Trend analysis
            $data = [
                ['timestamp' => time() - 300, 'value' => 100],
                ['timestamp' => time() - 200, 'value' => 110],
                ['timestamp' => time() - 100, 'value' => 120],
                ['timestamp' => time(), 'value' => 130],
            ];
            $trend = $analytics->analyzeTrend($data, AnalyticsEngine::TREND_LINEAR);
            $this->assert('AnalyticsEngine: Trend analysis', isset($trend['direction']));
            $this->assert('AnalyticsEngine: Has slope', isset($trend['slope']));

            // Test 2: Forecasting
            $forecast = $analytics->forecast($data, 3, AnalyticsEngine::FORECAST_LINEAR_REGRESSION);
            $this->assert('AnalyticsEngine: Forecast returns array', is_array($forecast));
            $this->assert('AnalyticsEngine: Has forecasts', isset($forecast['forecasts']));

            // Test 3: Anomaly detection
            $anomalyData = array_merge($data, [
                ['timestamp' => time() + 100, 'value' => 500], // Anomaly
            ]);
            $anomalies = $analytics->detectAnomalies($anomalyData, AnalyticsEngine::ANOMALY_IQR);
            $this->assert('AnalyticsEngine: Anomaly detection', is_array($anomalies));
            $this->assert('AnalyticsEngine: Has anomalies list', isset($anomalies['anomalies']));

            // Test 4: Statistics
            $values = [10, 20, 30, 40, 50];
            $stats = $analytics->calculateStatistics($values);
            $this->assert('AnalyticsEngine: Statistics has mean', isset($stats['mean']));
            $this->assert('AnalyticsEngine: Mean is correct', $stats['mean'] == 30);

        } catch (\Exception $e) {
            $this->assert('AnalyticsEngine: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test ReportGenerator (Phase 10)
     */
    private function testReportGenerator(): void
    {
        echo "  Testing ReportGenerator...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $metrics = new MetricsCollector($logger, $cache);
            $reportGen = new ReportGenerator($logger, $metrics);

            // Test 1: Generate HTML report
            $result = $reportGen->generate(
                ReportGenerator::TYPE_METRICS,
                ReportGenerator::FORMAT_HTML,
                [
                    'metrics' => ['test.metric'],
                    'start' => time() - 3600,
                    'end' => time(),
                ]
            );
            $this->assert('ReportGenerator: Generate returns array', is_array($result));
            $this->assert('ReportGenerator: Has path', isset($result['path']));

            // Test 2: Generate JSON report
            $result = $reportGen->generate(
                ReportGenerator::TYPE_CUSTOM,
                ReportGenerator::FORMAT_JSON,
                [
                    'data' => [
                        'title' => 'Test Report',
                        'summary' => ['count' => 100],
                    ],
                ]
            );
            $this->assert('ReportGenerator: JSON generation', isset($result['path']));

            // Test 3: Schedule report
            $schedule = $reportGen->schedule(
                ReportGenerator::TYPE_METRICS,
                ReportGenerator::FORMAT_PDF,
                ReportGenerator::PERIOD_DAILY
            );
            $this->assert('ReportGenerator: Schedule returns array', is_array($schedule));

        } catch (\Exception $e) {
            $this->assert('ReportGenerator: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test DashboardDataProvider (Phase 10)
     */
    private function testDashboardDataProvider(): void
    {
        echo "  Testing DashboardDataProvider...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $metrics = new MetricsCollector($logger, $cache);
            $health = new HealthMonitor($logger, $cache);
            $profiler = new PerformanceProfiler($logger, $cache);
            $alert = new AlertManager($logger, $cache);
            $analytics = new AnalyticsEngine($logger, $metrics);
            
            $dashboard = new DashboardDataProvider(
                $logger, $metrics, $health, $profiler, $alert, $analytics, $cache
            );

            // Test 1: Get overview widget
            $overview = $dashboard->getOverview(['period' => '24h']);
            $this->assert('DashboardDataProvider: Overview returns array', is_array($overview));
            $this->assert('DashboardDataProvider: Has system status', isset($overview['system_status']));

            // Test 2: Get health widget
            $healthWidget = $dashboard->getHealthWidget(['period' => '24h']);
            $this->assert('DashboardDataProvider: Health widget', is_array($healthWidget));

            // Test 3: Get complete dashboard
            $fullDashboard = $dashboard->getDashboard(['period' => '24h']);
            $this->assert('DashboardDataProvider: Full dashboard', is_array($fullDashboard));
            $this->assert('DashboardDataProvider: Has overview', isset($fullDashboard['overview']));
            $this->assert('DashboardDataProvider: Has health', isset($fullDashboard['health']));

        } catch (\Exception $e) {
            $this->assert('DashboardDataProvider: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test NotificationScheduler (Phase 10)
     */
    private function testNotificationScheduler(): void
    {
        echo "  Testing NotificationScheduler...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $cache = new CacheManager($logger);
            $alert = new AlertManager($logger, $cache);
            $metrics = new MetricsCollector($logger, $cache);
            $reportGen = new ReportGenerator($logger, $metrics);
            
            $scheduler = new NotificationScheduler($logger, $alert, $reportGen, $cache);

            // Test 1: Schedule notification
            $schedule = $scheduler->schedule(
                NotificationScheduler::TYPE_DIGEST,
                NotificationScheduler::FREQ_DAILY,
                [
                    'recipients' => ['test@example.com'],
                    'title' => 'Test Digest',
                ]
            );
            $this->assert('NotificationScheduler: Schedule created', isset($schedule['id']));
            $this->assert('NotificationScheduler: Has next_run', isset($schedule['next_run']));

            // Test 2: Get schedule
            $retrieved = $scheduler->getSchedule($schedule['id']);
            $this->assert('NotificationScheduler: Retrieve schedule', $retrieved !== null);

            // Test 3: Get all schedules
            $all = $scheduler->getAllSchedules();
            $this->assert('NotificationScheduler: Get all schedules', is_array($all));

            // Test 4: Cancel schedule
            $cancelled = $scheduler->cancel($schedule['id']);
            $this->assert('NotificationScheduler: Cancel schedule', $cancelled);

        } catch (\Exception $e) {
            $this->assert('NotificationScheduler: Exception', false, $e->getMessage());
        }
    }

    /**
     * Test ApiDocumentationGenerator (Phase 10)
     */
    private function testApiDocumentationGenerator(): void
    {
        echo "  Testing ApiDocumentationGenerator...\n";

        try {
            $logger = new Logger('test', storage_path('logs'));
            $docGen = new ApiDocumentationGenerator($logger, [
                'title' => 'Test API',
                'version' => '1.0.0',
                'base_url' => 'https://api.test.com',
            ]);

            // Test 1: Generate documentation
            $routes = [
                [
                    'path' => '/api/test',
                    'method' => 'GET',
                    'summary' => 'Test endpoint',
                    'description' => 'Test endpoint description',
                ],
                [
                    'path' => '/api/test/{id}',
                    'method' => 'POST',
                    'summary' => 'Create test',
                    'description' => 'Create test item',
                ],
            ];

            $result = $docGen->generate($routes, [
                'openapi' => true,
                'markdown' => true,
            ]);

            $this->assert('ApiDocGen: Generate returns array', is_array($result));
            $this->assert('ApiDocGen: Has outputs', isset($result['outputs']));
            $this->assert('ApiDocGen: Has OpenAPI', isset($result['outputs']['openapi']));
            $this->assert('ApiDocGen: Has Markdown', isset($result['outputs']['markdown']));

            // Test 2: OpenAPI format
            $openapi = $result['outputs']['openapi'];
            $spec = json_decode($openapi, true);
            $this->assert('ApiDocGen: OpenAPI is valid JSON', $spec !== null);
            $this->assert('ApiDocGen: Has openapi version', isset($spec['openapi']));

            // Test 3: Markdown format
            $markdown = $result['outputs']['markdown'];
            $this->assert('ApiDocGen: Markdown contains title', strpos($markdown, 'Test API') !== false);

        } catch (\Exception $e) {
            $this->assert('ApiDocumentationGenerator: Exception', false, $e->getMessage());
        }
    }

    /**
     * Assert test result
     */
    private function assert(string $name, bool $condition, ?string $message = null): void
    {
        if ($condition) {
            echo "    ✓ {$name}\n";
            $this->passed++;
        } else {
            echo "    ✗ {$name}";
            if ($message) {
                echo " - {$message}";
            }
            echo "\n";
            $this->failed++;
        }

        $this->results[] = [
            'name' => $name,
            'passed' => $condition,
            'message' => $message,
        ];
    }

    /**
     * Print summary
     */
    private function printSummary(): void
    {
        $duration = round((microtime(true) - $this->startTime) * 1000, 2);
        $total = $this->passed + $this->failed + $this->skipped;
        $passRate = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        echo "╔══════════════════════════════════════════════════════════╗\n";
        echo "║                    TEST SUMMARY                          ║\n";
        echo "╠══════════════════════════════════════════════════════════╣\n";
        echo sprintf("║  Total Tests:     %-35d║\n", $total);
        echo sprintf("║  Passed:          %-35d║\n", $this->passed);
        echo sprintf("║  Failed:          %-35d║\n", $this->failed);
        echo sprintf("║  Skipped:         %-35d║\n", $this->skipped);
        echo sprintf("║  Pass Rate:       %-33s  ║\n", $passRate . '%');
        echo sprintf("║  Duration:        %-33s  ║\n", $duration . 'ms');
        echo "╚══════════════════════════════════════════════════════════╝\n\n";

        if ($this->failed > 0) {
            echo "Failed Tests:\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['name']}";
                    if ($result['message']) {
                        echo ": {$result['message']}";
                    }
                    echo "\n";
                }
            }
            echo "\n";
        }

        // Exit code
        exit($this->failed > 0 ? 1 : 0);
    }
}

// Run tests
$suite = new ComprehensiveTestSuite();
$suite->runAll();
