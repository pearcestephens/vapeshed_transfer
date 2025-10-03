<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - The Vape Shed</title>
    
    <!-- Advanced CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/executive-dashboard.css" rel="stylesheet">
    
    <!-- Chart.js for advanced analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Real-time updates -->
    <script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
</head>
<body class="bg-dark text-light" data-theme="dark">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/">
            <i class="fas fa-brain me-2"></i>Executive Intelligence
        </a>
        
        <div class="navbar-nav ms-auto d-flex flex-row">
            <!-- System Status Indicators -->
            <div class="nav-item me-3">
                <span class="badge bg-success pulse" id="system-status">
                    <i class="fas fa-circle me-1"></i>LIVE
                </span>
            </div>
            
            <!-- Real-time Revenue -->
            <div class="nav-item me-3">
                <span class="text-light">
                    <i class="fas fa-dollar-sign me-1"></i>
                    <span id="live-revenue">$<?= number_format($data['summary_metrics']['total_revenue_30d'] ?? 0, 0) ?></span>
                </span>
            </div>
            
            <!-- Configuration -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-cog"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end bg-dark">
                    <li><a class="dropdown-item text-light" href="/dashboard/configuration">
                        <i class="fas fa-sliders-h me-2"></i>System Config
                    </a></li>
                    <li><a class="dropdown-item text-light" href="#" data-bs-toggle="modal" data-bs-target="#themeModal">
                        <i class="fas fa-palette me-2"></i>Theme Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="toggleKillSwitch()">
                        <i class="fas fa-stop-circle me-2"></i>Emergency Stop
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Main Dashboard Container -->
<div class="container-fluid mt-5 pt-3">
    
    <!-- Alert Bar -->
    <?php if (!empty($data['alerts'])): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert-ticker bg-warning text-dark rounded p-2">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <marquee behavior="scroll" direction="left" scrollamount="3">
                    <?php foreach ($data['alerts'] as $alert): ?>
                        <?= htmlspecialchars($alert['message']) ?> &nbsp;&nbsp;&nbsp; | &nbsp;&nbsp;&nbsp;
                    <?php endforeach; ?>
                </marquee>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Executive Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-gradient-primary border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title text-white-50 mb-1">Today's Revenue</h6>
                        <h3 class="text-white mb-0" id="today-revenue">
                            $<?= number_format($data['real_time_stats']['today_sales']['revenue'] ?? 0, 0) ?>
                        </h3>
                        <small class="text-white-50">
                            <?= $data['real_time_stats']['today_sales']['transaction_count'] ?? 0 ?> transactions
                        </small>
                    </div>
                    <div class="text-white-50">
                        <i class="fas fa-cash-register fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-gradient-success border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title text-white-50 mb-1">Autonomous Actions</h6>
                        <h3 class="text-white mb-0" id="autonomous-actions">
                            <?= ($data['system_status']['autonomous_engine']['recent_transfers'] ?? 0) + 
                                ($data['system_status']['autonomous_engine']['recent_price_changes'] ?? 0) ?>
                        </h3>
                        <small class="text-white-50">Last 24 hours</small>
                    </div>
                    <div class="text-white-50">
                        <i class="fas fa-robot fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-gradient-info border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title text-white-50 mb-1">Profit Impact</h6>
                        <h3 class="text-white mb-0" id="profit-impact">
                            $<?= number_format($data['system_status']['autonomous_engine']['recent_profit_impact'] ?? 0, 0) ?>
                        </h3>
                        <small class="text-white-50">Algorithm generated</small>
                    </div>
                    <div class="text-white-50">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card bg-gradient-warning border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="flex-grow-1">
                        <h6 class="card-title text-white-50 mb-1">System Health</h6>
                        <h3 class="text-white mb-0" id="system-health">
                            <?= $data['performance']['cache_hit_rate'] ?? 95 ?>%
                        </h3>
                        <small class="text-white-50">All systems operational</small>
                    </div>
                    <div class="text-white-50">
                        <i class="fas fa-server fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Grid -->
    <div class="row">
        
        <!-- Left Column: Charts & Analytics -->
        <div class="col-lg-8">
            
            <!-- Revenue Trend Chart -->
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-transparent border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-area me-2 text-primary"></i>Revenue Analytics
                    </h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="revenueRange" id="revenue24h" autocomplete="off" checked>
                        <label class="btn btn-outline-primary" for="revenue24h">24H</label>
                        
                        <input type="radio" class="btn-check" name="revenueRange" id="revenue7d" autocomplete="off">
                        <label class="btn btn-outline-primary" for="revenue7d">7D</label>
                        
                        <input type="radio" class="btn-check" name="revenueRange" id="revenue30d" autocomplete="off">
                        <label class="btn btn-outline-primary" for="revenue30d">30D</label>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
            
            <!-- Competitive Intelligence Panel -->
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-transparent border-secondary">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-eye me-2 text-danger"></i>Competitive Intelligence
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Price Opportunities</h6>
                            <div id="price-opportunities">
                                <!-- Dynamic content -->
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Competitive Threats</h6>
                            <div id="competitive-threats">
                                <!-- Dynamic content -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Right Column: System Status & Controls -->
        <div class="col-lg-4">
            
            <!-- System Status Panel -->
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-transparent border-secondary">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs me-2 text-success"></i>System Status
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Autonomous Engine -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-1">Autonomous Engine</h6>
                            <small class="text-muted">Profit optimization AI</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-<?= $data['system_status']['autonomous_engine']['status'] === 'disabled' ? 'danger' : 'success' ?>">
                                <?= strtoupper($data['system_status']['autonomous_engine']['status'] ?? 'UNKNOWN') ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Competitor Crawler -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-1">Competitor Crawler</h6>
                            <small class="text-muted"><?= $data['system_status']['competitor_crawler']['success_rate'] ?? 0 ?>% success rate</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">ACTIVE</span>
                        </div>
                    </div>
                    
                    <!-- Transfer Engine -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-1">Transfer Engine</h6>
                            <small class="text-muted">Stock optimization</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">READY</span>
                        </div>
                    </div>
                    
                    <!-- Pricing Engine -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Pricing Engine</h6>
                            <small class="text-muted">Dynamic pricing AI</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-success">ACTIVE</span>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card bg-dark border-secondary mb-4">
                <div class="card-header bg-transparent border-secondary">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="runOptimizationCycle()">
                            <i class="fas fa-play me-2"></i>Run Optimization
                        </button>
                        <button class="btn btn-info" onclick="updateCompetitorData()">
                            <i class="fas fa-download me-2"></i>Update Competitor Data
                        </button>
                        <button class="btn btn-success" onclick="generateReport()">
                            <i class="fas fa-file-pdf me-2"></i>Generate Report
                        </button>
                        <button class="btn btn-warning" onclick="openConfigModal()">
                            <i class="fas fa-sliders-h me-2"></i>Configure Systems
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-transparent border-secondary">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2 text-info"></i>Recent Activity
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach (array_slice($data['recent_actions'] ?? [], 0, 10) as $action): ?>
                        <div class="list-group-item bg-dark border-secondary text-light">
                            <div class="d-flex w-100 justify-content-between">
                                <small class="mb-1">
                                    <i class="fas fa-<?= $action['action_type'] === 'transfer' ? 'truck' : ($action['action_type'] === 'price_change' ? 'tag' : 'spider') ?> me-2"></i>
                                    <?= htmlspecialchars($action['description']) ?>
                                </small>
                                <small class="text-muted"><?= date('H:i', strtotime($action['timestamp'])) ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
