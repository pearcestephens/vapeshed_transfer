/**
 * Vapeshed Transfer Engine - Control Panel JavaScript
 * Enterprise-grade real-time interface management
 */

class TransferControlPanel {
    constructor() {
        this.config = {
            updateInterval: 5000,    // Status update interval
            consoleMaxLines: 1000,   // Max console lines
            progressSteps: [
                'Initializing engine...',
                'Loading configuration...',
                'Validating parameters...',
                'Connecting to database...',
                'Processing transfers...',
                'Generating reports...',
                'Finalizing results...'
            ]
        };
        
        this.state = {
            engineStatus: 'unknown',
            killSwitchActive: false,
            runInProgress: false,
            consoleLines: 0,
            consolePaused: false,
            autoScroll: true,
            currentPreset: 'balanced',
            lastRunId: null
        };
        
        this.timers = {
            statusUpdate: null,
            clockUpdate: null,
            progressSim: null,
            elapsedTimer: null,
        };
        
        this.elapsedStart = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadSettings();
        this.startStatusUpdates();
        this.startClock();
        this.updatePresetDescription();
        // Apply deep link to a specific tab if provided via hash
        this.applyHashTab();
        window.addEventListener('hashchange', () => this.applyHashTab());
        
        // Initialize console
        this.addConsoleMessage('Control panel initialized', 'info');
        this.addConsoleMessage('Starting engine status monitoring...', 'system');
        
        // Load initial data
        setTimeout(() => {
            this.checkEngineStatus();
            this.loadRecentRuns();
        }, 1000);

        // Load persistent engine defaults from backend (non-blocking)
        this.fetchPersistentSettings().catch(() => {
            // Silent fail; local UI defaults remain
        });
    }

    applyHashTab() {
        const hash = window.location.hash;
        if (!hash) return;
        const valid = ['#reportsTab', '#historyTab', '#settingsTab', '#apiTab'];
        if (!valid.includes(hash)) return;
        const btn = document.querySelector(`[data-bs-target='${hash}']`);
        if (btn) {
            // Trigger the tab to show
            btn.click();
            this.addConsoleMessage(`Switched to tab: ${hash.substring(1)}`, 'system');
        }
    }
    
    bindEvents() {
        // Emergency Controls
        $('#emergencyStop').on('click', () => this.activateKillSwitch());
        $('#systemResume').on('click', () => this.deactivateKillSwitch());
    $('#vsSubnavRefresh').on('click', () => this.checkEngineStatus());
        
        // Engine Controls
        $('#refreshStatus').on('click', () => this.checkEngineStatus());
        $('#runDiagnostics').on('click', () => this.runDiagnostics());
        
        // Configuration
        $('#presetSelector').on('change', (e) => this.changePreset(e.target.value));
        $('#reservePercent').on('input', (e) => this.updateRangeValue(e.target, 'reservePercentValue'));
        $('#weightMethod').on('change', () => this.updateAdvancedParams());
        
        // Run Controls
        $('#executeRun').on('click', () => this.executeTransfer());
        $('#validateRun').on('click', () => this.validateConfiguration());
        $('#previewRun').on('click', () => this.previewTransfer());
    $('#autoTune').on('click', () => this.autoTune());
    $('#testRun').on('click', () => this.testRun());
        
        // Product Management
        $('#loadProducts').on('click', () => this.loadProducts());
        $('#saveProducts').on('click', () => this.saveProducts());
        $('#clearProducts').on('click', () => this.clearProducts());
        
        // Console Controls
        $('#clearConsole').on('click', () => this.clearConsole());
        $('#pauseConsole').on('click', () => this.toggleConsolePause());
        $('#autoScroll').on('change', (e) => this.state.autoScroll = e.target.checked);
        
        // Settings Management
        $('#importPreset').on('click', () => this.importPreset());
        $('#exportPreset').on('click', () => this.exportPreset());
        $('#resetToDefaults').on('click', () => this.resetToDefaults());
    $('#saveEngineDefaults').on('click', () => this.savePersistentSettings());
    $('#loadEngineDefaults').on('click', () => this.fetchPersistentSettings());
        
        // Export Functions
        $('#exportJson').on('click', () => this.exportData('json'));
        $('#exportCsv').on('click', () => this.exportData('csv'));
    $('#exportReport').on('click', () => this.exportReport());
        
        // Advanced parameter toggles
        $('[data-bs-toggle="collapse"]').on('click', function() {
            const icon = $(this).find('.fas');
            setTimeout(() => {
                const isExpanded = $($(this).attr('data-bs-target')).hasClass('show');
                icon.text(isExpanded ? '🔼' : '🔽');
            }, 350);
        });
        
        // Form validation
        $('input[type="number"]').on('input', this.validateNumericInput);
        $('input[type="url"]').on('input', this.validateUrlInput);
        
        // Keyboard shortcuts
        $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
    }

