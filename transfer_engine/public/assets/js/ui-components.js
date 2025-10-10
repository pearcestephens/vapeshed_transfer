/**
 * UI Components Library
 *
 * Advanced JavaScript components for:
 * - Modal system
 * - Tab management
 * - Dropdown menus
 * - Data tables
 * - Form validation
 * - Chart components
 * - Loading states
 * - Theme management
 * - Responsive utilities
 * - Event handling
 *
 * @category   Frontend
 * @package    VapeshedTransfer
 * @subpackage JavaScript
 * @version    1.0.0
 */

class UIComponents {
    constructor() {
        this.modals = new Map();
        this.tabs = new Map();
        this.dropdowns = new Map();
        this.tables = new Map();
        this.forms = new Map();
        this.theme = localStorage.getItem('theme') || 'light';
        
        this.init();
    }

    /**
     * Initialize all UI components
     */
    init() {
        this.initTheme();
        this.initModals();
        this.initTabs();
        this.initDropdowns();
        this.initTables();
        this.initForms();
        this.initResponsive();
        this.bindEvents();
    }

    /**
     * Theme Management
     */
    initTheme() {
        document.documentElement.setAttribute('data-theme', this.theme);
        
        // Theme toggle button
        const themeToggle = document.querySelector('.theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => this.toggleTheme());
        }
    }

    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', this.theme);
        localStorage.setItem('theme', this.theme);
        
        // Dispatch theme change event
        window.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: this.theme }
        }));
    }

    /**
     * Modal System
     */
    initModals() {
        document.querySelectorAll('[data-modal-trigger]').forEach(trigger => {
            const modalId = trigger.getAttribute('data-modal-trigger');
            const modal = document.getElementById(modalId);
            
            if (modal) {
                this.modals.set(modalId, new Modal(modal));
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.openModal(modalId);
                });
            }
        });
    }

    openModal(modalId) {
        const modal = this.modals.get(modalId);
        if (modal) {
            modal.open();
        }
    }

    closeModal(modalId) {
        const modal = this.modals.get(modalId);
        if (modal) {
            modal.close();
        }
    }

    /**
     * Tab Management
     */
    initTabs() {
        document.querySelectorAll('.tab-container').forEach(container => {
            const tabId = container.id || `tab-${Date.now()}`;
            this.tabs.set(tabId, new TabManager(container));
        });
    }

    /**
     * Dropdown Menus
     */
    initDropdowns() {
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const dropdownId = dropdown.id || `dropdown-${Date.now()}`;
            this.dropdowns.set(dropdownId, new Dropdown(dropdown));
        });
    }

    /**
     * Enhanced Data Tables
     */
    initTables() {
        document.querySelectorAll('.data-table').forEach(table => {
            const tableId = table.id || `table-${Date.now()}`;
            this.tables.set(tableId, new DataTable(table));
        });
    }

    /**
     * Form Validation & Enhancement
     */
    initForms() {
        document.querySelectorAll('form[data-validate]').forEach(form => {
            const formId = form.id || `form-${Date.now()}`;
            this.forms.set(formId, new FormValidator(form));
        });
    }

    /**
     * Responsive Utilities
     */
    initResponsive() {
        this.responsive = new ResponsiveManager();
    }

    /**
     * Global Event Binding
     */
    bindEvents() {
        // Close modals on outside click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-backdrop')) {
                const modal = e.target.closest('.modal');
                if (modal) {
                    this.closeModal(modal.id);
                }
            }
        });

        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                this.dropdowns.forEach(dropdown => dropdown.close());
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                // Close open modals
                this.modals.forEach(modal => modal.close());
                // Close open dropdowns
                this.dropdowns.forEach(dropdown => dropdown.close());
            }
        });
    }

    /**
     * Utility Methods
     */
    showLoading(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.add('loading');
            element.innerHTML = '<div class="loading-spinner"></div>';
        }
    }

    hideLoading(element, originalContent = '') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.classList.remove('loading');
            element.innerHTML = originalContent;
        }
    }

    fadeIn(element, duration = 300) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.style.opacity = '0';
            element.style.display = 'block';
            
            const start = performance.now();
            const animate = (timestamp) => {
                const progress = (timestamp - start) / duration;
                element.style.opacity = Math.min(progress, 1);
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            requestAnimationFrame(animate);
        }
    }

    fadeOut(element, duration = 300) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            const start = performance.now();
            const initialOpacity = parseFloat(getComputedStyle(element).opacity) || 1;
            
            const animate = (timestamp) => {
                const progress = (timestamp - start) / duration;
                element.style.opacity = initialOpacity * (1 - Math.min(progress, 1));
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    element.style.display = 'none';
                }
            };
            requestAnimationFrame(animate);
        }
    }
}

