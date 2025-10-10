<?php
/**
 * MonitoringController.php - Monitoring & Observability API Controller
 *
 * Provides API endpoints for performance monitoring, health checks,
 * log aggregation, and alerting management.
 *
 * Endpoints:
 * - GET /api/monitoring/health - System health check
 * - GET /api/monitoring/performance - Performance metrics dashboard
 * - GET /api/monitoring/logs - Log search & aggregation
 * - GET /api/monitoring/alerts - Alert history & statistics
 * - POST /api/monitoring/alerts/send - Send test alert
 *
 * @package VapeshedTransfer
 * @subpackage Controllers\Api
 * @author Vapeshed Transfer Engine
 * @version 2.0.0
 */

namespace VapeshedTransfer\Controllers\Api;

use VapeshedTransfer\Controllers\BaseController;
use VapeshedTransfer\Support\Logger;
use VapeshedTransfer\Support\NeuroContext;
use VapeshedTransfer\Support\Api;
use VapeshedTransfer\Support\HealthMonitor;
use VapeshedTransfer\Support\PerformanceProfiler;
use VapeshedTransfer\Support\LogAggregator;
use VapeshedTransfer\Support\AlertManager;
use VapeshedTransfer\Support\Cache;

class MonitoringController extends BaseController
{
    private HealthMonitor $healthMonitor;
    private PerformanceProfiler $profiler;
    private LogAggregator $logAggregator;
    private AlertManager $alertManager;

    /**
     * Initialize controller with dependencies
     */
    public function __construct()
    {
        parent::__construct();

        $cache = new Cache($this->logger);

        $this->alertManager = new AlertManager(
            $this->logger,
            $cache,
            $this->getAlertConfig()
        );

        $this->healthMonitor = new HealthMonitor(
            $this->logger,
            $cache,
            $this->alertManager,
            $this->getHealthConfig()
        );

        $this->profiler = new PerformanceProfiler(
            $this->logger,
            $cache,
            $this->alertManager
        );

        $this->logAggregator = new LogAggregator(
            $this->logger,
            storage_path('logs')
        );
    }

