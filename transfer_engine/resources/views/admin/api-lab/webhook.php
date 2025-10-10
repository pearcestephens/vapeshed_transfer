<?php
/**
 * Webhook Testing Lab Interface
 * Advanced webhook testing with event simulation, payload editing, and response analysis
 */
?>

<div class="webhook-lab">
    <!-- Header -->
    <div class="lab-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i data-feather="activity" class="me-2"></i>
                Webhook Testing Lab
            </h4>
            <p class="text-muted mb-0">Test webhook endpoints with simulated events and custom payloads</p>
        </div>
        <div class="lab-actions">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary btn-sm" id="loadTemplate">
                    <i data-feather="file-text" class="me-1"></i>
                    Load Template
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="saveTest">
                    <i data-feather="save" class="me-1"></i>
                    Save Test
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" id="testHistory">
                    <i data-feather="clock" class="me-1"></i>
                    History
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Panel: Configuration -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="settings" class="me-2"></i>
                        Webhook Configuration
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Event Type Selection -->
                    <div class="form-group mb-3">
                        <label for="eventType" class="form-label">Event Type</label>
                        <select id="eventType" class="form-select">
                            <option value="">Select Event Type</option>
                            <optgroup label="Stock Events">
                                <option value="stock.transfer.created">Stock Transfer Created</option>
                                <option value="stock.transfer.completed">Stock Transfer Completed</option>
                                <option value="stock.transfer.failed">Stock Transfer Failed</option>
                                <option value="stock.adjustment">Stock Adjustment</option>
                                <option value="stock.alert.low">Low Stock Alert</option>
                            </optgroup>
                            <optgroup label="Purchase Order Events">
                                <option value="po.created">Purchase Order Created</option>
                                <option value="po.received">Purchase Order Received</option>
                                <option value="po.cancelled">Purchase Order Cancelled</option>
                            </optgroup>
                            <optgroup label="System Events">
                                <option value="system.health.warning">System Health Warning</option>
                                <option value="system.health.critical">System Health Critical</option>
                                <option value="sync.completed">Sync Completed</option>
                                <option value="sync.failed">Sync Failed</option>
                            </optgroup>
                            <optgroup label="Custom">
                                <option value="custom">Custom Event</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Webhook URL -->
                    <div class="form-group mb-3">
                        <label for="webhookUrl" class="form-label">Webhook URL</label>
                        <div class="input-group">
                            <input type="url" id="webhookUrl" class="form-control"
                                   placeholder="https://api.example.com/webhooks/endpoint"
                                   value="<?= $default_webhook_url ?? '' ?>">
                            <button class="btn btn-outline-secondary" type="button" id="validateUrl">
                                <i data-feather="check" class="me-1"></i>
                                Validate
                            </button>
                        </div>
                        <div class="form-text">
                            URL validation checks endpoint availability and SSL certificate
                        </div>
                    </div>

                    <!-- HTTP Method -->
                    <div class="form-group mb-3">
                        <label class="form-label">HTTP Method</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="httpMethod" id="methodPost" value="POST" checked>
                            <label class="btn btn-outline-primary" for="methodPost">POST</label>

                            <input type="radio" class="btn-check" name="httpMethod" id="methodPut" value="PUT">
                            <label class="btn btn-outline-primary" for="methodPut">PUT</label>

                            <input type="radio" class="btn-check" name="httpMethod" id="methodPatch" value="PATCH">
                            <label class="btn btn-outline-primary" for="methodPatch">PATCH</label>
                        </div>
                    </div>

                    <!-- Headers -->
                    <div class="form-group mb-3">
                        <label class="form-label">HTTP Headers</label>
                        <div id="headersContainer">
                            <div class="header-row d-flex mb-2">
                                <input type="text" class="form-control me-2" placeholder="Header Name"
                                       value="Content-Type">
                                <input type="text" class="form-control me-2" placeholder="Header Value"
                                       value="application/json">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-header">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                            <div class="header-row d-flex mb-2">
                                <input type="text" class="form-control me-2" placeholder="Header Name"
                                       value="Authorization">
                                <input type="text" class="form-control me-2" placeholder="Header Value"
                                       value="Bearer <?= $api_token ?? 'your-token-here' ?>">
                                <button type="button" class="btn btn-outline-danger btn-sm remove-header">
                                    <i data-feather="trash-2"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addHeader">
                            <i data-feather="plus" class="me-1"></i>
                            Add Header
                        </button>
                    </div>

                    <!-- Payload Editor -->
                    <div class="form-group mb-3">
                        <label for="payloadEditor" class="form-label">
                            Event Payload
                            <span class="badge bg-secondary ms-2">JSON</span>
                        </label>
                        <div class="payload-editor-container">
                            <textarea id="payloadEditor" class="form-control font-monospace" rows="12"
                                      placeholder="Enter JSON payload...">{
  "event": "stock.transfer.created",
  "timestamp": "<?= date('c') ?>",
  "data": {
    "transfer_id": 12345,
    "from_outlet": "Store A",
    "to_outlet": "Store B",
    "products": [
      {
        "product_id": "SKU001",
        "quantity": 10,
        "unit_cost": 25.99
      }
    ],
    "status": "pending",
    "created_by": "system"
  },
  "metadata": {
    "version": "1.0",
    "source": "transfer_engine",
    "test_mode": true
  }
}</textarea>
                        </div>
                        <div class="payload-tools mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="formatJson">
                                <i data-feather="align-left" class="me-1"></i>
                                Format JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="validateJson">
                                <i data-feather="check-circle" class="me-1"></i>
                                Validate JSON
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="generateSample">
                                <i data-feather="refresh-cw" class="me-1"></i>
                                Generate Sample
                            </button>
                        </div>
                    </div>

                    <!-- Test Options -->
                    <div class="form-group mb-3">
                        <label class="form-label">Test Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="followRedirects" checked>
                            <label class="form-check-label" for="followRedirects">
                                Follow Redirects
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="verifySSL" checked>
                            <label class="form-check-label" for="verifySSL">
                                Verify SSL Certificate
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="captureResponse" checked>
                            <label class="form-check-label" for="captureResponse">
                                Capture Full Response
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="testRetries">
                            <label class="form-check-label" for="testRetries">
                                Test Retry Logic (simulate failures)
                            </label>
                        </div>
                    </div>

                    <!-- Timeout -->
                    <div class="form-group mb-3">
                        <label for="timeoutSeconds" class="form-label">Timeout (seconds)</label>
                        <input type="number" id="timeoutSeconds" class="form-control" value="30" min="1" max="300">
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
                        <button type="button" class="btn btn-primary" id="sendWebhook">
                            <i data-feather="send" class="me-2"></i>
                            Send Webhook
                        </button>
                        <div class="row">
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-secondary w-100" id="testConnection">
                                    <i data-feather="wifi" class="me-1"></i>
                                    Test Connection
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="btn btn-outline-warning w-100" id="stressTest">
                                    <i data-feather="zap" class="me-1"></i>
                                    Stress Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Test Status -->
            <div class="card mb-3" id="testStatus" style="display: none;">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i data-feather="activity" class="me-2"></i>
                        Test Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="test-progress">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span id="testStatusText">Preparing request...</span>
                            <span id="testDuration" class="badge bg-secondary">0.00s</span>
                        </div>
                        <div class="progress">
                            <div id="testProgressBar" class="progress-bar" role="progressbar"
                                 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Response Analysis -->
            <div class="card" id="responseCard" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i data-feather="monitor" class="me-2"></i>
                        Response Analysis
                    </h6>
                    <div class="response-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="copyResponse">
                            <i data-feather="copy" class="me-1"></i>
                            Copy
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="downloadResponse">
                            <i data-feather="download" class="me-1"></i>
                            Download
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Response Summary -->
                    <div class="response-summary row mb-3">
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="responseStatus" class="mb-1">200</h4>
                                <small class="text-muted">Status</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="responseTime" class="mb-1">234ms</h4>
                                <small class="text-muted">Response Time</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="responseSize" class="mb-1">1.2KB</h4>
                                <small class="text-muted">Size</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="metric-card text-center">
                                <h4 id="responseType" class="mb-1">JSON</h4>
                                <small class="text-muted">Type</small>
                            </div>
                        </div>
                    </div>

                    <!-- Response Tabs -->
                    <ul class="nav nav-tabs" id="responseTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="response-body-tab" data-bs-toggle="tab"
                                    data-bs-target="#response-body" type="button" role="tab">
                                <i data-feather="file-text" class="me-1"></i>
                                Response Body
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="response-headers-tab" data-bs-toggle="tab"
                                    data-bs-target="#response-headers" type="button" role="tab">
                                <i data-feather="list" class="me-1"></i>
                                Headers
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="response-timing-tab" data-bs-toggle="tab"
                                    data-bs-target="#response-timing" type="button" role="tab">
                                <i data-feather="clock" class="me-1"></i>
                                Timing
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="response-curl-tab" data-bs-toggle="tab"
                                    data-bs-target="#response-curl" type="button" role="tab">
                                <i data-feather="terminal" class="me-1"></i>
                                cURL
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-3" id="responseTabContent">
                        <!-- Response Body -->
                        <div class="tab-pane fade show active" id="response-body" role="tabpanel">
                            <pre id="responseBody" class="response-content"><code></code></pre>
                        </div>

                        <!-- Response Headers -->
                        <div class="tab-pane fade" id="response-headers" role="tabpanel">
                            <div id="responseHeaders" class="headers-list"></div>
                        </div>

                        <!-- Response Timing -->
                        <div class="tab-pane fade" id="response-timing" role="tabpanel">
                            <div id="responseTiming" class="timing-breakdown"></div>
                        </div>

                        <!-- cURL Command -->
                        <div class="tab-pane fade" id="response-curl" role="tabpanel">
                            <pre id="curlCommand" class="curl-command"><code></code></pre>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="copyCurl">
                                <i data-feather="copy" class="me-1"></i>
                                Copy cURL Command
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="clock" class="me-2"></i>
                        Webhook Test History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="historyContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="clearHistory">
                        <i data-feather="trash-2" class="me-1"></i>
                        Clear History
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Test Modal -->
    <div class="modal fade" id="saveTestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-feather="save" class="me-2"></i>
                        Save Webhook Test
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="testName" class="form-label">Test Name</label>
                        <input type="text" id="testName" class="form-control"
                               placeholder="Enter descriptive name for this test">
                    </div>
                    <div class="form-group mb-3">
                        <label for="testDescription" class="form-label">Description (Optional)</label>
                        <textarea id="testDescription" class="form-control" rows="3"
                                  placeholder="Describe what this test validates..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="saveAsTemplate">
                        <label class="form-check-label" for="saveAsTemplate">
                            Save as template for reuse
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmSaveTest">Save Test</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Webhook Lab JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const WebhookLab = {
        init() {
            this.bindEvents();
            this.initializeEditor();
        },

        bindEvents() {
            // Event type change
            document.getElementById('eventType').addEventListener('change', (e) => {
                this.generateSamplePayload(e.target.value);
            });

            // Send webhook
            document.getElementById('sendWebhook').addEventListener('click', () => {
                this.sendWebhook();
            });

            // Test connection
            document.getElementById('testConnection').addEventListener('click', () => {
                this.testConnection();
            });

            // JSON tools
            document.getElementById('formatJson').addEventListener('click', () => {
                this.formatJson();
            });

            document.getElementById('validateJson').addEventListener('click', () => {
                this.validateJson();
            });

            document.getElementById('generateSample').addEventListener('click', () => {
                const eventType = document.getElementById('eventType').value;
                this.generateSamplePayload(eventType);
            });

            // Header management
            document.getElementById('addHeader').addEventListener('click', () => {
                this.addHeaderRow();
            });

            // Save/Load
            document.getElementById('saveTest').addEventListener('click', () => {
                this.showSaveModal();
            });

            document.getElementById('testHistory').addEventListener('click', () => {
                this.showHistoryModal();
            });
        },

        initializeEditor() {
            // Initialize syntax highlighting if available
            if (window.hljs) {
                hljs.highlightAll();
            }
        },

        async sendWebhook() {
            const url = document.getElementById('webhookUrl').value;
            const method = document.querySelector('input[name="httpMethod"]:checked').value;
            const payload = document.getElementById('payloadEditor').value;

            if (!url) {
                this.showError('Please enter a webhook URL');
                return;
            }

            try {
                JSON.parse(payload);
            } catch (error) {
                this.showError('Invalid JSON payload: ' + error.message);
                return;
            }

            // Show status card
            document.getElementById('testStatus').style.display = 'block';
            this.updateStatus('Sending webhook...', 50);

            try {
                const response = await fetch('/admin/api-lab/webhook', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'send_webhook',
                        url: url,
                        method: method,
                        payload: payload,
                        headers: this.collectHeaders(),
                        options: this.collectOptions()
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.displayResponse(result.result);
                    this.updateStatus('Webhook sent successfully', 100);
                } else {
                    this.showError(result.error);
                }

            } catch (error) {
                this.showError('Request failed: ' + error.message);
            }
        },

        collectHeaders() {
            const headers = {};
            document.querySelectorAll('.header-row').forEach(row => {
                const nameInput = row.querySelector('input:first-child');
                const valueInput = row.querySelector('input:nth-child(2)');

                if (nameInput.value && valueInput.value) {
                    headers[nameInput.value] = valueInput.value;
                }
            });
            return headers;
        },

        collectOptions() {
            return {
                follow_redirects: document.getElementById('followRedirects').checked,
                verify_ssl: document.getElementById('verifySSL').checked,
                capture_response: document.getElementById('captureResponse').checked,
                test_retries: document.getElementById('testRetries').checked,
                timeout: parseInt(document.getElementById('timeoutSeconds').value) || 30
            };
        },

        displayResponse(data) {
            // Show response card
            document.getElementById('responseCard').style.display = 'block';

            // Update summary metrics
            document.getElementById('responseStatus').textContent = data.status_code || 'N/A';
            document.getElementById('responseTime').textContent = (data.response_time || 0) + 'ms';
            document.getElementById('responseSize').textContent = this.formatBytes(data.response_size || 0);
            document.getElementById('responseType').textContent = data.content_type || 'Unknown';

            // Response body
            document.getElementById('responseBody').querySelector('code').textContent =
                JSON.stringify(data.body, null, 2);

            // Headers
            this.displayHeaders(data.headers || {});

            // Timing
            this.displayTiming(data.timing || {});

            // cURL command
            document.getElementById('curlCommand').querySelector('code').textContent =
                data.curl_command || '';

            // Syntax highlighting
            if (window.hljs) {
                hljs.highlightAll();
            }
        },

        displayHeaders(headers) {
            const container = document.getElementById('responseHeaders');
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

        displayTiming(timing) {
            const container = document.getElementById('responseTiming');
            container.innerHTML = '';

            const timingData = [
                { label: 'DNS Resolution', value: timing.namelookup_time || 0 },
                { label: 'Connection Time', value: timing.connect_time || 0 },
                { label: 'SSL Handshake', value: timing.ssl_time || 0 },
                { label: 'Time to First Byte', value: timing.pretransfer_time || 0 },
                { label: 'Download Time', value: timing.starttransfer_time || 0 },
                { label: 'Total Time', value: timing.total_time || 0 }
            ];

            timingData.forEach(item => {
                const row = document.createElement('div');
                row.className = 'timing-row d-flex justify-content-between py-1';
                row.innerHTML = `
                    <span>${item.label}:</span>
                    <span class="badge bg-secondary">${(item.value * 1000).toFixed(2)}ms</span>
                `;
                container.appendChild(row);
            });
        },

        generateSamplePayload(eventType) {
            const samples = {
                'stock.transfer.created': {
                    event: 'stock.transfer.created',
                    timestamp: new Date().toISOString(),
                    data: {
                        transfer_id: 12345,
                        from_outlet: 'Store A',
                        to_outlet: 'Store B',
                        products: [{
                            product_id: 'SKU001',
                            quantity: 10,
                            unit_cost: 25.99
                        }],
                        status: 'pending',
                        created_by: 'system'
                    }
                },
                'po.created': {
                    event: 'po.created',
                    timestamp: new Date().toISOString(),
                    data: {
                        po_id: 'PO-2024-001',
                        supplier: 'Supplier ABC',
                        total_cost: 1250.00,
                        expected_delivery: '2024-01-15',
                        items: [{
                            product_id: 'SKU002',
                            quantity: 50,
                            unit_cost: 25.00
                        }]
                    }
                }
            };

            const sample = samples[eventType];
            if (sample) {
                document.getElementById('payloadEditor').value = JSON.stringify(sample, null, 2);
            }
        },

        formatJson() {
            try {
                const editor = document.getElementById('payloadEditor');
                const json = JSON.parse(editor.value);
                editor.value = JSON.stringify(json, null, 2);
            } catch (error) {
                this.showError('Invalid JSON: ' + error.message);
            }
        },

        validateJson() {
            try {
                const payload = document.getElementById('payloadEditor').value;
                JSON.parse(payload);
                this.showSuccess('JSON is valid');
            } catch (error) {
                this.showError('Invalid JSON: ' + error.message);
            }
        },

        addHeaderRow() {
            const container = document.getElementById('headersContainer');
            const row = document.createElement('div');
            row.className = 'header-row d-flex mb-2';
            row.innerHTML = `
                <input type="text" class="form-control me-2" placeholder="Header Name">
                <input type="text" class="form-control me-2" placeholder="Header Value">
                <button type="button" class="btn btn-outline-danger btn-sm remove-header">
                    <i data-feather="trash-2"></i>
                </button>
            `;

            container.appendChild(row);

            // Bind remove handler
            row.querySelector('.remove-header').addEventListener('click', () => {
                row.remove();
            });

            feather.replace();
        },

        updateStatus(text, progress) {
            document.getElementById('testStatusText').textContent = text;
            document.getElementById('testProgressBar').style.width = progress + '%';
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
            // Implementation for error notifications
            console.error(message);
        },

        showSuccess(message) {
            // Implementation for success notifications
            console.log(message);
        }
    };

    // Initialize
    WebhookLab.init();
});
</script>