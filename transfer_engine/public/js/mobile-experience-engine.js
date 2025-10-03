/**
 * VapeShed Mobile-First Experience Engine
 * 
 * Optimized for 70% mobile user base with exciting browsing experience
 * Features: Touch-friendly interactions, swipe gestures, mobile-first design
 * Focus: Fast loading, thumb-friendly UI, engaging mobile animations
 * 
 * @author AI Assistant  
 * @created 2025-09-26
 * @mobile_optimization_priority HIGH
 */

class VapeShedMobileExperience {
    constructor() {
        this.isMobile = this.detectMobile();
        this.touchStartY = 0;
        this.touchStartX = 0;
        this.swipeThreshold = 50;
        this.scrollPosition = 0;
        this.isScrolling = false;
        
        console.log('📱 VapeShed Mobile Experience Loading...', {
            isMobile: this.isMobile,
            screenWidth: window.innerWidth,
            userAgent: navigator.userAgent.substring(0, 50)
        });
        
        this.init();
    }
    
    init() {
        // Mobile-first initialization
        this.setupMobileViewport();
        this.initializeMobileUI();
        this.setupTouchGestures();
        this.optimizeMobilePerformance();
        this.setupMobileAnimations();
        this.initializeSwipeNavigation();
        
        console.log('✅ Mobile Experience Ready - Optimized for 70% mobile traffic');
    }
    
    /**
     * Detect mobile device with comprehensive checks
     */
    detectMobile() {
        const mobileChecks = [
            /Android/i.test(navigator.userAgent),
            /webOS/i.test(navigator.userAgent),
            /iPhone/i.test(navigator.userAgent),
            /iPad/i.test(navigator.userAgent),
            /iPod/i.test(navigator.userAgent),
            /BlackBerry/i.test(navigator.userAgent),
            /Windows Phone/i.test(navigator.userAgent),
            window.innerWidth <= 768,
            'ontouchstart' in window,
            navigator.maxTouchPoints > 0
        ];
        
        return mobileChecks.some(check => check === true);
    }
    
    /**
     * Setup mobile viewport and meta tags
     */
    setupMobileViewport() {
        // Ensure proper mobile viewport
        let viewport = document.querySelector('meta[name="viewport"]');
        if (!viewport) {
            viewport = document.createElement('meta');
            viewport.name = 'viewport';
            document.head.appendChild(viewport);
        }
        
        viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover';
        
        // Add mobile-specific meta tags
        this.addMobileMetaTags();
        
        // Apply mobile-first CSS immediately
        this.applyMobileFirstCSS();
    }
    
    /**
     * Add mobile-specific meta tags
     */
    addMobileMetaTags() {
        const mobileMetaTags = [
            { name: 'format-detection', content: 'telephone=yes' },
            { name: 'mobile-web-app-capable', content: 'yes' },
            { name: 'mobile-web-app-status-bar-style', content: 'black-translucent' },
            { name: 'theme-color', content: '#003366' },
            { property: 'og:image:width', content: '1200' },
            { property: 'og:image:height', content: '630' }
        ];
        
        mobileMetaTags.forEach(tag => {
            if (!document.querySelector(`meta[${tag.name ? 'name' : 'property'}="${tag.name || tag.property}"]`)) {
                const meta = document.createElement('meta');
                if (tag.name) meta.name = tag.name;
                if (tag.property) meta.property = tag.property;
                meta.content = tag.content;
                document.head.appendChild(meta);
            }
        });
    }
    