    // ===============================================
    // Reports
    // ===============================================
    async exportReport() {
        try {
            // Hit JSON to get the safe, base-path-aware URL; if not found, show toast
            const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
            const metaUrl = base.replace(/\/$/, '') + '/api/reports/latest?json=1';
            const r = await fetch(metaUrl, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const json = await r.json();
            if (!json.success) throw new Error(json.error || 'No report available');
            const url = json.data && json.data.url ? json.data.url : (base.replace(/\/$/, '') + '/api/reports/latest');
            window.open(url, '_blank', 'noopener');
            this.addConsoleMessage('Opening latest transfer report…', 'info');
        } catch (e) {
            this.addConsoleMessage('No report available: ' + e.message, 'warning');
            this.showNotification('No transfer report found. Run a test or generate a report first.', 'warning');
        }
    }
    
    // ===============================================
    // Engine Status & Health Monitoring
    // ===============================================
    
    async checkEngineStatus() {
        try {
            this.updateEngineStatusIndicator('checking');
            this.addConsoleMessage('Checking engine status...', 'info');
            
            const response = await this.apiCall('engine/status');
            
            if (response.success) {
                const status = response.data;
                this.updateEngineStatus(status);
                this.addConsoleMessage(`Engine status: ${status.status.toUpperCase()}`, 'success');
            } else {
                throw new Error(response.error || 'Status check failed');
            }
        } catch (error) {
            this.updateEngineStatusIndicator('error');
            this.addConsoleMessage(`Engine check failed: ${error.message}`, 'error');
            $('#engineStatusText').text('Connection failed');
        }
    }
    
    updateEngineStatus(status) {
        this.state.engineStatus = status.status;
        
        const statusMap = {
            'healthy': { indicator: 'healthy', text: 'Engine running normally', badge: 'bg-success' },
            'warning': { indicator: 'warning', text: 'Engine operational with warnings', badge: 'bg-warning' },
            'error': { indicator: 'error', text: 'Engine error detected', badge: 'bg-danger' },
            'offline': { indicator: 'error', text: 'Engine offline', badge: 'bg-secondary' }
        };
        
        const config = statusMap[status.status] || statusMap['offline'];
        
        this.updateEngineStatusIndicator(config.indicator);
        $('#engineStatusText').text(config.text);
        $('#connectionStatus').removeClass().addClass(`badge ${config.badge}`).text(status.status.toUpperCase());
        
        // Update metrics
        if (status.metrics) {
            $('#engineUptime').text(this.formatUptime(status.metrics.uptime || 0));
            $('#engineLatency').text(status.metrics.latency || '--');
            $('#memoryUsage').text(this.formatBytes(status.metrics.memory || 0));
            $('#lastRunStatus').text(status.metrics.last_run || '--');
            // Secondary subnav fields if present
            $('#vsSubnavEngineState').text((config.text || 'Engine')).attr('data-state', status.status);
            $('#vsSubnavLastRun').text(status.metrics.last_run || '—');
            const dot = $('#vsSubnavStatus');
            if (dot.length) {
                const map = { healthy: 'ok', warning: 'warn', error: 'err', offline: 'off', checking: 'check' };
                dot.removeClass().addClass('status-dot status-' + (map[status.status] || 'off'));
            }
        }
        
        // Update kill switch status
        this.state.killSwitchActive = status.kill_switch || false;
        this.updateKillSwitchUI();
    }
    
    updateEngineStatusIndicator(status) {
        const indicator = $('#engineStatusDot');
        indicator.attr('data-status', status);
        
        // Update main status dot
        $('.status-indicator').attr('data-status', status);
    }
    
    async runDiagnostics() {
        try {
            $('#runDiagnostics').prop('disabled', true).html('<span class="fas">⏳</span> Running...');
            this.addConsoleMessage('Starting system diagnostics...', 'info');
            
            const response = await this.apiCall('engine/diagnostics');
            
            if (response.success) {
                this.addConsoleMessage('Diagnostics completed successfully', 'success');
                this.showNotification('System diagnostics completed', 'success');
                
                // Display results in console
                const results = response.data;
                Object.entries(results).forEach(([test, result]) => {
                    const status = result.passed ? 'success' : 'error';
                    this.addConsoleMessage(`${test}: ${result.message}`, status);
                });
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.addConsoleMessage(`Diagnostics failed: ${error.message}`, 'error');
            this.showNotification('Diagnostics failed', 'error');
        } finally {
            $('#runDiagnostics').prop('disabled', false).html('<span class="fas me-1">🔍</span> Run Diagnostics');
        }
    }

    // ===============================================
    // Test Mode & Auto-Tune
    // ===============================================
    async testRun() {
        try {
            this.addConsoleMessage('Starting TEST RUN (dry + test_mode)...', 'info');
            const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
            const url = base.replace(/\/$/, '') + '/api/transfer/test?demo_products=1&count=50&outlets=6';
            const r = await fetch(url, { headers: { 'Accept':'application/json' }});
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const data = await r.json();
            if (data && data.success) {
                const s = data.data && data.data.summary ? data.data.summary : {};
                this.addConsoleMessage(`Test run ok: lines=${s.total_lines || 0}, qty=${s.total_quantity || 0}, outlets=${s.outlets_affected || 0}`, 'success');
                this.showNotification('Test run completed', 'success');
            } else {
                throw new Error((data && data.error) || 'Unknown test error');
            }
        } catch (e) {
            this.addConsoleMessage('Test run failed: ' + e.message, 'error');
            this.showNotification('Test run failed', 'error');
        }
    }

    async autoTune() {
        try {
            this.addConsoleMessage('Auto-tune started (exploring safe parameter grid)...', 'info');
            const payload = {
                min_lines: parseInt($('#minCapPerOutlet').val()) || 3,
                max_per_product: parseInt($('#maxPerProduct').val()) || 40,
                reserves: [0.10, 0.15, 0.20, 0.25],
                gammas: [1.4, 1.6, 1.8, 2.0],
                taus: [4.0, 6.0, 8.0],
                weight_methods: ['power','softmax']
            };
            const res = await this.apiCall('auto-tune', 'POST', payload);
            if (!res.success) throw new Error(res.error || 'Auto-tune failed');
            const best = res.data && res.data.best ? res.data.best : null;
            if (best && best.config) {
                this.addConsoleMessage('Auto-tune best config: ' + JSON.stringify(best.config), 'success');
                const m = best.metrics || {};
                this.addConsoleMessage(`Projected: lines=${m.total_lines || 0}, qty=${m.total_quantity || 0}, outlets=${m.outlets_affected || 0}`, 'success');
                this.showNotification('Auto-tune completed. Best configuration suggested.', 'success');
                // Apply suggested top-level params to UI
                if (best.config.weight_method) $('#weightMethod').val(best.config.weight_method).trigger('change');
                if (best.config.weight_gamma) $('#weightGamma').val(best.config.weight_gamma);
                if (best.config.softmax_tau) $('#softmaxTau').val(best.config.softmax_tau);
                if (typeof best.config.reserve_percent !== 'undefined') {
                    $('#reservePercent').val(Math.round(best.config.reserve_percent * 100));
                    this.updateRangeValue($('#reservePercent')[0], 'reservePercentValue');
                }
                if (best.config.max_per_product) $('#maxPerProduct').val(best.config.max_per_product);
            } else {
                this.addConsoleMessage('Auto-tune did not yield a definitive best configuration', 'warning');
                this.showNotification('Auto-tune finished without a best pick', 'warning');
            }
        } catch (e) {
            this.addConsoleMessage('Auto-tune error: ' + e.message, 'error');
            this.showNotification('Auto-tune failed', 'error');
        }
    }
    
    // ===============================================
    // Kill Switch Management
    // ===============================================
    
    async activateKillSwitch() {
        if (!confirm('Are you sure you want to activate the emergency stop? This will halt all transfer operations.')) {
            return;
        }
        
        try {
            this.addConsoleMessage('Activating emergency stop...', 'warning');
            
            const response = await this.apiCall('kill-switch/activate', 'POST');
            
            if (response.success) {
                this.state.killSwitchActive = true;
                this.updateKillSwitchUI();
                this.addConsoleMessage('Emergency stop activated', 'warning');
                this.showNotification('Emergency stop activated', 'warning');
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.addConsoleMessage(`Failed to activate kill switch: ${error.message}`, 'error');
            this.showNotification('Kill switch activation failed', 'error');
        }
    }
    
    async deactivateKillSwitch() {
        try {
            this.addConsoleMessage('Deactivating emergency stop...', 'info');
            
            const response = await this.apiCall('kill-switch/deactivate', 'POST');
            
            if (response.success) {
                this.state.killSwitchActive = false;
                this.updateKillSwitchUI();
                this.addConsoleMessage('Emergency stop deactivated - system ready', 'success');
                this.showNotification('System resumed', 'success');
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.addConsoleMessage(`Failed to deactivate kill switch: ${error.message}`, 'error');
            this.showNotification('Kill switch deactivation failed', 'error');
        }
    }
    
    updateKillSwitchUI() {
        const killStatus = $('#killSwitchStatus');
        const executeBtn = $('#executeRun');
        
        if (this.state.killSwitchActive) {
            killStatus.removeClass().addClass('badge bg-danger').text('ACTIVE');
            executeBtn.prop('disabled', true).html('<span class="fas me-2">🛑</span>System Stopped');
            $('#emergencyStop').addClass('btn-danger').removeClass('btn-outline-danger');
            $('#systemResume').removeClass('btn-success').addClass('btn-outline-success');
        } else {
            killStatus.removeClass().addClass('badge bg-success').text('INACTIVE');
            executeBtn.prop('disabled', false).html('<span class="fas me-2">▶️</span>Execute Transfer');
            $('#emergencyStop').removeClass('btn-danger').addClass('btn-outline-danger');
            $('#systemResume').addClass('btn-success').removeClass('btn-outline-success');
        }
    }
    
    // ===============================================
    // Configuration Management
    // ===============================================
    
    changePreset(presetName) {
        this.state.currentPreset = presetName;
        this.updatePresetDescription();
        this.loadPresetConfiguration(presetName);
        this.addConsoleMessage(`Preset changed to: ${presetName}`, 'info');
    }
    
    updatePresetDescription() {
        const descriptions = {
            'balanced': 'Balanced allocation with moderate risk tolerance and steady distribution',
            'conservative': 'Conservative approach with higher safety margins and reduced risk',
            'aggressive': 'Aggressive allocation maximizing throughput with calculated risks',
            'softmax-strong': 'Advanced softmax distribution for complex allocation scenarios',
            'custom': 'Custom configuration with user-defined parameters'
        };
        
        $('#presetDescription').text(descriptions[this.state.currentPreset] || 'Custom configuration');
    }
    
    async loadPresetConfiguration(presetName) {
        try {
            // Use fn-based API to fetch a specific preset configuration
            const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
            const url = `${base.replace(/\/public\/?$/, '')}/control-panel/api/?fn=presets`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': $('#csrfToken').val()
                },
                body: JSON.stringify({ name: presetName })
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const data = await response.json();
            if (data && data.ok && data.data && data.data.config) {
                this.applyConfiguration(data.data.config);
                this.addConsoleMessage(`Loaded preset: ${presetName}`, 'success');
            } else if (data && data.success && data.config) {
                // Fallback shape
                this.applyConfiguration(data.config);
                this.addConsoleMessage(`Loaded preset: ${presetName}`, 'success');
            } else {
                throw new Error((data && (data.message || data.error)) || 'Unexpected preset response');
            }
        } catch (error) {
            this.addConsoleMessage(`Failed to load preset: ${error.message}`, 'error');
        }
    }
    
    applyConfiguration(config) {
        // Apply configuration values to form elements
        Object.entries(config).forEach(([key, value]) => {
            const element = $(`#${key}`);
            if (element.length) {
                if (element.is(':checkbox')) {
                    element.prop('checked', !!value);
                } else {
                    element.val(value);
                }
            }
        });
        
        // Update range displays
        this.updateRangeValue($('#reservePercent')[0], 'reservePercentValue');
        this.updateAdvancedParams();
    }

    async fetchPersistentSettings() {
        try {
            const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
            const url = base.replace(/\/$/, '') + '/api/settings';
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const json = await r.json();
            if (!json.success) throw new Error(json.error || 'Settings load failed');
            const settings = json.data || {};
            // Map backend keys to UI fields
            const map = {
                weight_method: 'weightMethod',
                reserve_percent: 'reservePercent',
                max_per_product: 'maxPerProduct',
                weight_gamma: 'weightGamma',
                softmax_tau: 'softmaxTau',
                weight_mix_beta: 'weightMixBeta',
                min_cap_per_outlet: 'minCapPerOutlet'
            };
            const uiConfig = {};
            Object.keys(map).forEach(k => {
                if (typeof settings[k] !== 'undefined') {
                    if (k === 'reserve_percent') {
                        uiConfig[map[k]] = Math.round(parseFloat(settings[k]) * 100);
                    } else {
                        uiConfig[map[k]] = settings[k];
                    }
                }
            });
            this.applyConfiguration(uiConfig);
            this.addConsoleMessage('Loaded engine defaults from server', 'success');
            this.showNotification('Engine defaults loaded', 'success');
        } catch (e) {
            this.addConsoleMessage('Failed to load engine defaults: ' + e.message, 'warning');
        }
    }

    async savePersistentSettings() {
        try {
            const config = this.collectConfiguration();
            // Map UI values back to backend schema
            const payload = {
                settings: {
                    weight_method: config.weight_method,
                    reserve_percent: config.reserve_percent,
                    max_per_product: config.max_per_product,
                    weight_gamma: config.weight_gamma,
                    softmax_tau: config.softmax_tau,
                    weight_mix_beta: config.weight_mix_beta,
                    min_cap_per_outlet: config.min_cap_per_outlet
                }
            };
            const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
            const url = base.replace(/\/$/, '') + '/api/settings';
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': $('#csrfToken').val(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const json = await r.json();
            if (!json.success) throw new Error(json.error || 'Save failed');
            this.addConsoleMessage('Engine defaults saved to server', 'success');
            this.showNotification('Settings saved', 'success');
        } catch (e) {
            this.addConsoleMessage('Failed to save settings: ' + e.message, 'error');
            this.showNotification('Failed to save settings', 'error');
        }
    }
    
    updateRangeValue(element, displayId) {
        const value = element.value;
        $(`#${displayId}`).text(`${value}%`);
        
        // Update range background gradient
        const percent = (value - element.min) / (element.max - element.min) * 100;
        element.style.background = `linear-gradient(90deg, #e9ecef 0%, #007bff ${percent}%, #e9ecef ${percent}%)`;
    }
    
    updateAdvancedParams() {
        const method = $('#weightMethod').val();
        const gammaGroup = $('#weightGamma').closest('.col-6');
        const tauGroup = $('#softmaxTau').closest('.col-6');
        
        if (method === 'power') {
            gammaGroup.show();
            tauGroup.hide();
        } else {
            gammaGroup.hide();
            tauGroup.show();
        }
    }
    
    validateConfiguration() {
        const errors = [];
        const warnings = [];
        
        // Validate numeric ranges
        const reservePercent = parseFloat($('#reservePercent').val());
        if (reservePercent > 50) {
            warnings.push('High reserve percentage may reduce transfer efficiency');
        }
        
        const maxPerProduct = parseInt($('#maxPerProduct').val());
        if (maxPerProduct > 100) {
            warnings.push('Very high max per product may cause allocation imbalances');
        }
        
        // Check engine status
        if (this.state.engineStatus !== 'healthy') {
            errors.push('Engine is not in healthy state');
        }
        
        if (this.state.killSwitchActive) {
            errors.push('Kill switch is active');
        }
        
        // Display results
        if (errors.length > 0) {
            this.showNotification(`Validation failed: ${errors.join(', ')}`, 'error');
            this.addConsoleMessage('Configuration validation failed', 'error');
            errors.forEach(error => this.addConsoleMessage(`ERROR: ${error}`, 'error'));
            return false;
        }
        
        if (warnings.length > 0) {
            this.showNotification(`Warnings: ${warnings.join(', ')}`, 'warning');
            warnings.forEach(warning => this.addConsoleMessage(`WARN: ${warning}`, 'warning'));
        } else {
            this.showNotification('Configuration validation passed', 'success');
            this.addConsoleMessage('Configuration validated successfully', 'success');
        }
        
        return true;
    }
    
    // ===============================================
    // Transfer Execution
    // ===============================================
    
    async executeTransfer() {
        if (this.state.runInProgress) {
            this.showNotification('Transfer already in progress', 'warning');
            return;
        }
        
        if (!this.validateConfiguration()) {
            return;
        }
        
        if (!confirm('Execute transfer with current configuration?')) {
            return;
        }
        
        try {
            this.state.runInProgress = true;
            this.elapsedStart = new Date();
            this.startElapsedTimer();
            this.showProgressMonitor();
            // Live SSE replaces simulated progress
            
            const config = this.collectConfiguration();
            // Generate a run_id client-side so we can subscribe before the backend starts writing
            const nowPart = new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
            const rndPart = Math.floor(Math.random() * 900000 + 100000);
            const runId = `run_${nowPart}_${rndPart}`;
            config.run_id = runId;
            this.state.lastRunId = runId;
            this.addConsoleMessage('Starting transfer execution...', 'info');
            // Start SSE stream before firing the request
            this.startSSE(runId);
            
            const response = await this.apiCall('transfer/execute', 'POST', config);
            
            if (response.success) {
                const confirmedRunId = (response.data && response.data.run_id) ? response.data.run_id : runId;
                this.state.lastRunId = confirmedRunId;
                
                this.addConsoleMessage(`Transfer executed successfully (ID: ${runId})`, 'success');
                this.showNotification('Transfer completed successfully', 'success');
                
                // Update counters
                this.updateRunCounter();
                this.loadRecentRuns();
                
                // Hide progress monitor
                setTimeout(() => {
                    this.hideProgressMonitor();
                }, 2000);
                
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.addConsoleMessage(`Transfer execution failed: ${error.message}`, 'error');
            this.showNotification('Transfer execution failed', 'error');
            this.hideProgressMonitor();
        } finally {
            this.state.runInProgress = false;
            this.stopElapsedTimer();
        }
    }
    
    async previewTransfer() {
        try {
            $('#previewRun').prop('disabled', true).html('<span class="fas">⏳</span> Loading...');
            
            const config = this.collectConfiguration();
            config.preview = true;
            
            this.addConsoleMessage('Generating transfer preview...', 'info');
            
            const response = await this.apiCall('transfer/preview', 'POST', config);
            
            if (response.success) {
                this.addConsoleMessage('Transfer preview generated', 'success');
                this.showPreviewModal(response.data);
            } else {
                throw new Error(response.error);
            }
        } catch (error) {
            this.addConsoleMessage(`Preview generation failed: ${error.message}`, 'error');
            this.showNotification('Preview failed', 'error');
        } finally {
            $('#previewRun').prop('disabled', false).html('<span class="fas me-1">👁️</span> Preview');
        }
    }
    
    collectConfiguration() {
        return {
            preset: $('#presetSelector').val(),
            weight_method: $('#weightMethod').val(),
            reserve_percent: parseFloat($('#reservePercent').val()) / 100,
            // New stock-only defaults (UI fields may be added later; keep safe defaults here)
            reserve_min_units: parseInt($('#reserveMinUnits').val() || 2),
            seed_qty_zero: parseInt($('#seedQtyZero').val() || 3),
            topup_low_to: parseInt($('#topupLowTo').val() || 10),
            mid_topup: parseInt($('#midTopup').val() || 5),
            proportional_share: parseFloat($('#proportionalShare').val() || 0.20),
            max_per_product: parseInt($('#maxPerProduct').val()),
            max_skus_per_store: parseInt($('#maxSkusPerStore').val() || 25),
            weight_gamma: parseFloat($('#weightGamma').val()),
            softmax_tau: parseFloat($('#softmaxTau').val()),
            weight_mix_beta: parseFloat($('#weightMixBeta').val()),
            min_cap_per_outlet: parseInt($('#minCapPerOutlet').val()),
            mode: 'balance_stock_only',
            live_mode: $('#liveMode').is(':checked'),
            save_snapshot: $('#saveSnapshot').is(':checked'),
            products: $('#productMemory').val().split('\n').filter(p => p.trim())
        };
    }
    
    // ===============================================
    // Progress Monitoring
    // ===============================================
    
    showProgressMonitor() {
        $('#progressMonitor').slideDown();
        $('#executeRun').prop('disabled', true).html('<span class="fas me-2">⏳</span>Executing...');
    }
    
    hideProgressMonitor() {
        $('#progressMonitor').slideUp();
        $('#executeRun').prop('disabled', false).html('<span class="fas me-2">▶️</span>Execute Transfer');
        $('#executionProgress').css('width', '0%');
        $('#progressElapsed').text('00:00');
    }
    
    startElapsedTimer() {
        if (this.timers.elapsedTimer) {
            clearInterval(this.timers.elapsedTimer);
        }
        const tick = () => {
            if (!this.elapsedStart) return;
            const now = new Date();
            const diffMs = now - this.elapsedStart;
            const totalSeconds = Math.floor(diffMs / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            const hh = hours > 0 ? String(hours).padStart(2, '0') + ':' : '';
            const mm = String(minutes).padStart(2, '0');
            const ss = String(seconds).padStart(2, '0');
            $('#progressElapsed').text(`${hh}${mm}:${ss}`);
        };
        tick();
        this.timers.elapsedTimer = setInterval(tick, 1000);
    }
    
    stopElapsedTimer() {
        if (this.timers.elapsedTimer) {
            clearInterval(this.timers.elapsedTimer);
            this.timers.elapsedTimer = null;
        }
    }
    
    simulateProgress() {
        // Deprecated by SSE live updates
    }

    startSSE(runId) {
        try {
            if (this.es) { try { this.es.close(); } catch(_){} }
            const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
            const url = base.replace(/\/$/, '') + '/api/transfer/stream?run_id=' + encodeURIComponent(runId);
            this.addConsoleMessage('Opening live stream for run ' + runId, 'system');
            const es = new EventSource(url);
            this.es = es;
            es.onmessage = (e) => {
                try {
                    const payload = JSON.parse(e.data);
                    this.handleStreamEvent(payload);
                } catch (err) {
                    this.addConsoleMessage('Stream parse error: ' + err.message, 'warning');
                }
            };
            es.addEventListener('ping', (e) => {
                // optional heartbeat handling
            });
            es.addEventListener('timeout', (e) => {
                this.addConsoleMessage('Stream timed out', 'warning');
                try { es.close(); } catch(_){}
            });
            es.addEventListener('error', (e) => {
                this.addConsoleMessage('Stream error', 'error');
            });
        } catch (e) {
            this.addConsoleMessage('Failed to start live stream: ' + e.message, 'error');
        }
    }

    handleStreamEvent(ev) {
        // ev: {stage, message, index,total, done, summary, profile, ...}
        if (ev.message) {
            this.addConsoleMessage(ev.message, ev.error ? 'error' : (ev.stage === 'done' ? 'success' : 'info'));
        }
        if (typeof ev.index !== 'undefined' && typeof ev.total !== 'undefined') {
            const percent = Math.max(0, Math.min(100, Math.round((ev.index / ev.total) * 100)));
            $('#executionProgress').css('width', percent + '%');
            $('#progressPercent').text(percent + '%');
            if (ev.stage) $('#progressStage').text(ev.stage);
        } else if (ev.stage) {
            $('#progressStage').text(ev.stage);
        }
        if (ev.done) {
            $('#executionProgress').css('width', '100%');
            $('#progressPercent').text('100%');
            if (this.es) { try { this.es.close(); } catch(_){} }
            // Render profiling (if provided)
            if (ev.profile) {
                this.renderProfiling(this.state.lastRunId || '—', ev.profile);
            }
        }
    }

    renderProfiling(runId, profile) {
        try {
            const list = $('#profilingList');
            const empty = $('#profilingEmpty');
            const totalEl = $('#profilingTotal');
            const rid = $('#profilingRunId');
            list.empty();
            let total = 0;
            // Stabilize order: group major stages first if present
            const preferred = ['validate_config','load_outlets','load_products','calculate_allocations'];
            const keys = Object.keys(profile);
            const ordered = [...preferred.filter(k => keys.includes(k)), ...keys.filter(k => !preferred.includes(k))];
            ordered.forEach(k => {
                const v = profile[k];
                if (typeof v !== 'number') return;
                total += v;
                list.append(`<li class="list-group-item d-flex justify-content-between align-items-center px-0">`+
                    `<span class="small text-muted">${this.escapeHtml(k)}</span>`+
                    `<span class="badge bg-secondary">${v.toFixed(2)} ms</span>`+
                `</li>`);
            });
            if (ordered.length === 0) {
                empty.removeClass('d-none').text('No profiling data available.');
                list.addClass('d-none');
                totalEl.text('—');
            } else {
                empty.addClass('d-none');
                list.removeClass('d-none');
                totalEl.text(total.toFixed(2) + ' ms');
                rid.text(runId);
            }
        } catch (e) {
            this.addConsoleMessage('Profiling render error: ' + e.message, 'warning');
        }
    }
    
    // ===============================================
    // Console Management
    // ===============================================
    
    addConsoleMessage(message, level = 'info', timestamp = null) {
        if (this.state.consolePaused) return;
        
        const now = timestamp || new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour12: false });
        
        const consoleLine = $(`
            <div class="console-line">
                <span class="timestamp">${timeStr}</span>
                <span class="level ${level}">${level.toUpperCase()}</span>
                <span class="message">${this.escapeHtml(message)}</span>
            </div>
        `);
        
        const consoleOutput = $('#consoleOutput');
        consoleOutput.append(consoleLine);
        
        // Limit console lines
        this.state.consoleLines++;
        if (this.state.consoleLines > this.config.consoleMaxLines) {
            consoleOutput.find('.console-line:first').remove();
            this.state.consoleLines--;
        }
        
        $('#consoleLines').text(this.state.consoleLines);
        
        // Auto-scroll
        if (this.state.autoScroll) {
            consoleOutput.scrollTop(consoleOutput[0].scrollHeight);
        }
    }
    
    clearConsole() {
        $('#consoleOutput').empty();
        this.state.consoleLines = 0;
        $('#consoleLines').text('0');
        this.addConsoleMessage('Console cleared', 'system');
    }
    
    toggleConsolePause() {
        this.state.consolePaused = !this.state.consolePaused;
        const btn = $('#pauseConsole');
        
        if (this.state.consolePaused) {
            btn.html('<span class="fas">▶️</span>').attr('title', 'Resume Updates');
            this.addConsoleMessage('Console paused', 'system');
        } else {
            btn.html('<span class="fas">⏸️</span>').attr('title', 'Pause Updates');
            this.addConsoleMessage('Console resumed', 'system');
        }
    }
    
    // ===============================================
    // Data Management
    // ===============================================
    
    async loadProducts() {
        try {
            const response = await this.apiCall('products/load');
            if (response.success) {
                $('#productMemory').val(response.data.join('\n'));
                this.showNotification('Products loaded successfully', 'success');
            }
        } catch (error) {
            this.showNotification('Failed to load products', 'error');
        }
    }
    
    async saveProducts() {
        try {
            const products = $('#productMemory').val().split('\n').filter(p => p.trim());
            await this.apiCall('products/save', 'POST', { products });
            this.showNotification('Products saved successfully', 'success');
        } catch (error) {
            this.showNotification('Failed to save products', 'error');
        }
    }
    
    clearProducts() {
        if (confirm('Clear all products from memory?')) {
            $('#productMemory').val('');
            this.showNotification('Product memory cleared', 'info');
        }
    }
    
    async loadRecentRuns() {
        try {
            const response = await this.apiCall('runs/recent');
            if (response.success) {
                this.updateRecentRunsList(response.data);
            }
        } catch (error) {
            this.addConsoleMessage('Failed to load recent runs', 'warning');
        }
    }
    
    updateRecentRunsList(runs) {
        const container = $('#recentRunsList');
        container.empty();
        
        if (runs.length === 0) {
            container.append(`
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <small class="text-muted">No recent runs</small>
                </div>
            `);
            return;
        }
        
        runs.slice(0, 5).forEach(run => {
            const timeStr = new Date(run.timestamp).toLocaleTimeString();
            const statusClass = run.status === 'success' ? 'success' : 'warning';
            
            container.append(`
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <div>
                        <small class="text-muted">${timeStr}</small>
                        <div class="small">${run.preset || 'Unknown'}</div>
                    </div>
                    <span class="badge bg-${statusClass}">${run.status}</span>
                </div>
            `);
        });
        
        this.updateRunCounter(runs.length);
    }
    
    updateRunCounter(total = null) {
        if (total !== null) {
            $('#runCounter').html(`Runs: <strong>${total}</strong>`);
        }
    }
    
    // ===============================================
    // Utility Functions
    // ===============================================
    
    async apiCall(endpoint, method = 'GET', data = null) {
        const base = (window.VapeshedTransfer && window.VapeshedTransfer.baseUrl) ? window.VapeshedTransfer.baseUrl : '';
        // Special-case: execute to REST controller with form encoding (PHP $_POST expectations)
        if (endpoint === 'transfer/execute') {
            const url = base.replace(/\/$/, '') + '/transfer/execute';
            const fd = new FormData();
            const cfg = data || {};
            const cfgMap = {
                preset: cfg.preset,
                weight_method: cfg.weight_method,
                reserve_percent: cfg.reserve_percent,
                reserve_min_units: cfg.reserve_min_units,
                seed_qty_zero: cfg.seed_qty_zero,
                topup_low_to: cfg.topup_low_to,
                mid_topup: cfg.mid_topup,
                proportional_share: cfg.proportional_share,
                max_per_product: cfg.max_per_product,
                max_skus_per_store: cfg.max_skus_per_store,
                weight_gamma: cfg.weight_gamma,
                softmax_tau: cfg.softmax_tau,
                weight_mix_beta: cfg.weight_mix_beta,
                min_cap_per_outlet: cfg.min_cap_per_outlet,
                mode: cfg.mode || 'balance_stock_only',
                live_mode: cfg.live_mode ? 1 : 0,
                save_snapshot: cfg.save_snapshot ? 1 : 0,
                run_id: cfg.run_id
            };
            Object.keys(cfgMap).forEach(k => {
                if (typeof cfgMap[k] !== 'undefined' && cfgMap[k] !== null && cfgMap[k] !== '') {
                    fd.append(`config[${k}]`, cfgMap[k]);
                }
            });
            const prods = Array.isArray(cfg.products) ? cfg.products : [];
            prods.forEach(p => fd.append('products[]', p));
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': $('#csrfToken').val(),
                    'Accept': 'application/json'
                },
                body: fd
            });
            if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            const json = await r.json();
            return { success: !!json.ok, data: json.data, error: json.error };
        }
        // Special-case: new REST endpoint for auto-tune
        if (endpoint === 'auto-tune') {
            const url = base.replace(/\/$/, '') + '/api/transfer/auto-tune';
            const options = {
                method: method || 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': $('#csrfToken').val(),
                    'Accept': 'application/json'
                },
                body: data ? JSON.stringify(data) : '{}'
            };
            const r = await fetch(url, options);
            if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
            return await r.json();
        }

