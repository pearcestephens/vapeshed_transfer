/**
 * Dashboard Power JavaScript Framework
 * 
 * Advanced dashboard controls with real-time updates, profile management,
 * and comprehensive user interaction handling
 * 
 * @package transfer_engine
 * @version 2.0
 */

class DashboardPower {
    constructor() {
        this.config = {};
        this.modules = new Map();
        this.preferences = {};
        this.autoRefreshInterval = null;
        this.liveMode = false;
        this.sseConnection = null;
        this.emergencyMode = false;
        
        // Bind methods
        this.handleProfileChange = this.handleProfileChange.bind(this);
        this.handleModuleRefresh = this.handleModuleRefresh.bind(this);
        this.handleEmergencyAction = this.handleEmergencyAction.bind(this);
        this.handleSettingsToggle = this.handleSettingsToggle.bind(this);
        this.updateLastRefresh = this.updateLastRefresh.bind(this);
    }

    /**
     * Initialize dashboard with configuration
     */
    static init(config) {
        if (window.DashboardPowerInstance) {
            return window.DashboardPowerInstance;
        }
        
        window.DashboardPowerInstance = new DashboardPower();
        window.DashboardPowerInstance.configure(config);
        window.DashboardPowerInstance.bindEvents();
        window.DashboardPowerInstance.loadModules();
        
        return window.DashboardPowerInstance;
    }

