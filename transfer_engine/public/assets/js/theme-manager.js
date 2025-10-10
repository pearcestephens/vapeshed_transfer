/**
 * Advanced Theme Manager
 *
 * Comprehensive theming system with:
 * - Light/dark mode switching
 * - Custom theme creation
 * - User preferences storage
 * - System theme detection
 * - Animation transitions
 * - Color scheme management
 * - Accessibility compliance
 * - Dynamic CSS variables
 * - Theme inheritance
 * - RTL support
 *
 * @category   JavaScript
 * @package    VapeshedTransfer
 * @subpackage Theming
 * @version    1.0.0
 */

class ThemeManager {
    /**
     * Configuration options
     */
    constructor(options = {}) {
        this.options = {
            // Storage settings
            storageKey: 'vapeshed_theme_preferences',
            storageType: 'localStorage', // localStorage, sessionStorage, cookie
            
            // Default themes
            defaultTheme: 'light',
            defaultDarkTheme: 'dark',
            
            // Animation settings
            transitionDuration: 300,
            enableTransitions: true,
            
            // System preferences
            respectSystemPreference: true,
            watchSystemChanges: true,
            
            // Accessibility
            respectReducedMotion: true,
            respectHighContrast: true,
            
            // Advanced features
            enableCustomThemes: true,
            enableColorSchemeGeneration: true,
            enableThemeInheritance: true,
            enableRTLSupport: true,
            
            // CSS variables prefix
            cssVariablePrefix: '--theme-',
            
            ...options
        };

        this.state = {
            currentTheme: null,
            systemPreference: null,
            customThemes: new Map(),
            isTransitioning: false,
            observers: new Set(),
            mediaQueries: new Map()
        };

        this.themes = new Map();
        this.colorSchemes = new Map();
        
        this.init();
    }

    /**
     * Initialize theme manager
     */
    init() {
        this.registerDefaultThemes();
        this.detectSystemPreference();
        this.loadUserPreferences();
        this.setupEventListeners();
        this.setupMediaQueries();
        this.applyInitialTheme();
        
        console.log('ThemeManager initialized', {
            currentTheme: this.state.currentTheme,
            systemPreference: this.state.systemPreference,
            availableThemes: Array.from(this.themes.keys())
        });
    }

