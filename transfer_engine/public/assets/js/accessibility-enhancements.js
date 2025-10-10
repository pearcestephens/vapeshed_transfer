/**
 * Accessibility Enhancements
 *
 * Comprehensive accessibility features for WCAG 2.1 AA compliance:
 * - Screen reader support
 * - Keyboard navigation
 * - Focus management
 * - ARIA live regions
 * - High contrast mode
 * - Reduced motion support
 * - Text scaling
 * - Color blindness support
 * - Voice control integration
 * - Assistive technology compatibility
 *
 * @category   JavaScript
 * @package    VapeshedTransfer
 * @subpackage Accessibility
 * @version    1.0.0
 */

class AccessibilityEnhancements {
    /**
     * Configuration options
     */
    constructor(options = {}) {
        this.options = {
            // Feature toggles
            enableKeyboardNavigation: true,
            enableFocusManagement: true,
            enableScreenReaderSupport: true,
            enableAriaLiveRegions: true,
            enableSkipLinks: true,
            enableHighContrast: true,
            enableReducedMotion: true,
            enableTextScaling: true,
            
            // Focus settings
            focusVisibleClass: 'focus-visible',
            focusWithinClass: 'focus-within',
            skipLinkSelector: '.skip-link',
            
            // Keyboard settings
            trapFocusInModals: true,
            enableKeyboardShortcuts: true,
            
            // Screen reader settings
            ariaLivePolite: 'polite',
            ariaLiveAssertive: 'assertive',
            announcePageChanges: true,
            
            // Text scaling
            minTextScale: 0.8,
            maxTextScale: 2.0,
            textScaleStep: 0.1,
            
            // Animation settings
            respectReducedMotion: true,
            animationDuration: 300,
            
            ...options
        };

        this.state = {
            currentTextScale: 1.0,
            isHighContrast: false,
            isReducedMotion: false,
            activeElement: null,
            focusStack: [],
            liveRegions: new Map(),
            keyboardUsers: new Set(),
            shortcuts: new Map()
        };

        this.announcements = {
            queue: [],
            isProcessing: false,
            debounceTimeout: null
        };

        this.init();
    }

    /**
     * Initialize accessibility enhancements
     */
    init() {
        this.detectUserPreferences();
        this.setupAriaLiveRegions();
        this.setupKeyboardNavigation();
        this.setupFocusManagement();
        this.setupSkipLinks();
        this.setupScreenReaderSupport();
        this.setupTextScaling();
        this.setupReducedMotion();
        this.setupHighContrast();
        this.setupKeyboardShortcuts();
        this.setupColorBlindnessSupport();
        this.enhanceFormAccessibility();
        this.enhanceTableAccessibility();
        this.enhanceNavigationAccessibility();
        
        console.log('AccessibilityEnhancements initialized', this.state);
    }

    /**
     * Detect user accessibility preferences
     */
    detectUserPreferences() {
        // Check for reduced motion preference
        if (window.matchMedia) {
            const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
            this.state.isReducedMotion = reducedMotionQuery.matches;
            reducedMotionQuery.addListener((e) => {
                this.state.isReducedMotion = e.matches;
                this.updateReducedMotion();
            });

            // Check for high contrast preference
            const highContrastQuery = window.matchMedia('(prefers-contrast: high)');
            this.state.isHighContrast = highContrastQuery.matches;
            highContrastQuery.addListener((e) => {
                this.state.isHighContrast = e.matches;
                this.updateHighContrast();
            });
        }

        // Detect keyboard users
        document.addEventListener('keydown', () => {
            this.state.keyboardUsers.add('keyboard');
            document.body.classList.add('user-is-tabbing');
        }, { once: true });

        document.addEventListener('mousedown', () => {
            this.state.keyboardUsers.delete('keyboard');
            document.body.classList.remove('user-is-tabbing');
        });
    }

    /**
     * Setup ARIA live regions
     */
    setupAriaLiveRegions() {
        if (!this.options.enableAriaLiveRegions) return;

        // Create polite live region
        this.createLiveRegion('polite', this.options.ariaLivePolite);
        
        // Create assertive live region
        this.createLiveRegion('assertive', this.options.ariaLiveAssertive);

        // Create status region
        this.createLiveRegion('status', 'polite');
    }

