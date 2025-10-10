/**
 * Toast System Component
 *
 * Lightweight toast notification component with:
 * - Minimal API
 * - Fast rendering
 * - Auto-positioning
 * - Gesture support
 * - Accessibility features
 * - Multiple themes
 * - Custom templates
 * - Event callbacks
 * - Batch operations
 * - Memory efficient
 *
 * @category   Frontend
 * @package    VapeshedTransfer
 * @subpackage JavaScript
 * @version    1.0.0
 */

class ToastSystem {
    constructor(options = {}) {
        this.config = {
            container: null,
            position: 'top-right',
            duration: 4000,
            theme: 'default',
            stackLimit: 3,
            spacing: 8,
            offset: 20,
            pauseOnHover: true,
            pauseOnFocus: true,
            closeOnClick: false,
            showCloseButton: true,
            animation: 'slide',
            accessibility: true,
            rtl: false,
            ...options
        };

        this.toasts = [];
        this.container = null;
        this.idCounter = 0;
        this.isPaused = false;
        
        this.init();
    }

    /**
     * Initialize toast system
     */
    init() {
        this.createContainer();
        this.bindGlobalEvents();
        this.setupAccessibility();
    }

    /**
     * Create toast container
     */
    createContainer() {
        if (this.config.container && typeof this.config.container === 'string') {
            this.container = document.querySelector(this.config.container);
        }
        
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }

        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-label', 'Notifications');
        