    /**
     * Register default themes
     */
    registerDefaultThemes() {
        // Light theme
        this.registerTheme('light', {
            name: 'Light',
            type: 'light',
            colors: {
                primary: '#007bff',
                secondary: '#6c757d',
                success: '#28a745',
                danger: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8',
                light: '#f8f9fa',
                dark: '#343a40',
                
                // Background colors
                background: '#ffffff',
                backgroundSecondary: '#f8f9fa',
                backgroundTertiary: '#e9ecef',
                
                // Text colors
                textPrimary: '#212529',
                textSecondary: '#6c757d',
                textMuted: '#868e96',
                textInverse: '#ffffff',
                
                // Border colors
                border: '#dee2e6',
                borderLight: '#f1f3f4',
                borderDark: '#adb5bd',
                
                // Shadow colors
                shadowLight: 'rgba(0, 0, 0, 0.075)',
                shadowMedium: 'rgba(0, 0, 0, 0.15)',
                shadowDark: 'rgba(0, 0, 0, 0.3)',
                
                // Interactive colors
                hover: '#f8f9fa',
                active: '#e9ecef',
                focus: 'rgba(0, 123, 255, 0.25)',
                
                // Status colors
                online: '#28a745',
                offline: '#6c757d',
                away: '#ffc107',
                busy: '#dc3545'
            },
            fonts: {
                primary: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                monospace: 'SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
            },
            spacing: {
                xs: '0.25rem',
                sm: '0.5rem',
                md: '1rem',
                lg: '1.5rem',
                xl: '3rem'
            },
            borderRadius: {
                sm: '0.25rem',
                md: '0.375rem',
                lg: '0.5rem',
                xl: '0.75rem',
                pill: '50rem'
            }
        });

        // Dark theme
        this.registerTheme('dark', {
            name: 'Dark',
            type: 'dark',
            colors: {
                primary: '#0d6efd',
                secondary: '#6c757d',
                success: '#198754',
                danger: '#dc3545',
                warning: '#fd7e14',
                info: '#0dcaf0',
                light: '#f8f9fa',
                dark: '#212529',
                
                // Background colors
                background: '#121212',
                backgroundSecondary: '#1e1e1e',
                backgroundTertiary: '#2d2d2d',
                
                // Text colors
                textPrimary: '#ffffff',
                textSecondary: '#adb5bd',
                textMuted: '#6c757d',
                textInverse: '#212529',
                
                // Border colors
                border: '#495057',
                borderLight: '#373a3c',
                borderDark: '#6c757d',
                
                // Shadow colors
                shadowLight: 'rgba(0, 0, 0, 0.3)',
                shadowMedium: 'rgba(0, 0, 0, 0.5)',
                shadowDark: 'rgba(0, 0, 0, 0.8)',
                
                // Interactive colors
                hover: '#2d2d2d',
                active: '#3d3d3d',
                focus: 'rgba(13, 110, 253, 0.25)',
                
                // Status colors
                online: '#198754',
                offline: '#6c757d',
                away: '#fd7e14',
                busy: '#dc3545'
            },
            fonts: {
                primary: 'Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                monospace: 'SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
            },
            spacing: {
                xs: '0.25rem',
                sm: '0.5rem',
                md: '1rem',
                lg: '1.5rem',
                xl: '3rem'
            },
            borderRadius: {
                sm: '0.25rem',
                md: '0.375rem',
                lg: '0.5rem',
                xl: '0.75rem',
                pill: '50rem'
            }
        });

        // High contrast theme
        this.registerTheme('high-contrast', {
            name: 'High Contrast',
            type: 'high-contrast',
            parent: 'light',
            colors: {
                primary: '#0000ff',
                secondary: '#000000',
                success: '#008000',
                danger: '#ff0000',
                warning: '#ffff00',
                info: '#00ffff',
                
                background: '#ffffff',
                backgroundSecondary: '#ffffff',
                backgroundTertiary: '#f0f0f0',
                
                textPrimary: '#000000',
                textSecondary: '#000000',
                textMuted: '#333333',
                textInverse: '#ffffff',
                
                border: '#000000',
                borderLight: '#666666',
                borderDark: '#000000'
            }
        });
    }

    /**
     * Register a theme
     */
    registerTheme(id, theme) {
        // Process theme inheritance
        if (theme.parent && this.themes.has(theme.parent)) {
            const parentTheme = this.themes.get(theme.parent);
            theme = this.mergeThemes(parentTheme, theme);
        }

        this.themes.set(id, {
            id,
            ...theme,
            cssVariables: this.generateCSSVariables(theme)
        });

        console.log(`Theme registered: ${id}`, theme);
        return this;
    }

    /**
     * Merge themes (for inheritance)
     */
    mergeThemes(parent, child) {
        const merged = JSON.parse(JSON.stringify(parent));
        
        // Deep merge colors
        if (child.colors) {
            merged.colors = { ...merged.colors, ...child.colors };
        }
        
        // Merge other properties
        Object.keys(child).forEach(key => {
            if (key !== 'colors' && key !== 'parent') {
                merged[key] = child[key];
            }
        });

        return merged;
    }

    /**
     * Generate CSS variables from theme
     */
    generateCSSVariables(theme) {
        const variables = {};
        const prefix = this.options.cssVariablePrefix;

        // Colors
        if (theme.colors) {
            Object.entries(theme.colors).forEach(([key, value]) => {
                variables[`${prefix}color-${this.kebabCase(key)}`] = value;
            });
        }

        // Fonts
        if (theme.fonts) {
            Object.entries(theme.fonts).forEach(([key, value]) => {
                variables[`${prefix}font-${this.kebabCase(key)}`] = value;
            });
        }

        // Spacing
        if (theme.spacing) {
            Object.entries(theme.spacing).forEach(([key, value]) => {
                variables[`${prefix}spacing-${key}`] = value;
            });
        }

        // Border radius
        if (theme.borderRadius) {
            Object.entries(theme.borderRadius).forEach(([key, value]) => {
                variables[`${prefix}border-radius-${key}`] = value;
            });
        }

        return variables;
    }