/**
 * Modal Component
 */
class Modal {
    constructor(element) {
        this.element = element;
        this.backdrop = null;
        this.isOpen = false;
        this.init();
    }

    init() {
        // Close button
        const closeBtn = this.element.querySelector('.modal-close, [data-modal-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }
    }

    open() {
        if (this.isOpen) return;

        this.isOpen = true;
        document.body.style.overflow = 'hidden';
        
        // Create backdrop
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';
        this.backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: var(--z-modal-backdrop);
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        document.body.appendChild(this.backdrop);
        
        // Show modal
        this.element.style.display = 'block';
        this.element.style.zIndex = 'var(--z-modal)';
        
        // Animate in
        requestAnimationFrame(() => {
            this.backdrop.style.opacity = '1';
            this.element.classList.add('fade-in');
        });
        
        // Dispatch event
        this.element.dispatchEvent(new CustomEvent('modal:opened'));
    }

    close() {
        if (!this.isOpen) return;

        this.isOpen = false;
        document.body.style.overflow = '';
        
        // Animate out
        this.backdrop.style.opacity = '0';
        this.element.style.opacity = '0';
        
        setTimeout(() => {
            this.element.style.display = 'none';
            this.element.style.opacity = '';
            this.element.classList.remove('fade-in');
            
            if (this.backdrop) {
                this.backdrop.remove();
                this.backdrop = null;
            }
        }, 300);
        
        // Dispatch event
        this.element.dispatchEvent(new CustomEvent('modal:closed'));
    }
}

/**
 * Tab Manager Component
 */
class TabManager {
    constructor(container) {
        this.container = container;
        this.tabs = container.querySelectorAll('.tab-nav button');
        this.panels = container.querySelectorAll('.tab-panel');
        this.activeTab = 0;
        
        this.init();
    }

    init() {
        this.tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => this.switchTab(index));
        });
        
        // Set initial active tab
        this.switchTab(0);
    }

    switchTab(index) {
        // Remove active class from all tabs and panels
        this.tabs.forEach(tab => tab.classList.remove('active'));
        this.panels.forEach(panel => panel.classList.remove('active'));
        
        // Add active class to selected tab and panel
        this.tabs[index].classList.add('active');
        this.panels[index].classList.add('active');
        
        this.activeTab = index;
        
        // Dispatch event
        this.container.dispatchEvent(new CustomEvent('tab:changed', {
            detail: { index, tab: this.tabs[index] }
        }));
    }
}

/**
 * Dropdown Component
 */
class Dropdown {
    constructor(element) {
        this.element = element;
        this.trigger = element.querySelector('.dropdown-trigger');
        this.menu = element.querySelector('.dropdown-menu');
        this.isOpen = false;
        
        this.init();
    }

    init() {
        if (this.trigger) {
            this.trigger.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggle();
            });
        }
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        if (this.isOpen) return;
        
        this.isOpen = true;
        this.element.classList.add('open');
        this.menu.style.display = 'block';
        
        // Position menu
        this.positionMenu();
        
        // Animate in
        requestAnimationFrame(() => {
            this.menu.style.opacity = '1';
            this.menu.style.transform = 'translateY(0)';
        });
    }

    close() {
        if (!this.isOpen) return;
        
        this.isOpen = false;
        this.element.classList.remove('open');
        this.menu.style.opacity = '0';
        this.menu.style.transform = 'translateY(-10px)';
        
        setTimeout(() => {
            this.menu.style.display = 'none';
        }, 200);
    }

    positionMenu() {
        const triggerRect = this.trigger.getBoundingClientRect();
        const menuRect = this.menu.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        
        // Reset position
        this.menu.style.top = '100%';
        this.menu.style.bottom = 'auto';
        
        // Check if menu would go below viewport
        if (triggerRect.bottom + menuRect.height > viewportHeight) {
            this.menu.style.top = 'auto';
            this.menu.style.bottom = '100%';
        }
    }
}

