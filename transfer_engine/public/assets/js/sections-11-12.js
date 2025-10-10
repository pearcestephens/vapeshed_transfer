/**
 * Sections 11 & 12 - Shared JavaScript Utilities
 * 
 * Common functions for traffic monitoring and API testing
 * 
 * @package     CIS Staff Portal
 * @version     1.0.0
 * @author      Ecigdis Limited Engineering Team
 * @copyright   2025 Ecigdis Limited
 */

// Global utilities namespace
window.CIS = window.CIS || {};
window.CIS.Sections11_12 = {
    
    /**
     * Get CSRF token from meta tag or cookie
     */
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        
        // Fallback to cookie
        const match = document.cookie.match(/csrf_token=([^;]+)/);
        return match ? match[1] : '';
    },
    
    /**
     * Make authenticated AJAX request
     */
    async request(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const config = { ...defaults, ...options };
        
        if (config.headers) {
            config.headers = { ...defaults.headers, ...config.headers };
        }
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error?.message || `HTTP ${response.status}`);
            }
            
            return data;
            
        } catch (error) {
            console.error('Request failed:', error);
            throw error;
        }
    },
    
    /**
     * Show loading indicator
     */
    showLoading(message = 'Loading...') {
        let loader = document.getElementById('global-loader');
        
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'global-loader';
            loader.className = 'global-loader';
            loader.innerHTML = `
                <div class="loader-content">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">${message}</p>
                </div>
            `;
            document.body.appendChild(loader);
        } else {
            loader.querySelector('p').textContent = message;
        }
        
        loader.style.display = 'flex';
    },
    
    /**
     * Hide loading indicator
     */
    hideLoading() {
        const loader = document.getElementById('global-loader');
        if (loader) {
            loader.style.display = 'none';
        }
    },
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        const container = this.getToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                ${this.getToastIcon(type)}
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        container.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto-remove
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
    },
    
    /**
     * Get toast icon for type
     */
    getToastIcon(type) {
        const icons = {
            success: '<i class="fas fa-check-circle"></i>',
            error: '<i class="fas fa-exclamation-circle"></i>',
            warning: '<i class="fas fa-exclamation-triangle"></i>',
            info: '<i class="fas fa-info-circle"></i>'
        };
        
        return icons[type] || icons.info;
    },
    
    /**
     * Get or create toast container
     */
    getToastContainer() {
        let container = document.getElementById('toast-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        return container;
    },
    
    /**
     * Show confirmation modal
     */
    confirm(message, title = 'Confirm', options = {}) {
        return new Promise((resolve) => {
            const modal = this.createModal({
                title: title,
                body: `<p>${message}</p>`,
                buttons: [
                    {
                        text: options.cancelText || 'Cancel',
                        class: 'btn-secondary',
                        onClick: () => {
                            modal.hide();
                            resolve(false);
                        }
                    },
                    {
                        text: options.confirmText || 'Confirm',
                        class: options.danger ? 'btn-danger' : 'btn-primary',
                        onClick: () => {
                            modal.hide();
                            resolve(true);
                        }
                    }
                ]
            });
            
            modal.show();
        });
    },
    
    /**
     * Create Bootstrap modal
     */
    createModal(config) {
        const modalId = 'modal-' + Math.random().toString(36).substr(2, 9);
        
        const buttonsHtml = config.buttons ? config.buttons.map(btn => `
            <button type="button" class="btn ${btn.class}" data-action="${btn.onClick ? 'custom' : 'dismiss'}">
                ${btn.text}
            </button>
        `).join('') : '';
        
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog">
                <div class="modal-dialog ${config.size || ''}" role="document">
                    <div class="modal-content">
                        ${config.title ? `
                            <div class="modal-header">
                                <h5 class="modal-title">${config.title}</h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                        ` : ''}
                        <div class="modal-body">
                            ${config.body}
                        </div>
                        ${buttonsHtml ? `
                            <div class="modal-footer">
                                ${buttonsHtml}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        const modalElement = document.createElement('div');
        modalElement.innerHTML = modalHtml;
        document.body.appendChild(modalElement.firstElementChild);
        
        const modal = {
            element: document.getElementById(modalId),
            show() {
                $(this.element).modal('show');
            },
            hide() {
                $(this.element).modal('hide');
                setTimeout(() => this.element.remove(), 300);
            }
        };
        
        // Attach button handlers
        if (config.buttons) {
            config.buttons.forEach((btn, index) => {
                if (btn.onClick) {
                    const button = modal.element.querySelectorAll('.modal-footer .btn')[index];
                    button.addEventListener('click', btn.onClick);
                }
            });
        }
        
        return modal;
    },
    
    /**
     * Format bytes to human readable
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },
    
    /**
     * Format duration in milliseconds
     */
    formatDuration(ms) {
        if (ms < 1000) {
            return `${Math.round(ms)}ms`;
        }
        if (ms < 60000) {
            return `${(ms / 1000).toFixed(2)}s`;
        }
        const minutes = Math.floor(ms / 60000);
        const seconds = ((ms % 60000) / 1000).toFixed(0);
        return `${minutes}m ${seconds}s`;
    },
    
    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showToast('Copied to clipboard!', 'success', 2000);
        } catch (error) {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.showToast('Copied to clipboard!', 'success', 2000);
        }
    },
    
    /**
     * Debounce function
     */
    debounce(func, wait = 300) {
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
    
    /**
     * Format date/time
     */
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString('en-NZ', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
};

// Shortcut
window.CIS.utils = window.CIS.Sections11_12;
