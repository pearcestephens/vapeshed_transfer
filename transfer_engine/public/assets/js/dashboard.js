/**
 * Unified Intelligence Platform - Main Dashboard JavaScript
 * Enterprise-grade ES6+ class-based architecture
 * File: dashboard.js
 * Version: 1.0.0
 * Last Modified: 2025-10-03
 */

'use strict';

// ============================================
// CONFIGURATION
// ============================================
const CONFIG = {
    apiBase: '/api',
    sseEndpoint: '/sse.php',
    refreshInterval: 30000, // 30 seconds
    activityFeedMax: 100,
    reconnectDelay: 5000,
    maxReconnectAttempts: 10
};

// ============================================
// DASHBOARD CONTROLLER (Main Orchestrator)
// ============================================
class DashboardController {
    constructor() {
        this.stats = new StatsManager();
        this.modules = new ModuleManager();
        this.activity = new ActivityFeedManager();
        this.sse = new SSEConnectionManager();
        this.isInitialized = false;
    }

    async init() {
        if (this.isInitialized) return;
        
        console.log('[Dashboard] Initializing...');
        
        // Initialize all managers
        this.stats.init();
        this.modules.init();
        this.activity.init();
        this.sse.init();
        
        // Load initial data
        await this.loadInitialData();
        
        // Setup auto-refresh
        this.setupAutoRefresh();
        
        // Setup event listeners
        this.setupEventListeners();
        
        this.isInitialized = true;
        console.log('[Dashboard] Initialization complete');
        
        this.activity.add('info', 'Dashboard initialized successfully');
    }

    async loadInitialData() {
        console.log('[Dashboard] Loading initial data...');
        
        try {
            // Load stats
            await this.stats.refresh();
            
            // Load module data
            await this.modules.refreshAll();
            
            console.log('[Dashboard] Initial data loaded');
        } catch (error) {
            console.error('[Dashboard] Failed to load initial data:', error);
            this.activity.add('danger', 'Failed to load initial data: ' + error.message);
        }
    }

    setupAutoRefresh() {
        setInterval(() => {
            console.log('[Dashboard] Auto-refresh triggered');
            this.stats.refresh();
            this.modules.refreshAll();
        }, CONFIG.refreshInterval);
    }

    setupEventListeners() {
        // Pause/Resume feed
        const pauseBtn = document.getElementById('pause-feed');
        if (pauseBtn) {
            pauseBtn.addEventListener('click', () => {
                this.activity.togglePause();
                const icon = pauseBtn.querySelector('i');
                const text = pauseBtn.querySelector('span') || pauseBtn;
                if (this.activity.isPaused) {
                    icon.classList.replace('fa-pause', 'fa-play');
                    text.textContent = ' Resume';
                } else {
                    icon.classList.replace('fa-play', 'fa-pause');
                    text.textContent = ' Pause';
                }
            });
        }
        
        // Clear feed
        const clearBtn = document.getElementById('clear-feed');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.activity.clear();
            });
        }
    }
}

// ============================================
// STATS MANAGER
// ============================================
class StatsManager {
    constructor() {
        this.data = {
            activeTransfers: 0,
            pricingProposals: 0,
            activeAlerts: 0,
            insightsToday: 0,
            systemHealth: 100
        };
    }

    init() {
        console.log('[Stats] Initializing...');
        this.updateUI();
    }

    async refresh() {
        try {
            const response = await fetch(`${CONFIG.apiBase}/stats`);
            if (!response.ok) throw new Error('Failed to fetch stats');
            
            const data = await response.json();
            this.data = { ...this.data, ...data };
            this.updateUI();
            
            console.log('[Stats] Refreshed:', this.data);
        } catch (error) {
            console.error('[Stats] Refresh failed:', error);
        }
    }

    updateUI() {
        this.updateElement('active-transfers', this.data.activeTransfers);
        this.updateElement('pricing-proposals', this.data.pricingProposals);
        this.updateElement('active-alerts', this.data.activeAlerts);
        this.updateElement('insights-today', this.data.insightsToday);
        this.updateElement('system-health', this.data.systemHealth + '%');
    }

    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }
}

