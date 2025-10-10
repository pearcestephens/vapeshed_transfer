<?php
/**
 * Analytics Dashboard View
 *
 * Comprehensive analytics interface with interactive charts,
 * metrics cards, trend analysis, and export capabilities
 *
 * @category   Views
 * @package    VapeshedTransfer
 * @subpackage Admin\Analytics
 */

// Security: Ensure this file is included within the application context
if (!defined('APP_ROOT')) {
    die('Direct access not permitted');
}

// Extract view data
$overview = $data['overview'] ?? [];
$transfers = $data['transfers'] ?? [];
$apiUsage = $data['api_usage'] ?? [];
$performance = $data['performance'] ?? [];
$trends = $data['trends'] ?? [];
$dateRanges = $data['date_ranges'] ?? [];
$currentRange = $data['current_range'] ?? 'last_30_days';
$startDate = $data['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $data['end_date'] ?? date('Y-m-d');
?>

<!-- Analytics Dashboard Header -->
<div class="analytics-header">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h1 class="page-title">
                <i class="fas fa-chart-line text-primary"></i>
                Analytics Dashboard
            </h1>
            <p class="text-muted">
                Comprehensive insights into transfer operations, API usage, and system performance
            </p>
        </div>
        <div class="col-md-6 text-end">
            <!-- Date Range Selector -->
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-calendar"></i>
                    <?php echo htmlspecialchars($dateRanges[$currentRange] ?? 'Select Range'); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php foreach ($dateRanges as $key => $label): ?>
                        <li>
                            <a class="dropdown-item <?php echo $key === $currentRange ? 'active' : ''; ?>"
                               href="#"
                               data-range="<?php echo htmlspecialchars($key); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Export Button -->
            <div class="btn-group ms-2" role="group">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" data-export="csv"><i class="fas fa-file-csv"></i> CSV</a></li>
                    <li><a class="dropdown-item" href="#" data-export="pdf"><i class="fas fa-file-pdf"></i> PDF</a></li>
                    <li><a class="dropdown-item" href="#" data-export="excel"><i class="fas fa-file-excel"></i> Excel</a></li>
                    <li><a class="dropdown-item" href="#" data-export="json"><i class="fas fa-file-code"></i> JSON</a></li>
                </ul>
            </div>

            <!-- Schedule Report Button -->
            <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#scheduleReportModal">
                <i class="fas fa-clock"></i> Schedule Report
            </button>
        </div>
    </div>

    <!-- Custom Date Range Picker (hidden by default) -->
    <div id="customDateRange" class="card mb-4" style="display: none;">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="customStartDate" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="customStartDate" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-4">
                    <label for="customEndDate" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="customEndDate" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-primary d-block w-100" id="applyCustomRange">
                        <i class="fas fa-check"></i> Apply Range
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overview Metrics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Transfers Card -->
    <div class="col-lg-3 col-md-6">
        <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="text-muted mb-0">Total Transfers</h6>
                        <h2 class="mt-2 mb-0"><?php echo number_format($overview['total_transfers'] ?? 0); ?></h2>
                    </div>
                    <div class="metric-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <?php
                    $comparison = $overview['comparison']['transfers_change'] ?? 0;
                    $changeClass = $comparison >= 0 ? 'text-success' : 'text-danger';
                    $changeIcon = $comparison >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                    ?>
                    <span class="<?php echo $changeClass; ?> me-2">
                        <i class="fas <?php echo $changeIcon; ?>"></i>
                        <?php echo abs($comparison); ?>%
                    </span>
                    <small class="text-muted">vs previous period</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Rate Card -->
    <div class="col-lg-3 col-md-6">
        <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="text-muted mb-0">Success Rate</h6>
                        <h2 class="mt-2 mb-0"><?php echo number_format($overview['success_rate'] ?? 0, 1); ?>%</h2>
                    </div>
                    <div class="metric-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" role="progressbar"
                         style="width: <?php echo $overview['success_rate'] ?? 0; ?>%"
                         aria-valuenow="<?php echo $overview['success_rate'] ?? 0; ?>"
                         aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Avg Processing Time Card -->
    <div class="col-lg-3 col-md-6">
        <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="text-muted mb-0">Avg Processing Time</h6>
                        <h2 class="mt-2 mb-0"><?php echo number_format($overview['avg_processing_time'] ?? 0, 2); ?>s</h2>
                    </div>
                    <div class="metric-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    Time from creation to completion
                </small>
            </div>
        </div>
    </div>

    <!-- API Calls Card -->
    <div class="col-lg-3 col-md-6">
        <div class="card metric-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="text-muted mb-0">API Calls</h6>
                        <h2 class="mt-2 mb-0"><?php echo number_format($overview['total_api_calls'] ?? 0); ?></h2>
                    </div>
                    <div class="metric-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-network-wired"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-success me-2">
                        <?php echo number_format($overview['api_success_rate'] ?? 0, 1); ?>% success
                    </span>
                    <small class="text-muted">rate</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Transfer Volume Chart -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">
                    <i class="fas fa-chart-area text-primary"></i>
                    Transfer Volume Trend
                </h5>
            </div>
            <div class="card-body">
                <canvas id="transferVolumeChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Chart -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie text-success"></i>
                    Status Breakdown
                </h5>
            </div>
            <div class="card-body">
                <canvas id="statusBreakdownChart"></canvas>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Successful</span>
                        <span class="fw-bold text-success">
                            <?php echo number_format($overview['successful_transfers'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Failed</span>
                        <span class="fw-bold text-danger">
                            <?php echo number_format($overview['failed_transfers'] ?? 0); ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Pending</span>
                        <span class="fw-bold text-warning">
                            <?php echo number_format($overview['pending_transfers'] ?? 0); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Analytics Tabs -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="transfers-tab" data-bs-toggle="tab"
                        data-bs-target="#transfers-panel" type="button" role="tab">
                    <i class="fas fa-exchange-alt"></i> Transfer Analytics
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="api-tab" data-bs-toggle="tab"
                        data-bs-target="#api-panel" type="button" role="tab">
                    <i class="fas fa-network-wired"></i> API Usage
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="performance-tab" data-bs-toggle="tab"
                        data-bs-target="#performance-panel" type="button" role="tab">
                    <i class="fas fa-tachometer-alt"></i> Performance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="insights-tab" data-bs-toggle="tab"
                        data-bs-target="#insights-panel" type="button" role="tab">
                    <i class="fas fa-lightbulb"></i> Insights
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            <!-- Transfer Analytics Panel -->
            <div class="tab-pane fade show active" id="transfers-panel" role="tabpanel">
                <div class="row g-4">
                    <!-- Top Routes -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Top Transfer Routes</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Route</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Success Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="topRoutesTableBody">
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Peak Hours -->
                    <div class="col-md-6">
                        <h6 class="mb-3">Peak Transfer Hours</h6>
                        <canvas id="peakHoursChart" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- API Usage Panel -->
            <div class="tab-pane fade" id="api-panel" role="tabpanel">
                <div class="row g-4">
                    <!-- Endpoint Stats -->
                    <div class="col-md-8">
                        <h6 class="mb-3">Top API Endpoints</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Endpoint</th>
                                        <th class="text-end">Calls</th>
                                        <th class="text-end">Avg Response</th>
                                        <th class="text-end">Error Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="endpointStatsTableBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Rate Limit Usage -->
                    <div class="col-md-4">
                        <h6 class="mb-3">Rate Limit Usage</h6>
                        <div id="rateLimitWidget">
                            <div class="text-center text-muted">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Panel -->
            <div class="tab-pane fade" id="performance-panel" role="tabpanel">
                <div class="row g-4">
                    <!-- Response Time Chart -->
                    <div class="col-md-12">
                        <h6 class="mb-3">Response Time Percentiles</h6>
                        <canvas id="responseTimeChart" height="80"></canvas>
                    </div>

                    <!-- Slow Queries -->
                    <div class="col-md-12">
                        <h6 class="mb-3">Slow Queries</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Query</th>
                                        <th class="text-end">Avg Time</th>
                                        <th class="text-end">Max Time</th>
                                        <th class="text-end">Count</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="slowQueriesTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Insights Panel -->
            <div class="tab-pane fade" id="insights-panel" role="tabpanel">
                <div class="row g-4">
                    <!-- Bottlenecks -->
                    <div class="col-md-12">
                        <h6 class="mb-3">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            Identified Bottlenecks
                        </h6>
                        <div id="bottlenecksContainer">
                            <div class="text-center text-muted">Loading...</div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="col-md-12">
                        <h6 class="mb-3">
                            <i class="fas fa-magic text-primary"></i>
                            Optimization Recommendations
                        </h6>
                        <div id="recommendationsContainer">
                            <div class="text-center text-muted">Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Report Modal -->
<div class="modal fade" id="scheduleReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-clock"></i>
                    Schedule Automated Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleReportForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

                    <div class="mb-3">
                        <label for="reportType" class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" name="report_type" required>
                            <option value="transfers">Transfer Analytics</option>
                            <option value="api_usage">API Usage</option>
                            <option value="performance">Performance Metrics</option>
                            <option value="full">Full Report (All Data)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reportFrequency" class="form-label">Frequency</label>
                        <select class="form-select" id="reportFrequency" name="frequency" required>
                            <option value="daily">Daily</option>
                            <option value="weekly" selected>Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reportFormat" class="form-label">Format</label>
                        <select class="form-select" id="reportFormat" name="format" required>
                            <option value="pdf" selected>PDF</option>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="reportRecipients" class="form-label">Email Recipients</label>
                        <input type="text" class="form-control" id="reportRecipients"
                               name="recipients" placeholder="email1@example.com, email2@example.com" required>
                        <small class="form-text text-muted">
                            Separate multiple emails with commas
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveScheduledReport">
                    <i class="fas fa-save"></i> Schedule Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for JavaScript -->
<script type="application/json" id="analyticsData">
<?php echo json_encode([
    'overview' => $overview,
    'transfers' => $transfers,
    'apiUsage' => $apiUsage,
    'performance' => $performance,
    'trends' => $trends,
    'dateRange' => [
        'start' => $startDate,
        'end' => $endDate,
        'current' => $currentRange
    ]
], JSON_PRETTY_PRINT); ?>
</script>

<!-- Analytics Dashboard JavaScript -->
<script src="/assets/js/analytics-dashboard.js"></script>

<style>
/* Custom Analytics Dashboard Styles */
.analytics-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
    padding: 2rem 1.5rem;
    border-radius: 0.5rem 0.5rem 0 0;
    color: white;
}

.analytics-header .page-title {
    color: white;
    margin-bottom: 0.5rem;
}

.analytics-header .text-muted {
    color: rgba(255, 255, 255, 0.8) !important;
}

.metric-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.metric-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.5rem;
}

.metric-card h2 {
    font-size: 2rem;
    font-weight: 700;
}

.nav-tabs .nav-link {
    color: #6c757d;
    border: none;
    padding: 0.75rem 1.5rem;
}

.nav-tabs .nav-link:hover {
    color: #495057;
    border: none;
}

.nav-tabs .nav-link.active {
    color: #667eea;
    background: transparent;
    border-bottom: 3px solid #667eea;
}

.table-hover tbody tr:hover {
    background-color: rgba(102, 126, 234, 0.05);
}

/* Loading animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 1.5s ease-in-out infinite;
}
</style>
