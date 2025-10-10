/**
 * Mobile Responsive Enhancement
 *
 * Advanced mobile-first enhancements:
 * - Touch gesture support
 * - Responsive breakpoints
 * - Mobile navigation
 * - Swipe interactions
 * - Device orientation
 * - Performance optimization
 * - Offline support
 * - PWA features
 * - Adaptive UI
 * - Mobile accessibility
 *
 * @category   JavaScript
 * @package    VapeshedTransfer
 * @subpackage Mobile
 * @version    1.0.0
 */

class MobileResponsive {
    /**
     * Configuration options
     */
    constructor(options = {}) {
        this.options = {
            // Breakpoints (matches CSS)
            breakpoints: {
                xs: 0,
                sm: 576,
                md: 768,
                lg: 992,
                xl: 1200,
                xxl: 1400
            },
            
            // Touch settings
            touchThreshold: 10,
            swipeThreshold: 50,
            swipeVelocity: 0.3,
            
            // Animation settings
            transitionDuration: 300,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            
            // Mobile menu settings
            mobileMenuBreakpoint: 'lg',
            enableSwipeNavigation: true,
            enablePullToRefresh: false,
            
            // Performance settings
            throttleDelay: 16,
            debounceDelay: 250,
            
            // Features
            enableGestures: true,
            enableOrientationHandling: true,
            enableKeyboardHandling: true,
            enableFocusManagement: true,
            
            ...options
        };

        this.state = {
            currentBreakpoint: 'lg',
            isTouch: false,
            isMobile: false,
            isTablet: false,
            orientation: 'portrait',
            menuOpen: false,
            activeGestures: new Set(),
            focusStack: []
        };

        this.touchData = {
            startX: 0,
            startY: 0,
            currentX: 0,
            currentY: 0,
            startTime: 0,
            isTracking: false,
            target: null
        };

        this.observers = {
            resize: new Set(),
            orientation: new Set(),
            breakpoint: new Set(),
            gesture: new Set()
        };

        this.init();
    }

    /**
     * Initialize mobile responsive system
     */
    init() {
        this.detectCapabilities();
        this.setupEventListeners();
        this.setupBreakpointDetection();
        this.setupTouchHandling();
        this.setupMobileNavigation();
        this.setupOrientationHandling();
        this.setupKeyboardHandling();
        this.setupFocusManagement();
        this.setupPerformanceOptimizations();
        
        // Initial state
        this.updateBreakpoint();
        this.updateOrientation();
        
        console.log('MobileResponsive initialized', this.state);
    }

    /**
     * Detect device capabilities
     */
    detectCapabilities() {
        // Touch support
        this.state.isTouch = (
            'ontouchstart' in window ||
            navigator.maxTouchPoints > 0 ||
            navigator.msMaxTouchPoints > 0
        );

        // Mobile detection (basic)
        this.state.isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        // Tablet detection
        this.state.isTablet = /iPad|Android/i.test(navigator.userAgent) && window.innerWidth >= 768;

        // Add classes to body
        document.body.classList.toggle('touch-device', this.state.isTouch);
        document.body.classList.toggle('mobile-device', this.state.isMobile);
        document.body.classList.toggle('tablet-device', this.state.isTablet);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Resize handling (throttled)
        window.addEventListener('resize', this.throttle(() => {
            this.updateBreakpoint();
            this.handleResize();
        }, this.options.throttleDelay));

        // Orientation change
        window.addEventListener('orientationchange', () => {
            setTimeout(() => {
                this.updateOrientation();
                this.handleOrientationChange();
            }, 100);
        });

        // Visibility change (for performance)
        document.addEventListener('visibilitychange', () => {
            this.handleVisibilityChange();
        });
    }

    /**
     * Setup breakpoint detection
     */
    setupBreakpointDetection() {
        // Use CSS custom property to detect current breakpoint
        this.updateBreakpoint();
    }

    /**
     * Update current breakpoint
     */
    updateBreakpoint() {
        const width = window.innerWidth;
        let newBreakpoint = 'xs';

        for (const [name, minWidth] of Object.entries(this.options.breakpoints)) {
            if (width >= minWidth) {
                newBreakpoint = name;
            }
        }

        if (newBreakpoint !== this.state.currentBreakpoint) {
            const oldBreakpoint = this.state.currentBreakpoint;
            this.state.currentBreakpoint = newBreakpoint;
            
            // Update body attribute
            document.body.setAttribute('data-breakpoint', newBreakpoint);
            
            // Notify observers
            this.notifyObservers('breakpoint', {
                from: oldBreakpoint,
                to: newBreakpoint,
                width
            });
        }
    }