    /**
     * Apply mobile-first CSS styles
     */
    applyMobileFirstCSS() {
        const mobileStyles = `
            /* Mobile-First Base Styles */
            :root {
                --mobile-padding: 1rem;
                --mobile-margin: 0.5rem;
                --touch-target: 44px;
                --thumb-zone: 48px;
                --swipe-indicator: #007bff;
                --mobile-header: 60px;
            }
            
            * {
                -webkit-tap-highlight-color: rgba(0, 0, 0, 0);
                -webkit-touch-callout: none;
                touch-action: manipulation;
            }
            
            body {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                font-size: 16px; /* Prevent iOS zoom on input focus */
                line-height: 1.5;
            }
            
            /* Mobile-Optimized Header */
            .mobile-header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: var(--mobile-header);
                background: rgba(0, 51, 102, 0.95);
                backdrop-filter: blur(10px);
                z-index: 1000;
                display: flex;
                align-items: center;
                padding: 0 var(--mobile-padding);
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            
            .mobile-header .logo {
                height: 30px;
                margin-right: auto;
            }
            
            .mobile-menu-btn, .mobile-search-btn, .mobile-cart-btn {
                width: var(--thumb-zone);
                height: var(--thumb-zone);
                border: none;
                background: none;
                color: white;
                font-size: 1.2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
                margin-left: 0.5rem;
                transition: all 0.2s ease;
            }
            
            .mobile-menu-btn:active, .mobile-search-btn:active, .mobile-cart-btn:active {
                background: rgba(255, 255, 255, 0.2);
                transform: scale(0.95);
            }
            
            /* Mobile Navigation */
            .mobile-nav {
                position: fixed;
                top: var(--mobile-header);
                left: -100%;
                width: 280px;
                height: calc(100vh - var(--mobile-header));
                background: white;
                z-index: 999;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }
            
            .mobile-nav.active {
                left: 0;
            }
            
            .mobile-nav-item {
                display: flex;
                align-items: center;
                padding: 1rem var(--mobile-padding);
                border-bottom: 1px solid #f0f0f0;
                color: #333;
                text-decoration: none;
                font-size: 1rem;
                min-height: var(--thumb-zone);
                transition: background 0.2s ease;
            }
            
            .mobile-nav-item:active {
                background: #f8f9fa;
            }
            
            .mobile-nav-item i {
                width: 24px;
                margin-right: 12px;
                color: #666;
            }
            
            /* Mobile Search */
            .mobile-search {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                height: 100vh;
                background: white;
                z-index: 1001;
                transform: translateY(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .mobile-search.active {
                transform: translateY(0);
            }
            
            .mobile-search-header {
                display: flex;
                align-items: center;
                padding: 1rem;
                border-bottom: 1px solid #e9ecef;
                background: #f8f9fa;
            }
            
            .mobile-search-input {
                flex: 1;
                height: var(--thumb-zone);
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 0 1rem;
                font-size: 16px; /* Prevent iOS zoom */
                margin-right: 1rem;
            }
            
            .mobile-search-close {
                width: var(--thumb-zone);
                height: var(--thumb-zone);
                border: none;
                background: #6c757d;
                color: white;
                border-radius: 8px;
                font-size: 1.1rem;
            }
            
            /* Mobile Product Grid */
            .mobile-product-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
                padding: var(--mobile-padding);
                margin-top: var(--mobile-header);
            }
            
            @media (max-width: 320px) {
                .mobile-product-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            .mobile-product-card {
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                transition: all 0.2s ease;
                position: relative;
            }
            
            .mobile-product-card:active {
                transform: scale(0.98);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            
            .mobile-product-image {
                width: 100%;
                aspect-ratio: 1;
                object-fit: cover;
                background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            }
            
            .mobile-product-info {
                padding: 0.75rem;
            }
            
            .mobile-product-title {
                font-size: 0.9rem;
                font-weight: 600;
                color: #333;
                margin-bottom: 0.25rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            
            .mobile-product-brand {
                font-size: 0.8rem;
                color: #666;
                margin-bottom: 0.5rem;
            }
            
            .mobile-product-price {
                font-size: 1rem;
                font-weight: bold;
                color: #007bff;
                margin-bottom: 0.5rem;
            }
            
            .mobile-add-cart-btn {
                width: 100%;
                height: 36px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 0.9rem;
                font-weight: 500;
                transition: all 0.2s ease;
            }
            
            .mobile-add-cart-btn:active {
                background: #0056b3;
                transform: scale(0.98);
            }
            
            /* Mobile Categories */
            .mobile-categories {
                display: flex;
                gap: 0.5rem;
                padding: var(--mobile-padding);
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }
            
            .mobile-categories::-webkit-scrollbar {
                display: none;
            }
            
            .mobile-category-chip {
                flex: 0 0 auto;
                padding: 0.5rem 1rem;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 20px;
                color: #495057;
                text-decoration: none;
                font-size: 0.85rem;
                font-weight: 500;
                white-space: nowrap;
                transition: all 0.2s ease;
                min-height: var(--touch-target);
                display: flex;
                align-items: center;
            }
            
            .mobile-category-chip.active {
                background: #007bff;
                color: white;
                border-color: #007bff;
            }
            
            .mobile-category-chip:active {
                transform: scale(0.95);
            }
            
            /* Mobile Bottom Bar */
            .mobile-bottom-bar {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 60px;
                background: white;
                border-top: 1px solid #e9ecef;
                display: flex;
                z-index: 998;
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            }
            
            .mobile-bottom-btn {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                color: #6c757d;
                text-decoration: none;
                font-size: 0.7rem;
                transition: all 0.2s ease;
                padding: 0.25rem;
            }
            
            .mobile-bottom-btn.active {
                color: #007bff;
            }
            
            .mobile-bottom-btn:active {
                background: #f8f9fa;
            }
            
            .mobile-bottom-btn i {
                font-size: 1.2rem;
                margin-bottom: 0.25rem;
            }
            
            /* Mobile Cart Badge */
            .mobile-cart-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                background: #dc3545;
                color: white;
                border-radius: 10px;
                min-width: 18px;
                height: 18px;
                font-size: 0.7rem;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
            }
            
            /* Mobile Swipe Indicators */
            .mobile-swipe-indicator {
                position: absolute;
                bottom: 10px;
                left: 50%;
                transform: translateX(-50%);
                display: flex;
                gap: 4px;
            }
            
            .mobile-swipe-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transition: all 0.2s ease;
            }
            
            .mobile-swipe-dot.active {
                background: white;
                transform: scale(1.2);
            }
            
            /* Mobile Loading States */
            .mobile-loading {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            
            .mobile-spinner {
                width: 32px;
                height: 32px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #007bff;
                border-radius: 50%;
                animation: mobile-spin 1s linear infinite;
            }
            
            @keyframes mobile-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Mobile Pull-to-Refresh */
            .mobile-pull-refresh {
                position: absolute;
                top: -60px;
                left: 50%;
                transform: translateX(-50%);
                width: 40px;
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #007bff;
                font-size: 1.5rem;
                transition: all 0.2s ease;
            }
            
            .mobile-pull-refresh.active {
                top: 20px;
            }
            
            /* Mobile Gestures */
            .mobile-swipeable {
                position: relative;
                overflow: hidden;
            }
            
            .mobile-swipe-actions {
                position: absolute;
                top: 0;
                right: -100px;
                bottom: 0;
                width: 100px;
                display: flex;
                background: #dc3545;
                align-items: center;
                justify-content: center;
                color: white;
                transition: right 0.2s ease;
            }
            
            .mobile-swipe-actions.visible {
                right: 0;
            }
            
            /* Mobile Performance Optimizations */
            .mobile-optimize-scroll {
                -webkit-overflow-scrolling: touch;
                transform: translate3d(0, 0, 0);
                will-change: scroll-position;
            }
            
            .mobile-optimize-animation {
                will-change: transform;
                transform: translate3d(0, 0, 0);
            }
            
            /* Mobile Safe Areas */
            .mobile-safe-top {
                padding-top: env(safe-area-inset-top);
            }
            
            .mobile-safe-bottom {
                padding-bottom: env(safe-area-inset-bottom);
            }
            
            /* Mobile Responsive Breakpoints */
            @media (max-width: 480px) {
                :root {
                    --mobile-padding: 0.75rem;
                    --mobile-margin: 0.375rem;
                }
                
                .mobile-product-grid {
                    gap: 0.375rem;
                    padding: 0.75rem;
                }
                
                .mobile-product-info {
                    padding: 0.5rem;
                }
            }
            
            @media (max-width: 360px) {
                :root {
                    --mobile-padding: 0.5rem;
                    --mobile-margin: 0.25rem;
                }
                
                .mobile-header {
                    height: 50px;
                }
                
                .mobile-nav {
                    top: 50px;
                    height: calc(100vh - 50px);
                }
            }
            
            /* Landscape Mobile Optimizations */
            @media (orientation: landscape) and (max-height: 500px) {
                .mobile-header {
                    height: 44px;
                }
                
                .mobile-nav {
                    top: 44px;
                    height: calc(100vh - 44px);
                }
                
                .mobile-product-grid {
                    grid-template-columns: repeat(3, 1fr);
                    margin-top: 44px;
                }
            }
        `;
        
        // Inject mobile styles
        const styleSheet = document.createElement('style');
        styleSheet.textContent = mobileStyles;
        document.head.appendChild(styleSheet);
        
        console.log('📱 Mobile-first CSS applied');
    }
    