/**
 * Enhanced Data Table Component
 */
class DataTable {
    constructor(table) {
        this.table = table;
        this.tbody = table.querySelector('tbody');
        this.thead = table.querySelector('thead');
        this.rows = Array.from(this.tbody.querySelectorAll('tr'));
        this.sortColumn = null;
        this.sortDirection = 'asc';
        this.currentPage = 1;
        this.rowsPerPage = 25;
        
        this.init();
    }

    init() {
        this.addSorting();
        this.addPagination();
        this.addSearch();
    }

    addSorting() {
        if (!this.thead) return;
        
        this.thead.querySelectorAll('th[data-sort]').forEach((th, index) => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => this.sort(index));
            
            // Add sort indicator
            const indicator = document.createElement('span');
            indicator.className = 'sort-indicator';
            indicator.innerHTML = '↕️';
            th.appendChild(indicator);
        });
    }

    sort(columnIndex) {
        const column = this.thead.querySelectorAll('th')[columnIndex];
        const dataType = column.getAttribute('data-sort');
        
        if (this.sortColumn === columnIndex) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortDirection = 'asc';
            this.sortColumn = columnIndex;
        }
        
        this.rows.sort((a, b) => {
            const aVal = a.cells[columnIndex].textContent.trim();
            const bVal = b.cells[columnIndex].textContent.trim();
            
            let comparison = 0;
            
            if (dataType === 'number') {
                comparison = parseFloat(aVal) - parseFloat(bVal);
            } else if (dataType === 'date') {
                comparison = new Date(aVal) - new Date(bVal);
            } else {
                comparison = aVal.localeCompare(bVal);
            }
            
            return this.sortDirection === 'asc' ? comparison : -comparison;
        });
        
        this.render();
        this.updateSortIndicators();
    }

    addPagination() {
        if (this.rows.length <= this.rowsPerPage) return;
        
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'table-pagination';
        this.table.parentNode.appendChild(paginationContainer);
        
        this.updatePagination();
    }

    addSearch() {
        const searchContainer = document.createElement('div');
        searchContainer.className = 'table-search';
        searchContainer.innerHTML = `
            <input type="text" placeholder="Search..." class="form-control-enhanced">
        `;
        
        this.table.parentNode.insertBefore(searchContainer, this.table);
        
        const searchInput = searchContainer.querySelector('input');
        searchInput.addEventListener('input', (e) => this.search(e.target.value));
    }

    search(query) {
        const filteredRows = this.rows.filter(row => {
            const text = row.textContent.toLowerCase();
            return text.includes(query.toLowerCase());
        });
        
        this.tbody.innerHTML = '';
        filteredRows.forEach(row => this.tbody.appendChild(row));
    }

    render() {
        this.tbody.innerHTML = '';
        
        const start = (this.currentPage - 1) * this.rowsPerPage;
        const end = start + this.rowsPerPage;
        const pageRows = this.rows.slice(start, end);
        
        pageRows.forEach(row => this.tbody.appendChild(row));
    }

    updateSortIndicators() {
        this.thead.querySelectorAll('.sort-indicator').forEach((indicator, index) => {
            if (index === this.sortColumn) {
                indicator.innerHTML = this.sortDirection === 'asc' ? '↑' : '↓';
            } else {
                indicator.innerHTML = '↕️';
            }
        });
    }

    updatePagination() {
        const totalPages = Math.ceil(this.rows.length / this.rowsPerPage);
        const paginationContainer = this.table.parentNode.querySelector('.table-pagination');
        
        if (!paginationContainer) return;
        
        let paginationHTML = '<div class="pagination">';
        
        // Previous button
        paginationHTML += `<button ${this.currentPage === 1 ? 'disabled' : ''} onclick="ui.tables.get('${this.table.id}').goToPage(${this.currentPage - 1})">‹</button>`;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === this.currentPage) {
                paginationHTML += `<button class="active">${i}</button>`;
            } else {
                paginationHTML += `<button onclick="ui.tables.get('${this.table.id}').goToPage(${i})">${i}</button>`;
            }
        }
        
        // Next button
        paginationHTML += `<button ${this.currentPage === totalPages ? 'disabled' : ''} onclick="ui.tables.get('${this.table.id}').goToPage(${this.currentPage + 1})">›</button>`;
        
        paginationHTML += '</div>';
        
        paginationContainer.innerHTML = paginationHTML;
    }

    goToPage(page) {
        const totalPages = Math.ceil(this.rows.length / this.rowsPerPage);
        if (page < 1 || page > totalPages) return;
        
        this.currentPage = page;
        this.render();
        this.updatePagination();
    }
}

