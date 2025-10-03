/**
 * Dashboard Index JavaScript - Real System Control
 * Production-ready dashboard interactions with live system monitoring
 * 
 * @package VapeshedTransfer\Assets\JS
 */

class DashboardIndex {
    constructor() {
        this.refreshInterval = null;
        this.activityUpdateInterval = null;
        this.init();
    }
    
    init() {
        // Initialize auto-refresh
        this.startAutoRefresh();
        
        // Initialize activity feed updates
        this.startActivityUpdates();
        
        // Initialize service control buttons
        this.initServiceControls();
        
        // Initialize keyboard shortcuts
        this.initKeyboardShortcuts();
        
        console.log('Dashboard Index initialized with real system monitoring');
    }
    
    startAutoRefresh() {
        // Refresh system status every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.refreshSystemStatus();
        }, 30000);
    }
    
    startActivityUpdates() {
        // Update activity feed every 15 seconds
        this.activityUpdateInterval = setInterval(() => {
            this.updateActivityFeed();
        }, 15000);
    }
    
    async refreshSystemStatus() {
        try {
            const response = await fetch('/dashboard.php?action=system_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (!response.ok) throw new Error('Failed to fetch system status');
            
            const data = await response.json();
            
            if (data.success) {
                this.updateSystemCards(data.data);
                this.updateLastRefreshTime();
            }
        } catch (error) {
            console.error('Error refreshing system status:', error);
            this.showError('Failed to refresh system status');
        }
    }
    
    updateSystemCards(status) {
        // Update active processes count
        const processesEl = document.getElementById('activeProcesses');
        if (processesEl) {
            processesEl.textContent = status.processes || 0;
        }
        
        // Update system load
        const loadEl = document.getElementById('systemLoad');
        if (loadEl) {
            loadEl.textContent = parseFloat(status.load_average || 0).toFixed(2);
        }
        
        // Update memory usage
        const memoryEl = document.getElementById('memoryUsage');
        if (memoryEl) {
            const percentage = Math.round(status.memory_usage?.used_percent || 0);
            memoryEl.textContent = percentage + '%';
            
            // Update card color based on memory usage
            const memoryCard = memoryEl.closest('.card');
            memoryCard.classList.remove('bg-success', 'bg-warning', 'bg-danger');
            if (percentage < 70) {
                memoryCard.classList.add('bg-success');
            } else if (percentage < 90) {
                memoryCard.classList.add('bg-warning');
            } else {
                memoryCard.classList.add('bg-danger');
            }
        }
        
        // Update system uptime
        const uptimeEl = document.getElementById('systemUptime');
        if (uptimeEl) {
            uptimeEl.textContent = status.uptime?.formatted || '0h 0m';
        }
    }
    
    async updateActivityFeed() {
        try {
            const response = await fetch('/dashboard.php?action=recent_activity', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (!response.ok) throw new Error('Failed to fetch activity feed');
            
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                this.updateActivityDisplay(data.data);
            }
        } catch (error) {
            console.error('Error updating activity feed:', error);
        }
    }
    
    updateActivityDisplay(activities) {
        const feedEl = document.getElementById('activityFeed');
        if (!feedEl) return;
        
        const html = activities.map(activity => {
            const time = new Date(activity.created_at).toLocaleTimeString();
            const details = activity.details ? 
                ` - <small class="text-muted">${this.truncateText(activity.details, 100)}</small>` : '';
            
            return `<div class="mb-1">
                <span class="text-muted">[${time}]</span>
                <span>${this.escapeHtml(activity.activity)}</span>
                ${details}
            </div>`;
        }).join('');
        
        feedEl.innerHTML = html;
        
        // Auto-scroll to bottom if user isn't scrolled up
        if (feedEl.scrollTop >= feedEl.scrollHeight - feedEl.clientHeight - 50) {
            feedEl.scrollTop = feedEl.scrollHeight;
        }
    }
    
    updateLastRefreshTime() {
        const timeEl = document.getElementById('lastUpdateTime');
        if (timeEl) {
            timeEl.textContent = new Date().toLocaleTimeString();
        }
    }
    
    initServiceControls() {
        // Service control buttons are defined globally for reuse
        window.restartService = (service) => this.restartService(service);
        window.viewLogs = (service) => this.viewLogs(service);
        window.runTransferCheck = () => this.runTransferCheck();
        window.toggleAIServices = () => this.toggleAIServices();
        window.startAllServices = () => this.startAllServices();
        window.restartAllServices = () => this.restartAllServices();
        window.emergencyStop = () => this.emergencyStop();
        window.refreshStatus = () => this.refreshStatus();
    }
    
    async restartService(serviceName) {
        if (!confirm(`Are you sure you want to restart ${serviceName}?`)) return;
        
        try {
            this.showLoading(`Restarting ${serviceName}...`);
            
            const response = await fetch('/dashboard.php?action=restart_service', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ service: serviceName })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`${serviceName} restarted successfully`);
                this.refreshSystemStatus();
            } else {
                throw new Error(data.error?.message || 'Service restart failed');
            }
        } catch (error) {
            this.showError(`Failed to restart ${serviceName}: ${error.message}`);
        } finally {
            this.hideLoading();
        }
    }
    
    viewLogs(service) {
        // Open logs page for specific service
        window.location.href = `/dashboard.php?page=logs&service=${service}`;
    }
    
    async runTransferCheck() {
        try {
            this.showLoading('Running transfer balance check...');
            
            const response = await fetch('/dashboard.php?action=run_transfer_check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Transfer check completed successfully');
                this.refreshSystemStatus();
            } else {
                throw new Error(data.error?.message || 'Transfer check failed');
            }
        } catch (error) {
            this.showError(`Transfer check failed: ${error.message}`);
        } finally {
            this.hideLoading();
        }
    }
    
    async toggleAIServices() {
        try {
            this.showLoading('Toggling AI services...');
            
            const response = await fetch('/dashboard.php?action=toggle_ai_services', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('AI services toggled successfully');
                this.refreshSystemStatus();
            } else {
                throw new Error(data.error?.message || 'AI services toggle failed');
            }
        } catch (error) {
            this.showError(`AI services toggle failed: ${error.message}`);
        } finally {
            this.hideLoading();
        }
    }
    
    async startAllServices() {
        if (!confirm('Start all transfer engine services?')) return;
        
        try {
            this.showLoading('Starting all services...');
            
            const response = await fetch('/dashboard.php?action=start_all_services', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('All services started successfully');
                this.refreshSystemStatus();
            } else {
                throw new Error(data.error?.message || 'Failed to start services');
            }
        } catch (error) {
            this.showError(`Failed to start services: ${error.message}`);
        } finally {
            this.hideLoading();
        }
    }
    
    async restartAllServices() {
        if (!confirm('Restart ALL transfer engine services? This will cause brief downtime.')) return;
        
        try {
            this.showLoading('Restarting all services...');
            
            const response = await fetch('/dashboard.php?action=restart_all_services', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('All services restarted successfully');
                this.refreshSystemStatus();
            } else {
                throw new Error(data.error?.message || 'Failed to restart services');
            }
        } catch (error) {
            this.showError(`Failed to restart services: ${error.message}`);
        } finally {
            this.hideLoading();
        }
    }
    
    async emergencyStop() {
        if (!confirm('EMERGENCY STOP: This will halt all transfer engine operations immediately. Are you sure?')) return;
        
        try {
            this.showLoading('Emergency stop in progress...');
            
            const response = await fetch('/dashboard.php?action=emergency_stop', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showWarning('EMERGENCY STOP EXECUTED - All services halted');
                this.refreshSystemStatus();
            } else {
                throw new Error(data.error?.message || 'Emergency stop failed');
            }
        } catch (error) {
            this.showError(`Emergency stop failed: ${error.message}`);
        } finally {
            this.hideLoading();
        }
    }
    
    refreshStatus() {
        this.refreshSystemStatus();
        this.updateActivityFeed();
        this.showSuccess('Status refreshed');
    }
    
    initKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        this.refreshStatus();
                        break;
                    case 's':
                        e.preventDefault();
                        this.startAllServices();
                        break;
                }
            }
        });
    }
    
    // Utility methods
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
    
    showLoading(message) {
        // Create or update loading indicator
        let loader = document.getElementById('systemLoader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'systemLoader';
            loader.className = 'alert alert-info position-fixed';
            loader.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            document.body.appendChild(loader);
        }
        loader.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${message}`;
    }
    
    hideLoading() {
        const loader = document.getElementById('systemLoader');
        if (loader) {
            loader.remove();
        }
    }
    
    showSuccess(message) {
        this.showAlert(message, 'success');
    }
    
    showError(message) {
        this.showAlert(message, 'danger');
    }
    
    showWarning(message) {
        this.showAlert(message, 'warning');
    }
    
    showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
    
    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        if (this.activityUpdateInterval) {
            clearInterval(this.activityUpdateInterval);
        }
    }
}

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.dashboardIndex = new DashboardIndex();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.dashboardIndex) {
        window.dashboardIndex.destroy();
    }
});