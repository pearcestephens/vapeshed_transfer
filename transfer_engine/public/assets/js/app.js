/**
 * Transfer Engine - Core JavaScript Module
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Core functionality for transfer engine frontend
 */

class TransferEngine {
    constructor() {
        // Derive base URL from meta tag or global if available to support subfolder deployments
        const metaBase = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
        const globalBase = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
    this.baseUrl = String(metaBase || globalBase || '').replace(/\/$/, '');
        this.apiBase = this.baseUrl + '/api';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeTooltips();
        this.setupAjaxDefaults();
    }

    /**
     * Set up default AJAX configuration
     */
    setupAjaxDefaults() {
        if (typeof $ !== 'undefined') {
            $.ajaxSetup({
                beforeSend: (xhr) => {
                    if (this.csrfToken) {
                        xhr.setRequestHeader('X-CSRF-Token', this.csrfToken);
                    }
                },
                error: (xhr, status, error) => {
                    this.handleAjaxError(xhr, status, error);
                }
            });
        }
    }

    /**
     * Bind global event handlers
     */
    bindEvents() {
        document.addEventListener('DOMContentLoaded', () => {
            this.initializeForms();
            this.initializeMetrics();
            // Header quick actions
            document.getElementById('emergencyStop')?.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleKillSwitch(true);
            });
            document.getElementById('systemResume')?.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleKillSwitch(false);
            });
            document.getElementById('vsSubnavRefresh')?.addEventListener('click', (e) => {
                e.preventDefault();
                this.refreshStatusBar();
            });
            // Initial status
            this.refreshStatusBar();
        });

        // Form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ajax-form')) {
                e.preventDefault();
                this.handleFormSubmit(e.target);
            }
        });

        // Button clicks
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-execute')) {
                this.handleExecuteTransfer(e.target);
            } else if (e.target.classList.contains('btn-kill-switch')) {
                this.handleKillSwitch(e.target);
            } else if (e.target.classList.contains('btn-load-preset')) {
                this.handleLoadPreset(e.target);
            }
        });
    }

    /**
     * Initialize native tooltips (no external dependencies)
     */
    initializeTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"], [title]');
        tooltipTriggerList.forEach(element => {
            // Simple tooltip implementation using native DOM
            element.addEventListener('mouseenter', function() {
                const title = this.getAttribute('title') || this.getAttribute('data-bs-title');
                if (title) {
                    const tooltip = document.createElement('div');
                    tooltip.textContent = title;
                    tooltip.className = 'custom-tooltip';
                    tooltip.style.cssText = `
                        position: absolute; 
                        background: #333; 
                        color: white; 
                        padding: 4px 8px; 
                        border-radius: 4px; 
                        font-size: 12px; 
                        pointer-events: none; 
                        z-index: 1000;
                    `;
                    document.body.appendChild(tooltip);
                    this._tooltip = tooltip;
                }
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    document.body.removeChild(this._tooltip);
                    this._tooltip = null;
                }
            });
        });
    }

    /**
     * Initialize forms with validation
     */
    initializeForms() {
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    }

    /**
     * Initialize real-time metrics
     */
    initializeMetrics() {
        const metricsContainer = document.querySelector('.metrics-container');
        if (metricsContainer) {
            this.updateMetrics();
            setInterval(() => this.updateMetrics(), 30000); // Update every 30 seconds
        }
    }

    /**
     * Handle AJAX form submissions
     */
    async handleFormSubmit(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');
        
        if (submitBtn) {
            this.setButtonLoading(submitBtn, true);
        }

        try {
            const response = await this.makeRequest(form.action, {
                method: form.method || 'POST',
                body: formData
            });

            if (response.success) {
                this.showAlert('success', response.message || 'Operation completed successfully');
                
                // Redirect if specified
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1000);
                }
                
                // Reset form if specified
                if (response.reset) {
                    form.reset();
                }
            } else {
                this.showAlert('danger', response.message || 'An error occurred');
            }
        } catch (error) {
            this.showAlert('danger', 'Network error: ' + error.message);
        } finally {
            if (submitBtn) {
                this.setButtonLoading(submitBtn, false);
            }
        }
    }

    /**
     * Execute transfer
     */
    async handleExecuteTransfer(button) {
        const configId = button.dataset.configId;
        const simulate = button.dataset.simulate === 'true';
        
        if (!configId) {
            this.showAlert('warning', 'No configuration selected');
            return;
        }

        // Confirm execution
        if (!simulate && !confirm('Execute transfer with this configuration? This action cannot be undone.')) {
            return;
        }

        this.setButtonLoading(button, true);

        try {
            const response = await this.makeRequest(`${this.apiBase}/transfer/execute`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    config_id: configId,
                    simulate: simulate
                })
            });

            if (response.success) {
                this.showAlert('success', simulate ? 'Simulation completed' : 'Transfer executed successfully');
                
                // Show execution details
                if (response.data && response.data.execution_id) {
                    setTimeout(() => {
                        const target = this.baseUrl + `/transfer/execution/${response.data.execution_id}`;
                        window.location.href = target;
                    }, 1500);
                }
                
                // Update metrics
                this.updateMetrics();
            } else {
                this.showAlert('danger', response.message || 'Transfer execution failed');
            }
        } catch (error) {
            this.showAlert('danger', 'Execution error: ' + error.message);
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    /**
     * Handle kill switch activation
     */
    async handleKillSwitch(button) {
        if (!confirm('EMERGENCY STOP: This will immediately halt all transfer operations. Continue?')) return;
        this.setButtonLoading(button, true);
        try {
            await this.toggleKillSwitch(true);
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    async toggleKillSwitch(activate = true) {
        try {
            const endpoint = activate ? 'activate' : 'deactivate';
            const response = await this.makeRequest(`${this.apiBase}/kill-switch/${endpoint}`, { method: 'POST' });
            if (response && (response.success || response.ok)) {
                if (activate) {
                    this.showAlert('warning', 'Kill switch ACTIVATED - All operations stopped');
                    this.setWriteButtonsEnabled(false);
                } else {
                    this.showAlert('success', 'Kill switch DEACTIVATED - Operations may resume');
                    this.setWriteButtonsEnabled(true);
                }
                this.refreshStatusBar();
            } else {
                throw new Error(response?.error || 'Unexpected response');
            }
        } catch (e) {
            this.showAlert('danger', `Kill switch ${activate ? 'activate' : 'deactivate'} failed: ${e.message}`);
        }
    }

    setWriteButtonsEnabled(enabled) {
        document.querySelectorAll('.btn-execute, [data-can-write]')
            .forEach(btn => {
                btn.disabled = !enabled;
                if (!enabled) {
                    const msg = 'Disabled by Kill Switch / write policy';
                    btn.setAttribute('title', msg);
                    btn.setAttribute('data-bs-title', msg);
                } else {
                    btn.removeAttribute('title');
                    btn.removeAttribute('data-bs-title');
                }
            });
    }

    async refreshStatusBar() {
        try {
            const [healthRes, killRes] = await Promise.all([
                this.makeRequest(`${this.baseUrl}/api/health`, { method: 'GET' }).catch(() => null),
                this.makeRequest(`${this.apiBase}/kill-switch`, { method: 'GET' }).catch(() => null)
            ]);
            const ok = !!(healthRes && (healthRes.ok || healthRes.success));
            const kill = !!(killRes && (killRes.data ? killRes.data.active : killRes.active));
            const dot = document.getElementById('vsSubnavStatus');
            const eng = document.getElementById('vsSubnavEngineState');
            if (dot) {
                dot.classList.remove('status-ok','status-warn','status-err');
                dot.classList.add(kill ? 'status-warn' : (ok ? 'status-ok' : 'status-err'));
            }
            if (eng) {
                eng.textContent = kill ? 'Stopped (Kill switch)' : (ok ? 'Online' : 'Degraded');
            }
            // When kill is on, proactively disable write buttons
            this.setWriteButtonsEnabled(!kill);
        } catch (_) {
            // noop
        }
    }

    /**
     * Load configuration preset
     */
    async handleLoadPreset(button) {
        const presetName = button.dataset.preset;
        
        if (!presetName) {
            this.showAlert('warning', 'No preset specified');
            return;
        }

        this.setButtonLoading(button, true);

        try {
            // Use control-panel fn API for presets to be consistent across deployments
            const url = this.baseUrl.replace(/\/$/, '').replace(/\/public\/?$/, '') + '/control-panel/api/?fn=presets';
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({ name: presetName })
            });
            if (!resp.ok) {
                throw new Error(`HTTP ${resp.status}`);
            }
            const data = await resp.json();
            if (data && data.ok && data.data && data.data.config) {
                this.populateConfigForm(data.data.config);
                this.showAlert('info', `Loaded preset: ${presetName}`);
            } else if (data && data.success && data.config) {
                this.populateConfigForm(data.config);
                this.showAlert('info', `Loaded preset: ${presetName}`);
            } else {
                this.showAlert('danger', (data && (data.message || data.error)) || 'Failed to load preset');
            }
        } catch (error) {
            this.showAlert('danger', 'Preset loading error: ' + error.message);
        } finally {
            this.setButtonLoading(button, false);
        }
    }

    /**
     * Populate configuration form with preset data
     */
    populateConfigForm(config) {
        const form = document.querySelector('#configForm');
        if (!form) return;

        Object.keys(config).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = Boolean(config[key]);
                } else {
                    input.value = config[key];
                }
            }
        });

        // Trigger change events to update UI
        form.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Update real-time metrics
     */
    async updateMetrics() {
        try {
            const response = await this.makeRequest(`${this.apiBase}/metrics/dashboard`, {
                method: 'GET'
            });

            if (response.success && response.data) {
                this.updateMetricCards(response.data);
            }
        } catch (error) {
            console.warn('Failed to update metrics:', error);
        }
    }

    /**
     * Update metric display cards
     */
    updateMetricCards(metrics) {
        Object.keys(metrics).forEach(key => {
            const element = document.querySelector(`[data-metric="${key}"]`);
            if (element) {
                element.textContent = this.formatMetricValue(metrics[key], key);
            }
        });
    }

    /**
     * Format metric values for display
     */
    formatMetricValue(value, type) {
        switch (type) {
            case 'success_rate':
                return `${(value * 100).toFixed(1)}%`;
            case 'avg_execution_time':
                return `${value.toFixed(2)}s`;
            case 'total_transfers':
            case 'active_configs':
                return value.toLocaleString();
            default:
                return value;
        }
    }

    /**
     * Make HTTP request with error handling
     */
    async makeRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfToken
            }
        };

        const requestOptions = { ...defaultOptions, ...options };
        
        // Merge headers
        if (options.headers) {
            requestOptions.headers = { ...defaultOptions.headers, ...options.headers };
        }

        const response = await fetch(url, requestOptions);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * Set button loading state
     */
    setButtonLoading(button, isLoading) {
        if (isLoading) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = '<span class="spinner"></span> Loading...';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.textContent;
        }
    }

    /**
     * Show alert message
     */
    showAlert(type, message, duration = 5000) {
        // Remove existing alerts
        document.querySelectorAll('.alert-dynamic').forEach(alert => alert.remove());

        // Create new alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show alert-dynamic`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Insert at top of main content
        const mainContent = document.querySelector('.main-content') || document.body;
        mainContent.insertBefore(alert, mainContent.firstChild);

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, duration);
        }
    }

    /**
     * Handle AJAX errors
     */
    handleAjaxError(xhr, status, error) {
        let message = 'An unexpected error occurred';
        
        if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        } else if (xhr.status === 0) {
            message = 'Network connection error';
        } else if (xhr.status === 403) {
            message = 'Permission denied';
        } else if (xhr.status === 404) {
            message = 'Resource not found';
        } else if (xhr.status >= 500) {
            message = 'Server error occurred';
        }

        this.showAlert('danger', message);
    }

    /**
     * Utility: Format file size
     */
    formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Utility: Format duration
     */
    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        } else if (minutes > 0) {
            return `${minutes}m ${secs}s`;
        } else {
            return `${secs}s`;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.transferEngine = new TransferEngine();
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TransferEngine;
}