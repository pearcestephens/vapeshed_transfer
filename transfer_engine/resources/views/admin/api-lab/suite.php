<?php
/**
 * API Test Suite Runner Interface
 * Comprehensive test suite execution with parallel running and detailed reporting
 */
?>

<div class="suite-runner">
    <!-- Header -->
    <div class="lab-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i data-feather="check-square" class="me-2"></i>
                API Test Suite Runner
            </h4>
            <p class="text-muted mb-0">Execute comprehensive test suites with parallel execution and detailed reporting</p>
        </div>
        <div class="lab-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-success btn-sm" id="createSuite">
                    <i data-feather="plus" class="me-1"></i>
                    Create Suite
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="importSuite">
                    <i data-feather="upload" class="me-1"></i>
                    Import Suite
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="suiteHistory">
                    <i data-feather="clock" class="me-1"></i>
                    Run History
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Panel: Suite Configuration -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="settings" class="me-2"></i>
                        Test Suite Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Predefined Test Suites -->
                    <div class="form-group mb-3">
                        <label for="predefinedSuite" class="form-label">Predefined Test Suites</label>
                        <select id="predefinedSuite" class="form-select">
                            <option value="">Custom Configuration</option>
                            <optgroup label="Transfer Engine Suites">
                                <option value="transfer_complete">Complete Transfer Test Suite (9 tests)</option>
                                <option value="transfer_basic">Basic Transfer Operations (5 tests)</option>
                                <option value="transfer_edge_cases">Transfer Edge Cases (7 tests)</option>
                            </optgroup>
                            <optgroup label="Purchase Order Suites">
                                <option value="po_complete">Complete PO Test Suite (9 tests)</option>
                                <option value="po_workflow">PO Workflow Tests (6 tests)</option>
                                <option value="po_validation">PO Validation Tests (4 tests)</option>
                            </optgroup>
                            <optgroup label="Inventory Suites">
                                <option value="inventory_sync">Inventory Sync Suite (5 tests)</option>
                                <option value="inventory_validation">Inventory Validation (3 tests)</option>
                                <option value="inventory_stress">Inventory Stress Tests (4 tests)</option>
                            </optgroup>
                            <optgroup label="Webhook Suites">
                                <option value="webhook_complete">Complete Webhook Suite (3 tests)</option>
                                <option value="webhook_reliability">Webhook Reliability (5 tests)</option>
                            </optgroup>
                            <optgroup label="Full System Suites">
                                <option value="smoke_tests">Smoke Test Suite (15 tests)</option>
                                <option value="regression_tests">Regression Test Suite (25 tests)</option>
                                <option value="stress_tests">Stress Test Suite (12 tests)</option>
                                <option value="integration_tests">Integration Test Suite (20 tests)</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Test Categories -->
                    <div class="test-categories mb-3" id="testCategories">
                        <label class="form-label">Test Categories</label>

                        <!-- Transfer Tests -->
                        <div class="category-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input category-toggle" type="checkbox" id="transferTests" value="transfer">
                                <label class="form-check-label fw-bold" for="transferTests">
                                    Transfer Engine Tests (9 tests)
                                </label>
                            </div>
                            <div class="category-tests ms-4" id="transferTestsList" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_create" value="transfer_create">
                                    <label class="form-check-label" for="test_transfer_create">Create Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_validate" value="transfer_validate">
                                    <label class="form-check-label" for="test_transfer_validate">Validate Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_execute" value="transfer_execute">
                                    <label class="form-check-label" for="test_transfer_execute">Execute Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_complete" value="transfer_complete">
                                    <label class="form-check-label" for="test_transfer_complete">Complete Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_cancel" value="transfer_cancel">
                                    <label class="form-check-label" for="test_transfer_cancel">Cancel Transfer</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_duplicate" value="transfer_duplicate">
                                    <label class="form-check-label" for="test_transfer_duplicate">Duplicate Transfer Detection</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_insufficient_stock" value="transfer_insufficient_stock">
                                    <label class="form-check-label" for="test_transfer_insufficient_stock">Insufficient Stock Handling</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_rollback" value="transfer_rollback">
                                    <label class="form-check-label" for="test_transfer_rollback">Transfer Rollback</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_transfer_bulk" value="transfer_bulk">
                                    <label class="form-check-label" for="test_transfer_bulk">Bulk Transfer Processing</label>
                                </div>
                            </div>
                        </div>

                        <!-- Purchase Order Tests -->
                        <div class="category-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input category-toggle" type="checkbox" id="poTests" value="po">
                                <label class="form-check-label fw-bold" for="poTests">
                                    Purchase Order Tests (9 tests)
                                </label>
                            </div>
                            <div class="category-tests ms-4" id="poTestsList" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_create" value="po_create">
                                    <label class="form-check-label" for="test_po_create">Create Purchase Order</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_receive" value="po_receive">
                                    <label class="form-check-label" for="test_po_receive">Receive Purchase Order</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_partial_receive" value="po_partial_receive">
                                    <label class="form-check-label" for="test_po_partial_receive">Partial Receive</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_cancel" value="po_cancel">
                                    <label class="form-check-label" for="test_po_cancel">Cancel Purchase Order</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_to_consignment" value="po_to_consignment">
                                    <label class="form-check-label" for="test_po_to_consignment">PO to Consignment Sync</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_validation" value="po_validation">
                                    <label class="form-check-label" for="test_po_validation">PO Data Validation</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_supplier_sync" value="po_supplier_sync">
                                    <label class="form-check-label" for="test_po_supplier_sync">Supplier Data Sync</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_cost_tracking" value="po_cost_tracking">
                                    <label class="form-check-label" for="test_po_cost_tracking">Cost Tracking</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_po_reporting" value="po_reporting">
                                    <label class="form-check-label" for="test_po_reporting">PO Reporting</label>
                                </div>
                            </div>
                        </div>

                        <!-- Inventory Tests -->
                        <div class="category-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input category-toggle" type="checkbox" id="inventoryTests" value="inventory">
                                <label class="form-check-label fw-bold" for="inventoryTests">
                                    Inventory Tests (5 tests)
                                </label>
                            </div>
                            <div class="category-tests ms-4" id="inventoryTestsList" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_inventory_sync" value="inventory_sync">
                                    <label class="form-check-label" for="test_inventory_sync">Inventory Synchronization</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_inventory_adjustment" value="inventory_adjustment">
                                    <label class="form-check-label" for="test_inventory_adjustment">Stock Adjustments</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_inventory_validation" value="inventory_validation">
                                    <label class="form-check-label" for="test_inventory_validation">Inventory Validation</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_inventory_reporting" value="inventory_reporting">
                                    <label class="form-check-label" for="test_inventory_reporting">Inventory Reporting</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_inventory_alerts" value="inventory_alerts">
                                    <label class="form-check-label" for="test_inventory_alerts">Low Stock Alerts</label>
                                </div>
                            </div>
                        </div>

                        <!-- Webhook Tests -->
                        <div class="category-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input category-toggle" type="checkbox" id="webhookTests" value="webhook">
                                <label class="form-check-label fw-bold" for="webhookTests">
                                    Webhook Tests (3 tests)
                                </label>
                            </div>
                            <div class="category-tests ms-4" id="webhookTestsList" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_webhook_delivery" value="webhook_delivery">
                                    <label class="form-check-label" for="test_webhook_delivery">Webhook Delivery</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_webhook_retry" value="webhook_retry">
                                    <label class="form-check-label" for="test_webhook_retry">Webhook Retry Logic</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input test-item" type="checkbox" id="test_webhook_security" value="webhook_security">
                                    <label class="form-check-label" for="test_webhook_security">Webhook Security</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Execution Options -->
                    <div class="execution-options mb-3">
                        <label class="form-label">Execution Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="parallelExecution" checked>
                            <label class="form-check-label" for="parallelExecution">
                                Parallel Execution (faster)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="stopOnFailure">
                            <label class="form-check-label" for="stopOnFailure">
                                Stop on First Failure
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="generateReport" checked>
                            <label class="form-check-label" for="generateReport">
                                Generate Detailed Report
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="emailResults">
                            <label class="form-check-label" for="emailResults">
                                Email Results
                            </label>
                        </div>
                    </div>

                    <!-- Advanced Configuration -->
                    <div class="advanced-config">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Advanced Configuration</h6>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleAdvanced">
                                <i data-feather="chevron-down" class="me-1"></i>
                                Show Advanced
                            </button>
                        </div>
                        <div id="advancedOptions" style="display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group mb-3">
                                        <label for="maxConcurrency" class="form-label">Max Concurrent Tests</label>
                                        <input type="number" id="maxConcurrency" class="form-control" value="5" min="1" max="20">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group mb-3">
                                        <label for="testTimeout" class="form-label">Test Timeout (seconds)</label>
                                        <input type="number" id="testTimeout" class="form-control" value="60" min="5" max="600">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="retryAttempts" class="form-label">Retry Failed Tests</label>
                                <input type="number" id="retryAttempts" class="form-control" value="1" min="0" max="5">
                            </div>
                            <div class="form-group mb-3">
                                <label for="testEnvironment" class="form-label">Test Environment</label>
                                <select id="testEnvironment" class="form-select">
                                    <option value="current">Current Environment</option>
                                    <option value="staging">Staging Environment</option>
                                    <option value="test">Test Environment</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Execution & Results -->
        <div class="col-lg-7">
            <!-- Suite Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="play" class="me-2"></i>
                        Suite Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="runSuite">
                            <i data-feather="play" class="me-2"></i>
                            Run Test Suite
                        </button>
                        <div class="row">
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-info w-100" id="validateSuite">
                                    <i data-feather="check-square" class="me-1"></i>
                                    Validate
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-secondary w-100" id="exportSuite">
                                    <i data-feather="download" class="me-1"></i>
                                    Export
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="btn btn-outline-warning w-100" id="saveSuite">
                                    <i data-feather="save" class="me-1"></i>
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Tests Summary -->
                    <div class="selected-tests-summary mt-3 p-3 bg-light rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Selected Tests:</span>
                            <span id="selectedTestCount" class="badge bg-primary">0</span>
                        </div>
                        <div class="mt-2">
                            <small id="estimatedDuration" class="text-muted">Estimated duration: --</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Execution Progress -->
            <div class="card mb-3" id="executionCard" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="activity" class="me-2"></i>
                        Test Execution Progress
                    </h6>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="stopExecution">
                        <i data-feather="square" class="me-1"></i>
                        Stop
                    </button>
                </div>
                <div class="card-body">
                    <!-- Overall Progress -->
                    <div class="overall-progress mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="overallStatus">Running test suite...</span>
                            <div class="execution-stats">
                                <span id="executionTime" class="badge bg-secondary me-2">0:00</span>
                                <span id="progressPercent" class="badge bg-primary">0%</span>
                            </div>
                        </div>
                        <div class="progress">
                            <div id="overallProgressBar" class="progress-bar" role="progressbar"
                                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    <!-- Test Results Summary -->
                    <div class="test-results-summary row">
                        <div class="col-3">
                            <div class="result-metric text-center">
                                <div id="testsTotal" class="h6 mb-0">0</div>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="result-metric text-center">
                                <div id="testsPassed" class="h6 mb-0 text-success">0</div>
                                <small class="text-muted">Passed</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="result-metric text-center">
                                <div id="testsFailed" class="h6 mb-0 text-danger">0</div>
                                <small class="text-muted">Failed</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="result-metric text-center">
                                <div id="testsSkipped" class="h6 mb-0 text-warning">0</div>
                                <small class="text-muted">Skipped</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Test Results -->
            <div class="card" id="liveResultsCard" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="list" class="me-2"></i>
                        Live Test Results
                    </h6>
                    <div class="result-filters btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="resultFilter" id="filterAllResults" value="all" checked>
                        <label class="btn btn-outline-secondary" for="filterAllResults">All</label>

                        <input type="radio" class="btn-check" name="resultFilter" id="filterPassed" value="passed">
                        <label class="btn btn-outline-success" for="filterPassed">Passed</label>

                        <input type="radio" class="btn-check" name="resultFilter" id="filterFailedResults" value="failed">
                        <label class="btn btn-outline-danger" for="filterFailedResults">Failed</label>

                        <input type="radio" class="btn-check" name="resultFilter" id="filterRunning" value="running">
                        <label class="btn btn-outline-warning" for="filterRunning">Running</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="liveTestResults" class="test-results-list" style="max-height: 400px; overflow-y: auto;">
                        <!-- Live test results will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Final Report Modal -->
    <div class="modal fade" id="finalReportModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="file-text" class="me-2"></i>
                        Test Suite Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="finalReportContent">
                        <!-- Final report will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="downloadReport">
                        <i data-feather="download" class="me-1"></i>
                        Download Report
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="emailReport">
                        <i data-feather="mail" class="me-1"></i>
                        Email Report
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Suite Runner JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const SuiteRunner = {
        executionInterval: null,
        currentExecution: null,

        init() {
            this.bindEvents();
            this.updateSelectedTestCount();
        },

        bindEvents() {
            // Predefined suite selection
            document.getElementById('predefinedSuite').addEventListener('change', (e) => {
                this.loadPredefinedSuite(e.target.value);
            });

            // Category toggles
            document.querySelectorAll('.category-toggle').forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    this.toggleCategory(e.target.value, e.target.checked);
                });
            });

            // Individual test checkboxes
            document.querySelectorAll('.test-item').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.updateSelectedTestCount();
                });
            });

            // Run suite
            document.getElementById('runSuite').addEventListener('click', () => {
                this.runTestSuite();
            });

            // Validate suite
            document.getElementById('validateSuite').addEventListener('click', () => {
                this.validateSuite();
            });

            // Advanced toggle
            document.getElementById('toggleAdvanced').addEventListener('click', () => {
                this.toggleAdvancedOptions();
            });

            // Stop execution
            document.getElementById('stopExecution').addEventListener('click', () => {
                this.stopExecution();
            });

            // Result filters
            document.querySelectorAll('input[name="resultFilter"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    this.filterResults(e.target.value);
                });
            });
        },

        loadPredefinedSuite(suiteId) {
            if (!suiteId) return;

            // Clear all selections first
            document.querySelectorAll('.test-item').forEach(checkbox => {
                checkbox.checked = false;
            });

            const suites = {
                'transfer_complete': ['transfer_create', 'transfer_validate', 'transfer_execute', 'transfer_complete', 'transfer_cancel', 'transfer_duplicate', 'transfer_insufficient_stock', 'transfer_rollback', 'transfer_bulk'],
                'transfer_basic': ['transfer_create', 'transfer_validate', 'transfer_execute', 'transfer_complete', 'transfer_cancel'],
                'po_complete': ['po_create', 'po_receive', 'po_partial_receive', 'po_cancel', 'po_to_consignment', 'po_validation', 'po_supplier_sync', 'po_cost_tracking', 'po_reporting'],
                'inventory_sync': ['inventory_sync', 'inventory_adjustment', 'inventory_validation', 'inventory_reporting', 'inventory_alerts'],
                'webhook_complete': ['webhook_delivery', 'webhook_retry', 'webhook_security'],
                'smoke_tests': [
                    'transfer_create', 'transfer_execute', 'transfer_complete',
                    'po_create', 'po_receive', 'po_to_consignment',
                    'inventory_sync', 'inventory_validation',
                    'webhook_delivery'
                ]
            };

            const tests = suites[suiteId];
            if (tests) {
                tests.forEach(testId => {
                    const checkbox = document.getElementById('test_' + testId);
                    if (checkbox) {
                        checkbox.checked = true;

                        // Show parent category
                        const categoryTests = checkbox.closest('.category-tests');
                        if (categoryTests) {
                            categoryTests.style.display = 'block';
                            const categoryToggle = document.querySelector(`[data-target="#${categoryTests.id}"]`);
                            if (categoryToggle) {
                                categoryToggle.checked = true;
                            }
                        }
                    }
                });
            }

            this.updateSelectedTestCount();
        },

        toggleCategory(category, checked) {
            const categoryList = document.getElementById(category + 'TestsList');
            if (categoryList) {
                categoryList.style.display = checked ? 'block' : 'none';

                // Check/uncheck all tests in category
                categoryList.querySelectorAll('.test-item').forEach(checkbox => {
                    checkbox.checked = checked;
                });
            }

            this.updateSelectedTestCount();
        },

        updateSelectedTestCount() {
            const selectedTests = document.querySelectorAll('.test-item:checked');
            const count = selectedTests.length;

            document.getElementById('selectedTestCount').textContent = count;

            // Estimate duration (assume 30 seconds per test on average)
            const estimatedSeconds = count * 30;
            const minutes = Math.floor(estimatedSeconds / 60);
            const seconds = estimatedSeconds % 60;

            document.getElementById('estimatedDuration').textContent =
                `Estimated duration: ${minutes}:${seconds.toString().padStart(2, '0')}`;
        },

        async runTestSuite() {
            const config = this.collectSuiteConfig();

            if (!this.validateSuiteConfig(config)) {
                return;
            }

            // Show execution card
            document.getElementById('executionCard').style.display = 'block';
            document.getElementById('liveResultsCard').style.display = 'block';

            // Start execution
            try {
                const response = await fetch('/admin/api-lab/suite', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'run_suite',
                        config: config
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.currentExecution = result.result.execution_id;
                    this.startExecutionMonitoring();
                } else {
                    this.showError(result.error);
                }

            } catch (error) {
                this.showError('Failed to start test suite: ' + error.message);
            }
        },

        collectSuiteConfig() {
            const selectedTests = Array.from(document.querySelectorAll('.test-item:checked'))
                .map(checkbox => checkbox.value);

            return {
                tests: selectedTests,
                parallel_execution: document.getElementById('parallelExecution').checked,
                stop_on_failure: document.getElementById('stopOnFailure').checked,
                generate_report: document.getElementById('generateReport').checked,
                email_results: document.getElementById('emailResults').checked,
                max_concurrency: parseInt(document.getElementById('maxConcurrency').value) || 5,
                test_timeout: parseInt(document.getElementById('testTimeout').value) || 60,
                retry_attempts: parseInt(document.getElementById('retryAttempts').value) || 1,
                test_environment: document.getElementById('testEnvironment').value
            };
        },

        validateSuiteConfig(config) {
            if (config.tests.length === 0) {
                this.showError('Please select at least one test to run');
                return false;
            }

            return true;
        },

        startExecutionMonitoring() {
            this.executionInterval = setInterval(async () => {
                try {
                    const response = await fetch('/admin/api-lab/suite', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: 'get_execution_status',
                            execution_id: this.currentExecution
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.updateExecutionProgress(result.result);

                        if (result.result.status === 'completed' || result.result.status === 'failed' || result.result.status === 'stopped') {
                            this.stopExecutionMonitoring();
                            this.showFinalReport(result.result);
                        }
                    }

                } catch (error) {
                    console.error('Failed to get execution status:', error);
                }
            }, 2000); // Poll every 2 seconds
        },

        stopExecutionMonitoring() {
            if (this.executionInterval) {
                clearInterval(this.executionInterval);
                this.executionInterval = null;
            }
        },

        updateExecutionProgress(status) {
            // Update overall progress
            document.getElementById('overallStatus').textContent = status.current_test || 'Processing...';
            document.getElementById('executionTime').textContent = this.formatDuration(status.elapsed_time || 0);
            document.getElementById('progressPercent').textContent = (status.progress || 0).toFixed(1) + '%';
            document.getElementById('overallProgressBar').style.width = (status.progress || 0) + '%';

            // Update test counts
            document.getElementById('testsTotal').textContent = status.total_tests || 0;
            document.getElementById('testsPassed').textContent = status.passed_tests || 0;
            document.getElementById('testsFailed').textContent = status.failed_tests || 0;
            document.getElementById('testsSkipped').textContent = status.skipped_tests || 0;

            // Update live results
            this.updateLiveResults(status.test_results || []);
        },

        updateLiveResults(results) {
            const container = document.getElementById('liveTestResults');
            container.innerHTML = '';

            results.forEach(test => {
                const resultItem = document.createElement('div');
                resultItem.className = 'test-result-item d-flex justify-content-between align-items-center p-3 border-bottom';
                resultItem.dataset.status = test.status;

                const statusClass = {
                    'passed': 'text-success',
                    'failed': 'text-danger',
                    'running': 'text-warning',
                    'skipped': 'text-muted'
                }[test.status] || 'text-secondary';

                const statusIcon = {
                    'passed': 'check-circle',
                    'failed': 'x-circle',
                    'running': 'loader',
                    'skipped': 'minus-circle'
                }[test.status] || 'circle';

                resultItem.innerHTML = `
                    <div class="test-info">
                        <h6 class="mb-1">${test.name}</h6>
                        <small class="text-muted">Duration: ${test.duration || 0}s</small>
                    </div>
                    <div class="test-status">
                        <i data-feather="${statusIcon}" class="${statusClass} me-2"></i>
                        <span class="${statusClass}">${test.status.toUpperCase()}</span>
                    </div>
                `;

                container.appendChild(resultItem);
            });

            feather.replace();
        },

        showFinalReport(execution) {
            const modal = new bootstrap.Modal(document.getElementById('finalReportModal'));
            modal.show();

            // Populate final report
            this.populateFinalReport(execution);
        },

        populateFinalReport(execution) {
            const container = document.getElementById('finalReportContent');

            const successRate = execution.total_tests ?
                ((execution.passed_tests / execution.total_tests) * 100).toFixed(1) : 0;

            container.innerHTML = `
                <div class="report-summary row mb-4">
                    <div class="col-3">
                        <div class="metric-card text-center">
                            <h3 class="text-primary">${execution.total_tests || 0}</h3>
                            <small class="text-muted">Total Tests</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="metric-card text-center">
                            <h3 class="text-success">${successRate}%</h3>
                            <small class="text-muted">Success Rate</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="metric-card text-center">
                            <h3 class="text-info">${this.formatDuration(execution.total_duration || 0)}</h3>
                            <small class="text-muted">Total Duration</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="metric-card text-center">
                            <h3 class="text-warning">${execution.failed_tests || 0}</h3>
                            <small class="text-muted">Failed Tests</small>
                        </div>
                    </div>
                </div>

                <div class="detailed-results">
                    <h6>Detailed Test Results</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(execution.test_results || []).map(test => `
                                    <tr>
                                        <td>${test.name}</td>
                                        <td><span class="badge bg-${test.status === 'passed' ? 'success' : test.status === 'failed' ? 'danger' : 'warning'}">${test.status}</span></td>
                                        <td>${test.duration || 0}s</td>
                                        <td><small class="text-muted">${test.message || '-'}</small></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        },

        toggleAdvancedOptions() {
            const options = document.getElementById('advancedOptions');
            const button = document.getElementById('toggleAdvanced');

            if (options.style.display === 'none') {
                options.style.display = 'block';
                button.innerHTML = '<i data-feather="chevron-up" class="me-1"></i>Hide Advanced';
            } else {
                options.style.display = 'none';
                button.innerHTML = '<i data-feather="chevron-down" class="me-1"></i>Show Advanced';
            }
            feather.replace();
        },

        formatDuration(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
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
    SuiteRunner.init();
});
</script>