</div>

<!-- Configuration Modal -->
<div class="modal fade" id="configModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">System Configuration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Configuration content will be loaded dynamically -->
                <div id="configContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Theme Modal -->
<div class="modal fade" id="themeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Theme Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="themeSelect" class="form-label">Dashboard Theme</label>
                    <select class="form-select bg-dark text-light border-secondary" id="themeSelect">
                        <option value="dark">Dark Mode</option>
                        <option value="light">Light Mode</option>
                        <option value="auto">Auto (System)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="refreshInterval" class="form-label">Refresh Interval</label>
                    <select class="form-select bg-dark text-light border-secondary" id="refreshInterval">
                        <option value="10000">10 seconds</option>
                        <option value="30000" selected>30 seconds</option>
                        <option value="60000">1 minute</option>
                        <option value="300000">5 minutes</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveThemeSettings()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/executive-dashboard.js"></script>

<script>
// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
    startRealTimeUpdates();
    initializeCharts();
});

// Dashboard configuration
const dashboardConfig = <?= json_encode($config) ?>;
const dashboardData = <?= json_encode($data) ?>;

// Real-time update interval
let updateInterval;

function initializeDashboard() {
    console.log('Executive Dashboard initialized');
    console.log('Performance: Data loaded in', '<?= $data['performance']['data_load_time'] ?>');
}

