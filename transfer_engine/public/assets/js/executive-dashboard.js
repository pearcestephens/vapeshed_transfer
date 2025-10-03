/**
 * Executive Dashboard JavaScript Framework
 * Advanced ES6+ class-based architecture for real-time business intelligence
 * The Vape Shed Enterprise Intelligence System
 */

class ExecutiveDashboard {
    constructor(config = {}) {
        this.config = {
            refreshInterval: 30000,
            apiBaseUrl: '/api',
            enableRealTime: true,
            enableNotifications: true,
            theme: 'dark',
            ...config
        };
        
        this.state = {
            isLoading: false,
            lastUpdate: null,
            connectionStatus: 'disconnected',
            activeModule: 'dashboard',
            alerts: [],
            metrics: {}
        };
        
        this.charts = {};
        this.updateInterval = null;
        this.eventSource = null;
        this.notificationQueue = [];
        
        this.init();
    }
    
    /**
     * Initialize dashboard
     */
    async init() {
        try {
            console.log('🚀 Initializing Executive Dashboard...');
            
            await this.loadConfiguration();
            this.setupEventListeners();
            this.initializeCharts();
            this.setupRealTimeUpdates();
            this.loadInitialData();
            
            console.log('✅ Executive Dashboard initialized successfully');
            
            // Show initialization complete notification
            this.showNotification('Dashboard initialized', 'success');
            
        } catch (error) {
            console.error('❌ Failed to initialize dashboard:', error);
            this.showNotification('Failed to initialize dashboard', 'error');
        }
    }
    