    /**
     * Create ARIA live region
     */
    createLiveRegion(id, politeness) {
        let region = document.getElementById(`aria-live-${id}`);
        
        if (!region) {
            region = document.createElement('div');
            region.id = `aria-live-${id}`;
            region.setAttribute('aria-live', politeness);
            region.setAttribute('aria-atomic', 'true');
            region.style.cssText = `
                position: absolute !important;
                left: -10000px !important;
                width: 1px !important;
                height: 1px !important;
                overflow: hidden !important;
            `;
            document.body.appendChild(region);
        }

        this.state.liveRegions.set(id, region);
        return region;
    }

    /**
     * Announce to screen readers
     */
    announce(message, priority = 'polite', options = {}) {
        if (!this.options.enableScreenReaderSupport || !message) return;

        const announcement = {
            message: message.trim(),
            priority,
            timestamp: Date.now(),
            ...options
        };

        // Add to queue
        this.announcements.queue.push(announcement);

        // Process queue
        this.processAnnouncementQueue();
    }

    /**
     * Process announcement queue
     */
    processAnnouncementQueue() {
        if (this.announcements.isProcessing || this.announcements.queue.length === 0) {
            return;
        }

        this.announcements.isProcessing = true;

        // Clear any existing debounce
        if (this.announcements.debounceTimeout) {
            clearTimeout(this.announcements.debounceTimeout);
        }

        // Debounce announcements to avoid overwhelming screen readers
        this.announcements.debounceTimeout = setTimeout(() => {
            const announcement = this.announcements.queue.shift();
            if (announcement) {
                this.makeAnnouncement(announcement);
            }

            this.announcements.isProcessing = false;

            // Process next announcement if queue is not empty
            if (this.announcements.queue.length > 0) {
                this.processAnnouncementQueue();
            }
        }, 100);
    }

    /**
     * Make announcement to live region
     */
    makeAnnouncement(announcement) {
        const regionId = announcement.priority === 'assertive' ? 'assertive' : 'polite';
        const region = this.state.liveRegions.get(regionId);
        
        if (region) {
            // Clear existing content
            region.textContent = '';
            
            // Set new content
            setTimeout(() => {
                region.textContent = announcement.message;
            }, 10);

            // Clear after announcement
            setTimeout(() => {
                if (region.textContent === announcement.message) {
                    region.textContent = '';
                }
            }, 1000);
        }
    }

    /**
     * Setup keyboard navigation
     */
    setupKeyboardNavigation() {
        if (!this.options.enableKeyboardNavigation) return;

        document.addEventListener('keydown', (e) => {
            this.handleKeyboardNavigation(e);
        });

        // Enhance focus visibility
        document.addEventListener('focusin', (e) => {
            this.handleFocusIn(e);
        });

        document.addEventListener('focusout', (e) => {
            this.handleFocusOut(e);
        });
    }