// ============================================
// MODULE MANAGER
// ============================================
class ModuleManager {
    constructor() {
        this.modules = new Map();
        this.moduleElements = [];
    }

    init() {
        console.log('[Modules] Initializing...');
        
        // Find all module cards
        this.moduleElements = document.querySelectorAll('[data-module]');
        
        // Initialize each module
        this.moduleElements.forEach(element => {
            const moduleName = element.getAttribute('data-module');
            this.modules.set(moduleName, new ModuleCard(moduleName, element));
        });
        
        console.log(`[Modules] Initialized ${this.modules.size} modules`);
    }

    async refreshAll() {
        const promises = [];
        this.modules.forEach(module => {
            promises.push(module.refresh());
        });
        await Promise.all(promises);
    }

    getModule(name) {
        return this.modules.get(name);
    }
}

// ============================================
// MODULE CARD
// ============================================
class ModuleCard {
    constructor(name, element) {
        this.name = name;
        this.element = element;
        this.data = {};
    }

    async refresh() {
        try {
            const response = await fetch(`${CONFIG.apiBase}/modules/${this.name}`);
            if (!response.ok) {
                console.warn(`[Module:${this.name}] API not ready`);
                return;
            }
            
            const data = await response.json();
            this.data = data;
            this.updateUI();
            
            console.log(`[Module:${this.name}] Refreshed:`, data);
        } catch (error) {
            console.warn(`[Module:${this.name}] Refresh skipped:`, error.message);
        }
    }

    updateUI() {
        // Update stats within module card
        const statElements = this.element.querySelectorAll('.stat-number');
        if (statElements.length >= 2 && this.data.stats) {
            statElements[0].textContent = this.data.stats.primary || '0';
            statElements[1].textContent = this.data.stats.secondary || '0';
        }
    }
}

// ============================================
// ACTIVITY FEED MANAGER
// ============================================
class ActivityFeedManager {
    constructor() {
        this.container = null;
        this.items = [];
        this.maxItems = CONFIG.activityFeedMax;
        this.isPaused = false;
    }

    init() {
        console.log('[Activity] Initializing...');
        this.container = document.getElementById('activity-feed');
    }

    add(type, message, details = null) {
        if (this.isPaused) return;
        
        const item = {
            id: Date.now() + Math.random(),
            type: type, // info, success, warning, danger
            message: message,
            details: details,
            timestamp: new Date()
        };
        
        this.items.unshift(item);
        
        // Limit items
        if (this.items.length > this.maxItems) {
            this.items = this.items.slice(0, this.maxItems);
        }
        
        this.render();
    }

    render() {
        if (!this.container) return;
        
        // Render only last 20 items for performance
        const visibleItems = this.items.slice(0, 20);
        
        this.container.innerHTML = visibleItems.map(item => `
            <div class="activity-item activity-${item.type}">
                <div class="activity-icon">
                    <i class="fas ${this.getIcon(item.type)}"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${this.escapeHtml(item.message)}</div>
                    <div class="activity-time">${this.formatTime(item.timestamp)}</div>
                    ${item.details ? `<div class="activity-details">${this.escapeHtml(item.details)}</div>` : ''}
                </div>
            </div>
        `).join('');
    }

    getIcon(type) {
        const icons = {
            info: 'fa-info-circle',
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            danger: 'fa-times-circle'
        };
        return icons[type] || icons.info;
    }

