<?php

/**
 * Analytics Controller
 *
 * Comprehensive analytics and reporting system for transfer operations,
 * API usage, performance metrics, and business intelligence
 *
 * Features:
 * - Transfer success/failure analytics
 * - API usage and cost tracking
 * - Performance metrics and trends
 * - Custom date range reports
 * - Export capabilities (CSV, PDF, Excel)
 * - Real-time dashboard widgets
 *
 * @category   Controllers
 * @package    VapeshedTransfer
 * @subpackage Admin\Analytics
 * @author     Vapeshed Transfer Team
 * @license    Proprietary
 * @version    1.0.0
 */

namespace VapeshedTransfer\App\Controllers\Admin\Analytics;

use VapeshedTransfer\App\Controllers\BaseController;
use VapeshedTransfer\App\Services\AnalyticsService;
use VapeshedTransfer\App\Core\Logger;
use VapeshedTransfer\App\Core\Security;
use VapeshedTransfer\App\Core\Database;

/**
 * Analytics Controller Class
 *
 * Handles all analytics and reporting functionality with comprehensive
 * data analysis, visualization, and export capabilities
 */
class AnalyticsController extends BaseController
{
    /**
     * @var AnalyticsService Service for analytics operations
     */
    private $analyticsService;

    /**
     * @var Logger Logger instance for tracking operations
     */
    private $logger;

    /**
     * @var Security Security handler for validation and sanitization
     */
    private $security;

    /**
     * @var Database Database connection instance
     */
    private $db;

    /**
     * @var array Supported export formats
     */
    private const EXPORT_FORMATS = ['csv', 'pdf', 'excel', 'json'];