    /**
     * Convert camelCase to kebab-case
     */
    kebabCase(str) {
        return str.replace(/([a-z0-9]|(?=[A-Z]))([A-Z])/g, '$1-$2').toLowerCase();
    }

    /**
     * Detect system color scheme preference
     */
    detectSystemPreference() {
        if (!window.matchMedia) {
            this.state.systemPreference = 'light';
            return;
        }

        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const lightModeQuery = window.matchMedia('(prefers-color-scheme: light)');
        const highContrastQuery = window.matchMedia('(prefers-contrast: high)');

        if (highContrastQuery.matches) {
            this.state.systemPreference = 'high-contrast';
        } else if (darkModeQuery.matches) {
            this.state.systemPreference = 'dark';
        } else if (lightModeQuery.matches) {
            this.state.systemPreference = 'light';
        } else {
            this.state.systemPreference = 'light';
        }

        console.log('System preference detected:', this.state.systemPreference);
    }

    /**
     * Setup media query listeners
     */
    setupMediaQueries() {
        if (!this.options.watchSystemChanges || !window.matchMedia) return;

        const queries = [
            ['(prefers-color-scheme: dark)', 'dark'],
            ['(prefers-color-scheme: light)', 'light'],
            ['(prefers-contrast: high)', 'high-contrast'],
            ['(prefers-reduced-motion: reduce)', 'reduced-motion']
        ];

        queries.forEach(([query, preference]) => {
            const mq = window.matchMedia(query);
            const handler = (e) => this.handleMediaQueryChange(preference, e.matches);
            
            mq.addListener(handler);
            this.state.mediaQueries.set(preference, { query: mq, handler });
        });
    }