/**
 * Form Validator Component
 */
class FormValidator {
    constructor(form) {
        this.form = form;
        this.rules = {};
        this.errors = {};
        
        this.init();
    }

    init() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
            }
        });
        
        // Real-time validation
        this.form.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('blur', () => this.validateField(field));
            field.addEventListener('input', () => this.clearFieldError(field));
        });
    }

    addRule(fieldName, rule, message) {
        if (!this.rules[fieldName]) {
            this.rules[fieldName] = [];
        }
        this.rules[fieldName].push({ rule, message });
    }

    validate() {
        this.errors = {};
        let isValid = true;
        
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field && !this.validateField(field)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    validateField(field) {
        const fieldName = field.name;
        const fieldRules = this.rules[fieldName] || [];
        const value = field.value.trim();
        
        this.clearFieldError(field);
        
        for (const { rule, message } of fieldRules) {
            if (!this.testRule(rule, value, field)) {
                this.setFieldError(field, message);
                return false;
            }
        }
        
        return true;
    }

    testRule(rule, value, field) {
        if (typeof rule === 'function') {
            return rule(value, field);
        }
        
        switch (rule) {
            case 'required':
                return value !== '';
            case 'email':
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            case 'url':
                return /^https?:\/\/.+/.test(value);
            case 'number':
                return !isNaN(value) && value !== '';
            default:
                if (rule instanceof RegExp) {
                    return rule.test(value);
                }
                return true;
        }
    }

    setFieldError(field, message) {
        field.classList.add('error');
        
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        this.errors[field.name] = message;
    }

    clearFieldError(field) {
        field.classList.remove('error');
        
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
        
        delete this.errors[field.name];
    }
}

/**
 * Responsive Manager
 */
class ResponsiveManager {
    constructor() {
        this.breakpoints = {
            xs: 0,
            sm: 576,
            md: 768,
            lg: 992,
            xl: 1200,
            xxl: 1400
        };
        
        this.current = this.getCurrentBreakpoint();
        this.init();
    }

    init() {
        window.addEventListener('resize', () => {
            const newBreakpoint = this.getCurrentBreakpoint();
            if (newBreakpoint !== this.current) {
                this.current = newBreakpoint;
                this.onBreakpointChange();
            }
        });
    }

    getCurrentBreakpoint() {
        const width = window.innerWidth;
        
        if (width >= this.breakpoints.xxl) return 'xxl';
        if (width >= this.breakpoints.xl) return 'xl';
        if (width >= this.breakpoints.lg) return 'lg';
        if (width >= this.breakpoints.md) return 'md';
        if (width >= this.breakpoints.sm) return 'sm';
        return 'xs';
    }

    onBreakpointChange() {
        document.body.setAttribute('data-breakpoint', this.current);
        
        window.dispatchEvent(new CustomEvent('breakpointChanged', {
            detail: { breakpoint: this.current, width: window.innerWidth }
        }));
    }

    isMobile() {
        return ['xs', 'sm'].includes(this.current);
    }

    isTablet() {
        return this.current === 'md';
    }

    isDesktop() {
        return ['lg', 'xl', 'xxl'].includes(this.current);
    }
}

// Initialize UI Components when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ui = new UIComponents();
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { UIComponents, Modal, TabManager, Dropdown, DataTable, FormValidator, ResponsiveManager };
}