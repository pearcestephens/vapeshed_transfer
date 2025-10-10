<?php
/**
 * Queue Job Testing Interface
 * Advanced interface for testing queue jobs with real-time monitoring and stress testing
 */
?>

<div class="queue-job-tester">
    <!-- Header -->
    <div class="lab-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i data-feather="layers" class="me-2"></i>
                Queue Job Tester
            </h4>
            <p class="text-muted mb-0">Test and monitor queue jobs with real-time status tracking and performance analysis</p>
        </div>
        <div class="lab-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-success btn-sm" id="queueStatus">
                    <i data-feather="activity" class="me-1"></i>
                    Queue Status
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="workerStats">
                    <i data-feather="cpu" class="me-1"></i>
                    Worker Stats
                </button>
                <button type="button" class="btn btn-outline-warning btn-sm" id="jobHistory">
                    <i data-feather="clock" class="me-1"></i>
                    Job History
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Panel: Job Configuration -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="settings" class="me-2"></i>
                        Job Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Job Type Selection -->
                    <div class="form-group mb-3">
                        <label for="jobType" class="form-label">Job Type</label>
                        <select id="jobType" class="form-select">
                            <option value="">Select Job Type</option>
                            <optgroup label="Transfer Jobs">
                                <option value="ProcessTransferJob">Process Transfer</option>
                                <option value="ValidateTransferJob">Validate Transfer</option>
                                <option value="CompleteTransferJob">Complete Transfer</option>
                                <option value="CancelTransferJob">Cancel Transfer</option>
                            </optgroup>
                            <optgroup label="Sync Jobs">
                                <option value="SyncProductsJob">Sync Products</option>
                                <option value="SyncInventoryJob">Sync Inventory</option>
                                <option value="SyncOutletsJob">Sync Outlets</option>
                                <option value="FullSyncJob">Full System Sync</option>
                            </optgroup>
                            <optgroup label="Notification Jobs">
                                <option value="SendEmailJob">Send Email</option>
                                <option value="SendWebhookJob">Send Webhook</option>
                                <option value="SendSMSJob">Send SMS</option>
                            </optgroup>
                            <optgroup label="Maintenance Jobs">
                                <option value="CleanupLogsJob">Cleanup Logs</option>
                                <option value="GenerateReportJob">Generate Report</option>
                                <option value="BackupDataJob">Backup Data</option>
                                <option value="HealthCheckJob">Health Check</option>
                            </optgroup>
                            <optgroup label="Test Jobs">
                                <option value="TestJob">Simple Test Job</option>
                                <option value="SlowJob">Slow Processing Job</option>
                                <option value="FailingJob">Intentionally Failing Job</option>
                                <option value="BulkJob">Bulk Processing Job</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Job Payload -->
                    <div class="form-group mb-3">
                        <label for="jobPayload" class="form-label">
                            Job Payload
                            <span class="badge bg-secondary ms-2">JSON</span>
                        </label>
                        <textarea id="jobPayload" class="form-control font-monospace" rows="10"
                                  placeholder="Enter job payload data...">{
  "transfer_id": 12345,
  "source_outlet_id": "outlet_1",
  "target_outlet_id": "outlet_2",
  "products": [
    {
      "product_id": "SKU001",
      "quantity": 10,
      "unit_cost": 25.99
    }
  ],
  "priority": "normal",
  "options": {
    "validate_stock": true,
    "send_notifications": true,
    "update_inventory": true
  },
  "metadata": {
    "created_by": "queue_tester",
    "test_mode": true,
    "correlation_id": "test-<?= uniqid() ?>"
  }
}</textarea>
                        <div class="payload-tools mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="formatJobJson">
                                <i data-feather="align-left" class="me-1"></i>
                                Format JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="validateJobJson">
                                <i data-feather="check-circle" class="me-1"></i>
                                Validate JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="generatePayload">
                                <i data-feather="refresh-cw" class="me-1"></i>
                                Generate Sample
                            </button>
                        </div>
                    </div>

                    <!-- Job Priority -->
                    <div class="form-group mb-3">
                        <label class="form-label">Job Priority</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="jobPriority" id="priorityLow" value="low">
                            <label class="btn btn-outline-secondary" for="priorityLow">Low</label>

                            <input type="radio" class="btn-check" name="jobPriority" id="priorityNormal" value="normal" checked>
                            <label class="btn btn-outline-primary" for="priorityNormal">Normal</label>

                            <input type="radio" class="btn-check" name="jobPriority" id="priorityHigh" value="high">
                            <label class="btn btn-outline-warning" for="priorityHigh">High</label>

                            <input type="radio" class="btn-check" name="jobPriority" id="priorityCritical" value="critical">
                            <label class="btn btn-outline-danger" for="priorityCritical">Critical</label>
                        </div>
                    </div>

                    <!-- Queue Selection -->
                    <div class="form-group mb-3">
                        <label for="queueName" class="form-label">Queue Name</label>
                        <select id="queueName" class="form-select">
                            <option value="default">Default Queue</option>
                            <option value="transfers">Transfer Queue</option>
                            <option value="sync">Sync Queue</option>
                            <option value="notifications">Notification Queue</option>
                            <option value="reports">Report Queue</option>
                            <option value="maintenance">Maintenance Queue</option>
                            <option value="high_priority">High Priority Queue</option>
                        </select>
                    </div>

                    <!-- Delay & Retry Configuration -->
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label for="jobDelay" class="form-label">Delay (seconds)</label>
                                <input type="number" id="jobDelay" class="form-control" value="0" min="0" max="3600">
                                <div class="form-text">Delay before job execution</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-3">
                                <label for="maxRetries" class="form-label">Max Retries</label>
                                <input type="number" id="maxRetries" class="form-control" value="3" min="0" max="10">
                                <div class="form-text">Maximum retry attempts</div>
                            </div>
                        </div>
                    </div>

                    <!-- Job Options -->
                    <div class="form-group mb-3">
                        <label class="form-label">Job Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="trackProgress" checked>
                            <label class="form-check-label" for="trackProgress">
                                Track Progress
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sendNotifications">
                            <label class="form-check-label" for="sendNotifications">
                                Send Completion Notifications
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="storeResults" checked>
                            <label class="form-check-label" for="storeResults">
                                Store Results
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="logExecution" checked>
                            <label class="form-check-label" for="logExecution">
                                Log Execution Details
                            </label>
                        </div>
                    </div>

                    <!-- Stress Test Configuration -->
                    <div class="stress-test-config mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Stress Test Configuration</h6>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleStressConfig">
                                <i data-feather="chevron-down" class="me-1"></i>
                                Show Stress Options
                            </button>
                        </div>
                        <div id="stressOptions" style="display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group mb-3">
                                        <label for="stressJobCount" class="form-label">Number of Jobs</label>
                                        <input type="number" id="stressJobCount" class="form-control" value="10" min="1" max="1000">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group mb-3">
                                        <label for="stressInterval" class="form-label">Dispatch Interval (ms)</label>
                                        <input type="number" id="stressInterval" class="form-control" value="100" min="0" max="10000">
                                    </div>
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="randomizePayload">
                                <label class="form-check-label" for="randomizePayload">
                                    Randomize Payload Data
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="varyPriority">
                                <label class="form-check-label" for="varyPriority">
                                    Vary Job Priorities
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Testing & Monitoring -->
        <div class="col-lg-6">
            <!-- Job Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="play" class="me-2"></i>
                        Job Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="dispatchJob">
                            <i data-feather="send" class="me-2"></i>
                            Dispatch Job
                        </button>
                        <div class="row">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-warning w-100" id="stressTest">
                                    <i data-feather="zap" class="me-1"></i>
                                    Stress Test
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-info w-100" id="bulkDispatch">
                                    <i data-feather="layers" class="me-1"></i>
                                    Bulk Dispatch
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queue Management -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="list" class="me-2"></i>
                        Queue Management
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 queue-action"
                                    data-action="pause_queue">
                                <i data-feather="pause" class="me-1"></i>
                                Pause Queue
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 queue-action"
                                    data-action="resume_queue">
                                <i data-feather="play" class="me-1"></i>
                                Resume Queue
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-warning btn-sm w-100 queue-action"
                                    data-action="retry_failed">
                                <i data-feather="refresh-cw" class="me-1"></i>
                                Retry Failed
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 queue-action"
                                    data-action="clear_failed">
                                <i data-feather="trash-2" class="me-1"></i>
                                Clear Failed
                            </button>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-info btn-sm w-100 queue-action"
                                    data-action="purge_queue">
                                <i data-feather="delete" class="me-1"></i>
                                Purge Queue
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Queue Status -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="activity" class="me-2"></i>
                        Live Queue Status
                    </h6>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshStats">
                        <i data-feather="refresh-cw" class="me-1"></i>
                        Refresh
                    </button>
                </div>
                <div class="card-body">
                    <div class="queue-stats row">
                        <div class="col-3">
                            <div class="stat-card text-center">
                                <h4 id="queuePending" class="mb-1 text-primary">--</h4>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card text-center">
                                <h4 id="queueProcessing" class="mb-1 text-warning">--</h4>
                                <small class="text-muted">Processing</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card text-center">
                                <h4 id="queueCompleted" class="mb-1 text-success">--</h4>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="stat-card text-center">
                                <h4 id="queueFailed" class="mb-1 text-danger">--</h4>
                                <small class="text-muted">Failed</small>
                            </div>
                        </div>
                    </div>

                    <!-- Worker Status -->
                    <div class="worker-status mt-3 pt-3 border-top">
                        <h6>Worker Status</h6>
                        <div class="row">
                            <div class="col-4">
                                <div class="worker-metric text-center">
                                    <div id="activeWorkers" class="h6 mb-0 text-success">--</div>
                                    <small class="text-muted">Active</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="worker-metric text-center">
                                    <div id="totalWorkers" class="h6 mb-0">--</div>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="worker-metric text-center">
                                    <div id="workerLoad" class="h6 mb-0">--%</div>
                                    <small class="text-muted">Load</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Jobs -->
            <div class="card" id="recentJobsCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="clock" class="me-2"></i>
                        Recent Jobs
                    </h6>
                    <div class="job-filters btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="jobFilter" id="filterAll" value="all" checked>
                        <label class="btn btn-outline-secondary" for="filterAll">All</label>

                        <input type="radio" class="btn-check" name="jobFilter" id="filterRunning" value="running">
                        <label class="btn btn-outline-warning" for="filterRunning">Running</label>

                        <input type="radio" class="btn-check" name="jobFilter" id="filterCompleted" value="completed">
                        <label class="btn btn-outline-success" for="filterCompleted">Done</label>

                        <input type="radio" class="btn-check" name="jobFilter" id="filterFailed" value="failed">
                        <label class="btn btn-outline-danger" for="filterFailed">Failed</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="recentJobsList" class="job-list">
                        <div class="job-item d-flex justify-content-between align-items-center p-3 border-bottom">
                            <div class="job-info">
                                <h6 class="mb-1">No recent jobs</h6>
                                <small class="text-muted">Dispatch a job to see it here</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Job Details Modal -->
    <div class="modal fade" id="jobDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="info" class="me-2"></i>
                        Job Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="jobDetailsContent">
                        <!-- Job details will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-warning" id="retryJob">
                        <i data-feather="refresh-cw" class="me-1"></i>
                        Retry Job
                    </button>
                    <button type="button" class="btn btn-outline-danger" id="cancelJob">
                        <i data-feather="x" class="me-1"></i>
                        Cancel Job
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stress Test Progress Modal -->
    <div class="modal fade" id="stressTestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="zap" class="me-2"></i>
                        Stress Test Progress
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="stress-progress">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="stressStatusText">Preparing stress test...</span>
                            <span id="stressProgress" class="badge bg-secondary">0%</span>
                        </div>
                        <div class="progress mb-3">
                            <div id="stressProgressBar" class="progress-bar" role="progressbar"
                                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                        <div class="stress-metrics row">
                            <div class="col-3">
                                <div class="metric-mini text-center">
                                    <div id="stressDispatched" class="h6 mb-0">0</div>
                                    <small class="text-muted">Dispatched</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="metric-mini text-center">
                                    <div id="stressCompleted" class="h6 mb-0 text-success">0</div>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="metric-mini text-center">
                                    <div id="stressFailed" class="h6 mb-0 text-danger">0</div>
                                    <small class="text-muted">Failed</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="metric-mini text-center">
                                    <div id="stressRate" class="h6 mb-0">0/s</div>
                                    <small class="text-muted">Rate</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger" id="stopStressTest">
                        <i data-feather="square" class="me-1"></i>
                        Stop Test
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Queue Job Tester JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const QueueJobTester = {
        refreshInterval: null,
        stressTestInterval: null,

        init() {
            this.bindEvents();
            this.loadQueueStats();
            this.startStatsRefresh();
        },

        bindEvents() {
            // Job type change
            document.getElementById('jobType').addEventListener('change', (e) => {
                this.generateSamplePayload(e.target.value);
            });

            // Dispatch job
            document.getElementById('dispatchJob').addEventListener('click', () => {
                this.dispatchJob();
            });

            // Stress test
            document.getElementById('stressTest').addEventListener('click', () => {
                this.startStressTest();
            });

            // Bulk dispatch
            document.getElementById('bulkDispatch').addEventListener('click', () => {
                this.bulkDispatch();
            });

            // Queue actions
            document.querySelectorAll('.queue-action').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.executeQueueAction(e.target.dataset.action);
                });
            });

            // JSON tools
            document.getElementById('formatJobJson').addEventListener('click', () => {
                this.formatJson();
            });

            document.getElementById('validateJobJson').addEventListener('click', () => {
                this.validateJson();
            });

            document.getElementById('generatePayload').addEventListener('click', () => {
                const jobType = document.getElementById('jobType').value;
                this.generateSamplePayload(jobType);
            });

            // Stress test toggle
            document.getElementById('toggleStressConfig').addEventListener('click', () => {
                this.toggleStressConfig();
            });

            // Refresh stats
            document.getElementById('refreshStats').addEventListener('click', () => {
                this.loadQueueStats();
            });

            // Job filters
            document.querySelectorAll('input[name="jobFilter"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    this.filterJobs(e.target.value);
                });
            });
        },

        async dispatchJob() {
            const jobConfig = this.collectJobConfig();

            if (!this.validateJobConfig(jobConfig)) {
                return;
            }

            try {
                const response = await fetch('/admin/api-lab/queue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'dispatch_job',
                        config: jobConfig
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.showSuccess('Job dispatched successfully');
                    this.addJobToList(result.result);
                    this.loadQueueStats();
                } else {
                    this.showError(result.error);
                }

            } catch (error) {
                this.showError('Failed to dispatch job: ' + error.message);
            }
        },

        async startStressTest() {
            const stressConfig = this.collectStressConfig();

            if (!stressConfig.job_count || stressConfig.job_count < 1) {
                this.showError('Please specify number of jobs for stress test');
                return;
            }

            // Show stress test modal
            const modal = new bootstrap.Modal(document.getElementById('stressTestModal'));
            modal.show();

            try {
                const response = await fetch('/admin/api-lab/queue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'start_stress_test',
                        config: stressConfig
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.monitorStressTest(result.result.test_id);
                } else {
                    this.showError(result.error);
                }

            } catch (error) {
                this.showError('Failed to start stress test: ' + error.message);
            }
        },

        collectJobConfig() {
            return {
                job_type: document.getElementById('jobType').value,
                payload: document.getElementById('jobPayload').value,
                priority: document.querySelector('input[name="jobPriority"]:checked')?.value || 'normal',
                queue: document.getElementById('queueName').value,
                delay: parseInt(document.getElementById('jobDelay').value) || 0,
                max_retries: parseInt(document.getElementById('maxRetries').value) || 3,
                options: {
                    track_progress: document.getElementById('trackProgress').checked,
                    send_notifications: document.getElementById('sendNotifications').checked,
                    store_results: document.getElementById('storeResults').checked,
                    log_execution: document.getElementById('logExecution').checked
                }
            };
        },

        collectStressConfig() {
            const baseConfig = this.collectJobConfig();

            return {
                ...baseConfig,
                job_count: parseInt(document.getElementById('stressJobCount').value) || 10,
                dispatch_interval: parseInt(document.getElementById('stressInterval').value) || 100,
                randomize_payload: document.getElementById('randomizePayload').checked,
                vary_priority: document.getElementById('varyPriority').checked
            };
        },

        validateJobConfig(config) {
            if (!config.job_type) {
                this.showError('Please select a job type');
                return false;
            }

            if (config.payload) {
                try {
                    JSON.parse(config.payload);
                } catch (error) {
                    this.showError('Invalid JSON payload: ' + error.message);
                    return false;
                }
            }

            return true;
        },

        generateSamplePayload(jobType) {
            const samples = {
                'ProcessTransferJob': {
                    transfer_id: 12345,
                    source_outlet_id: 'outlet_1',
                    target_outlet_id: 'outlet_2',
                    products: [
                        { product_id: 'SKU001', quantity: 10, unit_cost: 25.99 }
                    ],
                    options: { validate_stock: true, send_notifications: true }
                },
                'SyncProductsJob': {
                    outlet_ids: ['outlet_1', 'outlet_2'],
                    sync_direction: 'from_lightspeed',
                    limit: 100,
                    options: { include_deleted: false, full_sync: false }
                },
                'SendEmailJob': {
                    to: 'test@example.com',
                    subject: 'Test Email from Queue',
                    template: 'test_email',
                    data: { name: 'Test User', message: 'This is a test email' }
                },
                'TestJob': {
                    message: 'Hello from test job',
                    sleep_duration: 5,
                    simulate_failure: false,
                    return_data: { test: true }
                }
            };

            const sample = samples[jobType];
            if (sample) {
                document.getElementById('jobPayload').value = JSON.stringify(sample, null, 2);
            }
        },

        async loadQueueStats() {
            try {
                const response = await fetch('/admin/api-lab/queue', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'get_queue_stats'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.updateQueueStats(result.result);
                }

            } catch (error) {
                console.error('Failed to load queue stats:', error);
            }
        },

        updateQueueStats(stats) {
            document.getElementById('queuePending').textContent = stats.pending || 0;
            document.getElementById('queueProcessing').textContent = stats.processing || 0;
            document.getElementById('queueCompleted').textContent = stats.completed || 0;
            document.getElementById('queueFailed').textContent = stats.failed || 0;

            document.getElementById('activeWorkers').textContent = stats.workers?.active || 0;
            document.getElementById('totalWorkers').textContent = stats.workers?.total || 0;
            document.getElementById('workerLoad').textContent =
                (stats.workers?.load || 0).toFixed(1) + '%';
        },

        addJobToList(job) {
            const jobsList = document.getElementById('recentJobsList');

            // Remove "no jobs" message if it exists
            const noJobsMsg = jobsList.querySelector('.job-item');
            if (noJobsMsg && noJobsMsg.textContent.includes('No recent jobs')) {
                noJobsMsg.remove();
            }

            const jobItem = document.createElement('div');
            jobItem.className = 'job-item d-flex justify-content-between align-items-center p-3 border-bottom';
            jobItem.dataset.jobId = job.id;
            jobItem.dataset.status = job.status;

            const statusClass = {
                'pending': 'text-secondary',
                'processing': 'text-warning',
                'completed': 'text-success',
                'failed': 'text-danger'
            }[job.status] || 'text-muted';

            jobItem.innerHTML = `
                <div class="job-info">
                    <h6 class="mb-1">${job.type} #${job.id}</h6>
                    <small class="text-muted">Queue: ${job.queue} | Priority: ${job.priority}</small>
                </div>
                <div class="job-status">
                    <span class="badge ${statusClass}">${job.status}</span>
                    <button class="btn btn-outline-secondary btn-sm ms-2" onclick="QueueJobTester.showJobDetails('${job.id}')">
                        <i data-feather="eye"></i>
                    </button>
                </div>
            `;

            jobsList.prepend(jobItem);
            feather.replace();
        },

        startStatsRefresh() {
            this.refreshInterval = setInterval(() => {
                this.loadQueueStats();
            }, 5000); // Refresh every 5 seconds
        },

        formatJson() {
            try {
                const textarea = document.getElementById('jobPayload');
                const json = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(json, null, 2);
            } catch (error) {
                this.showError('Invalid JSON: ' + error.message);
            }
        },

        validateJson() {
            try {
                const payload = document.getElementById('jobPayload').value;
                if (payload) {
                    JSON.parse(payload);
                    this.showSuccess('JSON is valid');
                }
            } catch (error) {
                this.showError('Invalid JSON: ' + error.message);
            }
        },

        toggleStressConfig() {
            const options = document.getElementById('stressOptions');
            const button = document.getElementById('toggleStressConfig');

            if (options.style.display === 'none') {
                options.style.display = 'block';
                button.innerHTML = '<i data-feather="chevron-up" class="me-1"></i>Hide Stress Options';
            } else {
                options.style.display = 'none';
                button.innerHTML = '<i data-feather="chevron-down" class="me-1"></i>Show Stress Options';
            }
            feather.replace();
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
    QueueJobTester.init();
});
</script>