    /**
     * Initialize mobile-specific UI components
     */
    initializeMobileUI() {
        // Create mobile header
        this.createMobileHeader();
        
        // Create mobile navigation
        this.createMobileNavigation();
        
        // Create mobile search
        this.createMobileSearch();
        
        // Create mobile bottom bar
        this.createMobileBottomBar();
        
        // Setup mobile product grid
        this.setupMobileProductGrid();
        
        // Initialize mobile categories
        this.initializeMobileCategories();
        
        console.log('📱 Mobile UI components initialized');
    }
    
    /**
     * Create mobile header with logo and controls
     */
    createMobileHeader() {
        const header = document.createElement('div');
        header.className = 'mobile-header mobile-safe-top';
        header.innerHTML = `
            <img src="/images/logo-white.png" alt="VapeShed" class="logo">
            <button class="mobile-search-btn" data-action="search">
                <i class="fas fa-search"></i>
            </button>
            <button class="mobile-cart-btn" data-action="cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="mobile-cart-badge">3</span>
            </button>
            <button class="mobile-menu-btn" data-action="menu">
                <i class="fas fa-bars"></i>
            </button>
        `;
        
        document.body.insertBefore(header, document.body.firstChild);
        
        // Add click handlers
        header.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;
            if (action) {
                this.handleHeaderAction(action);
            }
        });
    }
    
    /**
     * Handle mobile header actions
     */
    handleHeaderAction(action) {
        switch (action) {
            case 'menu':
                this.toggleMobileMenu();
                break;
            case 'search':
                this.openMobileSearch();
                break;
            case 'cart':
                this.openMobileCart();
                break;
        }
    }
    
    /**
     * Create mobile navigation menu
     */
    createMobileNavigation() {
        const nav = document.createElement('div');
        nav.className = 'mobile-nav';
        nav.innerHTML = `
            <a href="/" class="mobile-nav-item">
                <i class="fas fa-home"></i>
                Home
            </a>
            <a href="/categories" class="mobile-nav-item">
                <i class="fas fa-th-large"></i>
                Categories
            </a>
            <a href="/brands" class="mobile-nav-item">
                <i class="fas fa-star"></i>
                Brands
            </a>
            <a href="/new-arrivals" class="mobile-nav-item">
                <i class="fas fa-sparkles"></i>
                New Arrivals
            </a>
            <a href="/customer/dashboard" class="mobile-nav-item">
                <i class="fas fa-user"></i>
                My Dashboard
            </a>
            <a href="/orders" class="mobile-nav-item">
                <i class="fas fa-box"></i>
                My Orders
            </a>
            <a href="/support" class="mobile-nav-item">
                <i class="fas fa-life-ring"></i>
                Support
            </a>
            <a href="/stores" class="mobile-nav-item">
                <i class="fas fa-map-marker-alt"></i>
                Store Locator
            </a>
        `;
        
        document.body.appendChild(nav);
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'mobile-nav-backdrop';
        backdrop.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(backdrop);
        
        // Close menu when clicking backdrop
        backdrop.addEventListener('click', () => {
            this.closeMobileMenu();
        });
    }
    
    /**
     * Toggle mobile menu
     */
    toggleMobileMenu() {
        const nav = document.querySelector('.mobile-nav');
        const backdrop = document.querySelector('.mobile-nav-backdrop');
        
        if (nav.classList.contains('active')) {
            this.closeMobileMenu();
        } else {
            nav.classList.add('active');
            backdrop.style.opacity = '1';
            backdrop.style.visibility = 'visible';
            document.body.style.overflow = 'hidden';
        }
    }
    
    /**
     * Close mobile menu
     */
    closeMobileMenu() {
        const nav = document.querySelector('.mobile-nav');
        const backdrop = document.querySelector('.mobile-nav-backdrop');
        
        nav.classList.remove('active');
        backdrop.style.opacity = '0';
        backdrop.style.visibility = 'hidden';
        document.body.style.overflow = '';
    }
    
    /**
     * Create mobile search interface
     */
    createMobileSearch() {
        const search = document.createElement('div');
        search.className = 'mobile-search mobile-safe-top';
        search.innerHTML = `
            <div class="mobile-search-header">
                <input type="text" class="mobile-search-input" placeholder="Search products, brands..." autocomplete="off">
                <button class="mobile-search-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mobile-search-results">
                <div class="mobile-loading" style="display: none;">
                    <div class="mobile-spinner"></div>
                </div>
                <div class="mobile-search-suggestions"></div>
            </div>
        `;
        
        document.body.appendChild(search);
        
        // Add search functionality
        const input = search.querySelector('.mobile-search-input');
        const closeBtn = search.querySelector('.mobile-search-close');
        
        input.addEventListener('input', (e) => {
            this.handleMobileSearch(e.target.value);
        });
        
        closeBtn.addEventListener('click', () => {
            this.closeMobileSearch();
        });
    }
    
    /**
     * Open mobile search
     */
    openMobileSearch() {
        const search = document.querySelector('.mobile-search');
        search.classList.add('active');
        
        // Focus input after animation
        setTimeout(() => {
            const input = search.querySelector('.mobile-search-input');
            input.focus();
        }, 300);
    }
    
    /**
     * Close mobile search
     */
    closeMobileSearch() {
        const search = document.querySelector('.mobile-search');
        search.classList.remove('active');
    }
    
    /**
     * Handle mobile search input
     */
    handleMobileSearch(query) {
        if (query.length < 2) return;
        
        const loading = document.querySelector('.mobile-search-results .mobile-loading');
        const suggestions = document.querySelector('.mobile-search-suggestions');
        
        loading.style.display = 'flex';
        
        // Simulate search API call
        setTimeout(() => {
            loading.style.display = 'none';
            
            const mockResults = [
                { name: 'IGET Bar Plus 6000 Puff', brand: 'IGET', price: '$45.90' },
                { name: 'Just Juice Salt 30mg', brand: 'Just Juice', price: '$19.90' },
                { name: 'Vuse ePod 2 Device', brand: 'Vuse', price: '$12.90' }
            ];
            
            suggestions.innerHTML = mockResults.map(item => `
                <div class="mobile-search-result mobile-nav-item">
                    <i class="fas fa-search"></i>
                    <div>
                        <div style="font-weight: 500;">${item.name}</div>
                        <div style="font-size: 0.8rem; color: #666;">${item.brand} - ${item.price}</div>
                    </div>
                </div>
            `).join('');
        }, 500);
    }
    
    /**
     * Create mobile bottom navigation bar
     */
    createMobileBottomBar() {
        const bottomBar = document.createElement('div');
        bottomBar.className = 'mobile-bottom-bar mobile-safe-bottom';
        bottomBar.innerHTML = `
            <a href="/" class="mobile-bottom-btn active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="/categories" class="mobile-bottom-btn">
                <i class="fas fa-th-large"></i>
                <span>Browse</span>
            </a>
            <a href="/search" class="mobile-bottom-btn">
                <i class="fas fa-search"></i>
                <span>Search</span>
            </a>
            <a href="/customer/dashboard" class="mobile-bottom-btn">
                <i class="fas fa-user"></i>
                <span>Account</span>
            </a>
            <a href="/cart" class="mobile-bottom-btn">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
                <span class="mobile-cart-badge">3</span>
            </a>
        `;
        
        document.body.appendChild(bottomBar);
        
        // Handle bottom bar navigation
        bottomBar.addEventListener('click', (e) => {
            e.preventDefault();
            const link = e.target.closest('.mobile-bottom-btn');
            if (link) {
                // Remove active class from all items
                bottomBar.querySelectorAll('.mobile-bottom-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked item
                link.classList.add('active');
                
                // Navigate to page
                this.handleBottomBarNavigation(link.href);
            }
        });
    }
    
    /**
     * Handle bottom bar navigation
     */
    handleBottomBarNavigation(href) {
        // Implement navigation logic
        console.log('📱 Navigating to:', href);
        
        // For now, just update the URL
        window.history.pushState({}, '', href);
    }
    
    /**
     * Setup mobile product grid with touch optimizations
     */
    setupMobileProductGrid() {
        // Find existing product grid or create new one
        let productGrid = document.querySelector('.products-grid, .product-list');
        
        if (!productGrid) {
            productGrid = document.createElement('div');
            productGrid.className = 'mobile-product-grid mobile-optimize-scroll';
            
            // Add to main content area
            const mainContent = document.querySelector('main, .main-content') || document.body;
            mainContent.appendChild(productGrid);
        } else {
            productGrid.className += ' mobile-product-grid mobile-optimize-scroll';
        }
        
        // Add sample products for demo
        this.populateMobileProducts(productGrid);
    }
    
    /**
     * Populate mobile product grid with optimized cards
     */
    populateMobileProducts(container) {
        const sampleProducts = [
            { id: 1, name: 'IGET Bar Plus 6000 Puff', brand: 'IGET', price: 45.90, image: '/images/iget-bar-plus.jpg' },
            { id: 2, name: 'Just Juice Salt Kiwifruit 30mg', brand: 'Just Juice', price: 19.90, image: '/images/just-juice-kiwi.jpg' },
            { id: 3, name: 'Vuse ePod 2 Device Kit', brand: 'Vuse', price: 12.90, image: '/images/vuse-epod-2.jpg' },
            { id: 4, name: 'IGET King 2600 Puff Disposable', brand: 'IGET', price: 32.90, image: '/images/iget-king.jpg' },
            { id: 5, name: 'Geekvape Aegis Mini Kit', brand: 'Geekvape', price: 79.90, image: '/images/aegis-mini.jpg' },
            { id: 6, name: 'Just Juice Berry Blast 50mg', brand: 'Just Juice', price: 24.90, image: '/images/just-juice-berry.jpg' }
        ];
        
        container.innerHTML = sampleProducts.map(product => `
            <div class="mobile-product-card mobile-optimize-animation" data-product-id="${product.id}">
                <img src="${product.image}" alt="${product.name}" class="mobile-product-image" 
                     onerror="this.src='/images/product-placeholder.png'">
                <div class="mobile-product-info">
                    <div class="mobile-product-title">${product.name}</div>
                    <div class="mobile-product-brand">${product.brand}</div>
                    <div class="mobile-product-price">$${product.price.toFixed(2)}</div>
                    <button class="mobile-add-cart-btn" data-product-id="${product.id}">
                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                    </button>
                </div>
            </div>
        `).join('');
        
        // Add touch event handlers
        container.addEventListener('click', (e) => {
            if (e.target.classList.contains('mobile-add-cart-btn')) {
                const productId = e.target.dataset.productId;
                this.addToMobileCart(productId);
            }
        });
    }
    
    /**
     * Initialize mobile categories with horizontal scroll
     */
    initializeMobileCategories() {
        const categories = [
            { name: 'Disposables', icon: 'fas fa-battery-three-quarters', active: true },
            { name: 'E-Liquids', icon: 'fas fa-tint' },
            { name: 'Pod Kits', icon: 'fas fa-microchip' },
            { name: 'Mods', icon: 'fas fa-cogs' },
            { name: 'Accessories', icon: 'fas fa-tools' },
            { name: 'New Arrivals', icon: 'fas fa-star' }
        ];
        
        const categoryContainer = document.createElement('div');
        categoryContainer.className = 'mobile-categories mobile-optimize-scroll';
        categoryContainer.innerHTML = categories.map(cat => `
            <a href="/category/${cat.name.toLowerCase()}" class="mobile-category-chip ${cat.active ? 'active' : ''}">
                <i class="${cat.icon} me-2"></i>
                ${cat.name}
            </a>
        `).join('');
        
        // Insert after header
        const header = document.querySelector('.mobile-header');
        header.insertAdjacentElement('afterend', categoryContainer);
        
        // Handle category selection
        categoryContainer.addEventListener('click', (e) => {
            e.preventDefault();
            const chip = e.target.closest('.mobile-category-chip');
            if (chip) {
                // Remove active from all chips
                categoryContainer.querySelectorAll('.mobile-category-chip').forEach(c => {
                    c.classList.remove('active');
                });
                
                // Add active to clicked chip
                chip.classList.add('active');
                
                console.log('📱 Category selected:', chip.textContent.trim());
            }
        });
    }
    
    /**
     * Setup touch gestures for mobile navigation
     */
    setupTouchGestures() {
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        
        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchmove', (e) => {
            if (!e.touches[0]) return;
            
            currentX = e.touches[0].clientX;
            currentY = e.touches[0].clientY;
            
            const diffX = startX - currentX;
            const diffY = startY - currentY;
            
            // Horizontal swipe for navigation
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > this.swipeThreshold) {
                if (diffX > 0) {
                    // Swipe left - next page or open cart
                    this.handleSwipeLeft();
                } else {
                    // Swipe right - previous page or open menu
                    this.handleSwipeRight();
                }
            }
            
            // Vertical swipe for refresh
            if (Math.abs(diffY) > Math.abs(diffX) && diffY < -this.swipeThreshold) {
                // Pull down - refresh
                this.handlePullToRefresh();
            }
        }, { passive: true });
        
        console.log('📱 Touch gestures initialized');
    }
    
    /**
     * Handle swipe left gesture
     */
    handleSwipeLeft() {
        // Open cart or navigate forward
        console.log('📱 Swipe left detected');
        // You could implement cart slide-in here
    }
    
    /**
     * Handle swipe right gesture  
     */
    handleSwipeRight() {
        const nav = document.querySelector('.mobile-nav');
        if (!nav.classList.contains('active')) {
            this.toggleMobileMenu();
        }
    }
    
    /**
     * Handle pull to refresh
     */
    handlePullToRefresh() {
        if (window.pageYOffset <= 0) {
            console.log('📱 Pull to refresh triggered');
            
            // Show refresh indicator
            const refreshIndicator = document.createElement('div');
            refreshIndicator.className = 'mobile-pull-refresh active';
            refreshIndicator.innerHTML = '<i class="fas fa-sync fa-spin"></i>';
            document.body.appendChild(refreshIndicator);
            
            // Simulate refresh
            setTimeout(() => {
                refreshIndicator.remove();
                this.showMobileNotification('Page refreshed!', 'success');
            }, 1500);
        }
    }
    
    /**
     * Optimize mobile performance
     */
    optimizeMobilePerformance() {
        // Lazy load images
        this.setupLazyLoading();
        
        // Debounce scroll events
        this.setupOptimizedScrolling();
        
        // Minimize DOM manipulation
        this.setupEfficientUpdates();
        
        // Preload critical resources
        this.preloadCriticalAssets();
        
        console.log('📱 Mobile performance optimizations applied');
    }
    
    /**
     * Setup lazy loading for images
     */
    setupLazyLoading() {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    /**
     * Setup optimized scrolling
     */
    setupOptimizedScrolling() {
        let ticking = false;
        
        const handleScroll = () => {
            this.scrollPosition = window.pageYOffset;
            this.updateScrollEffects();
            ticking = false;
        };
        
        const requestTick = () => {
            if (!ticking) {
                requestAnimationFrame(handleScroll);
                ticking = true;
            }
        };
        
        window.addEventListener('scroll', requestTick, { passive: true });
    }
    
    /**
     * Update scroll effects
     */
    updateScrollEffects() {
        const header = document.querySelector('.mobile-header');
        if (header) {
            if (this.scrollPosition > 100) {
                header.style.background = 'rgba(0, 51, 102, 0.98)';
                header.style.backdropFilter = 'blur(20px)';
            } else {
                header.style.background = 'rgba(0, 51, 102, 0.95)';
                header.style.backdropFilter = 'blur(10px)';
            }
        }
    }
    
    /**
     * Setup mobile animations
     */
    setupMobileAnimations() {
        // Stagger animations for product cards
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const animateOnScroll = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }, index * 100);
                    animateOnScroll.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        // Apply to product cards
        document.querySelectorAll('.mobile-product-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            animateOnScroll.observe(card);
        });
        
        console.log('📱 Mobile animations initialized');
    }
    
    /**
     * Initialize swipe navigation for product cards
     */
    initializeSwipeNavigation() {
        document.querySelectorAll('.mobile-product-card').forEach(card => {
            this.makeCardSwipeable(card);
        });
    }
    
    /**
     * Make product card swipeable
     */
    makeCardSwipeable(card) {
        let startX = 0;
        let currentTranslate = 0;
        let isDragging = false;
        
        card.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            isDragging = true;
            card.style.transition = 'none';
        });
        
        card.addEventListener('touchmove', (e) => {
            if (!isDragging) return;
            
            const currentX = e.touches[0].clientX;
            currentTranslate = currentX - startX;
            
            // Limit swipe distance
            if (Math.abs(currentTranslate) > 100) {
                currentTranslate = currentTranslate > 0 ? 100 : -100;
            }
            
            card.style.transform = `translateX(${currentTranslate}px)`;
        });
        
        card.addEventListener('touchend', () => {
            isDragging = false;
            card.style.transition = 'transform 0.2s ease';
            
            if (Math.abs(currentTranslate) > 50) {
                // Trigger action based on swipe direction
                if (currentTranslate > 0) {
                    // Swipe right - add to favorites
                    this.addToFavorites(card.dataset.productId);
                } else {
                    // Swipe left - add to cart
                    this.addToMobileCart(card.dataset.productId);
                }
            }
            
            // Reset position
            card.style.transform = 'translateX(0)';
            currentTranslate = 0;
        });
    }
    
    /**
     * Add product to mobile cart with animation
     */
    addToMobileCart(productId) {
        console.log('📱 Adding product to cart:', productId);
        
        // Update cart badge
        const cartBadges = document.querySelectorAll('.mobile-cart-badge');
        cartBadges.forEach(badge => {
            const currentCount = parseInt(badge.textContent) || 0;
            badge.textContent = currentCount + 1;
            
            // Animate badge
            badge.style.transform = 'scale(1.3)';
            setTimeout(() => {
                badge.style.transform = 'scale(1)';
            }, 200);
        });
        
        this.showMobileNotification('Added to cart!', 'success');
    }
    
    /**
     * Add product to favorites
     */
    addToFavorites(productId) {
        console.log('📱 Adding product to favorites:', productId);
        this.showMobileNotification('Added to favorites!', 'info');
    }
    
    /**
     * Show mobile notification
     */
    showMobileNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 70px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            z-index: 9999;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(() => {
            notification.style.transform = 'translateX(-50%) translateY(0)';
        }, 100);
        
        // Hide notification
        setTimeout(() => {
            notification.style.transform = 'translateX(-50%) translateY(-100%)';
            setTimeout(() => notification.remove(), 300);
        }, 2000);
    }
    
    /**
     * Open mobile cart
     */
    openMobileCart() {
        console.log('📱 Opening mobile cart');
        // Implement cart drawer/modal
        this.showMobileNotification('Cart opened!', 'info');
    }
    
    /**
     * Preload critical mobile assets
     */
    preloadCriticalAssets() {
        const criticalAssets = [
            '/images/logo-white.png',
            '/css/mobile-fonts.css',
            '/js/mobile-critical.js'
        ];
        
        criticalAssets.forEach(asset => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = asset;
            link.as = asset.includes('.css') ? 'style' : asset.includes('.js') ? 'script' : 'image';
            document.head.appendChild(link);
        });
    }
    
    /**
     * Setup efficient DOM updates
     */
    setupEfficientUpdates() {
        // Use DocumentFragment for batch updates
        this.documentFragment = document.createDocumentFragment();
        
        // Debounce resize events
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.handleMobileResize();
            }, 250);
        });
    }
    
    /**
     * Handle mobile resize events
     */
    handleMobileResize() {
        const newIsMobile = this.detectMobile();
        if (newIsMobile !== this.isMobile) {
            this.isMobile = newIsMobile;
            console.log('📱 Mobile state changed:', this.isMobile);
            // Reinitialize if needed
        }
    }
}

// Auto-initialize on mobile devices
document.addEventListener('DOMContentLoaded', () => {
    if (window.innerWidth <= 768 || /Mobi|Android/i.test(navigator.userAgent)) {
        window.vapeShedMobile = new VapeShedMobileExperience();
        console.log('📱 VapeShed Mobile Experience Activated for 70% mobile traffic optimization');
    }
});

// Handle orientation changes
window.addEventListener('orientationchange', () => {
    setTimeout(() => {
        if (window.vapeShedMobile) {
            window.vapeShedMobile.handleMobileResize();
        }
    }, 100);
});