    /**
     * System health check endpoint
     *
     * GET /api/monitoring/health
     *
     * Query params:
     * - detailed: Include detailed check results (true/false)
     *
     * @return array Health check results
     */
    public function health(): array
    {
        $startTime = microtime(true);

        try {
            $detailed = isset($_GET['detailed']) && $_GET['detailed'] === 'true';
            $result = $this->healthMonitor->check($detailed);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return Api::success([
                'health' => $result,
                'meta' => [
                    'response_time_ms' => $duration,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Health check endpoint failed', NeuroContext::wrap([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'monitoring_controller'));

            return Api::error(
                'Health check failed: ' . $e->getMessage(),
                500,
                ['exception' => get_class($e)]
            );
        }
    }

    /**
     * Health history endpoint
     *
     * GET /api/monitoring/health/history
     *
     * Query params:
     * - hours: Number of hours (default: 24)
     *
     * @return array Health history
     */
    public function healthHistory(): array
    {
        try {
            $hours = min(168, max(1, (int)($_GET['hours'] ?? 24))); // Max 7 days

            $history = $this->healthMonitor->getHistory($hours);
            $trends = $this->healthMonitor->getTrends($hours);

            return Api::success([
                'history' => $history,
                'trends' => $trends,
                'hours' => $hours,
            ]);

        } catch (\Exception $e) {
            return Api::error('Failed to retrieve health history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Performance metrics dashboard endpoint
     *
     * GET /api/monitoring/performance
     *
     * Query params:
     * - range: Time range (5m, 1h, 6h, 24h, 7d, 30d)
     *
     * @return array Performance dashboard data
     */
    public function performance(): array
    {
        try {
            $range = $_GET['range'] ?? '1h';
            $dashboard = $this->profiler->getDashboard($range);

            return Api::success([
                'dashboard' => $dashboard,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Performance dashboard failed', NeuroContext::wrap([
                'error' => $e->getMessage(),
            ], 'monitoring_controller'));

            return Api::error('Failed to retrieve performance data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Current request metrics endpoint
     *
     * GET /api/monitoring/performance/current
     *
     * @return array Current request metrics
     */
    public function performanceCurrent(): array
    {
        try {
            $metrics = $this->profiler->getRequestMetrics();

            return Api::success([
                'metrics' => $metrics,
            ]);

        } catch (\Exception $e) {
            return Api::error('Failed to retrieve current metrics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Log search endpoint
     *
     * GET /api/monitoring/logs
     *
     * Query params:
     * - query: Search query string
     * - severity: Filter by severity (debug, info, warning, error, critical)
     * - component: Filter by component name
     * - start_date: Start date (YYYY-MM-DD)
     * - end_date: End date (YYYY-MM-DD)
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 100, max: 1000)
     * - regex: Use regex search (true/false)
     *
     * @return array Log search results
     */
    public function logs(): array
    {
        try {
            $filters = [
                'query' => $_GET['query'] ?? '',
                'severity' => $_GET['severity'] ?? null,
                'component' => $_GET['component'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'page' => (int)($_GET['page'] ?? 1),
                'per_page' => (int)($_GET['per_page'] ?? 100),
                'regex' => isset($_GET['regex']) && $_GET['regex'] === 'true',
            ];

            $result = $this->logAggregator->search($filters);

            return Api::success($result);

        } catch (\Exception $e) {
            $this->logger->error('Log search failed', NeuroContext::wrap([
                'error' => $e->getMessage(),
                'filters' => $_GET,
            ], 'monitoring_controller'));

            return Api::error('Log search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Log statistics endpoint
     *
     * GET /api/monitoring/logs/stats
     *
     * Query params:
     * - start_date: Start date (YYYY-MM-DD, default: 7 days ago)
     * - end_date: End date (YYYY-MM-DD, default: today)
     *
     * @return array Log statistics
     */
    public function logStats(): array
    {
        try {
            $filters = [
                'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days')),
                'end_date' => $_GET['end_date'] ?? date('Y-m-d'),
            ];

            $stats = $this->logAggregator->getStats($filters);

            return Api::success([
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            return Api::error('Failed to retrieve log stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Log tail endpoint (real-time)
     *
     * GET /api/monitoring/logs/tail
     *
     * Query params:
     * - lines: Number of lines (default: 100, max: 1000)
     * - file: Specific log file (optional)
     *
     * @return array Recent log entries
     */
    public function logTail(): array
    {
        try {
            $lines = min(1000, max(10, (int)($_GET['lines'] ?? 100)));
            $file = $_GET['file'] ?? null;

            $result = $this->logAggregator->tail($lines, $file);

            return Api::success($result);

        } catch (\Exception $e) {
            return Api::error('Log tail failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Log export endpoint
     *
     * POST /api/monitoring/logs/export
     *
     * JSON body:
     * - filters: Search filters (same as /logs endpoint)
     * - format: Export format (json, csv)
     *
     * @return array Export result with download path
     */
    public function logExport(): array
    {
        Api::requireMethod('POST');

        try {
            $body = Api::getJsonBody();

            $filters = $body['filters'] ?? [];
            $format = $body['format'] ?? 'json';

            $result = $this->logAggregator->export($filters, $format);

            return Api::success([
                'export' => $result,
            ]);

        } catch (\Exception $e) {
            return Api::error('Log export failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Alert history endpoint
     *
     * GET /api/monitoring/alerts
     *
     * Query params:
     * - days: Number of days (default: 7, max: 30)
     *
     * @return array Alert statistics
     */
    public function alerts(): array
    {
        try {
            $days = min(30, max(1, (int)($_GET['days'] ?? 7)));
            $stats = $this->alertManager->getStats($days);

            return Api::success([
                'alerts' => $stats,
            ]);

        } catch (\Exception $e) {
            return Api::error('Failed to retrieve alerts: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send test alert endpoint
     *
     * POST /api/monitoring/alerts/send
     *
     * JSON body:
     * - title: Alert title
     * - message: Alert message
     * - severity: Severity level (info, warning, error, critical)
     * - context: Additional context (optional)
     * - channels: Specific channels (optional)
     *
     * @return array Send result
     */
    public function sendAlert(): array
    {
        Api::requireMethod('POST');

        try {
            $body = Api::getJsonBody();

            // Validate required fields
            if (empty($body['title'])) {
                return Api::error('Alert title is required', 400);
            }

            if (empty($body['message'])) {
                return Api::error('Alert message is required', 400);
            }

            $title = $body['title'];
            $message = $body['message'];
            $severity = $body['severity'] ?? AlertManager::SEVERITY_INFO;
            $context = $body['context'] ?? [];
            $channels = $body['channels'] ?? null;

            $result = $this->alertManager->send(
                $title,
                $message,
                $severity,
                $context,
                $channels
            );

            return Api::success([
                'alert' => $result,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Alert send failed', NeuroContext::wrap([
                'error' => $e->getMessage(),
            ], 'monitoring_controller'));

            return Api::error('Failed to send alert: ' . $e->getMessage(), 500);
        }
    }

    /**
     * System overview endpoint (combined metrics)
     *
     * GET /api/monitoring/overview
     *
     * @return array System overview
     */
    public function overview(): array
    {
        try {
            $health = $this->healthMonitor->check(false);
            $perfMetrics = $this->profiler->getSystemMetrics();
            $alertStats = $this->alertManager->getStats(1); // Last 24 hours
            $logStats = $this->logAggregator->getStats([
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d'),
            ]);

            return Api::success([
                'overview' => [
                    'health' => [
                        'status' => $health['status'],
                        'timestamp' => $health['timestamp'],
                    ],
                    'performance' => [
                        'memory_mb' => $perfMetrics['memory']['current_mb'],
                        'load_1m' => $perfMetrics['cpu']['load_1m'],
                    ],
                    'alerts_today' => $alertStats['total'] ?? 0,
                    'logs_today' => $logStats['total_entries'] ?? 0,
                    'errors_today' => ($logStats['by_severity']['error'] ?? 0) + ($logStats['by_severity']['critical'] ?? 0),
                ],
            ]);

        } catch (\Exception $e) {
            return Api::error('Failed to retrieve overview: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get alert configuration
     *
     * @return array Alert config
     */
    private function getAlertConfig(): array
    {
        return [
            'environment' => config('neuro.unified.environment', 'production'),
            'email' => [
                'enabled' => config('alerts.email.enabled', false),
                'from' => config('alerts.email.from', 'alerts@vapeshed.co.nz'),
                'to' => config('alerts.email.to', []),
            ],
            'slack' => [
                'enabled' => config('alerts.slack.enabled', false),
                'webhook_url' => config('alerts.slack.webhook_url', ''),
                'channel' => config('alerts.slack.channel', '#alerts'),
            ],
            'webhook' => [
                'enabled' => config('alerts.webhook.enabled', false),
                'url' => config('alerts.webhook.url', ''),
            ],
        ];
    }

    /**
     * Get health monitor configuration
     *
     * @return array Health config
     */
    private function getHealthConfig(): array
    {
        return [
            'db_host' => config('database.host', 'localhost'),
            'db_name' => config('database.database', 'vapeshed_transfer'),
            'db_user' => config('database.username', 'root'),
            'db_pass' => config('database.password', ''),
            'storage_path' => storage_path(),
        ];
    }
}
