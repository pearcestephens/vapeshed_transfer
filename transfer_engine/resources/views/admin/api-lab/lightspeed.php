<?php
/**
 * Lightspeed Sync Testing Interface
 * Advanced interface for testing Lightspeed sync operations with detailed validation
 */
?>

<div class="lightspeed-sync-tester">
    <!-- Header -->
    <div class="lab-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i data-feather="refresh-cw" class="me-2"></i>
                Lightspeed Sync Tester
            </h4>
            <p class="text-muted mb-0">Test and validate Lightspeed sync operations with comprehensive reporting</p>
        </div>
        <div class="lab-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-success btn-sm" id="syncStatus">
                    <i data-feather="activity" class="me-1"></i>
                    Sync Status
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="syncLogs">
                    <i data-feather="file-text" class="me-1"></i>
                    Sync Logs
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="syncHistory">
                    <i data-feather="clock" class="me-1"></i>
                    History
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Panel: Test Configuration -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="settings" class="me-2"></i>
                        Sync Test Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Sync Type Selection -->
                    <div class="form-group mb-3">
                        <label for="syncType" class="form-label">Sync Operation Type</label>
                        <select id="syncType" class="form-select">
                            <option value="">Select Sync Type</option>
                            <optgroup label="Transfer Operations">
                                <option value="transfer_to_consignment">Transfer → Consignment</option>
                                <option value="po_to_consignment">Purchase Order → Consignment</option>
                                <option value="stock_adjustment">Stock Adjustment Sync</option>
                            </optgroup>
                            <optgroup label="Data Sync">
                                <option value="products_sync">Products Sync</option>
                                <option value="inventory_sync">Inventory Sync</option>
                                <option value="outlets_sync">Outlets Sync</option>
                                <option value="customers_sync">Customers Sync</option>
                            </optgroup>
                            <optgroup label="Events">
                                <option value="webhook_trigger">Webhook Trigger Test</option>
                                <option value="event_replay">Event Replay</option>
                            </optgroup>
                            <optgroup label="Full Pipeline">
                                <option value="full_pipeline">Complete Pipeline Test</option>
                                <option value="stress_test">Stress Test (Multiple Operations)</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Operation Parameters -->
                    <div id="operationParams">
                        <!-- Transfer Parameters -->
                        <div class="param-section" id="transferParams" style="display: none;">
                            <h6 class="mb-3">Transfer Parameters</h6>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group mb-3">
                                        <label for="sourceOutlet" class="form-label">Source Outlet</label>
                                        <select id="sourceOutlet" class="form-select">
                                            <option value="">Select Source</option>
                                            <?php foreach ($outlets ?? [] as $outlet): ?>
                                            <option value="<?= $outlet['id'] ?>"><?= htmlspecialchars($outlet['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group mb-3">
                                        <label for="targetOutlet" class="form-label">Target Outlet</label>
                                        <select id="targetOutlet" class="form-select">
                                            <option value="">Select Target</option>
                                            <?php foreach ($outlets ?? [] as $outlet): ?>
                                            <option value="<?= $outlet['id'] ?>"><?= htmlspecialchars($outlet['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="transferId" class="form-label">Transfer ID (Optional)</label>
                                <input type="text" id="transferId" class="form-control"
                                       placeholder="Leave blank to create test transfer">
                            </div>
                        </div>

                        <!-- Data Sync Parameters -->
                        <div class="param-section" id="dataSyncParams" style="display: none;">
                            <h6 class="mb-3">Data Sync Parameters</h6>
                            <div class="form-group mb-3">
                                <label for="syncDirection" class="form-label">Sync Direction</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="syncDirection" id="syncFrom" value="from_lightspeed" checked>
                                    <label class="btn btn-outline-primary" for="syncFrom">From Lightspeed</label>

                                    <input type="radio" class="btn-check" name="syncDirection" id="syncTo" value="to_lightspeed">
                                    <label class="btn btn-outline-primary" for="syncTo">To Lightspeed</label>

                                    <input type="radio" class="btn-check" name="syncDirection" id="syncBoth" value="bidirectional">
                                    <label class="btn btn-outline-primary" for="syncBoth">Bidirectional</label>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="syncLimit" class="form-label">Record Limit</label>
                                <input type="number" id="syncLimit" class="form-control" value="50" min="1" max="1000">
                                <div class="form-text">Maximum number of records to sync (for testing)</div>
                            </div>
                        </div>

                        <!-- Pipeline Parameters -->
                        <div class="param-section" id="pipelineParams" style="display: none;">
                            <h6 class="mb-3">Pipeline Test Parameters</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeValidation" checked>
                                <label class="form-check-label" for="includeValidation">
                                    Include Data Validation
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeRollback">
                                <label class="form-check-label" for="includeRollback">
                                    Test Rollback Mechanisms
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="simulateErrors">
                                <label class="form-check-label" for="simulateErrors">
                                    Simulate Error Conditions
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Test Options -->
                    <div class="form-group mb-3">
                        <label class="form-label">Test Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dryRun" checked>
                            <label class="form-check-label" for="dryRun">
                                Dry Run (No Actual Changes)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="verboseLogging" checked>
                            <label class="form-check-label" for="verboseLogging">
                                Verbose Logging
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="captureMetrics" checked>
                            <label class="form-check-label" for="captureMetrics">
                                Capture Performance Metrics
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="emailReport">
                            <label class="form-check-label" for="emailReport">
                                Email Report After Completion
                            </label>
                        </div>
                    </div>

                    <!-- Test Data Configuration -->
                    <div class="form-group mb-3">
                        <label class="form-label">Test Data Configuration</label>
                        <div class="test-data-config">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="testData" id="useExisting" value="existing" checked>
                                <label class="form-check-label" for="useExisting">
                                    Use Existing Data
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="testData" id="createSample" value="sample">
                                <label class="form-check-label" for="createSample">
                                    Create Sample Test Data
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="testData" id="useCustom" value="custom">
                                <label class="form-check-label" for="useCustom">
                                    Use Custom Test Data
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Test Data -->
                    <div class="form-group mb-3" id="customDataGroup" style="display: none;">
                        <label for="customTestData" class="form-label">Custom Test Data (JSON)</label>
                        <textarea id="customTestData" class="form-control font-monospace" rows="6"
                                  placeholder="Enter JSON test data...">{
  "products": [
    {
      "sku": "TEST-001",
      "name": "Test Product 1",
      "quantity": 10
    }
  ],
  "transfers": [
    {
      "from_outlet_id": "outlet_1",
      "to_outlet_id": "outlet_2",
      "products": ["TEST-001"]
    }
  ]
}</textarea>
                    </div>

                    <!-- Advanced Configuration -->
                    <div class="advanced-config mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Advanced Configuration</h6>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAdvanced">
                                <i data-feather="chevron-down" class="me-1"></i>
                                Show Advanced
                            </button>
                        </div>
                        <div id="advancedOptions" style="display: none;">
                            <div class="form-group mb-3">
                                <label for="syncTimeout" class="form-label">Sync Timeout (seconds)</label>
                                <input type="number" id="syncTimeout" class="form-control" value="300" min="30" max="3600">
                            </div>
                            <div class="form-group mb-3">
                                <label for="retryAttempts" class="form-label">Retry Attempts</label>
                                <input type="number" id="retryAttempts" class="form-control" value="3" min="0" max="10">
                            </div>
                            <div class="form-group mb-3">
                                <label for="batchSize" class="form-label">Batch Size</label>
                                <input type="number" id="batchSize" class="form-control" value="50" min="1" max="500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Testing & Results -->
        <div class="col-lg-6">
            <!-- Sync Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="play" class="me-2"></i>
                        Sync Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="startSyncTest">
                            <i data-feather="play" class="me-2"></i>
                            Start Sync Test
                        </button>
                        <div class="row">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-info w-100" id="validateConfig">
                                    <i data-feather="check-square" class="me-1"></i>
                                    Validate Config
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100" id="previewTest">
                                    <i data-feather="eye" class="me-1"></i>
                                    Preview Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Sync Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="zap" class="me-2"></i>
                        Manual Sync Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 manual-sync"
                                    data-action="force_sync_all">
                                <i data-feather="refresh-cw" class="me-1"></i>
                                Force Sync All
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-warning btn-sm w-100 manual-sync"
                                    data-action="sync_pending">
                                <i data-feather="clock" class="me-1"></i>
                                Sync Pending
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success btn-sm w-100 manual-sync"
                                    data-action="sync_products">
                                <i data-feather="package" class="me-1"></i>
                                Sync Products
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-info btn-sm w-100 manual-sync"
                                    data-action="sync_inventory">
                                <i data-feather="box" class="me-1"></i>
                                Sync Inventory
                            </button>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 manual-sync"
                                    data-action="force_webhook_replay">
                                <i data-feather="repeat" class="me-1"></i>
                                Force Webhook Replay
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sync Status -->
            <div class="card mb-3" id="syncStatusCard" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="activity" class="me-2"></i>
                        Sync Status
                    </h6>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="cancelSync">
                        <i data-feather="x" class="me-1"></i>
                        Cancel
                    </button>
                </div>
                <div class="card-body">
                    <div class="sync-progress mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="syncStatusText">Initializing sync...</span>
                            <span id="syncDuration" class="badge bg-secondary">0:00</span>
                        </div>
                        <div class="progress">
                            <div id="syncProgressBar" class="progress-bar" role="progressbar"
                                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    <!-- Sync Metrics -->
                    <div class="sync-metrics row">
                        <div class="col-3">
                            <div class="metric-mini text-center">
                                <div id="syncProcessed" class="h6 mb-0">0</div>
                                <small class="text-muted">Processed</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-mini text-center">
                                <div id="syncSucceeded" class="h6 mb-0 text-success">0</div>
                                <small class="text-muted">Success</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-mini text-center">
                                <div id="syncFailed" class="h6 mb-0 text-danger">0</div>
                                <small class="text-muted">Failed</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-mini text-center">
                                <div id="syncSkipped" class="h6 mb-0 text-warning">0</div>
                                <small class="text-muted">Skipped</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sync Results -->
            <div class="card" id="syncResultsCard" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="check-circle" class="me-2"></i>
                        Sync Results
                    </h6>
                    <div class="result-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="exportResults">
                            <i data-feather="download" class="me-1"></i>
                            Export
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="shareResults">
                            <i data-feather="share-2" class="me-1"></i>
                            Share
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Results Summary -->
                    <div class="results-summary row mb-3">
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="totalOperations" class="mb-1">0</h4>
                                <small class="text-muted">Total Operations</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="successRate" class="mb-1 text-success">0%</h4>
                                <small class="text-muted">Success Rate</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="avgDuration" class="mb-1">0ms</h4>
                                <small class="text-muted">Avg Duration</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="dataVolume" class="mb-1">0KB</h4>
                                <small class="text-muted">Data Volume</small>
                            </div>
                        </div>
                    </div>

                    <!-- Results Tabs -->
                    <ul class="nav nav-tabs" id="resultsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="results-summary-tab" data-bs-toggle="tab"
                                    data-bs-target="#results-summary" type="button" role="tab">
                                <i data-feather="bar-chart-2" class="me-1"></i>
                                Summary
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="results-operations-tab" data-bs-toggle="tab"
                                    data-bs-target="#results-operations" type="button" role="tab">
                                <i data-feather="list" class="me-1"></i>
                                Operations
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="results-errors-tab" data-bs-toggle="tab"
                                    data-bs-target="#results-errors" type="button" role="tab">
                                <i data-feather="alert-triangle" class="me-1"></i>
                                Errors
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="results-performance-tab" data-bs-toggle="tab"
                                    data-bs-target="#results-performance" type="button" role="tab">
                                <i data-feather="activity" class="me-1"></i>
                                Performance
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="resultsTabContent">
                        <!-- Summary -->
                        <div class="tab-pane fade show active" id="results-summary" role="tabpanel">
                            <div id="resultsSummaryContent"></div>
                        </div>

                        <!-- Operations -->
                        <div class="tab-pane fade" id="results-operations" role="tabpanel">
                            <div id="resultsOperationsContent"></div>
                        </div>

                        <!-- Errors -->
                        <div class="tab-pane fade" id="results-errors" role="tabpanel">
                            <div id="resultsErrorsContent"></div>
                        </div>

                        <!-- Performance -->
                        <div class="tab-pane fade" id="results-performance" role="tabpanel">
                            <div id="resultsPerformanceContent"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Sync Log Modal -->
    <div class="modal fade" id="liveSyncLogModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="file-text" class="me-2"></i>
                        Live Sync Log
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="log-controls mb-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="autoScroll" checked>
                                    <label class="form-check-label" for="autoScroll">
                                        Auto Scroll
                                    </label>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="clearLog">
                                    <i data-feather="trash-2" class="me-1"></i>
                                    Clear Log
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="liveSyncLog" class="sync-log font-monospace" style="height: 400px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 0.375rem;">
                        <div class="log-entry">
                            <span class="timestamp">[<?= date('H:i:s') ?>]</span>
                            <span class="level info">INFO</span>
                            <span class="message">Sync log initialized. Waiting for operations...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Preview Modal -->
    <div class="modal fade" id="testPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="eye" class="me-2"></i>
                        Sync Test Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="testPreviewContent">
                        <!-- Test preview will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="executeFromPreview">
                        <i data-feather="play" class="me-1"></i>
                        Execute Test
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lightspeed Sync Tester JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const LightspeedSyncTester = {
        syncInterval: null,
        logEventSource: null,

        init() {
            this.bindEvents();
            this.loadSyncStatus();
        },

        bindEvents() {
            // Sync type change
            document.getElementById('syncType').addEventListener('change', (e) => {
                this.updateParameterSections(e.target.value);
            });

            // Test data change
            document.querySelectorAll('input[name="testData"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    this.toggleCustomDataSection(e.target.value === 'custom');
                });
            });

            // Advanced toggle
            document.getElementById('toggleAdvanced').addEventListener('click', () => {
                this.toggleAdvancedOptions();
            });

            // Start sync test
            document.getElementById('startSyncTest').addEventListener('click', () => {
                this.startSyncTest();
            });

            // Validate config
            document.getElementById('validateConfig').addEventListener('click', () => {
                this.validateConfiguration();
            });

            // Preview test
            document.getElementById('previewTest').addEventListener('click', () => {
                this.previewTest();
            });

            // Manual sync actions
            document.querySelectorAll('.manual-sync').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.executeManualSync(e.target.dataset.action);
                });
            });

            // Cancel sync
            document.getElementById('cancelSync').addEventListener('click', () => {
                this.cancelSync();
            });

            // Sync logs
            document.getElementById('syncLogs').addEventListener('click', () => {
                this.showLiveSyncLog();
            });

            // Clear log
            document.getElementById('clearLog').addEventListener('click', () => {
                this.clearSyncLog();
            });
        },

        updateParameterSections(syncType) {
            // Hide all parameter sections
            document.querySelectorAll('.param-section').forEach(section => {
                section.style.display = 'none';
            });

            // Show relevant sections based on sync type
            if (syncType.includes('transfer') || syncType.includes('po_to_consignment')) {
                document.getElementById('transferParams').style.display = 'block';
            }

            if (syncType.includes('sync') && !syncType.includes('transfer')) {
                document.getElementById('dataSyncParams').style.display = 'block';
            }

            if (syncType.includes('pipeline') || syncType.includes('stress_test')) {
                document.getElementById('pipelineParams').style.display = 'block';
            }
        },

        toggleCustomDataSection(show) {
            document.getElementById('customDataGroup').style.display = show ? 'block' : 'none';
        },

        toggleAdvancedOptions() {
            const options = document.getElementById('advancedOptions');
            const button = document.getElementById('toggleAdvanced');
            const icon = button.querySelector('i');

            if (options.style.display === 'none') {
                options.style.display = 'block';
                button.innerHTML = '<i data-feather="chevron-up" class="me-1"></i>Hide Advanced';
                feather.replace();
            } else {
                options.style.display = 'none';
                button.innerHTML = '<i data-feather="chevron-down" class="me-1"></i>Show Advanced';
                feather.replace();
            }
        },

        async startSyncTest() {
            const config = this.collectConfiguration();

            if (!this.validateConfig(config)) {
                return;
            }

            // Show status card
            document.getElementById('syncStatusCard').style.display = 'block';
            this.updateSyncStatus('Initializing sync test...', 0);

            try {
                const response = await fetch('/admin/api-lab/lightspeed', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'start_sync_test',
                        config: config
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.startSyncMonitoring(result.result.test_id);
                } else {
                    this.showError(result.error);
                }

            } catch (error) {
                this.showError('Failed to start sync test: ' + error.message);
            }
        },

        collectConfiguration() {
            return {
                sync_type: document.getElementById('syncType').value,
                source_outlet: document.getElementById('sourceOutlet').value,
                target_outlet: document.getElementById('targetOutlet').value,
                transfer_id: document.getElementById('transferId').value,
                sync_direction: document.querySelector('input[name="syncDirection"]:checked')?.value,
                sync_limit: parseInt(document.getElementById('syncLimit').value) || 50,
                test_data: document.querySelector('input[name="testData"]:checked')?.value,
                custom_data: document.getElementById('customTestData').value,
                options: {
                    dry_run: document.getElementById('dryRun').checked,
                    verbose_logging: document.getElementById('verboseLogging').checked,
                    capture_metrics: document.getElementById('captureMetrics').checked,
                    email_report: document.getElementById('emailReport').checked,
                    include_validation: document.getElementById('includeValidation')?.checked,
                    include_rollback: document.getElementById('includeRollback')?.checked,
                    simulate_errors: document.getElementById('simulateErrors')?.checked,
                    sync_timeout: parseInt(document.getElementById('syncTimeout').value) || 300,
                    retry_attempts: parseInt(document.getElementById('retryAttempts').value) || 3,
                    batch_size: parseInt(document.getElementById('batchSize').value) || 50
                }
            };
        },

        validateConfig(config) {
            if (!config.sync_type) {
                this.showError('Please select a sync operation type');
                return false;
            }

            if (config.sync_type.includes('transfer') && (!config.source_outlet || !config.target_outlet)) {
                this.showError('Please select both source and target outlets for transfer operations');
                return false;
            }

            if (config.test_data === 'custom' && !config.custom_data) {
                this.showError('Please provide custom test data or select a different test data option');
                return false;
            }

            // Validate custom JSON if provided
            if (config.test_data === 'custom') {
                try {
                    JSON.parse(config.custom_data);
                } catch (error) {
                    this.showError('Invalid JSON in custom test data: ' + error.message);
                    return false;
                }
            }

            return true;
        },

        async validateConfiguration() {
            const config = this.collectConfiguration();

            try {
                const response = await fetch('/admin/api-lab/lightspeed', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'validate_config',
                        config: config
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showSuccess('Configuration is valid');
                    this.displayValidationResults(result.result);
                } else {
                    this.showError('Configuration validation failed: ' + result.error);
                }

            } catch (error) {
                this.showError('Failed to validate configuration: ' + error.message);
            }
        },

        startSyncMonitoring(testId) {
            this.syncInterval = setInterval(async () => {
                try {
                    const response = await fetch('/admin/api-lab/lightspeed', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: 'get_sync_status',
                            test_id: testId
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.updateSyncProgress(result.result);

                        if (result.result.status === 'completed' || result.result.status === 'failed') {
                            this.stopSyncMonitoring();
                            this.displaySyncResults(result.result);
                        }
                    }

                } catch (error) {
                    console.error('Failed to get sync status:', error);
                }
            }, 2000); // Poll every 2 seconds
        },

        stopSyncMonitoring() {
            if (this.syncInterval) {
                clearInterval(this.syncInterval);
                this.syncInterval = null;
            }
        },

        updateSyncStatus(text, progress) {
            document.getElementById('syncStatusText').textContent = text;
            document.getElementById('syncProgressBar').style.width = progress + '%';
        },

        updateSyncProgress(status) {
            // Update progress bar
            this.updateSyncStatus(status.current_operation || 'Processing...', status.progress || 0);

            // Update duration
            document.getElementById('syncDuration').textContent = this.formatDuration(status.duration || 0);

            // Update metrics
            document.getElementById('syncProcessed').textContent = status.processed || 0;
            document.getElementById('syncSucceeded').textContent = status.succeeded || 0;
            document.getElementById('syncFailed').textContent = status.failed || 0;
            document.getElementById('syncSkipped').textContent = status.skipped || 0;
        },

        displaySyncResults(results) {
            // Hide status card and show results
            document.getElementById('syncStatusCard').style.display = 'none';
            document.getElementById('syncResultsCard').style.display = 'block';

            // Update summary metrics
            document.getElementById('totalOperations').textContent = results.total_operations || 0;
            document.getElementById('successRate').textContent =
                ((results.succeeded || 0) / (results.total_operations || 1) * 100).toFixed(1) + '%';
            document.getElementById('avgDuration').textContent =
                (results.avg_duration || 0).toFixed(2) + 'ms';
            document.getElementById('dataVolume').textContent =
                this.formatBytes(results.data_volume || 0);

            // Populate result tabs
            this.populateResultTabs(results);
        },

        populateResultTabs(results) {
            // Summary tab
            this.populateSummaryTab(results);

            // Operations tab
            this.populateOperationsTab(results.operations || []);

            // Errors tab
            this.populateErrorsTab(results.errors || []);

            // Performance tab
            this.populatePerformanceTab(results.performance || {});
        },

        populateSummaryTab(results) {
            const container = document.getElementById('resultsSummaryContent');
            container.innerHTML = `
                <div class="summary-stats">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h6>Test Configuration</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Type:</strong> ${results.sync_type || 'Unknown'}</li>
                                    <li><strong>Duration:</strong> ${this.formatDuration(results.total_duration || 0)}</li>
                                    <li><strong>Dry Run:</strong> ${results.dry_run ? 'Yes' : 'No'}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h6>Performance Metrics</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Throughput:</strong> ${(results.throughput || 0).toFixed(2)} ops/sec</li>
                                    <li><strong>Peak Memory:</strong> ${this.formatBytes(results.peak_memory || 0)}</li>
                                    <li><strong>API Calls:</strong> ${results.api_calls || 0}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        populateOperationsTab(operations) {
            const container = document.getElementById('resultsOperationsContent');

            if (operations.length === 0) {
                container.innerHTML = '<div class="text-muted">No operations recorded</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-sm table-striped">';
            html += '<thead><tr><th>Operation</th><th>Status</th><th>Duration</th><th>Details</th></tr></thead><tbody>';

            operations.forEach(op => {
                const statusClass = op.status === 'success' ? 'text-success' :
                                   op.status === 'failed' ? 'text-danger' : 'text-warning';

                html += `
                    <tr>
                        <td>${this.escapeHtml(op.operation || 'Unknown')}</td>
                        <td><span class="${statusClass}">${op.status || 'Unknown'}</span></td>
                        <td>${(op.duration || 0).toFixed(2)}ms</td>
                        <td><small class="text-muted">${this.escapeHtml(op.details || '')}</small></td>
                    </tr>
                `;
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
        },

        populateErrorsTab(errors) {
            const container = document.getElementById('resultsErrorsContent');

            if (errors.length === 0) {
                container.innerHTML = '<div class="text-success">No errors encountered</div>';
                return;
            }

            let html = '<div class="errors-list">';

            errors.forEach((error, index) => {
                html += `
                    <div class="error-item p-3 mb-3 border border-danger rounded">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="text-danger mb-1">Error #${index + 1}</h6>
                            <small class="text-muted">${error.timestamp || 'Unknown time'}</small>
                        </div>
                        <p class="mb-1"><strong>Operation:</strong> ${this.escapeHtml(error.operation || 'Unknown')}</p>
                        <p class="mb-1"><strong>Message:</strong> ${this.escapeHtml(error.message || 'No message')}</p>
                        ${error.stack ? `<details><summary>Stack Trace</summary><pre class="mt-2 small">${this.escapeHtml(error.stack)}</pre></details>` : ''}
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        },

        populatePerformanceTab(performance) {
            const container = document.getElementById('resultsPerformanceContent');

            container.innerHTML = `
                <div class="performance-metrics">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="metric-group">
                                <h6>Timing Metrics</h6>
                                <ul class="list-unstyled">
                                    <li>Total Duration: ${this.formatDuration(performance.total_duration || 0)}</li>
                                    <li>Avg Operation: ${(performance.avg_operation_time || 0).toFixed(2)}ms</li>
                                    <li>Max Operation: ${(performance.max_operation_time || 0).toFixed(2)}ms</li>
                                    <li>Min Operation: ${(performance.min_operation_time || 0).toFixed(2)}ms</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric-group">
                                <h6>Resource Usage</h6>
                                <ul class="list-unstyled">
                                    <li>Peak Memory: ${this.formatBytes(performance.peak_memory || 0)}</li>
                                    <li>CPU Usage: ${(performance.cpu_usage || 0).toFixed(1)}%</li>
                                    <li>API Rate Limit: ${performance.rate_limit_usage || 0}%</li>
                                    <li>DB Queries: ${performance.db_queries || 0}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric-group">
                                <h6>Throughput</h6>
                                <ul class="list-unstyled">
                                    <li>Operations/sec: ${(performance.throughput || 0).toFixed(2)}</li>
                                    <li>Records/sec: ${(performance.record_throughput || 0).toFixed(2)}</li>
                                    <li>Data/sec: ${this.formatBytes(performance.data_throughput || 0)}/s</li>
                                    <li>Efficiency: ${(performance.efficiency || 0).toFixed(1)}%</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },

        showLiveSyncLog() {
            const modal = new bootstrap.Modal(document.getElementById('liveSyncLogModal'));
            modal.show();

            // Start SSE connection for live logs
            this.startLogStreaming();
        },

        startLogStreaming() {
            if (this.logEventSource) {
                this.logEventSource.close();
            }

            try {
                this.logEventSource = new EventSource('/admin/api-lab/lightspeed/log-stream');

                this.logEventSource.onmessage = (event) => {
                    try {
                        const logEntry = JSON.parse(event.data);
                        this.appendLogEntry(logEntry);
                    } catch (error) {
                        console.error('Failed to parse log entry:', error);
                    }
                };

                this.logEventSource.onerror = (error) => {
                    console.error('Log stream error:', error);
                };

            } catch (error) {
                console.error('Failed to start log streaming:', error);
            }
        },

        appendLogEntry(entry) {
            const logContainer = document.getElementById('liveSyncLog');
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';

            const levelClass = {
                'INFO': 'info',
                'DEBUG': 'debug',
                'WARNING': 'warning',
                'ERROR': 'error'
            }[entry.level] || 'info';

            logEntry.innerHTML = `
                <span class="timestamp">[${entry.timestamp}]</span>
                <span class="level ${levelClass}">${entry.level}</span>
                <span class="message">${this.escapeHtml(entry.message)}</span>
            `;

            logContainer.appendChild(logEntry);

            // Auto scroll if enabled
            if (document.getElementById('autoScroll').checked) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        },

        clearSyncLog() {
            const logContainer = document.getElementById('liveSyncLog');
            logContainer.innerHTML = `
                <div class="log-entry">
                    <span class="timestamp">[${new Date().toLocaleTimeString()}]</span>
                    <span class="level info">INFO</span>
                    <span class="message">Log cleared by user</span>
                </div>
            `;
        },

        async loadSyncStatus() {
            try {
                const response = await fetch('/admin/api-lab/lightspeed', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'get_current_status'
                    })
                });

                const result = await response.json();

                if (result.success && result.result.active_sync) {
                    // Resume monitoring if there's an active sync
                    this.startSyncMonitoring(result.result.test_id);
                }

            } catch (error) {
                console.error('Failed to load sync status:', error);
            }
        },

        formatDuration(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + sizes[i];
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showError(message) {
            console.error(message);
            // Implementation for error notifications
        },

        showSuccess(message) {
            console.log(message);
            // Implementation for success notifications
        }
    };

    // Initialize
    LightspeedSyncTester.init();
});
</script>