    /**
     * Handle keyboard navigation
     */
    handleKeyboardNavigation(e) {
        const { key, ctrlKey, shiftKey, altKey } = e;

        // Arrow key navigation for custom components
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key)) {
            this.handleArrowKeyNavigation(e);
        }

        // Tab navigation enhancements
        if (key === 'Tab') {
            this.handleTabNavigation(e);
        }

        // Enter/Space activation
        if (key === 'Enter' || key === ' ') {
            this.handleActivationKeys(e);
        }

        // Escape key
        if (key === 'Escape') {
            this.handleEscapeKey(e);
        }
    }

    /**
     * Handle arrow key navigation
     */
    handleArrowKeyNavigation(e) {
        const target = e.target;
        
        // Tab navigation
        if (target.closest('[role="tablist"]')) {
            this.handleTabListNavigation(e);
        }
        
        // Menu navigation
        if (target.closest('[role="menu"], [role="menubar"]')) {
            this.handleMenuNavigation(e);
        }
        
        // Grid navigation
        if (target.closest('[role="grid"]')) {
            this.handleGridNavigation(e);
        }
    }

    /**
     * Handle tab list navigation
     */
    handleTabListNavigation(e) {
        const tablist = e.target.closest('[role="tablist"]');
        const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
        const currentIndex = tabs.indexOf(e.target);
        
        let newIndex;
        
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            newIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
        } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            newIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
        }
        
        if (newIndex !== undefined) {
            e.preventDefault();
            tabs[newIndex].focus();
            
            // Activate tab if needed
            if (tablist.getAttribute('data-activate-on-focus') === 'true') {
                tabs[newIndex].click();
            }
        }
    }

    /**
     * Handle menu navigation
     */
    handleMenuNavigation(e) {
        const menu = e.target.closest('[role="menu"], [role="menubar"]');
        const menuItems = Array.from(menu.querySelectorAll('[role="menuitem"], [role="menuitemcheckbox"], [role="menuitemradio"]'));
        const currentIndex = menuItems.indexOf(e.target);
        
        let newIndex;
        
        if (e.key === 'ArrowUp') {
            newIndex = currentIndex > 0 ? currentIndex - 1 : menuItems.length - 1;
        } else if (e.key === 'ArrowDown') {
            newIndex = currentIndex < menuItems.length - 1 ? currentIndex + 1 : 0;
        }
        
        if (newIndex !== undefined) {
            e.preventDefault();
            menuItems[newIndex].focus();
        }
    }

    /**
     * Handle grid navigation
     */
    handleGridNavigation(e) {
        const grid = e.target.closest('[role="grid"]');
        const currentCell = e.target.closest('[role="gridcell"]');
        
        if (!currentCell) return;
        
        const row = currentCell.closest('[role="row"]');
        const rows = Array.from(grid.querySelectorAll('[role="row"]'));
        const cells = Array.from(row.querySelectorAll('[role="gridcell"]'));
        
        const rowIndex = rows.indexOf(row);
        const cellIndex = cells.indexOf(currentCell);
        
        let newCell;
        
        if (e.key === 'ArrowLeft' && cellIndex > 0) {
            newCell = cells[cellIndex - 1];
        } else if (e.key === 'ArrowRight' && cellIndex < cells.length - 1) {
            newCell = cells[cellIndex + 1];
        } else if (e.key === 'ArrowUp' && rowIndex > 0) {
            const prevRow = rows[rowIndex - 1];
            const prevRowCells = prevRow.querySelectorAll('[role="gridcell"]');
            newCell = prevRowCells[Math.min(cellIndex, prevRowCells.length - 1)];
        } else if (e.key === 'ArrowDown' && rowIndex < rows.length - 1) {
            const nextRow = rows[rowIndex + 1];
            const nextRowCells = nextRow.querySelectorAll('[role="gridcell"]');
            newCell = nextRowCells[Math.min(cellIndex, nextRowCells.length - 1)];
        }
        
        if (newCell) {
            e.preventDefault();
            newCell.focus();
        }
    }

    /**
     * Handle tab navigation
     */
    handleTabNavigation(e) {
        // Trap focus in modals
        if (this.options.trapFocusInModals) {
            const modal = document.querySelector('.modal:not([style*="display: none"])');
            if (modal) {
                this.trapFocusInElement(modal, e);
            }
        }
    }

    /**
     * Trap focus within element
     */
    trapFocusInElement(element, e) {
        const focusableElements = this.getFocusableElements(element);
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey && document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
        } else if (!e.shiftKey && document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
        }
    }

    /**
     * Get focusable elements
     */
    getFocusableElements(container) {
        const selector = [
            'button:not([disabled])',
            '[href]',
            'input:not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"]):not([disabled])',
            '[contenteditable="true"]'
        ].join(', ');
        
        return Array.from(container.querySelectorAll(selector))
            .filter(el => {
                return el.offsetParent !== null && 
                       getComputedStyle(el).visibility !== 'hidden';
            });
    }

    /**
     * Handle activation keys
     */
    handleActivationKeys(e) {
        const target = e.target;
        
        // Handle custom interactive elements
        if (target.matches('[role="button"], [role="tab"], [role="menuitem"]') && 
            !target.matches('button, input, textarea, select, a[href]')) {
            
            e.preventDefault();
            target.click();
        }
        
        // Handle checkboxes and radio buttons
        if (target.matches('[role="checkbox"], [role="radio"]') && e.key === ' ') {
            e.preventDefault();
            this.toggleCheckable(target);
        }
    }

    /**
     * Toggle checkable element
     */
    toggleCheckable(element) {
        const isChecked = element.getAttribute('aria-checked') === 'true';
        const newState = !isChecked;
        
        element.setAttribute('aria-checked', newState.toString());
        
        // Announce state change
        const label = this.getAccessibleLabel(element);
        const state = newState ? 'checked' : 'unchecked';
        this.announce(`${label} ${state}`);
    }

    /**
     * Handle escape key
     */
    handleEscapeKey(e) {
        // Close modals
        const modal = document.querySelector('.modal:not([style*="display: none"])');
        if (modal) {
            const closeButton = modal.querySelector('[data-dismiss="modal"], .modal-close');
            if (closeButton) {
                closeButton.click();
            }
        }
        
        // Close dropdowns
        const dropdown = document.querySelector('.dropdown.show, .dropdown-menu.show');
        if (dropdown) {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            if (toggle) {
                toggle.click();
            }
        }
    }

    /**
     * Setup focus management
     */
    setupFocusManagement() {
        if (!this.options.enableFocusManagement) return;

        // Enhanced focus indicators
        this.setupFocusIndicators();
        
        // Focus restoration
        this.setupFocusRestoration();
    }

    /**
     * Setup focus indicators
     */
    setupFocusIndicators() {
        const style = document.createElement('style');
        style.textContent = `
            .${this.options.focusVisibleClass} {
                outline: 2px solid var(--theme-color-primary, #007bff) !important;
                outline-offset: 2px !important;
            }
            
            .${this.options.focusWithinClass} {
                box-shadow: 0 0 0 2px var(--theme-color-primary, #007bff) !important;
            }
            
            /* High contrast focus indicators */
            @media (prefers-contrast: high) {
                .${this.options.focusVisibleClass} {
                    outline: 3px solid currentColor !important;
                    outline-offset: 3px !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Handle focus in
     */
    handleFocusIn(e) {
        const target = e.target;
        
        // Add focus-visible class for keyboard users
        if (this.state.keyboardUsers.has('keyboard')) {
            target.classList.add(this.options.focusVisibleClass);
        }
        
        // Add focus-within to parent containers
        let parent = target.parentElement;
        while (parent && parent !== document.body) {
            if (parent.matches('.form-group, .input-group, .card')) {
                parent.classList.add(this.options.focusWithinClass);
            }
            parent = parent.parentElement;
        }
        
        // Update focus stack
        this.state.focusStack.push(target);
        if (this.state.focusStack.length > 10) {
            this.state.focusStack.shift();
        }
        
        // Announce focus changes for screen readers
        if (this.shouldAnnounceFocus(target)) {
            const label = this.getAccessibleLabel(target);
            if (label) {
                this.announce(`Focused on ${label}`, 'polite');
            }
        }
    }

    /**
     * Handle focus out
     */
    handleFocusOut(e) {
        const target = e.target;
        
        // Remove focus classes
        target.classList.remove(this.options.focusVisibleClass);
        
        // Remove focus-within from parent containers
        let parent = target.parentElement;
        while (parent && parent !== document.body) {
            parent.classList.remove(this.options.focusWithinClass);
            parent = parent.parentElement;
        }
    }

    /**
     * Should announce focus change
     */
    shouldAnnounceFocus(element) {
        // Don't announce for input fields during typing
        if (element.matches('input, textarea, select')) {
            return false;
        }
        
        // Announce for interactive elements
        return element.matches('button, [role="button"], [role="tab"], [role="menuitem"]');
    }

    /**
     * Get accessible label for element
     */
    getAccessibleLabel(element) {
        // Check aria-label
        let label = element.getAttribute('aria-label');
        if (label) return label;
        
        // Check aria-labelledby
        const labelledBy = element.getAttribute('aria-labelledby');
        if (labelledBy) {
            const labelElement = document.getElementById(labelledBy);
            if (labelElement) {
                return labelElement.textContent.trim();
            }
        }
        
        // Check associated label
        if (element.id) {
            const labelElement = document.querySelector(`label[for="${element.id}"]`);
            if (labelElement) {
                return labelElement.textContent.trim();
            }
        }
        
        // Check text content
        const textContent = element.textContent.trim();
        if (textContent) {
            return textContent;
        }
        
        // Check title
        const title = element.getAttribute('title');
        if (title) return title;
        
        // Check placeholder
        const placeholder = element.getAttribute('placeholder');
        if (placeholder) return placeholder;
        
        return 'unlabeled element';
    }

    /**
     * Setup focus restoration
     */
    setupFocusRestoration() {
        // Store focus before page unload
        window.addEventListener('beforeunload', () => {
            if (document.activeElement && document.activeElement.id) {
                sessionStorage.setItem('focusedElementId', document.activeElement.id);
            }
        });
        
        // Restore focus on page load
        window.addEventListener('load', () => {
            const focusedElementId = sessionStorage.getItem('focusedElementId');
            if (focusedElementId) {
                const element = document.getElementById(focusedElementId);
                if (element) {
                    element.focus();
                }
                sessionStorage.removeItem('focusedElementId');
            }
        });
    }

    /**
     * Setup skip links
     */
    setupSkipLinks() {
        if (!this.options.enableSkipLinks) return;

        // Ensure skip links exist
        this.createSkipLinks();
        
        // Enhance skip link functionality
        document.addEventListener('click', (e) => {
            if (e.target.matches(this.options.skipLinkSelector)) {
                e.preventDefault();
                const targetId = e.target.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                
                if (target) {
                    target.focus();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    this.announce(`Skipped to ${this.getAccessibleLabel(target)}`);
                }
            }
        });
    }

    /**
     * Create skip links if they don't exist
     */
    createSkipLinks() {
        if (document.querySelector(this.options.skipLinkSelector)) return;

        const skipNav = document.createElement('nav');
        skipNav.className = 'skip-links';
        skipNav.setAttribute('aria-label', 'Skip navigation');
        
        const skipList = document.createElement('ul');
        
        const skipItems = [
            { href: '#main-content', text: 'Skip to main content' },
            { href: '#primary-navigation', text: 'Skip to navigation' },
            { href: '#search', text: 'Skip to search' }
        ];
        
        skipItems.forEach(item => {
            const target = document.querySelector(item.href);
            if (target) {
                const li = document.createElement('li');
                const link = document.createElement('a');
                link.href = item.href;
                link.textContent = item.text;
                link.className = 'skip-link';
                li.appendChild(link);
                skipList.appendChild(li);
            }
        });
        
        skipNav.appendChild(skipList);
        document.body.insertBefore(skipNav, document.body.firstChild);

        // Add skip links styles
        const style = document.createElement('style');
        style.textContent = `
            .skip-links {
                position: absolute;
                top: -100px;
                left: 0;
                z-index: 9999;
            }
            
            .skip-link {
                position: absolute;
                top: -100px;
                left: 0;
                padding: 8px 16px;
                background: var(--theme-color-primary, #007bff);
                color: white;
                text-decoration: none;
                border-radius: 0 0 4px 0;
                transition: top 0.3s ease;
            }
            
            .skip-link:focus {
                top: 0;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Setup screen reader support
     */
    setupScreenReaderSupport() {
        if (!this.options.enableScreenReaderSupport) return;

        // Announce page changes
        if (this.options.announcePageChanges) {
            this.announcePageLoad();
        }
        
        // Enhance dynamic content announcements
        this.setupDynamicContentAnnouncements();
    }

    /**
     * Announce page load
     */
    announcePageLoad() {
        window.addEventListener('load', () => {
            const title = document.title;
            const main = document.querySelector('main, #main-content, .main-content');
            const landmarks = document.querySelectorAll('nav, main, aside, section[aria-label]').length;
            
            this.announce(`Page loaded: ${title}. ${landmarks} landmarks available.`, 'polite');
        });
    }

    /**
     * Setup dynamic content announcements
     */
    setupDynamicContentAnnouncements() {
        // Watch for content changes
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    this.handleContentChange(mutation);
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Handle content changes
     */
    handleContentChange(mutation) {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                // Announce new alerts or errors
                if (node.matches('.alert, .error, .success, .warning')) {
                    const message = node.textContent.trim();
                    if (message) {
                        this.announce(message, 'assertive');
                    }
                }
                
                // Announce new content in live regions
                if (node.matches('[aria-live]')) {
                    const message = node.textContent.trim();
                    if (message) {
                        const priority = node.getAttribute('aria-live');
                        this.announce(message, priority);
                    }
                }
            }
        });
    }

    /**
     * Setup text scaling
     */
    setupTextScaling() {
        if (!this.options.enableTextScaling) return;

        // Load saved text scale
        const savedScale = localStorage.getItem('textScale');
        if (savedScale) {
            this.state.currentTextScale = parseFloat(savedScale);
            this.applyTextScale(this.state.currentTextScale);
        }
    }

    /**
     * Increase text size
     */
    increaseTextSize() {
        const newScale = Math.min(
            this.state.currentTextScale + this.options.textScaleStep,
            this.options.maxTextScale
        );
        this.setTextScale(newScale);
    }

    /**
     * Decrease text size
     */
    decreaseTextSize() {
        const newScale = Math.max(
            this.state.currentTextScale - this.options.textScaleStep,
            this.options.minTextScale
        );
        this.setTextScale(newScale);
    }

    /**
     * Reset text size
     */
    resetTextSize() {
        this.setTextScale(1.0);
    }

    /**
     * Set text scale
     */
    setTextScale(scale) {
        this.state.currentTextScale = scale;
        this.applyTextScale(scale);
        localStorage.setItem('textScale', scale.toString());
        
        this.announce(`Text size set to ${Math.round(scale * 100)}%`);
    }

    /**
     * Apply text scale
     */
    applyTextScale(scale) {
        document.documentElement.style.setProperty('--text-scale', scale.toString());
        document.body.style.fontSize = `${scale}rem`;
    }

    /**
     * Setup reduced motion
     */
    setupReducedMotion() {
        this.updateReducedMotion();
    }

    /**
     * Update reduced motion setting
     */
    updateReducedMotion() {
        document.body.classList.toggle('reduce-motion', this.state.isReducedMotion);
        
        if (this.state.isReducedMotion) {
            // Disable animations
            const style = document.createElement('style');
            style.id = 'reduce-motion-styles';
            style.textContent = `
                *, *::before, *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                    scroll-behavior: auto !important;
                }
            `;
            document.head.appendChild(style);
        } else {
            // Remove reduced motion styles
            const style = document.getElementById('reduce-motion-styles');
            if (style) {
                style.remove();
            }
        }
    }

    /**
     * Setup high contrast
     */
    setupHighContrast() {
        this.updateHighContrast();
    }

    /**
     * Update high contrast setting
     */
    updateHighContrast() {
        document.body.classList.toggle('high-contrast', this.state.isHighContrast);
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        if (!this.options.enableKeyboardShortcuts) return;

        // Register default shortcuts
        this.registerShortcut('Alt+1', () => {
            const main = document.querySelector('main, #main-content');
            if (main) main.focus();
        }, 'Skip to main content');

        this.registerShortcut('Alt+2', () => {
            const nav = document.querySelector('nav, #navigation');
            if (nav) nav.focus();
        }, 'Skip to navigation');

        this.registerShortcut('Alt+=', () => {
            this.increaseTextSize();
        }, 'Increase text size');

        this.registerShortcut('Alt+-', () => {
            this.decreaseTextSize();
        }, 'Decrease text size');

        this.registerShortcut('Alt+0', () => {
            this.resetTextSize();
        }, 'Reset text size');

        // Listen for shortcuts
        document.addEventListener('keydown', (e) => {
            const shortcut = this.getShortcutString(e);
            const handler = this.state.shortcuts.get(shortcut);
            
            if (handler) {
                e.preventDefault();
                handler.callback();
                this.announce(`Activated: ${handler.description}`);
            }
        });
    }

    /**
     * Register keyboard shortcut
     */
    registerShortcut(keys, callback, description) {
        this.state.shortcuts.set(keys, { callback, description });
    }

    /**
     * Get shortcut string from event
     */
    getShortcutString(e) {
        const parts = [];
        
        if (e.ctrlKey) parts.push('Ctrl');
        if (e.altKey) parts.push('Alt');
        if (e.shiftKey) parts.push('Shift');
        if (e.metaKey) parts.push('Meta');
        
        if (e.key && e.key !== 'Control' && e.key !== 'Alt' && e.key !== 'Shift' && e.key !== 'Meta') {
            parts.push(e.key);
        }
        
        return parts.join('+');
    }

    /**
     * Setup color blindness support
     */
    setupColorBlindnessSupport() {
        // Add patterns and textures for color-coded information
        this.enhanceColorInformation();
    }

    /**
     * Enhance color information
     */
    enhanceColorInformation() {
        // Add text labels to color-only indicators
        const colorIndicators = document.querySelectorAll('.status-indicator, .badge, .alert');
        
        colorIndicators.forEach(indicator => {
            if (!indicator.textContent.trim()) {
                const classes = Array.from(indicator.classList);
                const statusClass = classes.find(cls => 
                    ['success', 'error', 'warning', 'info', 'danger'].some(status => cls.includes(status))
                );
                
                if (statusClass) {
                    const status = statusClass.replace(/.*-(success|error|warning|info|danger).*/, '$1');
                    const span = document.createElement('span');
                    span.className = 'sr-only';
                    span.textContent = status;
                    indicator.appendChild(span);
                }
            }
        });
    }

    /**
     * Enhance form accessibility
     */
    enhanceFormAccessibility() {
        // Add required field indicators
        document.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
            if (!field.getAttribute('aria-required')) {
                field.setAttribute('aria-required', 'true');
            }
            
            const label = document.querySelector(`label[for="${field.id}"]`);
            if (label && !label.querySelector('.required-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'required-indicator';
                indicator.textContent = ' *';
                indicator.setAttribute('aria-label', 'required');
                label.appendChild(indicator);
            }
        });

        // Enhance error messages
        document.querySelectorAll('.error-message, .invalid-feedback').forEach(error => {
            if (!error.id) {
                error.id = `error-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            }
            
            error.setAttribute('role', 'alert');
            error.setAttribute('aria-live', 'assertive');
        });
    }

    /**
     * Enhance table accessibility
     */
    enhanceTableAccessibility() {
        document.querySelectorAll('table').forEach(table => {
            // Add role if missing
            if (!table.getAttribute('role')) {
                table.setAttribute('role', 'table');
            }
            
            // Enhance headers
            table.querySelectorAll('th').forEach(th => {
                if (!th.getAttribute('scope')) {
                    th.setAttribute('scope', 'col');
                }
            });
            
            // Add table summary if missing
            if (!table.querySelector('caption') && !table.getAttribute('aria-label')) {
                const rows = table.querySelectorAll('tbody tr').length;
                const cols = table.querySelectorAll('thead th').length;
                table.setAttribute('aria-label', `Data table with ${rows} rows and ${cols} columns`);
            }
        });
    }

    /**
     * Enhance navigation accessibility
     */
    enhanceNavigationAccessibility() {
        // Add navigation landmarks
        document.querySelectorAll('nav').forEach(nav => {
            if (!nav.getAttribute('aria-label') && !nav.getAttribute('aria-labelledby')) {
                nav.setAttribute('aria-label', 'Navigation');
            }
        });

        // Enhance breadcrumbs
        const breadcrumb = document.querySelector('.breadcrumb, [aria-label*="breadcrumb"]');
        if (breadcrumb) {
            breadcrumb.setAttribute('aria-label', 'Breadcrumb navigation');
            
            const items = breadcrumb.querySelectorAll('a, span');
            items.forEach((item, index) => {
                if (index === items.length - 1) {
                    item.setAttribute('aria-current', 'page');
                }
            });
        }
    }

    /**
     * Get accessibility information
     */
    getAccessibilityInfo() {
        return {
            textScale: this.state.currentTextScale,
            isHighContrast: this.state.isHighContrast,
            isReducedMotion: this.state.isReducedMotion,
            keyboardUsers: Array.from(this.state.keyboardUsers),
            shortcuts: Array.from(this.state.shortcuts.keys()),
            liveRegions: Array.from(this.state.liveRegions.keys())
        };
    }

    /**
     * Cleanup
     */
    destroy() {
        // Clear shortcuts
        this.state.shortcuts.clear();
        
        // Clear live regions
        this.state.liveRegions.forEach(region => {
            if (region.parentNode) {
                region.parentNode.removeChild(region);
            }
        });
        this.state.liveRegions.clear();
        
        // Clear announcement queue
        this.announcements.queue = [];
        if (this.announcements.debounceTimeout) {
            clearTimeout(this.announcements.debounceTimeout);
        }

        console.log('AccessibilityEnhancements destroyed');
    }
}

// Initialize global instance
window.accessibility = new AccessibilityEnhancements();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AccessibilityEnhancements;
}