    /**
     * Load configuration from API
     */
    async loadConfiguration() {
        try {
            const response = await this.apiCall('/config/dashboard');
            if (response.success) {
                this.config = { ...this.config, ...response.data };
            }
        } catch (error) {
            console.warn('Could not load remote configuration, using defaults');
        }
    }
    
    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Page visibility API for pause/resume
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseUpdates();
            } else {
                this.resumeUpdates();
            }
        });
        
        // Window focus events
        window.addEventListener('focus', () => this.resumeUpdates());
        window.addEventListener('blur', () => this.pauseUpdates());
        
        // Navigation events
        this.setupNavigationListeners();
        
        // Quick action buttons
        this.setupQuickActionListeners();
        
        // Theme and settings
        this.setupSettingsListeners();
        
        // Keyboard shortcuts
        this.setupKeyboardShortcuts();
    }
    
    /**
     * Setup navigation listeners
     */
    setupNavigationListeners() {
        // Tab switching
        document.querySelectorAll('[data-tab]').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.switchTab(tab.dataset.tab);
            });
        });
        
        // Module switching
        document.querySelectorAll('[data-module]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.loadModule(link.dataset.module);
            });
        });
    }
    
    /**
     * Setup quick action button listeners
     */
    setupQuickActionListeners() {
        // Optimization cycle
        const optimizeBtn = document.getElementById('btn-optimize');
        if (optimizeBtn) {
            optimizeBtn.addEventListener('click', () => this.runOptimizationCycle());
        }
        
        // Update competitor data
        const updateBtn = document.getElementById('btn-update-competitors');
        if (updateBtn) {
            updateBtn.addEventListener('click', () => this.updateCompetitorData());
        }
        
        // Generate report
        const reportBtn = document.getElementById('btn-generate-report');
        if (reportBtn) {
            reportBtn.addEventListener('click', () => this.generateReport());
        }
        
        // Emergency stop
        const stopBtn = document.getElementById('btn-emergency-stop');
        if (stopBtn) {
            stopBtn.addEventListener('click', () => this.emergencyStop());
        }
    }
    
    /**
     * Setup settings and theme listeners
     */
    setupSettingsListeners() {
        // Theme selector
        const themeSelect = document.getElementById('themeSelect');
        if (themeSelect) {
            themeSelect.addEventListener('change', (e) => {
                this.setTheme(e.target.value);
            });
        }
        
        // Refresh interval
        const refreshSelect = document.getElementById('refreshInterval');
        if (refreshSelect) {
            refreshSelect.addEventListener('change', (e) => {
                this.setRefreshInterval(parseInt(e.target.value));
            });
        }
        
        // Auto-refresh toggle
        const autoRefreshToggle = document.getElementById('autoRefreshToggle');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                this.toggleAutoRefresh(e.target.checked);
            });
        }
    }
    
    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R: Refresh data
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshData();
            }
            
            // Ctrl/Cmd + Shift + O: Run optimization
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'O') {
                e.preventDefault();
                this.runOptimizationCycle();
            }
            
            // Escape: Close modals
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
            
            // Ctrl/Cmd + Shift + S: Emergency stop
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                this.emergencyStop();
            }
        });
    }
    
    /**
     * Initialize all charts
     */
    initializeCharts() {
        try {
            this.charts.revenue = this.createRevenueChart();
            this.charts.performance = this.createPerformanceChart();
            this.charts.competitive = this.createCompetitiveChart();
            this.charts.optimization = this.createOptimizationChart();
            
            console.log('📊 Charts initialized successfully');
        } catch (error) {
            console.error('❌ Failed to initialize charts:', error);
        }
    }
    
    /**
     * Create revenue trend chart
     */
    createRevenueChart() {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return null;
        
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Revenue',
                    data: [],
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }, {
                    label: 'Profit',
                    data: [],
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: false,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff',
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#0d6efd',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + 
                                       context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'hour',
                            displayFormats: {
                                hour: 'HH:mm'
                            }
                        },
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
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
    
    /**
     * Create performance metrics chart
     */
    createPerformanceChart() {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) return null;
        
        return new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Autonomous Engine', 'Competitor Crawler', 'Transfer Engine', 'Pricing Engine'],
                datasets: [{
                    data: [85, 92, 78, 95],
                    backgroundColor: [
                        '#0d6efd',
                        '#198754',
                        '#ffc107',
                        '#dc3545'
                    ],
                    borderWidth: 2,
                    borderColor: '#1a1d29'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#ffffff',
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Setup real-time updates
     */
    setupRealTimeUpdates() {
        if (!this.config.enableRealTime) return;
        
        // Start polling updates
        this.startPolling();
        
        // Setup Server-Sent Events if available
        this.setupSSE();
    }
    
    /**
     * Start polling for updates
     */
    startPolling() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        this.updateInterval = setInterval(() => {
            if (!document.hidden) {
                this.updateData();
            }
        }, this.config.refreshInterval);
        
        console.log(`🔄 Started polling updates every ${this.config.refreshInterval}ms`);
    }
    
    /**
     * Setup Server-Sent Events
     */
    setupSSE() {
        if (typeof EventSource === 'undefined') {
            console.warn('SSE not supported, using polling only');
            return;
        }
        
        try {
            this.eventSource = new EventSource(`${this.config.apiBaseUrl}/stream`);
            
            this.eventSource.onopen = () => {
                console.log('📡 SSE connection established');
                this.state.connectionStatus = 'connected';
                this.updateConnectionStatus();
            };
            
            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleSSEData(data);
                } catch (error) {
                    console.error('Failed to parse SSE data:', error);
                }
            };
            
            this.eventSource.onerror = (error) => {
                console.error('SSE error:', error);
                this.state.connectionStatus = 'error';
                this.updateConnectionStatus();
                
                // Reconnect after delay
                setTimeout(() => {
                    this.setupSSE();
                }, 5000);
            };
            
        } catch (error) {
            console.error('Failed to setup SSE:', error);
        }
    }
    
    /**
     * Handle Server-Sent Events data
     */
    handleSSEData(data) {
        switch (data.type) {
            case 'metrics_update':
                this.updateMetrics(data.payload);
                break;
                
            case 'alert':
                this.addAlert(data.payload);
                break;
                
            case 'system_status':
                this.updateSystemStatus(data.payload);
                break;
                
            case 'optimization_complete':
                this.handleOptimizationComplete(data.payload);
                break;
                
            case 'competitor_update':
                this.handleCompetitorUpdate(data.payload);
                break;
                
            default:
                console.log('Unknown SSE event type:', data.type);
        }
    }
    
    /**
     * Load initial dashboard data
     */
    async loadInitialData() {
        try {
            this.setState({ isLoading: true });
            
            const [metrics, status, alerts] = await Promise.all([
                this.apiCall('/dashboard/metrics'),
                this.apiCall('/dashboard/status'),
                this.apiCall('/dashboard/alerts')
            ]);
            
            if (metrics.success) {
                this.updateMetrics(metrics.data);
            }
            
            if (status.success) {
                this.updateSystemStatus(status.data);
            }
            
            if (alerts.success) {
                this.state.alerts = alerts.data;
                this.renderAlerts();
            }
            
            this.state.lastUpdate = new Date();
            
        } catch (error) {
            console.error('Failed to load initial data:', error);
            this.showNotification('Failed to load dashboard data', 'error');
        } finally {
            this.setState({ isLoading: false });
        }
    }
    
    /**
     * Update dashboard data
     */
    async updateData() {
        if (this.state.isLoading) return;
        
        try {
            const response = await this.apiCall('/dashboard/realtime');
            
            if (response.success) {
                this.updateMetrics(response.data);
                this.state.lastUpdate = new Date();
                this.updateLastUpdateDisplay();
            }
            
        } catch (error) {
            console.error('Failed to update data:', error);
        }
    }
    
    /**
     * Update metrics display
     */
    updateMetrics(metrics) {
        this.state.metrics = { ...this.state.metrics, ...metrics };
        
        // Update revenue
        if (metrics.today_revenue !== undefined) {
            this.updateElement('today-revenue', '$' + Number(metrics.today_revenue).toLocaleString());
        }
        
        // Update autonomous actions
        if (metrics.autonomous_actions !== undefined) {
            this.updateElement('autonomous-actions', metrics.autonomous_actions);
        }
        
        // Update profit impact
        if (metrics.profit_impact !== undefined) {
            this.updateElement('profit-impact', '$' + Number(metrics.profit_impact).toLocaleString());
        }
        
        // Update system health
        if (metrics.system_health !== undefined) {
            this.updateElement('system-health', metrics.system_health + '%');
        }
        
        // Update charts
        this.updateChartData(metrics);
        
        // Trigger metric update event
        this.triggerEvent('metricsUpdated', metrics);
    }
    
    /**
     * Update chart data
     */
    updateChartData(metrics) {
        // Update revenue chart
        if (this.charts.revenue && metrics.revenue_data) {
            const chart = this.charts.revenue;
            chart.data.labels = metrics.revenue_data.labels;
            chart.data.datasets[0].data = metrics.revenue_data.revenue;
            chart.data.datasets[1].data = metrics.revenue_data.profit;
            chart.update('none');
        }
        
        // Update performance chart
        if (this.charts.performance && metrics.performance_data) {
            const chart = this.charts.performance;
            chart.data.datasets[0].data = metrics.performance_data;
            chart.update('none');
        }
    }
    
    /**
     * Run optimization cycle
     */
    async runOptimizationCycle() {
        if (!confirm('🤖 Run autonomous optimization cycle now?\n\nThis will analyze all stores and execute profitable transfers and price adjustments.')) {
            return;
        }
        
        try {
            this.showSpinner('Running optimization cycle...');
            this.showNotification('Starting optimization cycle...', 'info');
            
            const response = await this.apiCall('/autonomous/start', 'POST', {
                dry_run: false,
                continuous_mode: false,
                max_transfers: 50,
                max_price_changes: 100
            });
            
            if (response.success) {
                this.showNotification(
                    `✅ Optimization started: ${response.data.estimated_duration}s estimated`, 
                    'success'
                );
                
                // Start monitoring progress
                this.monitorOptimization(response.data.run_id);
                
            } else {
                throw new Error(response.error || 'Unknown error');
            }
            
        } catch (error) {
            console.error('Failed to start optimization:', error);
            this.showNotification(`❌ Failed to start optimization: ${error.message}`, 'error');
        } finally {
            this.hideSpinner();
        }
    }
    
    /**
     * Monitor optimization progress
     */
    async monitorOptimization(runId) {
        const checkProgress = async () => {
            try {
                const response = await this.apiCall(`/autonomous/status/${runId}`);
                
                if (response.success) {
                    const { status, progress, results } = response.data;
                    
                    if (status === 'completed') {
                        this.showNotification(
                            `🎉 Optimization complete! ${results.transfers_executed} transfers, ${results.price_changes} price changes`, 
                            'success'
                        );
                        this.updateData(); // Refresh dashboard
                        return;
                    }
                    
                    if (status === 'failed') {
                        this.showNotification('❌ Optimization failed', 'error');
                        return;
                    }
                    
                    // Continue monitoring
                    setTimeout(checkProgress, 3000);
                }
                
            } catch (error) {
                console.error('Failed to check optimization progress:', error);
            }
        };
        
        checkProgress();
    }
    
    /**
     * Update competitor data
     */
    async updateCompetitorData() {
        try {
            this.showSpinner('Updating competitor data...');
            this.showNotification('Starting competitor data crawl...', 'info');
            
            const response = await this.apiCall('/crawler/run', 'POST', {
                priority_competitors: ['vapingkiwi', 'vapoureyes', 'nzvapor'],
                max_products: 1000,
                stealth_mode: true
            });
            
            if (response.success) {
                this.showNotification(
                    `🕷️ Crawler started: ${response.data.estimated_duration}m estimated`,
                    'success'
                );
                
                // Update competitive intelligence section
                setTimeout(() => {
                    this.updateCompetitiveIntelligence();
                }, 5000);
                
            } else {
                throw new Error(response.error || 'Unknown error');
            }
            
        } catch (error) {
            console.error('Failed to update competitor data:', error);
            this.showNotification(`❌ Failed to start crawler: ${error.message}`, 'error');
        } finally {
            this.hideSpinner();
        }
    }
    
    /**
     * Update competitive intelligence display
     */
    async updateCompetitiveIntelligence() {
        try {
            const response = await this.apiCall('/competitive/intelligence');
            
            if (response.success) {
                const { opportunities, threats } = response.data;
                
                // Update opportunities
                const opportunitiesEl = document.getElementById('price-opportunities');
                if (opportunitiesEl && opportunities) {
                    opportunitiesEl.innerHTML = opportunities.map(opp => `
                        <div class="competitive-opportunity">
                            <div>
                                <strong>${opp.product_name}</strong><br>
                                <small>Our: $${opp.our_price} | Them: $${opp.competitor_price}</small>
                            </div>
                            <div class="text-success">
                                +$${opp.potential_profit}
                            </div>
                        </div>
                    `).join('');
                }
                
                // Update threats
                const threatsEl = document.getElementById('competitive-threats');
                if (threatsEl && threats) {
                    threatsEl.innerHTML = threats.map(threat => `
                        <div class="competitive-threat">
                            <div>
                                <strong>${threat.product_name}</strong><br>
                                <small>Our: $${threat.our_price} | Them: $${threat.competitor_price}</small>
                            </div>
                            <div class="text-danger">
                                -${threat.price_difference}%
                            </div>
                        </div>
                    `).join('');
                }
            }
            
        } catch (error) {
            console.error('Failed to update competitive intelligence:', error);
        }
    }
    
    /**
     * Generate executive report
     */
    async generateReport() {
        try {
            this.showSpinner('Generating report...');
            
            const response = await this.apiCall('/reports/executive', 'POST', {
                format: 'pdf',
                include_charts: true,
                include_competitive: true,
                date_range: '30d'
            });
            
            if (response.success) {
                // Download the report
                window.open(response.data.download_url, '_blank');
                this.showNotification('📄 Report generated successfully', 'success');
            } else {
                throw new Error(response.error || 'Failed to generate report');
            }
            
        } catch (error) {
            console.error('Failed to generate report:', error);
            this.showNotification(`❌ Failed to generate report: ${error.message}`, 'error');
        } finally {
            this.hideSpinner();
        }
    }
    
    /**
     * Emergency stop all systems
     */
    async emergencyStop() {
        if (!confirm('🛑 EMERGENCY STOP\n\nThis will immediately halt all autonomous operations including:\n• Stock transfers\n• Price changes\n• Competitor crawling\n\nAre you sure?')) {
            return;
        }
        
        try {
            this.showSpinner('Activating emergency stop...');
            
            const response = await this.apiCall('/autonomous/emergency-stop', 'POST');
            
            if (response.success) {
                this.showNotification('🛑 Emergency stop activated - All systems halted', 'warning');
                
                // Update system status immediately
                this.updateSystemStatus({
                    autonomous_engine: { status: 'stopped' },
                    competitor_crawler: { status: 'stopped' },
                    transfer_engine: { status: 'stopped' },
                    pricing_engine: { status: 'stopped' }
                });
                
            } else {
                throw new Error(response.error || 'Failed to activate emergency stop');
            }
            
        } catch (error) {
            console.error('Failed to activate emergency stop:', error);
            this.showNotification(`❌ Emergency stop failed: ${error.message}`, 'error');
        } finally {
            this.hideSpinner();
        }
    }
    
    /**
     * Update system status indicators
     */
    updateSystemStatus(status) {
        Object.keys(status).forEach(system => {
            const indicator = document.querySelector(`[data-system="${system}"] .badge`);
            if (indicator) {
                const systemStatus = status[system].status;
                indicator.className = `badge bg-${this.getStatusColor(systemStatus)}`;
                indicator.textContent = systemStatus.toUpperCase();
            }
        });
    }
    
    /**
     * Get status indicator color
     */
    getStatusColor(status) {
        switch (status) {
            case 'active':
            case 'running':
            case 'ready':
                return 'success';
            case 'stopped':
            case 'failed':
            case 'error':
                return 'danger';
            case 'warning':
            case 'paused':
                return 'warning';
            default:
                return 'secondary';
        }
    }
    
    /**
     * API call helper
     */
    async apiCall(endpoint, method = 'GET', data = null) {
        try {
            const config = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            };
            
            if (data) {
                config.body = JSON.stringify(data);
            }
            
            const response = await fetch(`${this.config.apiBaseUrl}${endpoint}`, config);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return await response.json();
            
        } catch (error) {
            console.error(`API call failed [${method} ${endpoint}]:`, error);
            throw error;
        }
    }
    
    /**
     * Show loading spinner
     */
    showSpinner(message = 'Loading...') {
        // Implementation for loading overlay
        let spinner = document.getElementById('global-spinner');
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.id = 'global-spinner';
            spinner.className = 'spinner-overlay';
            spinner.innerHTML = `
                <div class="spinner-content">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="spinner-message mt-2">${message}</div>
                </div>
            `;
            document.body.appendChild(spinner);
        } else {
            spinner.querySelector('.spinner-message').textContent = message;
        }
        spinner.style.display = 'flex';
    }
    
    /**
     * Hide loading spinner
     */
    hideSpinner() {
        const spinner = document.getElementById('global-spinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }
    
    /**
     * Show notification
     */
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `toast align-items-center text-white bg-${type} border-0`;
        notification.setAttribute('role', 'alert');
        notification.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        // Add to notification container
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        container.appendChild(notification);
        
        // Initialize and show toast
        const toast = new bootstrap.Toast(notification, { delay: duration });
        toast.show();
        
        // Remove after hiding
        notification.addEventListener('hidden.bs.toast', () => {
            notification.remove();
        });
    }
    
    /**
     * Update element content safely
     */
    updateElement(id, content) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = content;
        }
    }
    
    /**
     * Set dashboard theme
     */
    setTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem('dashboard_theme', theme);
        this.config.theme = theme;
    }
    
    /**
     * Set refresh interval
     */
    setRefreshInterval(interval) {
        this.config.refreshInterval = interval;
        localStorage.setItem('dashboard_refresh_interval', interval);
        this.startPolling(); // Restart with new interval
    }
    
    /**
     * Toggle auto-refresh
     */
    toggleAutoRefresh(enabled) {
        this.config.enableRealTime = enabled;
        if (enabled) {
            this.startPolling();
        } else {
            clearInterval(this.updateInterval);
        }
    }
    
    /**
     * Pause updates (when tab not visible)
     */
    pauseUpdates() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }
    
    /**
     * Resume updates
     */
    resumeUpdates() {
        if (this.config.enableRealTime) {
            this.startPolling();
            this.updateData(); // Immediate update
        }
    }
    
    /**
     * Update connection status indicator
     */
    updateConnectionStatus() {
        const indicator = document.getElementById('system-status');
        if (indicator) {
            const { connectionStatus } = this.state;
            indicator.className = `badge bg-${connectionStatus === 'connected' ? 'success' : 'danger'} pulse`;
            indicator.innerHTML = `<i class="fas fa-circle me-1"></i>${connectionStatus.toUpperCase()}`;
        }
    }
    
    /**
     * Update last update display
     */
    updateLastUpdateDisplay() {
        const element = document.getElementById('last-update');
        if (element && this.state.lastUpdate) {
            element.textContent = `Last updated: ${this.state.lastUpdate.toLocaleTimeString()}`;
        }
    }
    
    /**
     * Set internal state
     */
    setState(newState) {
        this.state = { ...this.state, ...newState };
        this.triggerEvent('stateChanged', this.state);
    }
    
    /**
     * Trigger custom event
     */
    triggerEvent(eventName, data) {
        const event = new CustomEvent(`dashboard:${eventName}`, { detail: data });
        document.dispatchEvent(event);
    }
    
    /**
     * Refresh all dashboard data
     */
    async refreshData() {
        this.showNotification('🔄 Refreshing dashboard data...', 'info', 2000);
        await this.updateData();
        await this.updateCompetitiveIntelligence();
    }
    
    /**
     * Close all open modals
     */
    closeAllModals() {
        document.querySelectorAll('.modal.show').forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });
    }
    
    /**
     * Cleanup on destroy
     */
    destroy() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        
        console.log('🧹 Dashboard destroyed');
    }
}

// Global dashboard instance
let dashboard;

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Load configuration from page data
    const config = window.dashboardConfig || {};
    const data = window.dashboardData || {};
    
    // Initialize dashboard
    dashboard = new ExecutiveDashboard({
        ...config,
        initialData: data
    });
    
    // Make dashboard globally accessible for debugging
    window.dashboard = dashboard;
    
    console.log('🎛️ Executive Dashboard ready');
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ExecutiveDashboard;
}