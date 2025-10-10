<?php
/**
 * Analytics Routes Configuration
 *
 * Defines all routing rules for the analytics system including:
 * - Dashboard view
 * - Data endpoints (AJAX/API)
 * - Export functionality
 * - Report scheduling
 * - Metrics collection
 *
 * @category   Routes
 * @package    VapeshedTransfer
 * @subpackage Analytics
 * @version    1.0.0
 */

use App\Controllers\Api\AnalyticsController;

/**
 * Analytics Routes
 *
 * All routes require authentication and admin privileges
 */
return [
    // ========================================
    // DASHBOARD & VIEWS
    // ========================================

    /**
     * Analytics Dashboard (Main View)
     *
     * GET /admin/analytics/dashboard
     *
     * Renders the main analytics dashboard with charts and metrics
     * Requires: Authentication, Admin role
     */
    [
        'method' => 'GET',
        'path' => '/admin/analytics/dashboard',
        'controller' => AnalyticsController::class,
        'action' => 'dashboard',
        'middleware' => ['auth', 'admin'],
        'name' => 'analytics.dashboard'
    ],

    // ========================================
    // DATA ENDPOINTS (AJAX/API)
    // ========================================

    /**
     * Get Transfer Analytics Data
     *
     * POST /admin/analytics/transfer-analytics
     *
     * Returns comprehensive transfer statistics and trends
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     * - filters (optional): JSON object with filters
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/transfer-analytics',
        'controller' => AnalyticsController::class,
        'action' => 'getTransferAnalytics',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.transfer_analytics'
    ],

    /**
     * Get API Usage Metrics
     *
     * POST /admin/analytics/api-usage-metrics
     *
     * Returns API endpoint performance and rate limit data
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     * - provider (optional): Filter by API provider
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/api-usage-metrics',
        'controller' => AnalyticsController::class,
        'action' => 'getApiUsageMetrics',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.api_usage_metrics'
    ],

    /**
     * Get Performance Metrics
     *
     * POST /admin/analytics/performance-metrics
     *
     * Returns system and database performance data
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     * - metric_type (optional): Filter by metric type
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/performance-metrics',
        'controller' => AnalyticsController::class,
        'action' => 'getPerformanceMetrics',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.performance_metrics'
    ],

    /**
     * Get Dashboard Summary
     *
     * POST /admin/analytics/dashboard-summary
     *
     * Returns high-level summary for dashboard cards
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/dashboard-summary',
        'controller' => AnalyticsController::class,
        'action' => 'getDashboardSummary',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.dashboard_summary'
    ],

    /**
     * Get Transfer Trends
     *
     * POST /admin/analytics/transfer-trends
     *
     * Returns time-series data for transfer volume
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     * - granularity (optional): hourly, daily, weekly, monthly
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/transfer-trends',
        'controller' => AnalyticsController::class,
        'action' => 'getTransferTrends',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.transfer_trends'
    ],

    /**
     * Get Top Transfer Routes
     *
     * POST /admin/analytics/top-routes
     *
     * Returns most frequently used transfer routes
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     * - limit (optional): Number of routes (default: 10)
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/top-routes',
        'controller' => AnalyticsController::class,
        'action' => 'getTopRoutes',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.top_routes'
    ],

    /**
     * Get Performance Bottlenecks
     *
     * POST /admin/analytics/bottlenecks
     *
     * Returns identified performance issues and recommendations
     * Request params:
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     * - severity (optional): Filter by severity level
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/bottlenecks',
        'controller' => AnalyticsController::class,
        'action' => 'getBottlenecks',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.bottlenecks'
    ],

    // ========================================
    // EXPORT & REPORTING
    // ========================================

    /**
     * Export Analytics Report
     *
     * POST /admin/analytics/export-report
     *
     * Generates and downloads analytics report
     * Request params:
     * - format (required): csv, pdf, excel, json
     * - report_type (required): full, transfer_summary, api_usage, performance
     * - start_date (required): YYYY-MM-DD
     * - end_date (required): YYYY-MM-DD
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/export-report',
        'controller' => AnalyticsController::class,
        'action' => 'exportReport',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.export_report'
    ],

    /**
     * Schedule Report
     *
     * POST /admin/analytics/schedule-report
     *
     * Creates a scheduled report configuration
     * Request params:
     * - name (required): Report name
     * - report_type (required): Report type
     * - format (required): Output format
     * - frequency (required): daily, weekly, monthly
     * - recipients (required): JSON array of email addresses
     * - filters (optional): JSON object with filters
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/schedule-report',
        'controller' => AnalyticsController::class,
        'action' => 'scheduleReport',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.schedule_report'
    ],

    /**
     * Get Scheduled Reports
     *
     * GET /admin/analytics/scheduled-reports
     *
     * Returns list of configured scheduled reports
     */
    [
        'method' => 'GET',
        'path' => '/admin/analytics/scheduled-reports',
        'controller' => AnalyticsController::class,
        'action' => 'getScheduledReports',
        'middleware' => ['auth', 'admin'],
        'name' => 'analytics.scheduled_reports_list'
    ],

    /**
     * Update Scheduled Report
     *
     * POST /admin/analytics/scheduled-reports/{id}/update
     *
     * Updates an existing scheduled report
     * Request params: Same as schedule-report
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/scheduled-reports/{id}/update',
        'controller' => AnalyticsController::class,
        'action' => 'updateScheduledReport',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.update_scheduled_report'
    ],

    /**
     * Delete Scheduled Report
     *
     * POST /admin/analytics/scheduled-reports/{id}/delete
     *
     * Deletes a scheduled report
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/scheduled-reports/{id}/delete',
        'controller' => AnalyticsController::class,
        'action' => 'deleteScheduledReport',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.delete_scheduled_report'
    ],

    // ========================================
    // METRICS COLLECTION (Internal Use)
    // ========================================

    /**
     * Record Transfer Metric
     *
     * POST /api/analytics/record-transfer
     *
     * Internal endpoint for recording transfer metrics
     * Called automatically by transfer system
     */
    [
        'method' => 'POST',
        'path' => '/api/analytics/record-transfer',
        'controller' => AnalyticsController::class,
        'action' => 'recordTransferMetric',
        'middleware' => ['csrf'],
        'name' => 'analytics.record_transfer'
    ],

    /**
     * Record API Usage Metric
     *
     * POST /api/analytics/record-api-usage
     *
     * Internal endpoint for recording API usage
     * Called automatically by API wrapper
     */
    [
        'method' => 'POST',
        'path' => '/api/analytics/record-api-usage',
        'controller' => AnalyticsController::class,
        'action' => 'recordApiUsageMetric',
        'middleware' => ['csrf'],
        'name' => 'analytics.record_api_usage'
    ],

    /**
     * Record Performance Metric
     *
     * POST /api/analytics/record-performance
     *
     * Internal endpoint for recording performance data
     * Called by performance monitoring hooks
     */
    [
        'method' => 'POST',
        'path' => '/api/analytics/record-performance',
        'controller' => AnalyticsController::class,
        'action' => 'recordPerformanceMetric',
        'middleware' => ['csrf'],
        'name' => 'analytics.record_performance'
    ],

    // ========================================
    // MAINTENANCE & UTILITIES
    // ========================================

    /**
     * Clean Old Metrics
     *
     * POST /admin/analytics/clean-old-metrics
     *
     * Removes metrics older than specified days
     * Request params:
     * - days_to_keep (optional): Number of days (default: 90)
     */
    [
        'method' => 'POST',
        'path' => '/admin/analytics/clean-old-metrics',
        'controller' => AnalyticsController::class,
        'action' => 'cleanOldMetrics',
        'middleware' => ['auth', 'admin', 'csrf'],
        'name' => 'analytics.clean_old_metrics'
    ],

    /**
     * Get Analytics Health
     *
     * GET /admin/analytics/health
     *
     * Returns health status of analytics system
     */
    [
        'method' => 'GET',
        'path' => '/admin/analytics/health',
        'controller' => AnalyticsController::class,
        'action' => 'getHealth',
        'middleware' => ['auth', 'admin'],
        'name' => 'analytics.health'
    ],

    /**
     * Get Table Statistics
     *
     * GET /admin/analytics/table-stats
     *
     * Returns row counts and sizes for analytics tables
     */
    [
        'method' => 'GET',
        'path' => '/admin/analytics/table-stats',
        'controller' => AnalyticsController::class,
        'action' => 'getTableStatistics',
        'middleware' => ['auth', 'admin'],
        'name' => 'analytics.table_stats'
    ]
];