function startRealTimeUpdates() {
    updateInterval = setInterval(updateDashboardData, dashboardConfig.refresh_interval);
}

function updateDashboardData() {
    axios.get('/api/dashboard/realtime')
        .then(response => {
            if (response.data.success) {
                updateDisplayValues(response.data.data);
            }
        })
        .catch(error => {
            console.error('Failed to update dashboard data:', error);
        });
}

function updateDisplayValues(data) {
    // Update live values
    if (data.today_revenue) {
        document.getElementById('today-revenue').textContent = '$' + Number(data.today_revenue).toLocaleString();
    }
    
    if (data.autonomous_actions) {
        document.getElementById('autonomous-actions').textContent = data.autonomous_actions;
    }
    
    if (data.profit_impact) {
        document.getElementById('profit-impact').textContent = '$' + Number(data.profit_impact).toLocaleString();
    }
}

function initializeCharts() {
    // Revenue chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: [], // Will be populated with real data
            datasets: [{
                label: 'Revenue',
                data: [],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        color: '#ffffff'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                y: {
                    ticks: {
                        color: '#ffffff',
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Quick action functions
function runOptimizationCycle() {
    if (confirm('Run autonomous optimization cycle now?')) {
        showSpinner('Running optimization...');
        
        axios.post('/api/autonomous/start', {
            dry_run: false,
            continuous_mode: false
        })
        .then(response => {
            hideSpinner();
            if (response.data.success) {
                showToast('Optimization cycle started successfully', 'success');
                setTimeout(updateDashboardData, 2000);
            } else {
                showToast('Failed to start optimization: ' + response.data.error, 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showToast('Error starting optimization: ' + error.message, 'danger');
        });
    }
}

function updateCompetitorData() {
    showSpinner('Updating competitor data...');
    
    axios.post('/api/crawler/run')
        .then(response => {
            hideSpinner();
            if (response.data.success) {
                showToast('Competitor data updated successfully', 'success');
                setTimeout(updateDashboardData, 2000);
            } else {
                showToast('Failed to update competitor data: ' + response.data.error, 'danger');
            }
        })
        .catch(error => {
            hideSpinner();
            showToast('Error updating competitor data: ' + error.message, 'danger');
        });
}

function generateReport() {
    window.open('/api/reports/executive?format=pdf', '_blank');
}

function openConfigModal() {
    const modal = new bootstrap.Modal(document.getElementById('configModal'));
    
    // Load configuration content
    axios.get('/api/config/dashboard')
        .then(response => {
            document.getElementById('configContent').innerHTML = response.data.html;
            modal.show();
        })
        .catch(error => {
            console.error('Failed to load configuration:', error);
        });
}

function toggleKillSwitch() {
    if (confirm('Are you sure you want to activate the emergency stop? This will halt all autonomous operations.')) {
        axios.post('/api/autonomous/stop')
            .then(response => {
                if (response.data.success) {
                    showToast('Emergency stop activated', 'warning');
                    setTimeout(updateDashboardData, 1000);
                } else {
                    showToast('Failed to activate emergency stop', 'danger');
                }
            })
            .catch(error => {
                showToast('Error activating emergency stop: ' + error.message, 'danger');
            });
    }
}

function saveThemeSettings() {
    const theme = document.getElementById('themeSelect').value;
    const refreshInterval = document.getElementById('refreshInterval').value;
    
    // Save to localStorage
    localStorage.setItem('dashboard_theme', theme);
    localStorage.setItem('dashboard_refresh_interval', refreshInterval);
    
    // Apply theme
    document.body.setAttribute('data-theme', theme);
    
    // Update refresh interval
    clearInterval(updateInterval);
    dashboardConfig.refresh_interval = parseInt(refreshInterval);
    startRealTimeUpdates();
    
    showToast('Theme settings saved', 'success');
    bootstrap.Modal.getInstance(document.getElementById('themeModal')).hide();
}

// Utility functions
function showSpinner(message) {
    // Implementation for loading spinner
}

function hideSpinner() {
    // Implementation to hide spinner
}

function showToast(message, type) {
    // Implementation for toast notifications
    console.log(`${type.toUpperCase()}: ${message}`);
}
</script>

</body>
<script src="/js/dashboard_intel.js" defer></script>
</html>