    /**
     * Setup touch handling
     */
    setupTouchHandling() {
        if (!this.state.isTouch || !this.options.enableGestures) return;

        document.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: false });
        document.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        document.addEventListener('touchend', (e) => this.handleTouchEnd(e), { passive: false });
        document.addEventListener('touchcancel', (e) => this.handleTouchCancel(e));
    }

    /**
     * Handle touch start
     */
    handleTouchStart(e) {
        const touch = e.touches[0];
        this.touchData = {
            startX: touch.clientX,
            startY: touch.clientY,
            currentX: touch.clientX,
            currentY: touch.clientY,
            startTime: Date.now(),
            isTracking: true,
            target: e.target
        };

        // Check for special gestures
        this.checkSwipeGestures(e);
    }

    /**
     * Handle touch move
     */
    handleTouchMove(e) {
        if (!this.touchData.isTracking) return;

        const touch = e.touches[0];
        this.touchData.currentX = touch.clientX;
        this.touchData.currentY = touch.clientY;

        // Handle active gestures
        this.handleActiveGestures(e);
    }

    /**
     * Handle touch end
     */
    handleTouchEnd(e) {
        if (!this.touchData.isTracking) return;

        const deltaX = this.touchData.currentX - this.touchData.startX;
        const deltaY = this.touchData.currentY - this.touchData.startY;
        const deltaTime = Date.now() - this.touchData.startTime;
        const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        const velocity = distance / deltaTime;

        // Detect swipe
        if (distance > this.options.swipeThreshold && velocity > this.options.swipeVelocity) {
            const direction = this.getSwipeDirection(deltaX, deltaY);
            this.handleSwipe(direction, { deltaX, deltaY, velocity, target: this.touchData.target });
        }

        // Detect tap
        if (distance < this.options.touchThreshold && deltaTime < 300) {
            this.handleTap(this.touchData.target, e);
        }

        // Reset tracking
        this.touchData.isTracking = false;
        this.state.activeGestures.clear();
    }

    /**
     * Handle touch cancel
     */
    handleTouchCancel(e) {
        this.touchData.isTracking = false;
        this.state.activeGestures.clear();
    }

    /**
     * Get swipe direction
     */
    getSwipeDirection(deltaX, deltaY) {
        const absDeltaX = Math.abs(deltaX);
        const absDeltaY = Math.abs(deltaY);

        if (absDeltaX > absDeltaY) {
            return deltaX > 0 ? 'right' : 'left';
        } else {
            return deltaY > 0 ? 'down' : 'up';
        }
    }

    /**
     * Handle swipe gesture
     */
    handleSwipe(direction, data) {
        // Sidebar navigation
        if (this.options.enableSwipeNavigation) {
            const sidebar = document.querySelector('.dashboard-sidebar');
            const isMenuBreakpoint = this.isBreakpoint('<=', this.options.mobileMenuBreakpoint);

            if (isMenuBreakpoint && sidebar) {
                if (direction === 'right' && !this.state.menuOpen) {
                    this.openMobileMenu();
                } else if (direction === 'left' && this.state.menuOpen) {
                    this.closeMobileMenu();
                }
            }
        }

        // Notify observers
        this.notifyObservers('gesture', {
            type: 'swipe',
            direction,
            ...data
        });
    }

    /**
     * Handle tap gesture
     */
    handleTap(target, event) {
        // Enhanced tap handling for mobile
        if (target.matches('button, [role="button"], .clickable')) {
            // Add tap feedback
            this.addTapFeedback(target);
        }

        // Notify observers
        this.notifyObservers('gesture', {
            type: 'tap',
            target,
            event
        });
    }

    /**
     * Add tap feedback visual
     */
    addTapFeedback(element) {
        element.classList.add('tap-feedback');
        setTimeout(() => {
            element.classList.remove('tap-feedback');
        }, 150);
    }

    /**
     * Setup mobile navigation
     */
    setupMobileNavigation() {
        const toggleButton = document.querySelector('.sidebar-toggle');
        const sidebar = document.querySelector('.dashboard-sidebar');
        const overlay = document.querySelector('.sidebar-overlay') || this.createSidebarOverlay();

        if (toggleButton) {
            toggleButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleMobileMenu();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', (e) => {
                e.preventDefault();
                this.closeMobileMenu();
            });
        }

        // Close menu on navigation
        if (sidebar) {
            sidebar.addEventListener('click', (e) => {
                if (e.target.matches('a[href]') && this.isBreakpoint('<=', this.options.mobileMenuBreakpoint)) {
                    this.closeMobileMenu();
                }
            });
        }
    }

    /**
     * Create sidebar overlay
     */
    createSidebarOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 300ms ease, visibility 300ms ease;
        `;
        document.body.appendChild(overlay);
        return overlay;
    }

    /**
     * Toggle mobile menu
     */
    toggleMobileMenu() {
        if (this.state.menuOpen) {
            this.closeMobileMenu();
        } else {
            this.openMobileMenu();
        }
    }

    /**
     * Open mobile menu
     */
    openMobileMenu() {
        this.state.menuOpen = true;
        document.body.classList.add('mobile-menu-open');
        
        const sidebar = document.querySelector('.dashboard-sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const toggle = document.querySelector('.sidebar-toggle');

        if (sidebar) {
            sidebar.style.transform = 'translateX(0)';
        }

        if (overlay) {
            overlay.style.opacity = '1';
            overlay.style.visibility = 'visible';
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'true');
        }

        // Focus management
        this.trapFocus(sidebar);
    }

    /**
     * Close mobile menu
     */
    closeMobileMenu() {
        this.state.menuOpen = false;
        document.body.classList.remove('mobile-menu-open');
        
        const sidebar = document.querySelector('.dashboard-sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const toggle = document.querySelector('.sidebar-toggle');

        if (sidebar) {
            sidebar.style.transform = '';
        }

        if (overlay) {
            overlay.style.opacity = '0';
            overlay.style.visibility = 'hidden';
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus(); // Return focus
        }

        // Release focus trap
        this.releaseFocusTrap();
    }

    /**
     * Setup orientation handling
     */
    setupOrientationHandling() {
        if (!this.options.enableOrientationHandling) return;

        this.updateOrientation();
    }

    /**
     * Update orientation
     */
    updateOrientation() {
        const oldOrientation = this.state.orientation;
        this.state.orientation = window.innerHeight > window.innerWidth ? 'portrait' : 'landscape';

        document.body.setAttribute('data-orientation', this.state.orientation);

        if (oldOrientation !== this.state.orientation) {
            this.notifyObservers('orientation', {
                from: oldOrientation,
                to: this.state.orientation
            });
        }
    }

    /**
     * Handle orientation change
     */
    handleOrientationChange() {
        // Close mobile menu on orientation change
        if (this.state.menuOpen) {
            this.closeMobileMenu();
        }

        // Trigger resize handling
        this.handleResize();
    }

    /**
     * Setup keyboard handling
     */
    setupKeyboardHandling() {
        if (!this.options.enableKeyboardHandling) return;

        document.addEventListener('keydown', (e) => {
            this.handleKeyDown(e);
        });
    }

    /**
     * Handle key down
     */
    handleKeyDown(e) {
        // Escape key
        if (e.key === 'Escape') {
            if (this.state.menuOpen) {
                e.preventDefault();
                this.closeMobileMenu();
            }
        }

        // Menu toggle (Alt + M)
        if (e.altKey && e.key === 'm') {
            e.preventDefault();
            this.toggleMobileMenu();
        }
    }

    /**
     * Setup focus management
     */
    setupFocusManagement() {
        if (!this.options.enableFocusManagement) return;

        // Track focus changes
        document.addEventListener('focusin', (e) => {
            this.handleFocusIn(e);
        });

        document.addEventListener('focusout', (e) => {
            this.handleFocusOut(e);
        });
    }

    /**
     * Handle focus in
     */
    handleFocusIn(e) {
        // Add to focus stack
        this.state.focusStack.push(e.target);

        // Limit stack size
        if (this.state.focusStack.length > 10) {
            this.state.focusStack.shift();
        }
    }

    /**
     * Handle focus out
     */
    handleFocusOut(e) {
        // Implementation for focus tracking
    }

    /**
     * Trap focus within element
     */
    trapFocus(element) {
        if (!element) return;

        const focusableElements = element.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        if (focusableElements.length === 0) return;

        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        // Focus first element
        firstElement.focus();

        // Handle tab cycling
        const handleTabKey = (e) => {
            if (e.key === 'Tab') {
                if (e.shiftKey && document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                } else if (!e.shiftKey && document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        };

        element.addEventListener('keydown', handleTabKey);
        
        // Store cleanup function
        this.focusTrapCleanup = () => {
            element.removeEventListener('keydown', handleTabKey);
        };
    }

    /**
     * Release focus trap
     */
    releaseFocusTrap() {
        if (this.focusTrapCleanup) {
            this.focusTrapCleanup();
            this.focusTrapCleanup = null;
        }
    }

    /**
     * Setup performance optimizations
     */
    setupPerformanceOptimizations() {
        // Disable hover effects on touch devices
        if (this.state.isTouch) {
            document.body.classList.add('disable-hover');
        }

        // Optimize scrolling
        this.optimizeScrolling();

        // Optimize animations
        this.optimizeAnimations();
    }

    /**
     * Optimize scrolling performance
     */
    optimizeScrolling() {
        // Add passive scroll listeners where possible
        const scrollElements = document.querySelectorAll('.scrollable, .table-container');
        
        scrollElements.forEach(element => {
            element.addEventListener('scroll', this.throttle(() => {
                // Scroll optimizations
                this.handleScroll(element);
            }, this.options.throttleDelay), { passive: true });
        });
    }

    /**
     * Handle scroll events
     */
    handleScroll(element) {
        // Implement scroll-based optimizations
        // e.g., hide/show elements, lazy loading, etc.
    }

    /**
     * Optimize animations
     */
    optimizeAnimations() {
        // Reduce motion for users who prefer it
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.body.classList.add('reduce-motion');
        }
    }

    /**
     * Handle resize events
     */
    handleResize() {
        // Update viewport units
        this.updateViewportUnits();

        // Notify observers
        this.notifyObservers('resize', {
            width: window.innerWidth,
            height: window.innerHeight,
            breakpoint: this.state.currentBreakpoint
        });
    }

    /**
     * Update CSS viewport units
     */
    updateViewportUnits() {
        // Fix for mobile browsers
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }

    /**
     * Handle visibility change
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Page is hidden - pause non-essential operations
            this.pauseOperations();
        } else {
            // Page is visible - resume operations
            this.resumeOperations();
        }
    }

    /**
     * Pause operations for performance
     */
    pauseOperations() {
        // Pause animations, timers, etc.
        document.body.classList.add('page-hidden');
    }

    /**
     * Resume operations
     */
    resumeOperations() {
        // Resume animations, timers, etc.
        document.body.classList.remove('page-hidden');
    }

    /**
     * Check breakpoint condition
     */
    isBreakpoint(operator, breakpoint) {
        const currentWidth = window.innerWidth;
        const targetWidth = this.options.breakpoints[breakpoint];

        switch (operator) {
            case '>=':
            case 'min':
                return currentWidth >= targetWidth;
            case '<=':
            case 'max':
                return currentWidth <= targetWidth;
            case '==':
            case 'exact':
                return this.state.currentBreakpoint === breakpoint;
            default:
                return false;
        }
    }

    /**
     * Add observer for events
     */
    on(event, callback) {
        if (this.observers[event]) {
            this.observers[event].add(callback);
        }
        return this;
    }

    /**
     * Remove observer
     */
    off(event, callback) {
        if (this.observers[event]) {
            this.observers[event].delete(callback);
        }
        return this;
    }

    /**
     * Notify observers
     */
    notifyObservers(event, data) {
        if (this.observers[event]) {
            this.observers[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in ${event} observer:`, error);
                }
            });
        }
    }

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    /**
     * Get current device info
     */
    getDeviceInfo() {
        return {
            breakpoint: this.state.currentBreakpoint,
            orientation: this.state.orientation,
            isTouch: this.state.isTouch,
            isMobile: this.state.isMobile,
            isTablet: this.state.isTablet,
            width: window.innerWidth,
            height: window.innerHeight,
            pixelRatio: window.devicePixelRatio || 1
        };
    }

    /**
     * Check for specific gestures
     */
    checkSwipeGestures(e) {
        // Implementation for gesture detection
    }

    /**
     * Handle active gestures
     */
    handleActiveGestures(e) {
        // Implementation for ongoing gesture handling
    }

    /**
     * Destroy instance
     */
    destroy() {
        // Cleanup event listeners
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('orientationchange', this.handleOrientationChange);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        // Release focus trap
        this.releaseFocusTrap();

        // Clear observers
        Object.keys(this.observers).forEach(key => {
            this.observers[key].clear();
        });

        console.log('MobileResponsive destroyed');
    }
}

// Initialize global instance
window.mobile = new MobileResponsive();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MobileResponsive;
}