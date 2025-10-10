<?php
/**
 * Vend API Testing Interface
 * Advanced interface for testing Vend API endpoints with authentication and response analysis
 */
?>

<div class="vend-api-tester">
    <!-- Header -->
    <div class="lab-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i data-feather="shopping-cart" class="me-2"></i>
                Vend API Tester
            </h4>
            <p class="text-muted mb-0">Test Vend Lightspeed API endpoints with live authentication</p>
        </div>
        <div class="lab-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-success btn-sm" id="testAuth">
                    <i data-feather="shield-check" class="me-1"></i>
                    Test Auth
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="apiDocs">
                    <i data-feather="book" class="me-1"></i>
                    API Docs
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="requestHistory">
                    <i data-feather="clock" class="me-1"></i>
                    History
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Panel: Request Configuration -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="settings" class="me-2"></i>
                        API Request Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Authentication Status -->
                    <div class="auth-status-card mb-3 p-3 border rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="auth-info">
                                <h6 class="mb-1">Authentication Status</h6>
                                <p class="mb-0 text-muted">
                                    Domain: <code><?= $vend_domain ?? 'not-configured' ?></code>
                                </p>
                            </div>
                            <div class="auth-status">
                                <span id="authStatusBadge" class="badge bg-secondary">Checking...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Endpoint Selection -->
                    <div class="form-group mb-3">
                        <label for="endpointCategory" class="form-label">Endpoint Category</label>
                        <select id="endpointCategory" class="form-select">
                            <option value="">Select Category</option>
                            <option value="products">Products</option>
                            <option value="inventory">Inventory</option>
                            <option value="sales">Sales</option>
                            <option value="customers">Customers</option>
                            <option value="suppliers">Suppliers</option>
                            <option value="outlets">Outlets</option>
                            <option value="consignments">Consignments</option>
                            <option value="webhooks">Webhooks</option>
                            <option value="custom">Custom Endpoint</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label for="apiEndpoint" class="form-label">API Endpoint</label>
                        <select id="apiEndpoint" class="form-select" disabled>
                            <option value="">Select endpoint category first</option>
                        </select>
                    </div>

                    <!-- Custom Endpoint -->
                    <div class="form-group mb-3" id="customEndpointGroup" style="display: none;">
                        <label for="customEndpoint" class="form-label">Custom Endpoint Path</label>
                        <div class="input-group">
                            <span class="input-group-text">/api/</span>
                            <input type="text" id="customEndpoint" class="form-control"
                                   placeholder="endpoint/path">
                        </div>
                    </div>

                    <!-- HTTP Method -->
                    <div class="form-group mb-3">
                        <label class="form-label">HTTP Method</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="vendMethod" id="vendGet" value="GET" checked>
                            <label class="btn btn-outline-primary" for="vendGet">GET</label>

                            <input type="radio" class="btn-check" name="vendMethod" id="vendPost" value="POST">
                            <label class="btn btn-outline-primary" for="vendPost">POST</label>

                            <input type="radio" class="btn-check" name="vendMethod" id="vendPut" value="PUT">
                            <label class="btn btn-outline-primary" for="vendPut">PUT</label>

                            <input type="radio" class="btn-check" name="vendMethod" id="vendDelete" value="DELETE">
                            <label class="btn btn-outline-primary" for="vendDelete">DELETE</label>
                        </div>
                    </div>

                    <!-- Query Parameters -->
                    <div class="form-group mb-3">
                        <label class="form-label">Query Parameters</label>
                        <div id="paramsContainer">
                            <div class="param-row d-flex mb-2">
                                <input type="text" class="form-control me-2" placeholder="Parameter Name"
                                       value="limit">
                                <input type="text" class="form-control me-2" placeholder="Parameter Value"
                                       value="50">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-param">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                            <div class="param-row d-flex mb-2">
                                <input type="text" class="form-control me-2" placeholder="Parameter Name"
                                       value="after">
                                <input type="text" class="form-control me-2" placeholder="Parameter Value"
                                       value="">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-param">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addParam">
                            <i data-feather="plus" class="me-1"></i>
                            Add Parameter
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm ms-2" id="quickParams">
                            <i data-feather="zap" class="me-1"></i>
                            Quick Add
                        </button>
                    </div>

                    <!-- Request Body (for POST/PUT) -->
                    <div class="form-group mb-3" id="requestBodyGroup" style="display: none;">
                        <label for="requestBody" class="form-label">
                            Request Body
                            <span class="badge bg-secondary ms-2">JSON</span>
                        </label>
                        <textarea id="requestBody" class="form-control font-monospace" rows="8"
                                  placeholder="Enter JSON request body...">{
  "name": "Test Product",
  "sku": "TEST-SKU-001",
  "type": "product",
  "description": "Test product for API validation",
  "supply_price": 25.00,
  "retail_price": 49.99,
  "active": true,
  "track_quantity": true
}</textarea>
                        <div class="request-tools mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="formatRequestJson">
                                <i data-feather="align-left" class="me-1"></i>
                                Format JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="validateRequestJson">
                                <i data-feather="check-circle" class="me-1"></i>
                                Validate JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="loadSample">
                                <i data-feather="refresh-cw" class="me-1"></i>
                                Load Sample
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Options -->
                    <div class="form-group mb-3">
                        <label class="form-label">Advanced Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeDeleted">
                            <label class="form-check-label" for="includeDeleted">
                                Include Deleted Items
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rawResponse">
                            <label class="form-check-label" for="rawResponse">
                                Return Raw Response
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="debugMode">
                            <label class="form-check-label" for="debugMode">
                                Debug Mode (verbose logging)
                            </label>
                        </div>
                    </div>

                    <!-- Rate Limiting Info -->
                    <div class="rate-limit-info p-3 bg-light rounded">
                        <h6 class="mb-2">
                            <i data-feather="activity" class="me-1"></i>
                            Rate Limiting
                        </h6>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="metric-mini">
                                    <div id="rateLimitRemaining" class="h6 mb-0">--</div>
                                    <small class="text-muted">Remaining</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-mini">
                                    <div id="rateLimitTotal" class="h6 mb-0">--</div>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="metric-mini">
                                    <div id="rateLimitReset" class="h6 mb-0">--</div>
                                    <small class="text-muted">Reset</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Testing & Results -->
        <div class="col-lg-6">
            <!-- Test Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="play" class="me-2"></i>
                        Test Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="sendRequest">
                            <i data-feather="send" class="me-2"></i>
                            Send API Request
                        </button>
                        <div class="row">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-info w-100" id="previewRequest">
                                    <i data-feather="eye" class="me-1"></i>
                                    Preview Request
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-warning w-100" id="validateRequest">
                                    <i data-feather="check-square" class="me-1"></i>
                                    Validate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="zap" class="me-2"></i>
                        Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 quick-action"
                                    data-endpoint="products">
                                <i data-feather="package" class="me-1"></i>
                                List Products
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 quick-action"
                                    data-endpoint="inventory">
                                <i data-feather="box" class="me-1"></i>
                                Check Inventory
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 quick-action"
                                    data-endpoint="outlets">
                                <i data-feather="map-pin" class="me-1"></i>
                                List Outlets
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 quick-action"
                                    data-endpoint="sales">
                                <i data-feather="trending-up" class="me-1"></i>
                                Recent Sales
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Status -->
            <div class="card mb-3" id="apiStatus" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="activity" class="me-2"></i>
                        Request Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="request-progress">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="requestStatusText">Preparing request...</span>
                            <span id="requestDuration" class="badge bg-secondary">0.00s</span>
                        </div>
                        <div class="progress">
                            <div id="requestProgressBar" class="progress-bar" role="progressbar"
                                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Response -->
            <div class="card" id="apiResponseCard" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="monitor" class="me-2"></i>
                        API Response
                    </h6>
                    <div class="response-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyApiResponse">
                            <i data-feather="copy" class="me-1"></i>
                            Copy
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="saveApiResponse">
                            <i data-feather="save" class="me-1"></i>
                            Save
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Response Summary -->
                    <div class="api-response-summary row mb-3">
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="apiResponseStatus" class="mb-1">200</h4>
                                <small class="text-muted">Status</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="apiResponseTime" class="mb-1">234ms</h4>
                                <small class="text-muted">Response Time</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="apiResponseRecords" class="mb-1">25</h4>
                                <small class="text-muted">Records</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="apiResponseSize" class="mb-1">2.4KB</h4>
                                <small class="text-muted">Size</small>
                            </div>
                        </div>
                    </div>

                    <!-- Response Tabs -->
                    <ul class="nav nav-tabs" id="apiResponseTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="api-data-tab" data-bs-toggle="tab"
                                    data-bs-target="#api-data" type="button" role="tab">
                                <i data-feather="database" class="me-1"></i>
                                Data
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="api-raw-tab" data-bs-toggle="tab"
                                    data-bs-target="#api-raw" type="button" role="tab">
                                <i data-feather="code" class="me-1"></i>
                                Raw JSON
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="api-headers-tab" data-bs-toggle="tab"
                                    data-bs-target="#api-headers" type="button" role="tab">
                                <i data-feather="list" class="me-1"></i>
                                Headers
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="api-curl-tab" data-bs-toggle="tab"
                                    data-bs-target="#api-curl" type="button" role="tab">
                                <i data-feather="terminal" class="me-1"></i>
                                cURL
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="apiResponseTabContent">
                        <!-- Formatted Data -->
                        <div class="tab-pane fade show active" id="api-data" role="tabpanel">
                            <div id="apiDataFormatted" class="formatted-data"></div>
                        </div>

                        <!-- Raw JSON -->
                        <div class="tab-pane fade" id="api-raw" role="tabpanel">
                            <pre id="apiRawJson" class="response-content"><code></code></pre>
                        </div>

                        <!-- Response Headers -->
                        <div class="tab-pane fade" id="api-headers" role="tabpanel">
                            <div id="apiResponseHeaders" class="headers-list"></div>
                        </div>

                        <!-- cURL Command -->
                        <div class="tab-pane fade" id="api-curl" role="tabpanel">
                            <pre id="apiCurlCommand" class="curl-command"><code></code></pre>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="copyApiCurl">
                                <i data-feather="copy" class="me-1"></i>
                                Copy cURL Command
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Parameters Modal -->
    <div class="modal fade" id="quickParamsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="zap" class="me-2"></i>
                        Quick Parameter Sets
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="param-sets">
                        <div class="param-set" data-params='{"limit": "100", "sort": "name"}'>
                            <h6>Standard Pagination</h6>
                            <p class="text-muted">limit=100, sort=name</p>
                        </div>
                        <div class="param-set" data-params='{"limit": "50", "after": "", "sort": "updated_at"}'>
                            <h6>Recent Updates</h6>
                            <p class="text-muted">limit=50, sort=updated_at</p>
                        </div>
                        <div class="param-set" data-params='{"active": "true", "limit": "200"}'>
                            <h6>Active Items Only</h6>
                            <p class="text-muted">active=true, limit=200</p>
                        </div>
                        <div class="param-set" data-params='{"deleted": "true", "limit": "25"}'>
                            <h6>Deleted Items</h6>
                            <p class="text-muted">deleted=true, limit=25</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="eye" class="me-2"></i>
                        Request Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="requestPreviewContent">
                        <!-- Request preview will be populated here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="sendFromPreview">
                        <i data-feather="send" class="me-1"></i>
                        Send Request
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vend API Tester JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const VendApiTester = {
        endpoints: {
            products: [
                { value: 'products', label: 'List All Products', method: 'GET' },
                { value: 'products/{id}', label: 'Get Product by ID', method: 'GET' },
                { value: 'products', label: 'Create Product', method: 'POST' },
                { value: 'products/{id}', label: 'Update Product', method: 'PUT' },
                { value: 'products/{id}', label: 'Delete Product', method: 'DELETE' }
            ],
            inventory: [
                { value: 'inventory', label: 'List Inventory', method: 'GET' },
                { value: 'inventory/{outlet_id}', label: 'Get Outlet Inventory', method: 'GET' },
                { value: 'inventory', label: 'Update Inventory', method: 'POST' }
            ],
            sales: [
                { value: 'sales', label: 'List Sales', method: 'GET' },
                { value: 'sales/{id}', label: 'Get Sale by ID', method: 'GET' },
                { value: 'sales', label: 'Create Sale', method: 'POST' }
            ],
            outlets: [
                { value: 'outlets', label: 'List Outlets', method: 'GET' },
                { value: 'outlets/{id}', label: 'Get Outlet by ID', method: 'GET' }
            ],
            consignments: [
                { value: 'consignments', label: 'List Consignments', method: 'GET' },
                { value: 'consignments/{id}', label: 'Get Consignment by ID', method: 'GET' },
                { value: 'consignments', label: 'Create Consignment', method: 'POST' }
            ]
        },

        init() {
            this.bindEvents();
            this.checkAuthStatus();
        },

        bindEvents() {
            // Category selection
            document.getElementById('endpointCategory').addEventListener('change', (e) => {
                this.populateEndpoints(e.target.value);
            });

            // Endpoint selection
            document.getElementById('apiEndpoint').addEventListener('change', (e) => {
                this.updateMethodFromEndpoint(e.target.value);
            });

            // Method change
            document.querySelectorAll('input[name="vendMethod"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    this.toggleRequestBody(e.target.value);
                });
            });

            // Send request
            document.getElementById('sendRequest').addEventListener('click', () => {
                this.sendApiRequest();
            });

            // Test auth
            document.getElementById('testAuth').addEventListener('click', () => {
                this.testAuthentication();
            });

            // Quick actions
            document.querySelectorAll('.quick-action').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    this.quickAction(e.target.dataset.endpoint);
                });
            });

            // Add parameter
            document.getElementById('addParam').addEventListener('click', () => {
                this.addParameterRow();
            });

            // Quick params
            document.getElementById('quickParams').addEventListener('click', () => {
                this.showQuickParams();
            });

            // JSON tools
            document.getElementById('formatRequestJson').addEventListener('click', () => {
                this.formatRequestJson();
            });

            document.getElementById('validateRequestJson').addEventListener('click', () => {
                this.validateRequestJson();
            });

            // Preview request
            document.getElementById('previewRequest').addEventListener('click', () => {
                this.previewRequest();
            });
        },

        populateEndpoints(category) {
            const endpointSelect = document.getElementById('apiEndpoint');
            const customGroup = document.getElementById('customEndpointGroup');

            endpointSelect.innerHTML = '<option value="">Select endpoint</option>';

            if (category === 'custom') {
                endpointSelect.disabled = true;
                customGroup.style.display = 'block';
            } else {
                endpointSelect.disabled = false;
                customGroup.style.display = 'none';

                if (this.endpoints[category]) {
                    this.endpoints[category].forEach(endpoint => {
                        const option = document.createElement('option');
                        option.value = endpoint.value;
                        option.textContent = endpoint.label;
                        option.dataset.method = endpoint.method;
                        endpointSelect.appendChild(option);
                    });
                }
            }
        },

        updateMethodFromEndpoint(endpoint) {
            const option = document.querySelector(`#apiEndpoint option[value="${endpoint}"]`);
            if (option && option.dataset.method) {
                const methodRadio = document.getElementById(`vend${option.dataset.method.toLowerCase()}`);
                if (methodRadio) {
                    methodRadio.checked = true;
                    this.toggleRequestBody(option.dataset.method);
                }
            }
        },

        toggleRequestBody(method) {
            const requestBodyGroup = document.getElementById('requestBodyGroup');
            requestBodyGroup.style.display = ['POST', 'PUT', 'PATCH'].includes(method) ? 'block' : 'none';
        },

        async sendApiRequest() {
            const endpoint = this.buildEndpointUrl();
            const method = document.querySelector('input[name="vendMethod"]:checked').value;
            const params = this.collectParameters();
            const body = this.getRequestBody();

            if (!endpoint) {
                this.showError('Please select an endpoint');
                return;
            }

            // Show status
            document.getElementById('apiStatus').style.display = 'block';
            this.updateRequestStatus('Sending API request...', 50);

            try {
                const response = await fetch('/admin/api-lab/vend', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'send_request',
                        endpoint: endpoint,
                        method: method,
                        params: params,
                        body: body,
                        options: this.collectOptions()
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.displayApiResponse(result.result);
                    this.updateRequestStatus('Request completed', 100);
                    this.updateRateLimits(result.result.rate_limits);
                } else {
                    this.showError(result.error);
                }

            } catch (error) {
                this.showError('Request failed: ' + error.message);
            }
        },

        async checkAuthStatus() {
            try {
                const response = await fetch('/admin/api-lab/vend', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'check_auth'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.updateAuthStatus(result.result.authenticated, result.result.message);
                } else {
                    this.updateAuthStatus(false, result.error);
                }

            } catch (error) {
                this.updateAuthStatus(false, 'Auth check failed');
            }
        },

        updateAuthStatus(authenticated, message) {
            const badge = document.getElementById('authStatusBadge');

            if (authenticated) {
                badge.className = 'badge bg-success';
                badge.textContent = 'Authenticated';
            } else {
                badge.className = 'badge bg-danger';
                badge.textContent = 'Not Authenticated';
            }

            badge.title = message;
        },

        buildEndpointUrl() {
            const category = document.getElementById('endpointCategory').value;

            if (category === 'custom') {
                return document.getElementById('customEndpoint').value;
            } else {
                return document.getElementById('apiEndpoint').value;
            }
        },

        collectParameters() {
            const params = {};

            document.querySelectorAll('.param-row').forEach(row => {
                const nameInput = row.querySelector('input:first-child');
                const valueInput = row.querySelector('input:nth-child(2)');

                if (nameInput.value && valueInput.value) {
                    params[nameInput.value] = valueInput.value;
                }
            });

            return params;
        },

        getRequestBody() {
            const bodyTextarea = document.getElementById('requestBody');
            return bodyTextarea.value || null;
        },

        collectOptions() {
            return {
                include_deleted: document.getElementById('includeDeleted').checked,
                raw_response: document.getElementById('rawResponse').checked,
                debug_mode: document.getElementById('debugMode').checked
            };
        },

        displayApiResponse(data) {
            // Show response card
            document.getElementById('apiResponseCard').style.display = 'block';

            // Update summary
            document.getElementById('apiResponseStatus').textContent = data.status_code || 'N/A';
            document.getElementById('apiResponseTime').textContent = (data.response_time || 0) + 'ms';
            document.getElementById('apiResponseRecords').textContent = this.countRecords(data.data);
            document.getElementById('apiResponseSize').textContent = this.formatBytes(data.response_size || 0);

            // Display data tabs
            this.displayFormattedData(data.data);
            this.displayRawJson(data);
            this.displayResponseHeaders(data.headers || {});
            this.displayCurl(data.curl_command);

            // Syntax highlighting
            if (window.hljs) {
                hljs.highlightAll();
            }
        },

        displayFormattedData(data) {
            const container = document.getElementById('apiDataFormatted');

            if (Array.isArray(data)) {
                container.innerHTML = this.formatArrayData(data);
            } else if (typeof data === 'object') {
                container.innerHTML = this.formatObjectData(data);
            } else {
                container.innerHTML = `<pre>${this.escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
            }
        },

        formatArrayData(array) {
            if (array.length === 0) {
                return '<div class="text-muted">No records found</div>';
            }

            // Create table for array data
            const firstItem = array[0];
            const headers = Object.keys(firstItem);

            let html = '<div class="table-responsive"><table class="table table-sm table-striped">';
            html += '<thead><tr>';
            headers.forEach(header => {
                html += `<th>${this.escapeHtml(header)}</th>`;
            });
            html += '</tr></thead><tbody>';

            array.slice(0, 50).forEach(item => { // Limit to first 50 items
                html += '<tr>';
                headers.forEach(header => {
                    const value = item[header];
                    html += `<td>${this.escapeHtml(this.formatValue(value))}</td>`;
                });
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            if (array.length > 50) {
                html += `<div class="text-muted mt-2">Showing first 50 of ${array.length} records</div>`;
            }

            return html;
        },

        formatObjectData(obj) {
            let html = '<div class="object-viewer">';

            Object.entries(obj).forEach(([key, value]) => {
                html += `
                    <div class="object-row d-flex justify-content-between py-1 border-bottom">
                        <strong>${this.escapeHtml(key)}:</strong>
                        <span class="text-muted">${this.escapeHtml(this.formatValue(value))}</span>
                    </div>
                `;
            });

            html += '</div>';
            return html;
        },

        formatValue(value) {
            if (value === null) return 'null';
            if (value === undefined) return 'undefined';
            if (typeof value === 'object') return JSON.stringify(value);
            return String(value);
        },

        displayRawJson(data) {
            document.getElementById('apiRawJson').querySelector('code').textContent =
                JSON.stringify(data, null, 2);
        },

        displayResponseHeaders(headers) {
            const container = document.getElementById('apiResponseHeaders');
            container.innerHTML = '';

            Object.entries(headers).forEach(([name, value]) => {
                const row = document.createElement('div');
                row.className = 'header-row d-flex justify-content-between py-1 border-bottom';
                row.innerHTML = `
                    <strong>${this.escapeHtml(name)}:</strong>
                    <span class="text-muted">${this.escapeHtml(value)}</span>
                `;
                container.appendChild(row);
            });
        },

        displayCurl(curlCommand) {
            document.getElementById('apiCurlCommand').querySelector('code').textContent =
                curlCommand || 'cURL command not available';
        },

        updateRateLimits(rateLimits) {
            if (rateLimits) {
                document.getElementById('rateLimitRemaining').textContent = rateLimits.remaining || '--';
                document.getElementById('rateLimitTotal').textContent = rateLimits.total || '--';
                document.getElementById('rateLimitReset').textContent =
                    rateLimits.reset ? new Date(rateLimits.reset * 1000).toLocaleTimeString() : '--';
            }
        },

        countRecords(data) {
            if (Array.isArray(data)) return data.length;
            if (typeof data === 'object' && data !== null) return Object.keys(data).length;
            return data ? 1 : 0;
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + sizes[i];
        },

        updateRequestStatus(text, progress) {
            document.getElementById('requestStatusText').textContent = text;
            document.getElementById('requestProgressBar').style.width = progress + '%';
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

        addParameterRow() {
            const container = document.getElementById('paramsContainer');
            const row = document.createElement('div');
            row.className = 'param-row d-flex mb-2';
            row.innerHTML = `
                <input type="text" class="form-control me-2" placeholder="Parameter Name">
                <input type="text" class="form-control me-2" placeholder="Parameter Value">
                <button type="button" class="btn btn-outline-danger btn-sm remove-param">
                    <i data-feather="trash-2"></i>
                </button>
            `;

            container.appendChild(row);

            // Bind remove handler
            row.querySelector('.remove-param').addEventListener('click', () => {
                row.remove();
            });

            feather.replace();
        },

        quickAction(endpoint) {
            // Set up quick action based on endpoint type
            document.getElementById('endpointCategory').value = endpoint;
            this.populateEndpoints(endpoint);

            // Select first endpoint in category
            const endpointSelect = document.getElementById('apiEndpoint');
            if (endpointSelect.options.length > 1) {
                endpointSelect.selectedIndex = 1;
                this.updateMethodFromEndpoint(endpointSelect.value);
            }
        },

        formatRequestJson() {
            try {
                const textarea = document.getElementById('requestBody');
                const json = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(json, null, 2);
            } catch (error) {
                this.showError('Invalid JSON: ' + error.message);
            }
        },

        validateRequestJson() {
            try {
                const body = document.getElementById('requestBody').value;
                if (body) {
                    JSON.parse(body);
                    // Show success
                }
            } catch (error) {
                this.showError('Invalid JSON: ' + error.message);
            }
        }
    };

    // Initialize
    VendApiTester.init();
});
</script>