    /**
     * Configure dashboard instance
     */
    configure(config) {
        this.config = {
            profile: 'standard',
            modules: [],
            preferences: {},
            endpoints: {
                ajax: '/admin/dashboard/ajax',
                moduleData: '/admin/dashboard/module-data',
                preferences: '/admin/dashboard/preferences'
            },
            ...config
        };
        
        this.preferences = this.config.preferences;
        console.log('Dashboard Power initialized:', this.config);
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Profile selector
        const profileSelect = document.getElementById('profileSelect');
        if (profileSelect) {
            profileSelect.addEventListener('change', this.handleProfileChange);
        }

        // Settings toggle
        const settingsToggle = document.getElementById('settingsToggle');
        if (settingsToggle) {
            settingsToggle.addEventListener('click', this.handleSettingsToggle);
        }

        // Time window changes
        const timeWindow = document.getElementById('timeWindow');
        if (timeWindow) {
            timeWindow.addEventListener('change', (e) => {
                this.updateTimeWindow(e.target.value);
            });
        }

        // Refresh controls
        const refreshAll = document.getElementById('refreshAll');
        if (refreshAll) {
            refreshAll.addEventListener('click', () => {
                this.refreshAllModules();
            });
        }

        // Refresh interval controls
        document.querySelectorAll('.refresh-interval').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const interval = parseInt(e.target.dataset.interval);
                this.setAutoRefreshInterval(interval);
            });
        });

        // Emergency actions
        document.querySelectorAll('.emergency-action').forEach(btn => {
            btn.addEventListener('click', this.handleEmergencyAction);
        });

        // System actions
        document.querySelectorAll('.system-action').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.handleSystemAction(e.target.dataset.action);
            });
        });

        // Theme controls
        document.querySelectorAll('input[name="theme"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.setTheme(e.target.value);
            });
        });

        // Accent color controls
        document.querySelectorAll('input[name="accent"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.setAccentColor(e.target.value);
            });
        });

        // Font scale control
        const fontScale = document.getElementById('fontScale');
        if (fontScale) {
            fontScale.addEventListener('input', (e) => {
                this.setFontScale(e.target.value);
            });
        }

        // Layout controls
        document.querySelectorAll('input[name="columns"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.setGridColumns(e.target.value);
            });
        });

        const densitySelect = document.getElementById('densitySelect');
        if (densitySelect) {
            densitySelect.addEventListener('change', (e) => {
                this.setGridDensity(e.target.value);
            });
        }

        // Privacy options
        document.querySelectorAll('.privacy-option').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.setPrivacyOption(e.target.id, e.target.checked);
            });
        });

        // Live mode toggle
        const liveMode = document.getElementById('liveMode');
        if (liveMode) {
            liveMode.addEventListener('change', (e) => {
                this.toggleLiveMode(e.target.checked);
            });
        }

        // War room mode
        const warRoomMode = document.getElementById('warRoomMode');
        if (warRoomMode) {
            warRoomMode.addEventListener('change', (e) => {
                this.toggleWarRoomMode(e.target.checked);
            });
        }

        // Demo mode
        const demoMode = document.getElementById('demoMode');
        if (demoMode) {
            demoMode.addEventListener('change', (e) => {
                this.toggleDemoMode(e.target.checked);
            });
        }

        // Reset layout
        const resetLayout = document.getElementById('resetLayout');
        if (resetLayout) {
            resetLayout.addEventListener('click', () => {
                this.resetLayout();
            });
        }

        // Export config
        const exportConfig = document.getElementById('exportConfig');
        if (exportConfig) {
            exportConfig.addEventListener('click', () => {
                this.exportConfig();
            });
        }

        // Module controls
        this.bindModuleEvents();

        // Keyboard shortcuts
        this.bindKeyboardShortcuts();
    }

    /**
     * Bind module-specific events
     */
    bindModuleEvents() {
        // Module refresh buttons
        document.querySelectorAll('.module-refresh').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const moduleId = e.target.dataset.module;
                this.refreshModule(moduleId);
            });
        });

        // Module config buttons
        document.querySelectorAll('.module-config').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const moduleId = e.target.dataset.module;
                this.toggleModuleSettings(moduleId);
            });
        });

        // Module fullscreen
        document.querySelectorAll('.module-fullscreen').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const moduleId = e.target.dataset.module;
                this.toggleModuleFullscreen(moduleId);
            });
        });

        // Module hide
        document.querySelectorAll('.module-hide').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const moduleId = e.target.dataset.module;
                this.hideModule(moduleId);
            });
        });

        // Module settings save
        document.querySelectorAll('.save-module-settings').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = e.target.dataset.module;
                this.saveModuleSettings(moduleId);
            });
        });

        // Module settings reset
        document.querySelectorAll('.reset-module-settings').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = e.target.dataset.module;
                this.resetModuleSettings(moduleId);
            });
        });

        // Close settings panels
        document.querySelectorAll('.close-settings').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const panel = e.target.closest('.module-settings-panel');
                if (panel) {
                    panel.classList.add('collapsed');
                }
            });
        });

        // Add module buttons
        document.querySelectorAll('.add-module').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const moduleId = e.target.dataset.module;
                this.addModule(moduleId);
            });
        });
    }

    /**
     * Bind keyboard shortcuts
     */
    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R: Refresh all modules
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshAllModules();
            }
            
            // Ctrl/Cmd + Shift + S: Toggle settings
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                this.handleSettingsToggle();
            }
            
            // Ctrl/Cmd + Shift + L: Toggle live mode
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'L') {
                e.preventDefault();
                this.toggleLiveMode(!this.liveMode);
            }
            
            // Escape: Close any open panels
            if (e.key === 'Escape') {
                this.closeAllPanels();
            }
        });
    }

    /**
     * Load and initialize modules
     */
    loadModules() {
        this.config.modules.forEach(moduleId => {
            this.initializeModule(moduleId);
        });
    }

    /**
     * Initialize a specific module
     */
    async initializeModule(moduleId) {
        const moduleElement = document.getElementById(`module-${moduleId}`);
        const contentElement = document.getElementById(`content-${moduleId}`);
        
        if (!moduleElement || !contentElement) {
            console.warn(`Module ${moduleId} not found in DOM`);
            return;
        }

        try {
            // Set loading state
            this.setModuleStatus(moduleId, 'loading');
            
            // Load module data
            const data = await this.fetchModuleData(moduleId);
            
            // Render module content
            this.renderModuleContent(moduleId, data);
            
            // Set ready state
            this.setModuleStatus(moduleId, 'ready');
            
            // Store module instance
            this.modules.set(moduleId, {
                element: moduleElement,
                content: contentElement,
                data: data,
                lastUpdate: Date.now()
            });
            
        } catch (error) {
            console.error(`Failed to initialize module ${moduleId}:`, error);
            this.setModuleStatus(moduleId, 'error');
            this.renderModuleError(moduleId, error);
        }
    }

    /**
     * Fetch data for a specific module
     */
    async fetchModuleData(moduleId, timeWindow = null) {
        const url = new URL(this.config.endpoints.moduleData, window.location.origin);
        url.searchParams.set('module', moduleId);
        
        if (timeWindow) {
            url.searchParams.set('time_window', timeWindow);
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'get_module_data',
                module: moduleId,
                time_window: timeWindow || this.preferences.time?.window || 'last_24h'
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Unknown error');
        }

        return result.result;
    }

    /**
     * Render module content
     */
    renderModuleContent(moduleId, data) {
        const contentElement = document.getElementById(`content-${moduleId}`);
        if (!contentElement) return;

        // Module-specific rendering
        switch (moduleId) {
            case 'neuro_strategic':
                this.renderNeuroStrategic(contentElement, data);
                break;
            case 'system_health':
                this.renderSystemHealth(contentElement, data);
                break;
            case 'opportunities':
                this.renderOpportunities(contentElement, data);
                break;
            default:
                this.renderGenericModule(contentElement, data);
        }
    }

    /**
     * Render Neuro Strategic module
     */
    renderNeuroStrategic(element, data) {
        const vectors = data.vectors || {};
        
        element.innerHTML = `
            <div class="neuro-vectors">
                <div class="vectors-radar mb-3">
                    <canvas id="neuroRadar" width="200" height="200"></canvas>
                </div>
                <div class="vectors-list">
                    ${Object.entries(vectors).map(([key, value]) => `
                        <div class="vector-item d-flex justify-content-between align-items-center mb-2">
                            <span class="vector-name">${this.formatLabel(key)}</span>
                            <div class="vector-value">
                                <div class="progress" style="width: 100px;">
                                    <div class="progress-bar bg-accent" 
                                         style="width: ${value * 100}%"
                                         aria-valuenow="${value * 100}" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <span class="ms-2 small text-muted">${(value * 100).toFixed(1)}%</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="neuro-meta mt-3 pt-3 border-top">
                    <small class="text-muted">
                        Policy: ${data.policy_version || 'Unknown'} | 
                        Confidence: ${((data.confidence || 0) * 100).toFixed(1)}% |
                        Updated: ${this.formatTime(data.last_update)}
                    </small>
                </div>
            </div>
        `;
        
        // Draw radar chart
        this.drawRadarChart('neuroRadar', vectors);
    }

    /**
     * Render System Health module
     */
    renderSystemHealth(element, data) {
        const metrics = data.metrics || {};
        const status = data.status || 'UNKNOWN';
        
        const statusClass = {
            'HEALTHY': 'text-success',
            'WARNING': 'text-warning',
            'ERROR': 'text-danger',
            'UNKNOWN': 'text-muted'
        }[status] || 'text-muted';

        element.innerHTML = `
            <div class="system-health">
                <div class="health-status text-center mb-3">
                    <div class="status-icon ${statusClass}">
                        <i data-feather="${status === 'HEALTHY' ? 'check-circle' : status === 'WARNING' ? 'alert-triangle' : 'x-circle'}" class="feather-lg"></i>
                    </div>
                    <h5 class="${statusClass}">${status}</h5>
                </div>
                
                <div class="health-metrics">
                    ${Object.entries(metrics).map(([key, value]) => {
                        const isPercentage = key.includes('_pct');
                        const isTime = key.includes('_ms');
                        const threshold = this.getHealthThreshold(key);
                        const warningClass = this.getMetricWarningClass(value, threshold);
                        
                        return `
                            <div class="metric-item d-flex justify-content-between align-items-center mb-2">
                                <span class="metric-name">${this.formatLabel(key)}</span>
                                <span class="metric-value ${warningClass}">
                                    ${value}${isPercentage ? '%' : isTime ? 'ms' : ''}
                                </span>
                            </div>
                        `;
                    }).join('')}
                </div>
                
                <div class="health-actions mt-3 pt-3 border-top">
                    <button class="btn btn-outline-primary btn-sm run-health-check">
                        <i data-feather="activity"></i>
                        Run Health Check
                    </button>
                </div>
                
                <div class="health-meta mt-3">
                    <small class="text-muted">
                        Last check: ${this.formatTime(data.last_check)}
                    </small>
                </div>
            </div>
        `;
        
        // Re-initialize Feather icons
        feather.replace();
        
        // Bind health check button
        element.querySelector('.run-health-check')?.addEventListener('click', () => {
            this.runHealthCheck();
        });
    }

    /**
     * Render Opportunities module
     */
    renderOpportunities(element, data) {
        element.innerHTML = `
            <div class="opportunities">
                <div class="opportunities-summary row mb-3">
                    <div class="col-6 col-md-3">
                        <div class="metric-card text-center">
                            <h3 class="text-accent">${data.total_opportunities || 0}</h3>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="metric-card text-center">
                            <h3 class="text-success">${data.high_confidence || 0}</h3>
                            <small class="text-muted">High Conf.</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="metric-card text-center">
                            <h3 class="text-info">${data.avg_roi || 0}%</h3>
                            <small class="text-muted">Avg ROI</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="metric-card text-center">
                            <h3 class="text-warning">${data.pending_approval || 0}</h3>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                
                <div class="opportunities-categories">
                    <h6>Top Categories</h6>
                    <div class="categories-list">
                        ${(data.top_categories || []).map(category => `
                            <span class="badge bg-secondary me-1 mb-1">${category}</span>
                        `).join('')}
                    </div>
                </div>
                
                <div class="opportunities-actions mt-3 pt-3 border-top">
                    <button class="btn btn-primary btn-sm">
                        <i data-feather="eye"></i>
                        View All Opportunities
                    </button>
                </div>
            </div>
        `;
        
        feather.replace();
    }

    /**
     * Render generic module content
     */
    renderGenericModule(element, data) {
        element.innerHTML = `
            <div class="generic-module">
                <pre class="json-data">${JSON.stringify(data, null, 2)}</pre>
            </div>
        `;
    }

    /**
     * Set module status indicator
     */
    setModuleStatus(moduleId, status) {
        const statusElement = document.querySelector(`#module-${moduleId} .module-status`);
        if (!statusElement) return;

        // Remove existing status classes
        statusElement.className = statusElement.className.replace(/status-\w+/g, '');
        
        // Add new status class
        statusElement.classList.add(`status-${status}`);
        
        // Update icon
        const icon = statusElement.querySelector('i');
        if (icon) {
            const iconMap = {
                loading: 'loader',
                ready: 'check-circle',
                error: 'x-circle',
                warning: 'alert-triangle'
            };
            
            icon.setAttribute('data-feather', iconMap[status] || 'help-circle');
            feather.replace();
        }
    }

    /**
     * Handle profile changes
     */
    async handleProfileChange(e) {
        const newProfile = e.target.value;
        
        try {
            const response = await fetch(this.config.endpoints.ajax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'switch_profile',
                    profile: newProfile
                })
            });

            const result = await response.json();
            
            if (result.success && result.result.reload_required) {
                // Show loading indicator
                this.showGlobalLoading('Switching to ' + newProfile + ' profile...');
                
                // Reload page after short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
            
        } catch (error) {
            console.error('Failed to switch profile:', error);
            this.showNotification('Failed to switch profile', 'error');
        }
    }

    /**
     * Handle emergency actions
     */
    handleEmergencyAction(e) {
        const action = e.target.dataset.action;
        
        // Show confirmation modal
        const modal = document.getElementById('emergencyModal');
        const actionName = document.getElementById('emergencyActionName');
        const actionDesc = document.getElementById('emergencyActionDescription');
        const confirmBtn = document.getElementById('confirmEmergencyAction');
        
        if (!modal || !actionName || !actionDesc || !confirmBtn) return;

        const actionDetails = {
            pause_all: {
                name: 'Pause All Automation',
                description: 'This will pause all automated processes across the system. Manual intervention will be required to resume operations.'
            },
            emergency_stop: {
                name: 'Emergency Stop',
                description: 'This will immediately stop all non-critical system processes. Only essential monitoring will continue.'
            },
            kill_switch: {
                name: 'Kill Switch',
                description: 'This will shut down all automated systems immediately. This is an extreme measure and should only be used in critical situations.'
            }
        };

        const details = actionDetails[action];
        if (!details) return;

        actionName.textContent = details.name;
        actionDesc.textContent = details.description;
        
        // Remove existing click handlers
        confirmBtn.replaceWith(confirmBtn.cloneNode(true));
        const newConfirmBtn = document.getElementById('confirmEmergencyAction');
        
        // Add new click handler
        newConfirmBtn.addEventListener('click', async () => {
            try {
                await this.executeEmergencyAction(action);
                
                // Close modal
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
                
            } catch (error) {
                console.error('Emergency action failed:', error);
                this.showNotification('Emergency action failed: ' + error.message, 'error');
            }
        });

        // Show modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }

    /**
     * Execute emergency action
     */
    async executeEmergencyAction(action) {
        const response = await fetch(this.config.endpoints.ajax, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'emergency_pause',
                emergency_action: action
            })
        });

        const result = await response.json();
        
        if (result.success) {
            this.emergencyMode = true;
            document.body.classList.add('emergency-mode');
            this.showNotification(result.result.message, 'warning');
            
            // Update UI to reflect emergency state
            this.updateEmergencyState(action);
        } else {
            throw new Error(result.error || 'Emergency action failed');
        }
    }

    /**
     * Update UI for emergency state
     */
    updateEmergencyState(action) {
        // Add emergency banner
        const banner = document.createElement('div');
        banner.className = 'alert alert-danger alert-dismissible emergency-banner';
        banner.innerHTML = `
            <div class="d-flex align-items-center">
                <i data-feather="alert-triangle" class="me-2"></i>
                <strong>EMERGENCY MODE ACTIVE:</strong>
                <span class="ms-2">${action.replace('_', ' ').toUpperCase()}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.querySelector('.dashboard-main').prepend(banner);
        feather.replace();
    }

    /**
     * Handle system actions
     */
    async handleSystemAction(action) {
        try {
            switch (action) {
                case 'health_check':
                    await this.runHealthCheck();
                    break;
                case 'refresh_all':
                    this.refreshAllModules();
                    break;
                case 'burst_refresh':
                    this.startBurstRefresh();
                    break;
                default:
                    console.warn('Unknown system action:', action);
            }
        } catch (error) {
            console.error('System action failed:', error);
            this.showNotification('System action failed: ' + error.message, 'error');
        }
    }

    /**
     * Toggle settings panel
     */
    handleSettingsToggle() {
        const panel = document.getElementById('settingsPanel');
        if (panel) {
            panel.classList.toggle('collapsed');
        }
    }

    /**
     * Refresh all modules
     */
    refreshAllModules() {
        this.showGlobalLoading('Refreshing all modules...');
        
        const promises = this.config.modules.map(moduleId => 
            this.refreshModule(moduleId).catch(error => {
                console.error(`Failed to refresh module ${moduleId}:`, error);
                return null;
            })
        );

        Promise.all(promises).then(() => {
            this.hideGlobalLoading();
            this.updateLastRefresh();
            this.showNotification('All modules refreshed', 'success');
        });
    }

    /**
     * Refresh single module
     */
    async refreshModule(moduleId) {
        try {
            this.setModuleStatus(moduleId, 'loading');
            
            const data = await this.fetchModuleData(moduleId);
            this.renderModuleContent(moduleId, data);
            
            // Update stored data
            const module = this.modules.get(moduleId);
            if (module) {
                module.data = data;
                module.lastUpdate = Date.now();
            }
            
            this.setModuleStatus(moduleId, 'ready');
            
        } catch (error) {
            console.error(`Failed to refresh module ${moduleId}:`, error);
            this.setModuleStatus(moduleId, 'error');
            throw error;
        }
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh(intervalSeconds = 30) {
        this.stopAutoRefresh();
        
        this.autoRefreshInterval = setInterval(() => {
            if (!this.emergencyMode) {
                this.refreshAllModules();
            }
        }, intervalSeconds * 1000);
        
        console.log(`Auto-refresh started: ${intervalSeconds}s interval`);
    }

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    /**
     * Set auto-refresh interval
     */
    setAutoRefreshInterval(seconds) {
        this.startAutoRefresh(seconds);
        
        // Update UI
        document.querySelectorAll('.refresh-interval').forEach(btn => {
            btn.classList.remove('active');
        });
        
        document.querySelector(`[data-interval="${seconds}"]`)?.classList.add('active');
        
        this.showNotification(`Auto-refresh set to ${seconds}s`, 'info');
    }

    /**
     * Enable live mode
     */
    enableLiveMode() {
        this.liveMode = true;
        document.body.setAttribute('data-live-mode', 'true');
        
        // Start SSE connection for real-time updates
        this.connectSSE();
        
        // Start aggressive refresh
        this.startAutoRefresh(5);
        
        this.showNotification('Live mode enabled', 'success');
    }

    /**
     * Disable live mode
     */
    disableLiveMode() {
        this.liveMode = false;
        document.body.setAttribute('data-live-mode', 'false');
        
        // Close SSE connection
        this.disconnectSSE();
        
        // Return to normal refresh
        this.startAutoRefresh(30);
        
        this.showNotification('Live mode disabled', 'info');
    }

    /**
     * Toggle live mode
     */
    toggleLiveMode(enabled) {
        if (enabled) {
            this.enableLiveMode();
        } else {
            this.disableLiveMode();
        }
    }

    /**
     * Connect Server-Sent Events
     */
    connectSSE() {
        if (this.sseConnection) {
            this.sseConnection.close();
        }

        try {
            this.sseConnection = new EventSource('/admin/dashboard/sse');
            
            this.sseConnection.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleSSEUpdate(data);
                } catch (error) {
                    console.error('Failed to parse SSE data:', error);
                }
            };
            
            this.sseConnection.onerror = (error) => {
                console.error('SSE connection error:', error);
                
                // Reconnect after delay
                setTimeout(() => {
                    if (this.liveMode) {
                        this.connectSSE();
                    }
                }, 5000);
            };
            
        } catch (error) {
            console.error('Failed to establish SSE connection:', error);
        }
    }

    /**
     * Disconnect SSE
     */
    disconnectSSE() {
        if (this.sseConnection) {
            this.sseConnection.close();
            this.sseConnection = null;
        }
    }

    /**
     * Handle SSE updates
     */
    handleSSEUpdate(data) {
        if (data.type === 'module_update' && data.module) {
            this.renderModuleContent(data.module, data.data);
        } else if (data.type === 'system_alert') {
            this.showNotification(data.message, data.severity || 'info');
        }
    }

    /**
     * Utility methods
     */
    
    formatLabel(str) {
        return str.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    formatTime(timestamp) {
        if (!timestamp) return 'Unknown';
        
        try {
            const date = new Date(timestamp);
            return date.toLocaleTimeString();
        } catch {
            return 'Invalid time';
        }
    }

    getHealthThreshold(metric) {
        const thresholds = {
            'db_latency_ms': { warning: 100, critical: 500 },
            'memory_usage_pct': { warning: 80, critical: 95 },
            'cpu_usage_pct': { warning: 70, critical: 90 },
            'disk_usage_pct': { warning: 85, critical: 95 }
        };
        
        return thresholds[metric] || { warning: 80, critical: 95 };
    }

    getMetricWarningClass(value, threshold) {
        if (value >= threshold.critical) return 'text-danger';
        if (value >= threshold.warning) return 'text-warning';
        return 'text-success';
    }

    showNotification(message, type = 'info') {
        // Create or update notification
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible notification fade show`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Position notification
        notification.style.position = 'fixed';
        notification.style.top = '1rem';
        notification.style.right = '1rem';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    showGlobalLoading(message = 'Loading...') {
        // Implementation for global loading indicator
        let loader = document.getElementById('globalLoader');
        
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'globalLoader';
            loader.className = 'global-loader';
            loader.innerHTML = `
                <div class="loader-content">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">${message}</p>
                </div>
            `;
            document.body.appendChild(loader);
        }
        
        loader.querySelector('p').textContent = message;
        loader.classList.add('show');
    }

    hideGlobalLoading() {
        const loader = document.getElementById('globalLoader');
        if (loader) {
            loader.classList.remove('show');
        }
    }

    updateLastRefresh() {
        const element = document.getElementById('lastUpdated');
        if (element) {
            element.textContent = new Date().toLocaleTimeString();
        }
    }

    drawRadarChart(canvasId, data) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = Math.min(centerX, centerY) - 20;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw background circles
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        for (let i = 1; i <= 5; i++) {
            ctx.beginPath();
            ctx.arc(centerX, centerY, (radius / 5) * i, 0, 2 * Math.PI);
            ctx.stroke();
        }

        // Draw axes
        const labels = Object.keys(data);
        const values = Object.values(data);
        const angleStep = (2 * Math.PI) / labels.length;

        ctx.strokeStyle = '#cbd5e1';
        for (let i = 0; i < labels.length; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const x = centerX + Math.cos(angle) * radius;
            const y = centerY + Math.sin(angle) * radius;
            
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.lineTo(x, y);
            ctx.stroke();
        }

        // Draw data
        ctx.fillStyle = 'rgba(20, 184, 166, 0.3)';
        ctx.strokeStyle = '#14b8a6';
        ctx.lineWidth = 2;
        
        ctx.beginPath();
        for (let i = 0; i < values.length; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const distance = values[i] * radius;
            const x = centerX + Math.cos(angle) * distance;
            const y = centerY + Math.sin(angle) * distance;
            
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.closePath();
        ctx.fill();
        ctx.stroke();

        // Draw points
        ctx.fillStyle = '#14b8a6';
        for (let i = 0; i < values.length; i++) {
            const angle = i * angleStep - Math.PI / 2;
            const distance = values[i] * radius;
            const x = centerX + Math.cos(angle) * distance;
            const y = centerY + Math.sin(angle) * distance;
            
            ctx.beginPath();
            ctx.arc(x, y, 3, 0, 2 * Math.PI);
            ctx.fill();
        }
    }

    // Additional utility methods...
    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.preferences.theme = this.preferences.theme || {};
        this.preferences.theme.mode = theme;
        this.savePreferences();
    }

    setAccentColor(color) {
        document.documentElement.setAttribute('data-accent', color);
        this.preferences.theme = this.preferences.theme || {};
        this.preferences.theme.accent = color;
        this.savePreferences();
    }

    setFontScale(scale) {
        document.documentElement.style.setProperty('--font-scale', scale + '%');
        document.getElementById('fontScaleValue').textContent = scale + '%';
        this.preferences.theme = this.preferences.theme || {};
        this.preferences.theme.font_scale = parseInt(scale);
        this.savePreferences();
    }

    setGridColumns(columns) {
        document.documentElement.style.setProperty('--grid-columns', columns);
        this.preferences.layout = this.preferences.layout || {};
        this.preferences.layout.columns = parseInt(columns);
        this.savePreferences();
    }

    setGridDensity(density) {
        document.documentElement.setAttribute('data-density', density);
        this.preferences.layout = this.preferences.layout || {};
        this.preferences.layout.density = density;
        this.savePreferences();
    }

    async savePreferences() {
        try {
            await fetch(this.config.endpoints.ajax, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'update_preferences',
                    preferences: JSON.stringify(this.preferences)
                })
            });
        } catch (error) {
            console.error('Failed to save preferences:', error);
        }
    }
}

// Global export
window.DashboardPower = DashboardPower;