    /**
     * @var array Valid date range presets
     */
    private const DATE_RANGES = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_quarter' => 'This Quarter',
        'last_quarter' => 'Last Quarter',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
        'custom' => 'Custom Range'
    ];

    /**
     * @var array Cache for frequently accessed analytics data
     */
    private $cache = [];

    /**
     * @var int Cache TTL in seconds (5 minutes default)
     */
    private const CACHE_TTL = 300;

    /**
     * Constructor
     *
     * Initializes analytics controller with required dependencies
     * and sets up service connections
     */
    public function __construct()
    {
        parent::__construct();

        // Initialize core services
        $this->logger = Logger::getInstance();
        $this->security = new Security();
        $this->db = Database::getInstance();
        $this->analyticsService = new AnalyticsService();

        // Log controller initialization
        $this->logger->info('AnalyticsController initialized', [
            'user_id' => $_SESSION['user_id'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Display Main Analytics Dashboard
     *
     * Renders the comprehensive analytics dashboard with key metrics,
     * charts, and interactive reports
     *
     * @return void Renders dashboard view
     */
    public function index(): void
    {
        try {
            // Verify authentication
            if (!$this->security->isAuthenticated()) {
                $this->redirectToLogin();
                return;
            }

            // Check permissions
            if (!$this->security->hasPermission('view_analytics')) {
                $this->renderError('Insufficient permissions', 403);
                return;
            }

            // Get default date range (last 30 days)
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));

            // Fetch dashboard data
            $dashboardData = [
                'overview' => $this->getOverviewMetrics($startDate, $endDate),
                'transfers' => $this->getTransferAnalytics($startDate, $endDate),
                'api_usage' => $this->getApiUsageMetrics($startDate, $endDate),
                'performance' => $this->getPerformanceMetrics($startDate, $endDate),
                'trends' => $this->getTrendData($startDate, $endDate),
                'date_ranges' => self::DATE_RANGES,
                'current_range' => 'last_30_days',
                'start_date' => $startDate,
                'end_date' => $endDate
            ];

            // Log dashboard access
            $this->logger->info('Analytics dashboard accessed', [
                'user_id' => $_SESSION['user_id'],
                'date_range' => "{$startDate} to {$endDate}"
            ]);

            // Render dashboard view
            $this->render('admin/analytics/dashboard', $dashboardData);

        } catch (\Exception $e) {
            $this->logger->error('Error loading analytics dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->renderError('Failed to load analytics dashboard', 500);
        }
    }

    /**
     * Get Transfer Analytics Data
     *
     * Retrieves comprehensive transfer analytics including success rates,
     * failure patterns, volume trends, and store-to-store analysis
     *
     * @return void Returns JSON response
     */
    public function getTransferAnalytics(): void
    {
        try {
            // Verify AJAX request
            if (!$this->isAjaxRequest()) {
                $this->jsonError('Invalid request method', 400);
                return;
            }

            // Validate CSRF token
            if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->jsonError('Invalid CSRF token', 403);
                return;
            }

            // Get and validate date range parameters
            $startDate = $this->security->sanitize($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->security->sanitize($_POST['end_date'] ?? date('Y-m-d'));

            if (!$this->validateDateRange($startDate, $endDate)) {
                $this->jsonError('Invalid date range', 400);
                return;
            }

            // Check cache first
            $cacheKey = "transfer_analytics_{$startDate}_{$endDate}";
            if (isset($this->cache[$cacheKey]) && 
                time() - $this->cache[$cacheKey]['timestamp'] < self::CACHE_TTL) {
                $this->jsonSuccess($this->cache[$cacheKey]['data']);
                return;
            }

            // Fetch transfer analytics data
            $analytics = $this->analyticsService->getTransferAnalytics($startDate, $endDate);

            // Calculate additional metrics
            $analytics['success_rate'] = $this->calculateSuccessRate($analytics);
            $analytics['avg_processing_time'] = $this->calculateAverageProcessingTime($analytics);
            $analytics['peak_hours'] = $this->identifyPeakHours($analytics);
            $analytics['store_patterns'] = $this->analyzeStorePatterns($analytics);

            // Cache the results
            $this->cache[$cacheKey] = [
                'data' => $analytics,
                'timestamp' => time()
            ];

            // Log analytics request
            $this->logger->info('Transfer analytics retrieved', [
                'date_range' => "{$startDate} to {$endDate}",
                'record_count' => count($analytics['transfers'] ?? [])
            ]);

            $this->jsonSuccess($analytics);

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving transfer analytics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->jsonError('Failed to retrieve transfer analytics', 500);
        }
    }

    /**
     * Get API Usage Metrics
     *
     * Provides detailed API usage statistics including endpoint hit counts,
     * response times, error rates, and cost analysis
     *
     * @return void Returns JSON response
     */
    public function getApiUsageMetrics(): void
    {
        try {
            // Verify request and authentication
            if (!$this->isAjaxRequest() || !$this->security->isAuthenticated()) {
                $this->jsonError('Unauthorized', 401);
                return;
            }

            // Validate CSRF
            if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->jsonError('Invalid CSRF token', 403);
                return;
            }

            // Get parameters
            $startDate = $this->security->sanitize($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->security->sanitize($_POST['end_date'] ?? date('Y-m-d'));
            $groupBy = $this->security->sanitize($_POST['group_by'] ?? 'endpoint');

            // Validate grouping parameter
            if (!in_array($groupBy, ['endpoint', 'status', 'hour', 'day'])) {
                $this->jsonError('Invalid grouping parameter', 400);
                return;
            }

            // Fetch API usage data
            $metrics = $this->analyticsService->getApiUsageMetrics($startDate, $endDate, $groupBy);

            // Calculate percentiles (p50, p95, p99)
            $metrics['percentiles'] = $this->calculateResponseTimePercentiles($metrics['response_times'] ?? []);

            // Calculate error rates
            $metrics['error_rate'] = $this->calculateErrorRate($metrics);

            // Estimate API costs
            $metrics['estimated_costs'] = $this->calculateApiCosts($metrics);

            // Identify rate limit usage
            $metrics['rate_limit_usage'] = $this->analyzeRateLimitUsage($metrics);

            $this->jsonSuccess($metrics);

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving API usage metrics', [
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Failed to retrieve API metrics', 500);
        }
    }

    /**
     * Get Performance Metrics
     *
     * Retrieves system performance data including response times,
     * database query performance, and resource utilization
     *
     * @return void Returns JSON response
     */
    public function getPerformanceMetrics(): void
    {
        try {
            // Authentication and validation
            if (!$this->isAjaxRequest() || !$this->security->isAuthenticated()) {
                $this->jsonError('Unauthorized', 401);
                return;
            }

            if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->jsonError('Invalid CSRF token', 403);
                return;
            }

            // Get time range
            $startDate = $this->security->sanitize($_POST['start_date'] ?? date('Y-m-d', strtotime('-7 days')));
            $endDate = $this->security->sanitize($_POST['end_date'] ?? date('Y-m-d'));

            // Fetch performance data
            $performance = [
                'response_times' => $this->getResponseTimeStats($startDate, $endDate),
                'database_performance' => $this->getDatabasePerformanceStats($startDate, $endDate),
                'resource_usage' => $this->getResourceUsageStats($startDate, $endDate),
                'slow_queries' => $this->getSlowQueries($startDate, $endDate),
                'bottlenecks' => $this->identifyBottlenecks($startDate, $endDate)
            ];

            $this->jsonSuccess($performance);

        } catch (\Exception $e) {
            $this->logger->error('Error retrieving performance metrics', [
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Failed to retrieve performance metrics', 500);
        }
    }

    /**
     * Export Analytics Report
     *
     * Generates and exports analytics data in specified format
     * Supports CSV, PDF, Excel, and JSON formats
     *
     * @return void Sends file download response
     */
    public function exportReport(): void
    {
        try {
            // Verify authentication
            if (!$this->security->isAuthenticated()) {
                $this->jsonError('Unauthorized', 401);
                return;
            }

            // Check export permissions
            if (!$this->security->hasPermission('export_reports')) {
                $this->jsonError('Insufficient permissions', 403);
                return;
            }

            // Get export parameters
            $format = strtolower($this->security->sanitize($_POST['format'] ?? 'csv'));
            $reportType = $this->security->sanitize($_POST['report_type'] ?? 'transfers');
            $startDate = $this->security->sanitize($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
            $endDate = $this->security->sanitize($_POST['end_date'] ?? date('Y-m-d'));

            // Validate format
            if (!in_array($format, self::EXPORT_FORMATS)) {
                $this->jsonError('Invalid export format', 400);
                return;
            }

            // Validate report type
            if (!in_array($reportType, ['transfers', 'api_usage', 'performance', 'full'])) {
                $this->jsonError('Invalid report type', 400);
                return;
            }

            // Generate report data
            $reportData = $this->generateReportData($reportType, $startDate, $endDate);

            // Export based on format
            switch ($format) {
                case 'csv':
                    $this->exportToCsv($reportData, $reportType, $startDate, $endDate);
                    break;

                case 'pdf':
                    $this->exportToPdf($reportData, $reportType, $startDate, $endDate);
                    break;

                case 'excel':
                    $this->exportToExcel($reportData, $reportType, $startDate, $endDate);
                    break;

                case 'json':
                    $this->exportToJson($reportData, $reportType, $startDate, $endDate);
                    break;
            }

            // Log export action
            $this->logger->info('Report exported', [
                'user_id' => $_SESSION['user_id'],
                'format' => $format,
                'report_type' => $reportType,
                'date_range' => "{$startDate} to {$endDate}"
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error exporting report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->jsonError('Failed to export report', 500);
        }
    }

    /**
     * Schedule Automated Report
     *
     * Sets up scheduled report generation and email delivery
     *
     * @return void Returns JSON response
     */
    public function scheduleReport(): void
    {
        try {
            // Verify request
            if (!$this->isAjaxRequest() || !$this->security->isAuthenticated()) {
                $this->jsonError('Unauthorized', 401);
                return;
            }

            // Validate CSRF
            if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->jsonError('Invalid CSRF token', 403);
                return;
            }

            // Check permissions
            if (!$this->security->hasPermission('schedule_reports')) {
                $this->jsonError('Insufficient permissions', 403);
                return;
            }

            // Get schedule parameters
            $reportType = $this->security->sanitize($_POST['report_type'] ?? '');
            $frequency = $this->security->sanitize($_POST['frequency'] ?? 'weekly');
            $format = strtolower($this->security->sanitize($_POST['format'] ?? 'pdf'));
            $recipients = $this->security->sanitize($_POST['recipients'] ?? '');

            // Validate parameters
            if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
                $this->jsonError('Invalid frequency', 400);
                return;
            }

            if (!in_array($format, self::EXPORT_FORMATS)) {
                $this->jsonError('Invalid format', 400);
                return;
            }

            // Validate email addresses
            $emailList = array_map('trim', explode(',', $recipients));
            foreach ($emailList as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->jsonError('Invalid email address: ' . $email, 400);
                    return;
                }
            }

            // Create scheduled report
            $scheduleId = $this->analyticsService->createScheduledReport([
                'report_type' => $reportType,
                'frequency' => $frequency,
                'format' => $format,
                'recipients' => $emailList,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Log schedule creation
            $this->logger->info('Scheduled report created', [
                'schedule_id' => $scheduleId,
                'report_type' => $reportType,
                'frequency' => $frequency
            ]);

            $this->jsonSuccess([
                'schedule_id' => $scheduleId,
                'message' => 'Report scheduled successfully'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error scheduling report', [
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Failed to schedule report', 500);
        }
    }

    /**
     * Get Custom Analytics Query
     *
     * Allows advanced users to run custom analytics queries
     * with built-in safety checks and query validation
     *
     * @return void Returns JSON response
     */
    public function customQuery(): void
    {
        try {
            // Verify authentication and permissions
            if (!$this->isAjaxRequest() || !$this->security->isAuthenticated()) {
                $this->jsonError('Unauthorized', 401);
                return;
            }

            if (!$this->security->hasPermission('run_custom_queries')) {
                $this->jsonError('Insufficient permissions', 403);
                return;
            }

            // Validate CSRF
            if (!$this->security->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->jsonError('Invalid CSRF token', 403);
                return;
            }

            // Get query parameters
            $queryType = $this->security->sanitize($_POST['query_type'] ?? '');
            $metrics = json_decode($_POST['metrics'] ?? '[]', true);
            $filters = json_decode($_POST['filters'] ?? '[]', true);
            $groupBy = $this->security->sanitize($_POST['group_by'] ?? '');

            // Validate query safety (no destructive operations allowed)
            if (!$this->validateQuerySafety($queryType, $metrics, $filters)) {
                $this->jsonError('Unsafe query detected', 400);
                return;
            }

            // Execute custom query
            $results = $this->analyticsService->executeCustomQuery([
                'type' => $queryType,
                'metrics' => $metrics,
                'filters' => $filters,
                'group_by' => $groupBy
            ]);

            // Log custom query execution
            $this->logger->info('Custom analytics query executed', [
                'user_id' => $_SESSION['user_id'],
                'query_type' => $queryType,
                'result_count' => count($results)
            ]);

            $this->jsonSuccess($results);

        } catch (\Exception $e) {
            $this->logger->error('Error executing custom query', [
                'error' => $e->getMessage()
            ]);

            $this->jsonError('Failed to execute custom query', 500);
        }
    }

    // =====================================================================
    // PRIVATE HELPER METHODS
    // =====================================================================

    /**
     * Get Overview Metrics
     *
     * Calculates high-level overview metrics for dashboard
     *
     * @param string $startDate Start date for analysis
     * @param string $endDate   End date for analysis
     *
     * @return array Overview metrics data
     */
    private function getOverviewMetrics(string $startDate, string $endDate): array
    {
        return $this->analyticsService->getOverviewMetrics($startDate, $endDate);
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
    private function getTrendData(string $startDate, string $endDate): array
    {
        return $this->analyticsService->getTrendData($startDate, $endDate);
    }

    /**
     * Calculate Success Rate
     *
     * Computes success rate percentage from analytics data
     *
     * @param array $analytics Analytics data
     *
     * @return float Success rate percentage
     */
    private function calculateSuccessRate(array $analytics): float
    {
        $total = ($analytics['successful'] ?? 0) + ($analytics['failed'] ?? 0);
        return $total > 0 ? round(($analytics['successful'] ?? 0) / $total * 100, 2) : 0.0;
    }

    /**
     * Calculate Average Processing Time
     *
     * Computes average processing time from transfer data
     *
     * @param array $analytics Analytics data
     *
     * @return float Average processing time in seconds
     */
    private function calculateAverageProcessingTime(array $analytics): float
    {
        $times = $analytics['processing_times'] ?? [];
        return count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0.0;
    }

    /**
     * Identify Peak Hours
     *
     * Analyzes transfer data to identify peak usage hours
     *
     * @param array $analytics Analytics data
     *
     * @return array Peak hours data
     */
    private function identifyPeakHours(array $analytics): array
    {
        // Implementation would analyze hourly distribution
        return $this->analyticsService->identifyPeakHours($analytics);
    }

    /**
     * Analyze Store Patterns
     *
     * Identifies patterns in store-to-store transfers
     *
     * @param array $analytics Analytics data
     *
     * @return array Store pattern analysis
     */
    private function analyzeStorePatterns(array $analytics): array
    {
        return $this->analyticsService->analyzeStorePatterns($analytics);
    }

    /**
     * Calculate Response Time Percentiles
     *
     * Computes p50, p95, p99 percentiles for response times
     *
     * @param array $responseTimes Array of response times
     *
     * @return array Percentile values
     */
    private function calculateResponseTimePercentiles(array $responseTimes): array
    {
        if (empty($responseTimes)) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0];
        }

        sort($responseTimes);
        $count = count($responseTimes);

        return [
            'p50' => $responseTimes[(int)($count * 0.50)] ?? 0,
            'p95' => $responseTimes[(int)($count * 0.95)] ?? 0,
            'p99' => $responseTimes[(int)($count * 0.99)] ?? 0
        ];
    }

    /**
     * Calculate Error Rate
     *
     * Computes error rate percentage from API metrics
     *
     * @param array $metrics API metrics data
     *
     * @return float Error rate percentage
     */
    private function calculateErrorRate(array $metrics): float
    {
        $total = ($metrics['total_requests'] ?? 0);
        $errors = ($metrics['error_count'] ?? 0);
        return $total > 0 ? round($errors / $total * 100, 2) : 0.0;
    }

    /**
     * Calculate API Costs
     *
     * Estimates API usage costs based on call volume
     *
     * @param array $metrics API metrics data
     *
     * @return array Cost breakdown
     */
    private function calculateApiCosts(array $metrics): array
    {
        return $this->analyticsService->calculateApiCosts($metrics);
    }

    /**
     * Analyze Rate Limit Usage
     *
     * Analyzes how close API usage is to rate limits
     *
     * @param array $metrics API metrics data
     *
     * @return array Rate limit analysis
     */
    private function analyzeRateLimitUsage(array $metrics): array
    {
        return $this->analyticsService->analyzeRateLimitUsage($metrics);
    }

    /**
     * Get Response Time Statistics
     *
     * Retrieves response time statistics for date range
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Response time stats
     */
    private function getResponseTimeStats(string $startDate, string $endDate): array
    {
        return $this->analyticsService->getResponseTimeStats($startDate, $endDate);
    }

    /**
     * Get Database Performance Statistics
     *
     * Retrieves database query performance metrics
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Database performance stats
     */
    private function getDatabasePerformanceStats(string $startDate, string $endDate): array
    {
        return $this->analyticsService->getDatabasePerformanceStats($startDate, $endDate);
    }

    /**
     * Get Resource Usage Statistics
     *
     * Retrieves system resource usage metrics
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Resource usage stats
     */
    private function getResourceUsageStats(string $startDate, string $endDate): array
    {
        return $this->analyticsService->getResourceUsageStats($startDate, $endDate);
    }

    /**
     * Get Slow Queries
     *
     * Retrieves list of slow database queries
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Slow query list
     */
    private function getSlowQueries(string $startDate, string $endDate): array
    {
        return $this->analyticsService->getSlowQueries($startDate, $endDate);
    }

    /**
     * Identify Bottlenecks
     *
     * Identifies performance bottlenecks in the system
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return array Bottleneck analysis
     */
    private function identifyBottlenecks(string $startDate, string $endDate): array
    {
        return $this->analyticsService->identifyBottlenecks($startDate, $endDate);
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
    private function generateReportData(string $reportType, string $startDate, string $endDate): array
    {
        return $this->analyticsService->generateReportData($reportType, $startDate, $endDate);
    }

    /**
     * Export to CSV
     *
     * Exports report data to CSV format
     *
     * @param array  $data       Report data
     * @param string $reportType Report type
     * @param string $startDate  Start date
     * @param string $endDate    End date
     *
     * @return void Sends CSV file
     */
    private function exportToCsv(array $data, string $reportType, string $startDate, string $endDate): void
    {
        $filename = "{$reportType}_report_{$startDate}_to_{$endDate}.csv";

        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        $output = fopen('php://output', 'w');

        // Write headers
        if (!empty($data) && is_array($data[0])) {
            fputcsv($output, array_keys($data[0]));
        }

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Export to PDF
     *
     * Exports report data to PDF format
     *
     * @param array  $data       Report data
     * @param string $reportType Report type
     * @param string $startDate  Start date
     * @param string $endDate    End date
     *
     * @return void Sends PDF file
     */
    private function exportToPdf(array $data, string $reportType, string $startDate, string $endDate): void
    {
        // PDF generation would use a library like TCPDF or FPDF
        // For now, returning JSON with PDF intent
        $this->jsonSuccess([
            'message' => 'PDF export functionality coming soon',
            'data' => $data
        ]);
    }

    /**
     * Export to Excel
     *
     * Exports report data to Excel format
     *
     * @param array  $data       Report data
     * @param string $reportType Report type
     * @param string $startDate  Start date
     * @param string $endDate    End date
     *
     * @return void Sends Excel file
     */
    private function exportToExcel(array $data, string $reportType, string $startDate, string $endDate): void
    {
        // Excel generation would use PhpSpreadsheet library
        // For now, returning JSON with Excel intent
        $this->jsonSuccess([
            'message' => 'Excel export functionality coming soon',
            'data' => $data
        ]);
    }

    /**
     * Export to JSON
     *
     * Exports report data to JSON format
     *
     * @param array  $data       Report data
     * @param string $reportType Report type
     * @param string $startDate  Start date
     * @param string $endDate    End date
     *
     * @return void Sends JSON file
     */
    private function exportToJson(array $data, string $reportType, string $startDate, string $endDate): void
    {
        $filename = "{$reportType}_report_{$startDate}_to_{$endDate}.json";

        header('Content-Type: application/json');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Validate Date Range
     *
     * Validates that date range is valid and reasonable
     *
     * @param string $startDate Start date
     * @param string $endDate   End date
     *
     * @return bool True if valid
     */
    private function validateDateRange(string $startDate, string $endDate): bool
    {
        // Validate date formats
        if (!strtotime($startDate) || !strtotime($endDate)) {
            return false;
        }

        // Ensure start is before end
        if (strtotime($startDate) > strtotime($endDate)) {
            return false;
        }

        // Ensure range is not too large (max 1 year)
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
        if ($daysDiff > 365) {
            return false;
        }

        return true;
    }

    /**
     * Validate Query Safety
     *
     * Ensures custom queries don't contain dangerous operations
     *
     * @param string $queryType Query type
     * @param array  $metrics   Metrics to query
     * @param array  $filters   Query filters
     *
     * @return bool True if safe
     */
    private function validateQuerySafety(string $queryType, array $metrics, array $filters): bool
    {
        // Disallow destructive operations
        $dangerousKeywords = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'UPDATE'];

        $queryString = strtoupper($queryType . implode('', $metrics) . json_encode($filters));

        foreach ($dangerousKeywords as $keyword) {
            if (strpos($queryString, $keyword) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if Request is AJAX
     *
     * Determines if the current request is an AJAX request
     *
     * @return bool True if AJAX request
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
