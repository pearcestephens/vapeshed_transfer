/**
 * Notifications System
 *
 * Advanced notification system with:
 * - Toast notifications (4 types)
 * - System alerts
 * - Real-time updates
 * - Progress notifications
 * - Action buttons
 * - Auto-dismiss
 * - Sound support
 * - Position management
 * - Persistence options
 * - Queue management
 *
 * @category   Frontend
 * @package    VapeshedTransfer
 * @subpackage JavaScript
 * @version    1.0.0
 */

class NotificationSystem {
    constructor(options = {}) {
        this.options = {
            position: 'top-right',
            autoClose: true,
            duration: 5000,
            maxNotifications: 5,
            showProgress: true,
            enableSounds: false,
            enablePersistence: false,
            animations: true,
            ...options
        };
        
        this.notifications = new Map();
        this.queue = [];
        this.container = null;
        this.sounds = {};
        
        this.init();
    }

    /**
     * Initialize notification system
     */
    init() {
        this.createContainer();
        this.loadSounds();
        this.bindEvents();
    }

    /**
     * Create notification container
     */
    createContainer() {
        this.container = document.createElement('div');
        this.container.className = `notification-container notification-${this.options.position}`;
        this.container.style.cssText = `
            position: fixed;
            z-index: var(--z-toast, 1080);
            pointer-events: none;
            max-width: 400px;
        `;
        
        this.setContainerPosition();
        document.body.appendChild(this.container);
    }

    /**
     * Set container position
     */
    setContainerPosition() {
        const positions = {
            'top-right': { top: '20px', right: '20px' },
            'top-left': { top: '20px', left: '20px' },
            'top-center': { top: '20px', left: '50%', transform: 'translateX(-50%)' },
            'bottom-right': { bottom: '20px', right: '20px' },
            'bottom-left': { bottom: '20px', left: '20px' },
            'bottom-center': { bottom: '20px', left: '50%', transform: 'translateX(-50%)' }
        };
        
        const position = positions[this.options.position] || positions['top-right'];
        Object.assign(this.container.style, position);
    }

    /**
     * Load notification sounds
     */
    loadSounds() {
        if (!this.options.enableSounds) return;
        
        const soundFiles = {
            success: '/assets/sounds/success.mp3',
            error: '/assets/sounds/error.mp3',
            warning: '/assets/sounds/warning.mp3',
            info: '/assets/sounds/info.mp3'
        };
        
        Object.entries(soundFiles).forEach(([type, url]) => {
            this.sounds[type] = new Audio(url);
            this.sounds[type].volume = 0.3;
        });
    }

