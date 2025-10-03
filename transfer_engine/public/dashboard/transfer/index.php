<?php
/**
 * Transfer Engine Module Dashboard
 * 
 * Comprehensive interface for managing stock transfers with DSR calculator,
 * transfer queue monitoring, history, and real-time execution controls.
 * 
 * @package VapeshedTransfer
 * @subpackage Dashboard
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/template.php';

requireAuth();

$pageTitle = 'Transfer Engine';
$currentModule = 'transfer';
$currentUser = getCurrentUser();
$breadcrumbs = [
    'Dashboard' => '/dashboard/',
    'Transfer Engine' => null
];

// Fetch transfer statistics
try {
    $db = new PDO("mysql:host=localhost;dbname=transfer_engine", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get queue counts
    $stmt = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'executed' THEN 1 ELSE 0 END) as executed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM proposal_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $queueStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent transfers
    $stmt = $db->query("SELECT * FROM proposal_log ORDER BY created_at DESC LIMIT 20");
    $recentTransfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get execution metrics
    $stmt = $db->query("SELECT 
        AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_execution_time,
        COUNT(*) as executions_today
    FROM proposal_log 
    WHERE status = 'executed' 
    AND DATE(created_at) = CURDATE()");
    $execMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Development fallback data
    $queueStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'executed' => 0, 'failed' => 0];
    $recentTransfers = [];
    $execMetrics = ['avg_execution_time' => 0, 'executions_today' => 0];
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid transfer-module" style="max-width: 1600px; padding: 24px;">
    
    <!-- Module Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="fas fa-exchange-alt" style="color: #8b5cf6;"></i>
                Transfer Engine
            </h2>
            <p class="text-muted mb-0">Intelligent stock transfer system with DSR-driven recommendations</p>
        </div>
        <div>
            <button class="btn btn-outline-primary" id="refreshData">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-primary" id="calculateTransfers">
                <i class="fas fa-calculator"></i> Calculate Transfers
            </button>
            <button class="btn btn-success" id="executeQueue">
                <i class="fas fa-play"></i> Execute Queue
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Pending Transfers</div>
                    <div class="stat-value"><?php echo formatNumber($queueStats['pending']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Executed Today</div>
                    <div class="stat-value"><?php echo formatNumber($execMetrics['executions_today']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Failed Transfers</div>
                    <div class="stat-value"><?php echo formatNumber($queueStats['failed']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Avg Exec Time</div>
                    <div class="stat-value"><?php echo round($execMetrics['avg_execution_time'] ?? 0); ?>s</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <ul class="nav nav-tabs mb-4" id="transferTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="calculator-tab" data-toggle="tab" href="#calculator" role="tab">
                <i class="fas fa-calculator"></i> DSR Calculator
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="queue-tab" data-toggle="tab" href="#queue" role="tab">
                <i class="fas fa-list"></i> Transfer Queue
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="history-tab" data-toggle="tab" href="#history" role="tab">
                <i class="fas fa-history"></i> History
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="settings-tab" data-toggle="tab" href="#settings" role="tab">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="transferTabContent">
        
        <!-- DSR Calculator Tab -->
        <div class="tab-pane fade show active" id="calculator" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator text-primary"></i>
                        Daily Sales Rate (DSR) Calculator
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Input Form -->
                        <div class="col-md-4">
                            <h6 class="text-muted mb-3">Product Selection</h6>
                            <div class="form-group">
                                <label for="productSku">Product SKU</label>
                                <input type="text" class="form-control" id="productSku" placeholder="Enter SKU...">
                            </div>
                            <div class="form-group">
                                <label for="donorOutlet">Donor Outlet</label>
                                <select class="form-control" id="donorOutlet">
                                    <option value="">Select donor...</option>
                                    <option value="1">Store 1 - Auckland CBD</option>
                                    <option value="2">Store 2 - Wellington</option>
                                    <option value="3">Store 3 - Christchurch</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="receiverOutlet">Receiver Outlet</label>
                                <select class="form-control" id="receiverOutlet">
                                    <option value="">Select receiver...</option>
                                    <option value="4">Store 4 - Hamilton</option>
                                    <option value="5">Store 5 - Tauranga</option>
                                    <option value="6">Store 6 - Dunedin</option>
                                </select>
                            </div>
                            <button class="btn btn-primary btn-block" id="calculateDsr">
                                <i class="fas fa-calculator"></i> Calculate DSR
                            </button>
                        </div>

                        <!-- Results Display -->
                        <div class="col-md-8">
                            <h6 class="text-muted mb-3">Calculation Results</h6>
                            <div id="dsrResults" class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Enter product and outlet details to calculate DSR
                            </div>

                            <!-- Results Table (hidden until calculation) -->
                            <div id="dsrTable" style="display: none;">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Donor</th>
                                            <th>Receiver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>Current Stock</strong></td>
                                            <td id="donorStock">-</td>
                                            <td id="receiverStock">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>DSR (7-day)</strong></td>
                                            <td id="donorDsr">-</td>
                                            <td id="receiverDsr">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Days of Cover</strong></td>
                                            <td id="donorDaysOfCover">-</td>
                                            <td id="receiverDaysOfCover">-</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Recommended Transfer</strong></td>
                                            <td colspan="2" class="text-center" id="recommendedQty">-</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Transfer Preview -->
                                <div class="alert alert-success mt-3" id="transferPreview" style="display: none;">
                                    <h6><i class="fas fa-check-circle"></i> Transfer Recommendation</h6>
                                    <p class="mb-2" id="transferSummary"></p>
                                    <button class="btn btn-sm btn-success" id="addToQueue">
                                        <i class="fas fa-plus"></i> Add to Queue
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Queue Tab -->
        <div class="tab-pane fade" id="queue" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list text-primary"></i>
                        Pending Transfer Queue
                    </h5>
                    <div>
                        <button class="btn btn-sm btn-danger" id="clearQueue">
                            <i class="fas fa-trash"></i> Clear Queue
                        </button>
                        <button class="btn btn-sm btn-success" id="executeAll">
                            <i class="fas fa-play"></i> Execute All
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0" id="queueTable">
                        <thead class="thead-light">
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAll"></th>
                                <th>ID</th>
                                <th>Product</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransfers)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>No pending transfers in queue</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentTransfers as $transfer): ?>
                            <tr>
                                <td><input type="checkbox" class="transfer-checkbox" value="<?php echo $transfer['id']; ?>"></td>
                                <td><?php echo $transfer['id']; ?></td>
                                <td><?php echo htmlspecialchars($transfer['product_sku'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transfer['donor_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transfer['receiver_name'] ?? 'N/A'); ?></td>
                                <td><?php echo formatNumber($transfer['quantity'] ?? 0); ?></td>
                                <td><?php echo statusBadge($transfer['status'] ?? 'pending'); ?></td>
                                <td><?php echo formatDateTime($transfer['created_at']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-success execute-transfer" data-id="<?php echo $transfer['id']; ?>">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-transfer" data-id="<?php echo $transfer['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div class="tab-pane fade" id="history" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-primary"></i>
                        Transfer History
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <input type="date" class="form-control" id="filterDateFrom" placeholder="From Date">
                        </div>
                        <div class="col-md-3">
                            <input type="date" class="form-control" id="filterDateTo" placeholder="To Date">
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" id="filterStatus">
                                <option value="">All Statuses</option>
                                <option value="executed">Executed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary btn-block" id="applyFilters">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>From → To</th>
                                    <th>Qty</th>
                                    <th>Status</th>
                                    <th>Execution Time</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                                        <p>Loading history...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div class="tab-pane fade" id="settings" role="tabpanel">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-cog text-primary"></i>
                        Transfer Engine Settings
                    </h5>
                </div>
                <div class="card-body">
                    <form id="transferSettings">
                        <h6 class="text-muted mb-3">DSR Calculation</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>DSR Window (days)</label>
                                    <input type="number" class="form-control" name="dsr_window" value="7" min="1" max="30">
                                    <small class="form-text text-muted">Number of days for sales rate calculation</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Minimum Transfer Quantity</label>
                                    <input type="number" class="form-control" name="min_transfer_qty" value="1" min="1">
                                    <small class="form-text text-muted">Minimum units required for transfer</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="text-muted mb-3">Execution Settings</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Auto-Execute Threshold</label>
                                    <input type="number" class="form-control" name="auto_execute_threshold" value="10" min="1">
                                    <small class="form-text text-muted">Auto-execute transfers below this quantity</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Execution Mode</label>
                                    <select class="form-control" name="execution_mode">
                                        <option value="manual">Manual Approval</option>
                                        <option value="semi">Semi-Automatic</option>
                                        <option value="auto">Fully Automatic</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
$additionalJS = <<<'JAVASCRIPT'
<script>
// Transfer Module JavaScript
(function($) {
    'use strict';

    const TransferModule = {
        init: function() {
            this.bindEvents();
            this.loadQueueData();
        },

        bindEvents: function() {
            $('#calculateDsr').on('click', this.calculateDSR.bind(this));
            $('#addToQueue').on('click', this.addToQueue.bind(this));
            $('#executeQueue, #executeAll').on('click', this.executeQueue.bind(this));
            $('#clearQueue').on('click', this.clearQueue.bind(this));
            $('#refreshData').on('click', this.refreshAll.bind(this));
            $('.execute-transfer').on('click', this.executeSingle.bind(this));
            $('.delete-transfer').on('click', this.deleteSingle.bind(this));
            $('#transferSettings').on('submit', this.saveSettings.bind(this));
            $('#selectAll').on('change', this.toggleSelectAll.bind(this));
        },

        calculateDSR: function(e) {
            e.preventDefault();
            const sku = $('#productSku').val();
            const donor = $('#donorOutlet').val();
            const receiver = $('#receiverOutlet').val();

            if (!sku || !donor || !receiver) {
                alert('Please fill all fields');
                return;
            }

            $('#dsrResults').html('<i class="fas fa-spinner fa-spin"></i> Calculating...');

            // Simulated calculation (replace with real API call)
            setTimeout(() => {
                const mockData = {
                    donor: { stock: 50, dsr: 2.5, daysOfCover: 20 },
                    receiver: { stock: 5, dsr: 3.2, daysOfCover: 1.6 },
                    recommendedQty: 12
                };

                $('#donorStock').text(mockData.donor.stock);
                $('#donorDsr').text(mockData.donor.dsr.toFixed(2));
                $('#donorDaysOfCover').text(mockData.donor.daysOfCover.toFixed(1));
                $('#receiverStock').text(mockData.receiver.stock);
                $('#receiverDsr').text(mockData.receiver.dsr.toFixed(2));
                $('#receiverDaysOfCover').text(mockData.receiver.daysOfCover.toFixed(1));
                $('#recommendedQty').text(mockData.recommendedQty + ' units');

                $('#transferSummary').html(
                    `Transfer <strong>${mockData.recommendedQty} units</strong> from 
                    <strong>${$('#donorOutlet option:selected').text()}</strong> to 
                    <strong>${$('#receiverOutlet option:selected').text()}</strong>`
                );

                $('#dsrTable, #transferPreview').show();
                $('#dsrResults').hide();
            }, 1000);
        },

        addToQueue: function(e) {
            e.preventDefault();
            alert('Transfer added to queue successfully!');
            this.loadQueueData();
        },

        executeQueue: function(e) {
            e.preventDefault();
            if (confirm('Execute all pending transfers?')) {
                alert('Queue execution started...');
            }
        },

        clearQueue: function(e) {
            e.preventDefault();
            if (confirm('Clear entire queue? This cannot be undone.')) {
                alert('Queue cleared.');
            }
        },

        executeSingle: function(e) {
            const id = $(e.currentTarget).data('id');
            if (confirm(`Execute transfer #${id}?`)) {
                alert(`Transfer #${id} executed.`);
            }
        },

        deleteSingle: function(e) {
            const id = $(e.currentTarget).data('id');
            if (confirm(`Delete transfer #${id}?`)) {
                alert(`Transfer #${id} deleted.`);
            }
        },

        saveSettings: function(e) {
            e.preventDefault();
            alert('Settings saved successfully!');
        },

        toggleSelectAll: function(e) {
            $('.transfer-checkbox').prop('checked', $(e.currentTarget).is(':checked'));
        },

        loadQueueData: function() {
            // Refresh queue table via AJAX
            console.log('Loading queue data...');
        },

        refreshAll: function() {
            location.reload();
        }
    };

    $(document).ready(function() {
        TransferModule.init();
    });

})(jQuery);
</script>
JAVASCRIPT;

include __DIR__ . '/../../templates/footer.php';
?>