        this.updateContainerPosition();
        this.updateContainerStyle();
    }

    /**
     * Update container position
     */
    updateContainerPosition() {
        const positions = {
            'top-left': { top: this.config.offset, left: this.config.offset },
            'top-center': { top: this.config.offset, left: '50%', transform: 'translateX(-50%)' },
            'top-right': { top: this.config.offset, right: this.config.offset },
            'bottom-left': { bottom: this.config.offset, left: this.config.offset },
            'bottom-center': { bottom: this.config.offset, left: '50%', transform: 'translateX(-50%)' },
            'bottom-right': { bottom: this.config.offset, right: this.config.offset },
            'center': { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }
        };

        const style = positions[this.config.position] || positions['top-right'];
        Object.assign(this.container.style, {
            position: 'fixed',
            zIndex: '9999',
            pointerEvents: 'none',
            direction: this.config.rtl ? 'rtl' : 'ltr',
            ...style
        });
    }

    /**
     * Update container styling
     */
    updateContainerStyle() {
        this.container.style.cssText += `
            display: flex;
            flex-direction: ${this.config.position.includes('bottom') ? 'column-reverse' : 'column'};
            gap: ${this.config.spacing}px;
            max-width: 420px;
            width: auto;
        `;
    }

    /**
     * Show toast notification
     */
    show(message, type = 'default', options = {}) {
        const toastOptions = { ...this.config, ...options };
        const toast = this.createToast(message, type, toastOptions);
        
        this.addToast(toast);
        return toast.id;
    }

    /**
     * Create toast object
     */
    createToast(message, type, options) {
        const id = `toast-${++this.idCounter}`;
        
        const toast = {
            id,
            message,
            type,
            options,
            element: null,
            timer: null,
            remainingTime: options.duration,
            startTime: Date.now(),
            isPaused: false
        };

        toast.element = this.createElement(toast);
        return toast;
    }

    /**
     * Create toast DOM element
     */
    createElement(toast) {
        const element = document.createElement('div');
        element.className = `toast toast-${toast.type} toast-theme-${this.config.theme}`;
        element.setAttribute('data-toast-id', toast.id);
        element.setAttribute('role', 'alert');
        element.setAttribute('aria-atomic', 'true');
        
        if (toast.options.accessibility) {
            element.setAttribute('tabindex', '0');
        }

        // Base styling
        element.style.cssText = `
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 16px;
            min-width: 300px;
            max-width: 400px;
            pointer-events: auto;
            position: relative;
            cursor: ${toast.options.closeOnClick ? 'pointer' : 'default'};
            transition: all 0.3s ease;
            transform: translateX(${this.getInitialTransform()});
            opacity: 0;
        `;

        // Type-specific styling
        this.applyTypeStyles(element, toast.type);

        // Create content
        const content = this.createContent(toast);
        element.appendChild(content);

        // Event listeners
        this.bindToastEvents(element, toast);

        return element;
    }

    /**
     * Get initial transform for animation
     */
    getInitialTransform() {
        if (this.config.position.includes('right')) return '100%';
        if (this.config.position.includes('left')) return '-100%';
        return '0';
    }

    /**
     * Apply type-specific styles
     */
    applyTypeStyles(element, type) {
        const styles = {
            success: {
                borderLeft: '4px solid #28a745',
                background: '#f8fff9'
            },
            error: {
                borderLeft: '4px solid #dc3545',
                background: '#fffcfc'
            },
            warning: {
                borderLeft: '4px solid #ffc107',
                background: '#fffef7'
            },
            info: {
                borderLeft: '4px solid #17a2b8',
                background: '#f7fcff'
            },
            default: {
                borderLeft: '4px solid #6c757d'
            }
        };

        Object.assign(element.style, styles[type] || styles.default);
    }

    /**
     * Create toast content
     */
    createContent(toast) {
        const content = document.createElement('div');
        content.className = 'toast-content';
        content.style.cssText = 'display: flex; align-items: flex-start; gap: 12px;';

        // Icon
        const icon = this.createIcon(toast.type);
        if (icon) {
            content.appendChild(icon);
        }

        // Message container
        const messageContainer = document.createElement('div');
        messageContainer.className = 'toast-message-container';
        messageContainer.style.cssText = 'flex: 1; min-width: 0;';

        // Title (if provided)
        if (toast.options.title) {
            const title = document.createElement('div');
            title.className = 'toast-title';
            title.textContent = toast.options.title;
            title.style.cssText = 'font-weight: 600; margin-bottom: 4px; color: #212529;';
            messageContainer.appendChild(title);
        }

        // Message
        const message = document.createElement('div');
        message.className = 'toast-message';
        message.innerHTML = toast.message;
        message.style.cssText = 'color: #6c757d; font-size: 14px; line-height: 1.4; word-wrap: break-word;';
        messageContainer.appendChild(message);

        // Actions (if provided)
        if (toast.options.actions) {
            const actions = this.createActions(toast);
            messageContainer.appendChild(actions);
        }

        content.appendChild(messageContainer);

        // Close button
        if (toast.options.showCloseButton) {
            const closeButton = this.createCloseButton(toast);
            content.appendChild(closeButton);
        }

        return content;
    }

    /**
     * Create icon element
     */
    createIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        if (!icons[type]) return null;

        const icon = document.createElement('div');
        icon.className = 'toast-icon';
        icon.innerHTML = icons[type];
        icon.style.cssText = 'font-size: 18px; flex-shrink: 0; margin-top: 2px;';
        
        return icon;
    }

    /**
     * Create action buttons
     */
    createActions(toast) {
        const actions = document.createElement('div');
        actions.className = 'toast-actions';
        actions.style.cssText = 'margin-top: 12px; display: flex; gap: 8px; flex-wrap: wrap;';

        toast.options.actions.forEach(action => {
            const button = document.createElement('button');
            button.textContent = action.text;
            button.className = 'toast-action-button';
            button.style.cssText = `
                background: ${action.primary ? '#007bff' : 'transparent'};
                color: ${action.primary ? '#ffffff' : '#007bff'};
                border: 1px solid #007bff;
                border-radius: 4px;
                padding: 4px 12px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s ease;
            `;

            button.addEventListener('click', (e) => {
                e.stopPropagation();
                if (action.handler) {
                    action.handler(toast);
                }
                if (action.dismiss !== false) {
                    this.dismiss(toast.id);
                }
            });

            actions.appendChild(button);
        });

        return actions;
    }

    /**
     * Create close button
     */
    createCloseButton(toast) {
        const button = document.createElement('button');
        button.className = 'toast-close-button';
        button.innerHTML = '×';
        button.setAttribute('aria-label', 'Close notification');
        button.style.cssText = `
            background: none;
            border: none;
            font-size: 20px;
            color: #adb5bd;
            cursor: pointer;
            padding: 0;
            margin: 0;
            line-height: 1;
            flex-shrink: 0;
            transition: color 0.2s ease;
        `;

        button.addEventListener('click', (e) => {
            e.stopPropagation();
            this.dismiss(toast.id);
        });

        button.addEventListener('mouseenter', () => {
            button.style.color = '#6c757d';
        });

        button.addEventListener('mouseleave', () => {
            button.style.color = '#adb5bd';
        });

        return button;
    }

    /**
     * Bind toast event listeners
     */
    bindToastEvents(element, toast) {
        // Click to close
        if (toast.options.closeOnClick) {
            element.addEventListener('click', () => this.dismiss(toast.id));
        }

        // Pause on hover
        if (toast.options.pauseOnHover) {
            element.addEventListener('mouseenter', () => this.pauseToast(toast.id));
            element.addEventListener('mouseleave', () => this.resumeToast(toast.id));
        }

        // Pause on focus
        if (toast.options.pauseOnFocus && toast.options.accessibility) {
            element.addEventListener('focusin', () => this.pauseToast(toast.id));
            element.addEventListener('focusout', () => this.resumeToast(toast.id));
        }

        // Keyboard navigation
        if (toast.options.accessibility) {
            element.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.dismiss(toast.id);
                } else if (e.key === 'Enter' && toast.options.closeOnClick) {
                    this.dismiss(toast.id);
                }
            });
        }

        // Swipe to dismiss (touch devices)
        this.addSwipeSupport(element, toast);
    }

    /**
     * Add swipe gesture support
     */
    addSwipeSupport(element, toast) {
        let startX = 0;
        let currentX = 0;
        let isDragging = false;

        element.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
            element.style.transition = 'none';
        });

        element.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            
            currentX = e.touches[0].clientX;
            const deltaX = currentX - startX;
            const direction = this.config.position.includes('right') ? 1 : -1;
            
            if (deltaX * direction > 0) {
                element.style.transform = `translateX(${deltaX}px)`;
                element.style.opacity = Math.max(0.3, 1 - Math.abs(deltaX) / 200);
            }
        });

        element.addEventListener('touchend', () => {
            if (!isDragging) return;
            
            isDragging = false;
            element.style.transition = 'all 0.3s ease';
            
            const deltaX = Math.abs(currentX - startX);
            if (deltaX > 100) {
                this.dismiss(toast.id);
            } else {
                element.style.transform = 'translateX(0)';
                element.style.opacity = '1';
            }
        });
    }

    /**
     * Add toast to container
     */
    addToast(toast) {
        // Enforce stack limit
        if (this.toasts.length >= this.config.stackLimit) {
            this.dismiss(this.toasts[0].id);
        }

        this.toasts.push(toast);
        this.container.appendChild(toast.element);

        // Animate in
        requestAnimationFrame(() => {
            toast.element.style.transform = 'translateX(0)';
            toast.element.style.opacity = '1';
        });

        // Auto dismiss
        if (toast.options.duration > 0) {
            this.scheduleAutoDismiss(toast);
        }

        // Dispatch event
        this.dispatchEvent('show', toast);

        return toast;
    }

    /**
     * Schedule auto dismiss
     */
    scheduleAutoDismiss(toast) {
        toast.timer = setTimeout(() => {
            this.dismiss(toast.id);
        }, toast.remainingTime);
    }

    /**
     * Dismiss toast
     */
    dismiss(id) {
        const toastIndex = this.toasts.findIndex(t => t.id === id);
        if (toastIndex === -1) return;

        const toast = this.toasts[toastIndex];
        
        // Clear timer
        if (toast.timer) {
            clearTimeout(toast.timer);
        }

        // Animate out
        const direction = this.config.position.includes('right') ? '100%' : '-100%';
        toast.element.style.transform = `translateX(${direction})`;
        toast.element.style.opacity = '0';

        setTimeout(() => {
            if (toast.element.parentNode) {
                toast.element.parentNode.removeChild(toast.element);
            }
            this.toasts.splice(toastIndex, 1);
            
            // Dispatch event
            this.dispatchEvent('dismiss', toast);
        }, 300);
    }

    /**
     * Pause specific toast
     */
    pauseToast(id) {
        const toast = this.toasts.find(t => t.id === id);
        if (!toast || toast.isPaused) return;

        if (toast.timer) {
            clearTimeout(toast.timer);
            toast.remainingTime -= Date.now() - toast.startTime;
            toast.isPaused = true;
        }
    }

    /**
     * Resume specific toast
     */
    resumeToast(id) {
        const toast = this.toasts.find(t => t.id === id);
        if (!toast || !toast.isPaused) return;

        toast.isPaused = false;
        toast.startTime = Date.now();
        
        if (toast.remainingTime > 0) {
            this.scheduleAutoDismiss({
                ...toast,
                remainingTime: toast.remainingTime
            });
        }
    }

    /**
     * Pause all toasts
     */
    pauseAll() {
        this.isPaused = true;
        this.toasts.forEach(toast => this.pauseToast(toast.id));
    }

    /**
     * Resume all toasts
     */
    resumeAll() {
        this.isPaused = false;
        this.toasts.forEach(toast => this.resumeToast(toast.id));
    }

    /**
     * Clear all toasts
     */
    clear() {
        this.toasts.slice().forEach(toast => this.dismiss(toast.id));
    }

    /**
     * Bind global events
     */
    bindGlobalEvents() {
        // Pause when page becomes hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAll();
            } else {
                this.resumeAll();
            }
        });

        // Update position on resize
        window.addEventListener('resize', () => {
            this.updateContainerPosition();
        });
    }

    /**
     * Setup accessibility features
     */
    setupAccessibility() {
        if (!this.config.accessibility) return;

        // Announce toast count changes to screen readers
        const announcer = document.createElement('div');
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        announcer.style.cssText = 'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;';
        document.body.appendChild(announcer);

        this.announcer = announcer;
    }

    /**
     * Dispatch custom events
     */
    dispatchEvent(type, toast) {
        const event = new CustomEvent(`toast:${type}`, {
            detail: { toast, count: this.toasts.length }
        });
        document.dispatchEvent(event);

        // Update announcer for screen readers
        if (this.announcer && type === 'show') {
            this.announcer.textContent = `New ${toast.type} notification: ${toast.message}`;
        }
    }

    /**
     * Update configuration
     */
    updateConfig(newConfig) {
        this.config = { ...this.config, ...newConfig };
        this.updateContainerPosition();
        this.updateContainerStyle();
    }

    /**
     * Get current toasts
     */
    getToasts() {
        return this.toasts.slice();
    }

    /**
     * Check if toast exists
     */
    exists(id) {
        return this.toasts.some(t => t.id === id);
    }

    /**
     * Destroy toast system
     */
    destroy() {
        this.clear();
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        if (this.announcer && this.announcer.parentNode) {
            this.announcer.parentNode.removeChild(this.announcer);
        }
    }
}

// Convenience methods
ToastSystem.prototype.success = function(message, options = {}) {
    return this.show(message, 'success', options);
};

ToastSystem.prototype.error = function(message, options = {}) {
    return this.show(message, 'error', { duration: 0, ...options });
};

ToastSystem.prototype.warning = function(message, options = {}) {
    return this.show(message, 'warning', options);
};

ToastSystem.prototype.info = function(message, options = {}) {
    return this.show(message, 'info', options);
};

// Initialize global instance
window.addEventListener('DOMContentLoaded', () => {
    window.toast = new ToastSystem();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ToastSystem;
}