    /**
     * Handle media query changes
     */
    handleMediaQueryChange(preference, matches) {
        if (preference === 'reduced-motion') {
            document.body.classList.toggle('reduce-motion', matches);
            return;
        }

        if (matches) {
            this.state.systemPreference = preference;
            
            // Apply system preference if user hasn't set a specific theme
            if (this.options.respectSystemPreference && !this.getUserPreference()) {
                this.setTheme(preference);
            }
        }
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Theme toggle buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-theme-toggle]')) {
                e.preventDefault();
                this.toggleTheme();
            }
            
            if (e.target.matches('[data-theme-set]')) {
                e.preventDefault();
                const theme = e.target.getAttribute('data-theme-set');
                this.setTheme(theme);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+Shift+T for theme toggle
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
        });
    }

    /**
     * Load user preferences from storage
     */
    loadUserPreferences() {
        try {
            const stored = this.getStoredData();
            if (stored && stored.theme && this.themes.has(stored.theme)) {
                this.state.currentTheme = stored.theme;
                return;
            }
        } catch (error) {
            console.warn('Failed to load theme preferences:', error);
        }

        // Fallback to system preference or default
        if (this.options.respectSystemPreference && this.state.systemPreference) {
            this.state.currentTheme = this.state.systemPreference;
        } else {
            this.state.currentTheme = this.options.defaultTheme;
        }
    }

    /**
     * Save user preferences to storage
     */
    saveUserPreferences() {
        try {
            const data = {
                theme: this.state.currentTheme,
                timestamp: Date.now(),
                version: '1.0'
            };

            this.setStoredData(data);
        } catch (error) {
            console.warn('Failed to save theme preferences:', error);
        }
    }

    /**
     * Get stored data
     */
    getStoredData() {
        const key = this.options.storageKey;
        
        switch (this.options.storageType) {
            case 'localStorage':
                return JSON.parse(localStorage.getItem(key) || 'null');
            case 'sessionStorage':
                return JSON.parse(sessionStorage.getItem(key) || 'null');
            case 'cookie':
                return this.getCookie(key);
            default:
                return null;
        }
    }

    /**
     * Set stored data
     */
    setStoredData(data) {
        const key = this.options.storageKey;
        const value = JSON.stringify(data);
        
        switch (this.options.storageType) {
            case 'localStorage':
                localStorage.setItem(key, value);
                break;
            case 'sessionStorage':
                sessionStorage.setItem(key, value);
                break;
            case 'cookie':
                this.setCookie(key, value, 365);
                break;
        }
    }

    /**
     * Get cookie value
     */
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            try {
                return JSON.parse(parts.pop().split(';').shift());
            } catch {
                return null;
            }
        }
        return null;
    }

    /**
     * Set cookie value
     */
    setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
    }

    /**
     * Apply initial theme
     */
    applyInitialTheme() {
        if (this.state.currentTheme) {
            this.applyTheme(this.state.currentTheme);
        }
    }

    /**
     * Set current theme
     */
    setTheme(themeId, options = {}) {
        if (!this.themes.has(themeId)) {
            console.warn(`Theme not found: ${themeId}`);
            return false;
        }

        const oldTheme = this.state.currentTheme;
        this.state.currentTheme = themeId;

        // Apply theme
        const success = this.applyTheme(themeId, options);
        
        if (success) {
            // Save preferences
            if (!options.temporary) {
                this.saveUserPreferences();
            }

            // Notify observers
            this.notifyObservers('themeChanged', {
                from: oldTheme,
                to: themeId,
                theme: this.themes.get(themeId)
            });

            console.log(`Theme changed: ${oldTheme} → ${themeId}`);
        }

        return success;
    }

    /**
     * Apply theme to document
     */
    applyTheme(themeId, options = {}) {
        const theme = this.themes.get(themeId);
        if (!theme) return false;

        const { enableTransition = this.options.enableTransitions } = options;

        // Start transition
        if (enableTransition && !this.options.respectReducedMotion) {
            this.startThemeTransition();
        }

        // Apply CSS variables
        this.applyCSSVariables(theme.cssVariables);

        // Update HTML attributes
        document.documentElement.setAttribute('data-theme', themeId);
        document.documentElement.setAttribute('data-theme-type', theme.type || 'light');

        // Update color scheme meta tag
        this.updateColorSchemeMeta(theme.type);

        // End transition
        if (enableTransition && !this.options.respectReducedMotion) {
            setTimeout(() => {
                this.endThemeTransition();
            }, this.options.transitionDuration);
        }

        return true;
    }

    /**
     * Apply CSS variables
     */
    applyCSSVariables(variables) {
        const root = document.documentElement;
        
        Object.entries(variables).forEach(([property, value]) => {
            root.style.setProperty(property, value);
        });
    }

    /**
     * Update color scheme meta tag
     */
    updateColorSchemeMeta(type) {
        let metaTag = document.querySelector('meta[name="color-scheme"]');
        
        if (!metaTag) {
            metaTag = document.createElement('meta');
            metaTag.name = 'color-scheme';
            document.head.appendChild(metaTag);
        }

        metaTag.content = type === 'dark' ? 'dark light' : 'light dark';
    }

    /**
     * Start theme transition
     */
    startThemeTransition() {
        this.state.isTransitioning = true;
        document.body.classList.add('theme-transitioning');
        
        // Add transition styles
        const style = document.createElement('style');
        style.id = 'theme-transition-styles';
        style.textContent = `
            * {
                transition: 
                    background-color ${this.options.transitionDuration}ms ease,
                    border-color ${this.options.transitionDuration}ms ease,
                    color ${this.options.transitionDuration}ms ease,
                    box-shadow ${this.options.transitionDuration}ms ease !important;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * End theme transition
     */
    endThemeTransition() {
        this.state.isTransitioning = false;
        document.body.classList.remove('theme-transitioning');
        
        // Remove transition styles
        const style = document.getElementById('theme-transition-styles');
        if (style) {
            style.remove();
        }
    }

    /**
     * Toggle between light and dark themes
     */
    toggleTheme() {
        const currentTheme = this.getCurrentTheme();
        
        if (currentTheme && currentTheme.type === 'dark') {
            this.setTheme(this.options.defaultTheme);
        } else {
            this.setTheme(this.options.defaultDarkTheme);
        }
    }

    /**
     * Get current theme object
     */
    getCurrentTheme() {
        return this.themes.get(this.state.currentTheme);
    }

    /**
     * Get current theme ID
     */
    getCurrentThemeId() {
        return this.state.currentTheme;
    }

    /**
     * Get all available themes
     */
    getAvailableThemes() {
        return Array.from(this.themes.values());
    }

    /**
     * Get user preference (without system fallback)
     */
    getUserPreference() {
        try {
            const stored = this.getStoredData();
            return stored ? stored.theme : null;
        } catch {
            return null;
        }
    }

    /**
     * Create custom theme
     */
    createCustomTheme(id, theme) {
        if (!this.options.enableCustomThemes) {
            console.warn('Custom themes are disabled');
            return false;
        }

        this.registerTheme(id, { ...theme, custom: true });
        this.state.customThemes.set(id, theme);
        
        return true;
    }

    /**
     * Delete custom theme
     */
    deleteCustomTheme(id) {
        if (!this.state.customThemes.has(id)) {
            return false;
        }

        this.themes.delete(id);
        this.state.customThemes.delete(id);
        
        // Switch to default if current theme was deleted
        if (this.state.currentTheme === id) {
            this.setTheme(this.options.defaultTheme);
        }

        return true;
    }

    /**
     * Generate color scheme from base color
     */
    generateColorScheme(baseColor, type = 'light') {
        if (!this.options.enableColorSchemeGeneration) {
            return null;
        }

        // This is a simplified color scheme generator
        // In a real implementation, you might use a more sophisticated color theory library
        const hsl = this.hexToHsl(baseColor);
        
        return {
            primary: baseColor,
            secondary: this.adjustHue(baseColor, 30),
            success: this.adjustHue(baseColor, 120),
            danger: this.adjustHue(baseColor, -60),
            warning: this.adjustHue(baseColor, 60),
            info: this.adjustHue(baseColor, 180)
        };
    }

    /**
     * Convert hex to HSL
     */
    hexToHsl(hex) {
        const r = parseInt(hex.slice(1, 3), 16) / 255;
        const g = parseInt(hex.slice(3, 5), 16) / 255;
        const b = parseInt(hex.slice(5, 7), 16) / 255;

        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        let h, s, l = (max + min) / 2;

        if (max === min) {
            h = s = 0;
        } else {
            const d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                case b: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }

        return [h * 360, s * 100, l * 100];
    }

    /**
     * Adjust hue of color
     */
    adjustHue(hex, adjustment) {
        const [h, s, l] = this.hexToHsl(hex);
        const newH = (h + adjustment) % 360;
        return this.hslToHex(newH, s, l);
    }

    /**
     * Convert HSL to hex
     */
    hslToHex(h, s, l) {
        h /= 360;
        s /= 100;
        l /= 100;

        const hue2rgb = (p, q, t) => {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1/6) return p + (q - p) * 6 * t;
            if (t < 1/2) return q;
            if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
            return p;
        };

        const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        const p = 2 * l - q;

        const r = Math.round(hue2rgb(p, q, h + 1/3) * 255);
        const g = Math.round(hue2rgb(p, q, h) * 255);
        const b = Math.round(hue2rgb(p, q, h - 1/3) * 255);

        return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
    }

    /**
     * Add theme change observer
     */
    on(callback) {
        this.state.observers.add(callback);
        return this;
    }

    /**
     * Remove theme change observer
     */
    off(callback) {
        this.state.observers.delete(callback);
        return this;
    }

    /**
     * Notify observers
     */
    notifyObservers(event, data) {
        this.state.observers.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Error in theme observer:', error);
            }
        });
    }

    /**
     * Reset to system preference
     */
    resetToSystemPreference() {
        if (this.state.systemPreference) {
            this.setTheme(this.state.systemPreference);
        }
    }

    /**
     * Export current theme
     */
    exportTheme(themeId = this.state.currentTheme) {
        const theme = this.themes.get(themeId);
        if (!theme) return null;

        return JSON.stringify(theme, null, 2);
    }

    /**
     * Import theme from JSON
     */
    importTheme(themeData, id) {
        try {
            const theme = JSON.parse(themeData);
            return this.registerTheme(id, theme);
        } catch (error) {
            console.error('Failed to import theme:', error);
            return false;
        }
    }

    /**
     * Cleanup
     */
    destroy() {
        // Remove media query listeners
        this.state.mediaQueries.forEach(({ query, handler }) => {
            query.removeListener(handler);
        });

        // Clear observers
        this.state.observers.clear();

        // Remove transition styles if present
        const style = document.getElementById('theme-transition-styles');
        if (style) {
            style.remove();
        }

        console.log('ThemeManager destroyed');
    }
}

// Initialize global instance
window.theme = new ThemeManager();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ThemeManager;
}