    formatTime(date) {
        return date.toLocaleTimeString('en-NZ', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    togglePause() {
        this.isPaused = !this.isPaused;
        console.log(`[Activity] ${this.isPaused ? 'Paused' : 'Resumed'}`);
    }

    clear() {
        this.items = [];
        this.render();
        console.log('[Activity] Cleared');
    }
}

// ============================================
// SSE CONNECTION MANAGER
// ============================================
class SSEConnectionManager {
    constructor() {
        this.eventSource = null;
        this.indicator = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
    }

    init() {
        console.log('[SSE] Initializing...');
        this.indicator = document.getElementById('sse-indicator');
        this.connect();
    }

    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        
        console.log('[SSE] Connecting...');
        this.updateIndicator('connecting', 'Connecting...');
        
        try {
            this.eventSource = new EventSource(CONFIG.sseEndpoint);
            
            this.eventSource.onopen = () => {
                console.log('[SSE] Connected');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.updateIndicator('connected', 'Connected');
                
                if (window.dashboard) {
                    window.dashboard.activity.add('success', 'Live feed connected');
                }
            };
            
            this.eventSource.onmessage = (event) => {
                this.handleMessage(event);
            };
            
            this.eventSource.onerror = (error) => {
                console.error('[SSE] Connection error:', error);
                this.handleError();
            };
            
            // Custom event listeners
            this.eventSource.addEventListener('heartbeat', (event) => {
                console.log('[SSE] Heartbeat received');
            });
            
            this.eventSource.addEventListener('proposal', (event) => {
                this.handleProposal(JSON.parse(event.data));
            });
            
            this.eventSource.addEventListener('alert', (event) => {
                this.handleAlert(JSON.parse(event.data));
            });
            
        } catch (error) {
            console.error('[SSE] Failed to connect:', error);
            this.handleError();
        }
    }

    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            console.log('[SSE] Message received:', data);
            
            if (window.dashboard) {
                window.dashboard.activity.add('info', data.message || 'System update received');
            }
        } catch (error) {
            console.error('[SSE] Failed to parse message:', error);
        }
    }

    handleProposal(data) {
        console.log('[SSE] Proposal received:', data);
        if (window.dashboard) {
            window.dashboard.activity.add('info', `New ${data.type} proposal: ${data.sku || 'N/A'}`);
            window.dashboard.stats.refresh();
        }
    }

    handleAlert(data) {
        console.log('[SSE] Alert received:', data);
        if (window.dashboard) {
            const type = data.severity === 'critical' ? 'danger' : 'warning';
            window.dashboard.activity.add(type, `Alert: ${data.message}`);
        }
    }

    handleError() {
        this.isConnected = false;
        this.updateIndicator('disconnected', 'Disconnected');
        
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        
        // Attempt reconnection
        if (this.reconnectAttempts < CONFIG.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`[SSE] Reconnecting in ${CONFIG.reconnectDelay}ms (attempt ${this.reconnectAttempts})...`);
            
            this.reconnectTimer = setTimeout(() => {
                this.connect();
            }, CONFIG.reconnectDelay);
        } else {
            console.error('[SSE] Max reconnect attempts reached');
            if (window.dashboard) {
                window.dashboard.activity.add('danger', 'Live feed connection lost');
            }
        }
    }

    updateIndicator(status, text) {
        if (!this.indicator) return;
        
        this.indicator.className = 'sse-indicator ' + status;
        const textElement = this.indicator.querySelector('.sse-text');
        if (textElement) {
            textElement.textContent = text;
        }
    }

    disconnect() {
        console.log('[SSE] Disconnecting...');
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        this.updateIndicator('disconnected', 'Disconnected');
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
const Utils = {
    formatNumber(num) {
        return new Intl.NumberFormat('en-NZ').format(num);
    },
    
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-NZ', {
            style: 'currency',
            currency: 'NZD'
        }).format(amount);
    },
    
    formatPercent(value, decimals = 1) {
        return `${(value * 100).toFixed(decimals)}%`;
    },
    
    formatDate(date) {
        return new Intl.DateTimeFormat('en-NZ', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        }).format(date);
    },
    
    formatDateTime(date) {
        return new Intl.DateTimeFormat('en-NZ', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    },
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
};

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    console.log('[Dashboard] DOM loaded, initializing...');
    
    // Create global dashboard instance
    window.dashboard = new DashboardController();
    window.dashboard.init().catch(error => {
        console.error('[Dashboard] Initialization error:', error);
    });
    
    // Expose utilities globally
    window.DashboardUtils = Utils;
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.dashboard && window.dashboard.sse) {
        window.dashboard.sse.disconnect();
    }
});