    /**
     * Bind global events
     */
    bindEvents() {
        // Page visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseAll();
            } else {
                this.resumeAll();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'x') {
                this.clearAll();
            }
        });
    }

    /**
     * Show success notification
     */
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    /**
     * Show error notification
     */
    error(message, options = {}) {
        return this.show(message, 'error', {
            autoClose: false,
            ...options
        });
    }

    /**
     * Show warning notification
     */
    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    /**
     * Show info notification
     */
    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    /**
     * Show notification
     */
    show(message, type = 'info', options = {}) {
        const notification = this.createNotification(message, type, options);
        
        // Check if we need to queue
        if (this.notifications.size >= this.options.maxNotifications) {
            this.queue.push({ message, type, options });
            return notification.id;
        }
        
        this.addNotification(notification);
        return notification.id;
    }

    /**
     * Create notification object
     */
    createNotification(message, type, options) {
        const id = `notification-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        
        const notification = {
            id,
            message,
            type,
            timestamp: Date.now(),
            options: {
                ...this.options,
                ...options
            },
            element: null,
            timer: null,
            progressTimer: null,
            isPaused: false
        };

        notification.element = this.createElement(notification);
        return notification;
    }

    /**
     * Create notification DOM element
     */
    createElement(notification) {
        const element = document.createElement('div');
        element.className = `notification notification-${notification.type}`;
        element.setAttribute('data-notification-id', notification.id);
        element.style.cssText = `
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #dee2e6);
            border-radius: var(--radius-lg, 0.5rem);
            box-shadow: var(--shadow-lg, 0 1rem 3rem rgba(0, 0, 0, 0.175));
            padding: 16px;
            margin-bottom: 12px;
            min-width: 300px;
            max-width: 400px;
            pointer-events: auto;
            position: relative;
            overflow: hidden;
            transform: translateX(${this.options.position.includes('right') ? '100%' : '-100%'});
            opacity: 0;
            transition: all 0.3s ease;
        `;

        // Type-specific styling
        const typeColors = {
            success: 'var(--success-color, #28a745)',
            error: 'var(--danger-color, #dc3545)',
            warning: 'var(--warning-color, #ffc107)',
            info: 'var(--info-color, #17a2b8)'
        };
        
        element.style.borderLeftColor = typeColors[notification.type];
        element.style.borderLeftWidth = '4px';

        // Create content
        const content = this.createContent(notification);
        element.appendChild(content);

        // Create progress bar
        if (notification.options.showProgress && notification.options.autoClose) {
            const progress = this.createProgressBar(notification);
            element.appendChild(progress);
        }

        return element;
    }

    /**
     * Create notification content
     */
    createContent(notification) {
        const content = document.createElement('div');
        content.className = 'notification-content';
        
        const header = document.createElement('div');
        header.className = 'notification-header';
        header.style.cssText = `
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        `;

        // Icon
        const icon = document.createElement('div');
        icon.className = 'notification-icon';
        icon.innerHTML = this.getIcon(notification.type);
        icon.style.cssText = `
            width: 20px;
            height: 20px;
            margin-right: 8px;
            color: ${this.getTypeColor(notification.type)};
        `;

        // Title
        const title = document.createElement('div');
        title.className = 'notification-title';
        title.textContent = this.getTypeTitle(notification.type);
        title.style.cssText = `
            font-weight: 600;
            color: var(--text-primary, #212529);
            flex: 1;
        `;

        // Close button
        const closeBtn = document.createElement('button');
        closeBtn.className = 'notification-close';
        closeBtn.innerHTML = '×';
        closeBtn.style.cssText = `
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-secondary, #6c757d);
            padding: 0;
            line-height: 1;
            margin-left: 8px;
        `;
        closeBtn.addEventListener('click', () => this.close(notification.id));

        header.appendChild(icon);
        header.appendChild(title);
        header.appendChild(closeBtn);

        // Message
        const message = document.createElement('div');
        message.className = 'notification-message';
        message.innerHTML = notification.message;
        message.style.cssText = `
            color: var(--text-secondary, #6c757d);
            font-size: 14px;
            line-height: 1.4;
        `;

        // Actions
        if (notification.options.actions) {
            const actions = this.createActions(notification);
            content.appendChild(header);
            content.appendChild(message);
            content.appendChild(actions);
        } else {
            content.appendChild(header);
            content.appendChild(message);
        }

        return content;
    }

    /**
     * Create action buttons
     */
    createActions(notification) {
        const actions = document.createElement('div');
        actions.className = 'notification-actions';
        actions.style.cssText = `
            margin-top: 12px;
            display: flex;
            gap: 8px;
        `;

        notification.options.actions.forEach(action => {
            const button = document.createElement('button');
            button.textContent = action.text;
            button.className = `btn btn-sm ${action.primary ? 'btn-primary' : 'btn-outline-secondary'}`;
            button.style.cssText = `
                padding: 4px 12px;
                font-size: 12px;
                border-radius: 4px;
                cursor: pointer;
                border: 1px solid;
                transition: all 0.15s ease;
            `;

            button.addEventListener('click', () => {
                if (action.handler) {
                    action.handler(notification);
                }
                if (action.closeOnClick !== false) {
                    this.close(notification.id);
                }
            });

            actions.appendChild(button);
        });

        return actions;
    }

    /**
     * Create progress bar
     */
    createProgressBar(notification) {
        const progress = document.createElement('div');
        progress.className = 'notification-progress';
        progress.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: ${this.getTypeColor(notification.type)};
            width: 100%;
            animation: notificationProgress ${notification.options.duration}ms linear;
        `;

        return progress;
    }

    /**
     * Add notification to container
     */
    addNotification(notification) {
        this.notifications.set(notification.id, notification);
        this.container.appendChild(notification.element);

        // Play sound
        if (this.options.enableSounds && this.sounds[notification.type]) {
            this.sounds[notification.type].play().catch(() => {});
        }

        // Animate in
        if (this.options.animations) {
            requestAnimationFrame(() => {
                notification.element.style.transform = 'translateX(0)';
                notification.element.style.opacity = '1';
            });
        }

        // Auto close
        if (notification.options.autoClose) {
            this.scheduleClose(notification);
        }

        // Persist if enabled
        if (this.options.enablePersistence) {
            this.persistNotification(notification);
        }

        // Dispatch event
        document.dispatchEvent(new CustomEvent('notification:shown', {
            detail: notification
        }));
    }

    /**
     * Schedule notification close
     */
    scheduleClose(notification) {
        notification.timer = setTimeout(() => {
            this.close(notification.id);
        }, notification.options.duration);
    }

    /**
     * Close notification
     */
    close(id) {
        const notification = this.notifications.get(id);
        if (!notification) return;

        // Clear timers
        if (notification.timer) {
            clearTimeout(notification.timer);
        }
        if (notification.progressTimer) {
            clearTimeout(notification.progressTimer);
        }

        // Animate out
        if (this.options.animations) {
            notification.element.style.transform = `translateX(${this.options.position.includes('right') ? '100%' : '-100%'})`;
            notification.element.style.opacity = '0';

            setTimeout(() => {
                this.removeNotification(id);
            }, 300);
        } else {
            this.removeNotification(id);
        }
    }

    /**
     * Remove notification from container
     */
    removeNotification(id) {
        const notification = this.notifications.get(id);
        if (!notification) return;

        // Remove from DOM
        if (notification.element && notification.element.parentNode) {
            notification.element.parentNode.removeChild(notification.element);
        }

        // Remove from map
        this.notifications.delete(id);

        // Process queue
        if (this.queue.length > 0) {
            const queued = this.queue.shift();
            this.show(queued.message, queued.type, queued.options);
        }

        // Dispatch event
        document.dispatchEvent(new CustomEvent('notification:closed', {
            detail: { id }
        }));
    }

    /**
     * Clear all notifications
     */
    clearAll() {
        this.notifications.forEach((notification) => {
            this.close(notification.id);
        });
        this.queue = [];
    }

    /**
     * Pause all notifications
     */
    pauseAll() {
        this.notifications.forEach((notification) => {
            if (notification.timer && !notification.isPaused) {
                clearTimeout(notification.timer);
                notification.isPaused = true;
            }
        });
    }

    /**
     * Resume all notifications
     */
    resumeAll() {
        this.notifications.forEach((notification) => {
            if (notification.isPaused && notification.options.autoClose) {
                this.scheduleClose(notification);
                notification.isPaused = false;
            }
        });
    }

    /**
     * Update notification
     */
    update(id, message, type = null) {
        const notification = this.notifications.get(id);
        if (!notification) return;

        const messageElement = notification.element.querySelector('.notification-message');
        if (messageElement) {
            messageElement.innerHTML = message;
        }

        if (type && type !== notification.type) {
            notification.type = type;
            notification.element.className = `notification notification-${type}`;
            
            const icon = notification.element.querySelector('.notification-icon');
            if (icon) {
                icon.innerHTML = this.getIcon(type);
                icon.style.color = this.getTypeColor(type);
            }
            
            const title = notification.element.querySelector('.notification-title');
            if (title) {
                title.textContent = this.getTypeTitle(type);
            }
        }
    }

    /**
     * Get notification count
     */
    getCount() {
        return this.notifications.size;
    }

    /**
     * Check if notification exists
     */
    exists(id) {
        return this.notifications.has(id);
    }

    /**
     * Persist notification to localStorage
     */
    persistNotification(notification) {
        const stored = JSON.parse(localStorage.getItem('notifications') || '[]');
        stored.push({
            id: notification.id,
            message: notification.message,
            type: notification.type,
            timestamp: notification.timestamp
        });
        
        // Keep only last 50 notifications
        if (stored.length > 50) {
            stored.splice(0, stored.length - 50);
        }
        
        localStorage.setItem('notifications', JSON.stringify(stored));
    }

    /**
     * Get persisted notifications
     */
    getPersistedNotifications() {
        return JSON.parse(localStorage.getItem('notifications') || '[]');
    }

    /**
     * Clear persisted notifications
     */
    clearPersistedNotifications() {
        localStorage.removeItem('notifications');
    }

    /**
     * Utility methods
     */
    getIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || icons.info;
    }

    getTypeColor(type) {
        const colors = {
            success: 'var(--success-color, #28a745)',
            error: 'var(--danger-color, #dc3545)',
            warning: 'var(--warning-color, #ffc107)',
            info: 'var(--info-color, #17a2b8)'
        };
        return colors[type] || colors.info;
    }

    getTypeTitle(type) {
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        return titles[type] || titles.info;
    }

    /**
     * Destroy notification system
     */
    destroy() {
        this.clearAll();
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }
        this.notifications.clear();
        this.queue = [];
    }
}

// CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes notificationProgress {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    .notification:hover .notification-progress {
        animation-play-state: paused;
    }
    
    .notification-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    @media (max-width: 768px) {
        .notification-container {
            left: 10px !important;
            right: 10px !important;
            max-width: none;
        }
        
        .notification {
            min-width: auto !important;
            max-width: none !important;
        }
    }
`;
document.head.appendChild(style);

// Initialize global notification system
window.addEventListener('DOMContentLoaded', () => {
    window.notifications = new NotificationSystem();
    
    // Global notification functions
    window.notify = {
        success: (message, options) => window.notifications.success(message, options),
        error: (message, options) => window.notifications.error(message, options),
        warning: (message, options) => window.notifications.warning(message, options),
        info: (message, options) => window.notifications.info(message, options),
        clear: () => window.notifications.clearAll(),
        update: (id, message, type) => window.notifications.update(id, message, type)
    };
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationSystem;
}