        // Default: control panel API router uses ?fn=…; map common REST-y endpoints to fn names
        const map = {
            'engine/status': 'status',
            'engine/diagnostics': 'diagnostics',
            'kill-switch/activate': 'killswitch',
            'kill-switch/deactivate': 'killswitch',
            'transfer/execute': 'execute',
            'transfer/preview': 'preview',
            'products/load': 'products_load',
            'products/save': 'products_save',
            'runs/recent': 'recent_runs'
        };
        const fn = map[endpoint] || endpoint;
        const url = `${base.replace(/\/public\/?$/, '')}/control-panel/api/?fn=${encodeURIComponent(fn)}`;
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': $('#csrfToken').val(),
                'Accept': 'application/json'
            }
        };
        if (data && method !== 'GET') options.body = JSON.stringify(data);
        const response = await fetch(url, options);
        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        return await response.json();
    }
    
    showNotification(message, type = 'info', duration = 5000) {
        // Use the existing notification system from app.js
        if (typeof showNotification === 'function') {
            showNotification(message, type, duration);
        } else {
            // Fallback notification
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }
    
    startStatusUpdates() {
        this.timers.statusUpdate = setInterval(() => {
            if (!this.state.runInProgress) {
                this.checkEngineStatus();
            }
        }, this.config.updateInterval);
    }
    
    startClock() {
        const updateClock = () => {
            const now = new Date();
            const t = now.toLocaleTimeString('en-US', { hour12: false });
            $('#currentTime').text(t);
            $('#vsSubnavTime').text(t);
        };
        
        updateClock();
        this.timers.clockUpdate = setInterval(updateClock, 1000);
    }
    
    loadSettings() {
        // Load saved settings from localStorage
        const savedSettings = localStorage.getItem('transferControlPanel');
        if (savedSettings) {
            try {
                const settings = JSON.parse(savedSettings);
                Object.assign(this.state, settings);
            } catch (error) {
                console.warn('Failed to load saved settings:', error);
            }
        }
    }
    
    saveSettings() {
        localStorage.setItem('transferControlPanel', JSON.stringify({
            currentPreset: this.state.currentPreset,
            autoScroll: this.state.autoScroll
        }));
    }
    
    formatUptime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }
    
    formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    validateNumericInput(e) {
        const input = e.target;
        const value = parseFloat(input.value);
        const min = parseFloat(input.min);
        const max = parseFloat(input.max);
        
        if (!isNaN(min) && value < min) {
            input.setCustomValidity(`Value must be at least ${min}`);
        } else if (!isNaN(max) && value > max) {
            input.setCustomValidity(`Value must be no more than ${max}`);
        } else {
            input.setCustomValidity('');
        }
    }
    
    validateUrlInput(e) {
        const input = e.target;
        try {
            new URL(input.value);
            input.setCustomValidity('');
        } catch {
            input.setCustomValidity('Please enter a valid URL');
        }
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + E: Execute transfer
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            if (!this.state.runInProgress && !this.state.killSwitchActive) {
                this.executeTransfer();
            }
        }
        
        // Ctrl/Cmd + R: Refresh status
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            this.checkEngineStatus();
        }
        
        // Escape: Stop current operation
        if (e.key === 'Escape') {
            if (this.state.runInProgress) {
                this.activateKillSwitch();
            }
        }
    }
    
    // Cleanup
    destroy() {
        Object.values(this.timers).forEach(timer => {
            if (timer) clearInterval(timer);
        });
        
        this.saveSettings();
    }
}

// Initialize when DOM is ready
$(document).ready(function() {
    // Initialize control panel
    window.transferControlPanel = new TransferControlPanel();
    
    // Handle page unload
    $(window).on('beforeunload', function() {
        if (window.transferControlPanel) {
            window.transferControlPanel.destroy();
        }
    });
});