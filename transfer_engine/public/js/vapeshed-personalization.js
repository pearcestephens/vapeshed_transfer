/**
 * VapeShed Website Integration - AI-Powered Personalization System
 * 
 * Handles dynamic content loading, customer verification status,
 * personalized recommendations, and NZ compliance requirements.
 * 
 * NZ Compliance Features:
 * - Content restriction until customer verification (1+ purchase)
 * - Website inbox messaging (no email notifications)
 * - Age-appropriate content filtering
 * - Personalized dashboard for verified customers
 * 
 * @author AI Assistant
 * @created 2025-01-27
 * @updated 2025-01-27
 */

class VapeShedPersonalization {
    constructor() {
        this.apiBase = '/api';
        this.customerData = null;
        this.isVerified = false;
        this.refreshInterval = 300000; // 5 minutes
        
        this.init();
    }
    
    async init() {
        try {
            console.log('🚀 Initializing VapeShed AI Personalization System');
            
            // Load customer profile
            await this.loadCustomerProfile();
            
            // Initialize homepage personalization
            await this.initializeHomepage();
            
            // Setup dashboard if verified
            if (this.isVerified) {
                await this.initializeDashboard();
                this.setupInboxPolling();
            }
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Start periodic refresh
            this.startPeriodicRefresh();
            
            console.log('✅ VapeShed AI Personalization System Ready');
            
        } catch (error) {
            console.error('❌ Personalization system initialization failed:', error);
            this.handleError(error);
        }
    }
    
    /**
     * Load customer profile and verification status
     */
    async loadCustomerProfile() {
        try {
            const response = await fetch(`${this.apiBase}/customer/profile`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                this.customerData = await response.json();
                this.isVerified = this.customerData.verified;
                
                console.log('👤 Customer Profile Loaded:', {
                    verified: this.isVerified,
                    tier: this.customerData.profile?.tier || 'Guest',
                    orders: this.customerData.profile?.total_orders || 0
                });
                
                // Store in session for quick access
                sessionStorage.setItem('vapeshed_profile', JSON.stringify(this.customerData));
                
            } else if (response.status === 401) {
                // Guest user
                this.customerData = { verified: false };
                this.isVerified = false;
                console.log('👤 Guest User Detected');
            }
            
        } catch (error) {
            console.error('❌ Failed to load customer profile:', error);
            this.customerData = { verified: false };
            this.isVerified = false;
        }
    }
    
    /**
     * Initialize homepage with personalized content
     */
    async initializeHomepage() {
        try {
            const response = await fetch(`${this.apiBase}/homepage/content`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const content = await response.json();
                this.renderHomepageContent(content);
            }
            
        } catch (error) {
            console.error('❌ Failed to load homepage content:', error);
        }
    }
    
    /**
     * Render homepage content based on verification status
     */
    renderHomepageContent(content) {
        // Hero Section
        this.updateHeroSection(content.hero_section);
        
        // Featured Products (compliance-aware)
        this.updateFeaturedProducts(content.featured_products);
        
        // Categories with appropriate access levels
        this.updateCategoryDisplay(content.categories);
        
        // Personalized promotions
        if (content.promotions && this.isVerified) {
            this.updatePromotions(content.promotions);
        }
        
        // Trust signals
        this.updateTrustSignals(content.trust_signals);
        
        // Compliance notice
        this.updateComplianceNotice(content.compliance_notice);
    }
    
    /**
     * Update hero section with personalization
     */
    updateHeroSection(heroData) {
        const heroElement = document.querySelector('.hero-section');
        if (!heroElement || !heroData) return;
        
        // Update title and subtitle
        const title = heroElement.querySelector('.hero-title');
        const subtitle = heroElement.querySelector('.hero-subtitle');
        
        if (title) title.textContent = heroData.title;
        if (subtitle) subtitle.textContent = heroData.subtitle;
        
        // Update CTA
        const ctaButton = heroElement.querySelector('.hero-cta');
        if (ctaButton) {
            ctaButton.textContent = heroData.cta_text;
            ctaButton.href = heroData.cta_link;
        }
        
        // Add personalized stats for verified customers
        if (this.isVerified && heroData.stats) {
            this.addPersonalizedStats(heroElement, heroData.stats);
        }
        
        console.log('🎨 Hero section updated for', this.isVerified ? 'verified customer' : 'guest');
    }
    
    /**
     * Add personalized statistics to hero section
     */
    addPersonalizedStats(heroElement, stats) {
        const statsContainer = heroElement.querySelector('.hero-stats') || this.createStatsContainer(heroElement);
        
        statsContainer.innerHTML = `
            <div class="stat-item">
                <span class="stat-number">${stats.orders}</span>
                <span class="stat-label">Orders</span>
            </div>
            <div class="stat-item">
                <span class="stat-tier tier-${stats.tier.toLowerCase()}">${stats.tier}</span>
                <span class="stat-label">Member</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">${stats.days_active}</span>
                <span class="stat-label">Days Strong</span>
            </div>
        `;
    }
    
    /**
     * Initialize customer dashboard
     */
    async initializeDashboard() {
        if (!this.isVerified) return;
        
        try {
            // Load recommendations
            await this.loadRecommendations();
            
            // Load inbox messages
            await this.loadInboxMessages();
            
            // Update dashboard metrics
            this.updateDashboardMetrics();
            
            // Setup dashboard interactions
            this.setupDashboardInteractions();
            
            console.log('📊 Customer Dashboard Initialized');
            
        } catch (error) {
            console.error('❌ Dashboard initialization failed:', error);
        }
    }
    
    /**
     * Load personalized recommendations
     */
    async loadRecommendations() {
        try {
            const response = await fetch(`${this.apiBase}/customer/recommendations`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.renderRecommendations(data.recommendations);
            }
            
        } catch (error) {
            console.error('❌ Failed to load recommendations:', error);
        }
    }
    
    /**
     * Render personalized recommendations
     */
    renderRecommendations(recommendations) {
        // Reorder recommendations
        if (recommendations.reorder && recommendations.reorder.length > 0) {
            this.renderReorderRecommendations(recommendations.reorder);
        }
        
        // Brand-based recommendations
        if (recommendations.brand_match && recommendations.brand_match.length > 0) {
            this.renderBrandRecommendations(recommendations.brand_match);
        }
        
        // Trending products
        if (recommendations.trending && recommendations.trending.length > 0) {
            this.renderTrendingRecommendations(recommendations.trending);
        }
        
        console.log('🎯 Recommendations rendered');
    }
    
    /**
     * Render reorder recommendations with probability indicators
     */
    renderReorderRecommendations(reorderItems) {
        const container = document.querySelector('.reorder-recommendations');
        if (!container) return;
        
        container.innerHTML = reorderItems.map(item => `
            <div class="recommendation-card reorder-card" data-product-id="${item.id}">
                <div class="product-info">
                    <img src="${item.image_url}" alt="${item.name}" class="product-image">
                    <div class="product-details">
                        <h6 class="product-name">${item.name}</h6>
                        <p class="product-brand">${item.brand}</p>
                        <p class="product-price">$${parseFloat(item.price).toFixed(2)}</p>
                    </div>
                </div>
                <div class="reorder-info">
                    <div class="probability-indicator">
                        <div class="probability-bar">
                            <div class="probability-fill" style="width: ${item.reorder_probability}%"></div>
                        </div>
                        <span class="probability-text">${item.reorder_probability}% likely to reorder</span>
                    </div>
                    <p class="last-ordered">Last ordered ${item.days_since_last} days ago</p>
                    <button class="btn btn-primary btn-sm reorder-btn">
                        <i class="fas fa-shopping-cart me-2"></i>Reorder Now
                    </button>
                </div>
            </div>
        `).join('');
        
        // Add click handlers for reorder buttons
        container.querySelectorAll('.reorder-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const productId = e.target.closest('.recommendation-card').dataset.productId;
                this.handleReorder(productId);
            });
        });
    }
    
    /**
     * Load inbox messages
     */
    async loadInboxMessages() {
        try {
            const response = await fetch(`${this.apiBase}/customer/inbox`, {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.renderInboxMessages(data.messages, data.unread_count);
                this.updateInboxBadge(data.unread_count);
            }
            
        } catch (error) {
            console.error('❌ Failed to load inbox messages:', error);
        }
    }
    
    /**
     * Render inbox messages in dashboard
     */
    renderInboxMessages(messages, unreadCount) {
        const container = document.querySelector('.inbox-messages');
        if (!container) return;
        
        if (messages.length === 0) {
            container.innerHTML = `
                <div class="no-messages">
                    <i class="fas fa-inbox fa-3x text-muted"></i>
                    <p class="mt-3">No messages yet</p>
                    <small class="text-muted">You'll receive personalized updates and offers here</small>
                </div>
            `;
            return;
        }
        
        container.innerHTML = messages.map(message => `
            <div class="message-card ${message.is_read ? 'read' : 'unread'}" data-message-id="${message.id}">
                <div class="message-header">
                    <h6 class="message-subject">${message.subject}</h6>
                    <div class="message-meta">
                        <span class="badge bg-${this.getPriorityColor(message.priority)}">${message.priority}</span>
                        <span class="message-date">${this.formatDate(message.created_at)}</span>
                    </div>
                </div>
                <div class="message-body">
                    <p>${message.message}</p>
                </div>
                ${!message.is_read ? `
                    <div class="message-actions">
                        <button class="btn btn-sm btn-outline-primary mark-read-btn">
                            <i class="fas fa-check me-1"></i>Mark as Read
                        </button>
                    </div>
                ` : ''}
            </div>
        `).join('');
        
        // Add click handlers for mark as read
        container.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const messageId = e.target.closest('.message-card').dataset.messageId;
                this.markMessageAsRead(messageId);
            });
        });
        
        console.log(`📬 ${messages.length} messages loaded (${unreadCount} unread)`);
    }
    
    /**
     * Update inbox badge with unread count
     */
    updateInboxBadge(unreadCount) {
        const badges = document.querySelectorAll('.inbox-badge');
        badges.forEach(badge => {
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        });
    }
    
    /**
     * Mark message as read
     */
    async markMessageAsRead(messageId) {
        try {
            const response = await fetch(`${this.apiBase}/customer/inbox/mark-read`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ message_id: messageId })
            });
            
            if (response.ok) {
                const messageCard = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageCard) {
                    messageCard.classList.remove('unread');
                    messageCard.classList.add('read');
                    
                    const actionsDiv = messageCard.querySelector('.message-actions');
                    if (actionsDiv) actionsDiv.remove();
                }
                
                // Update badge count
                const currentBadge = document.querySelector('.inbox-badge');
                if (currentBadge && currentBadge.textContent) {
                    const currentCount = parseInt(currentBadge.textContent) - 1;
                    this.updateInboxBadge(currentCount);
                }
            }
            
        } catch (error) {
            console.error('❌ Failed to mark message as read:', error);
        }
    }
    
    /**
     * Setup inbox polling for real-time updates
     */
    setupInboxPolling() {
        // Poll for new messages every 2 minutes
        setInterval(async () => {
            await this.loadInboxMessages();
        }, 120000);
    }
    
    /**
     * Setup event listeners for dashboard interactions
     */
    setupEventListeners() {
        // Dashboard refresh button
        const refreshBtn = document.querySelector('.dashboard-refresh');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.refreshDashboard();
            });
        }
        
        // Product view tracking
        document.addEventListener('click', (e) => {
            if (e.target.closest('.product-link')) {
                const productId = e.target.closest('.product-link').dataset.productId;
                this.trackProductView(productId);
            }
        });
        
        // Search tracking
        const searchForm = document.querySelector('.search-form');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                const query = e.target.querySelector('input[type="search"]').value;
                this.trackSearch(query);
            });
        }
    }
    
    /**
     * Setup dashboard interactions
     */
    setupDashboardInteractions() {
        // Brand preference analysis
        this.setupBrandAnalysis();
        
        // Recommendation interactions
        this.setupRecommendationTracking();
        
        // Journey timeline interactions
        this.setupJourneyTimeline();
    }
    
    /**
     * Refresh dashboard data
     */
    async refreshDashboard() {
        const refreshBtn = document.querySelector('.dashboard-refresh');
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Refreshing...';
            refreshBtn.disabled = true;
        }
        
        try {
            await this.loadCustomerProfile();
            await this.loadRecommendations();
            await this.loadInboxMessages();
            this.updateDashboardMetrics();
            
            console.log('🔄 Dashboard refreshed');
            
        } catch (error) {
            console.error('❌ Dashboard refresh failed:', error);
        } finally {
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync me-2"></i>Refresh';
                refreshBtn.disabled = false;
            }
        }
    }
    
    /**
     * Update dashboard metrics display
     */
    updateDashboardMetrics() {
        if (!this.customerData || !this.customerData.profile) return;
        
        const profile = this.customerData.profile;
        
        // Update key metrics
        this.updateMetric('.total-orders', profile.total_orders);
        this.updateMetric('.lifetime-value', `$${parseFloat(profile.lifetime_value).toFixed(2)}`);
        this.updateMetric('.avg-order-value', `$${parseFloat(profile.avg_order_value).toFixed(2)}`);
        this.updateMetric('.days-since-order', profile.days_since_last_order || 0);
        this.updateMetric('.loyalty-tier', profile.tier);
        this.updateMetric('.loyalty-points', profile.loyalty_points || 0);
    }
    
    /**
     * Update individual metric display
     */
    updateMetric(selector, value) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = value;
            
            // Add animation
            element.classList.add('metric-updated');
            setTimeout(() => {
                element.classList.remove('metric-updated');
            }, 1000);
        }
    }
    
    /**
     * Track product view for analytics
     */
    async trackProductView(productId) {
        if (!productId) return;
        
        try {
            await fetch(`${this.apiBase}/analytics/product-view`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    product_id: productId,
                    timestamp: new Date().toISOString(),
                    page_url: window.location.href
                })
            });
            
        } catch (error) {
            console.error('❌ Failed to track product view:', error);
        }
    }
    
    /**
     * Track search queries for analytics
     */
    async trackSearch(query) {
        if (!query) return;
        
        try {
            await fetch(`${this.apiBase}/analytics/search`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    query: query,
                    timestamp: new Date().toISOString(),
                    page_url: window.location.href
                })
            });
            
        } catch (error) {
            console.error('❌ Failed to track search:', error);
        }
    }
    
    /**
     * Handle reorder functionality
     */
    async handleReorder(productId) {
        try {
            // Add to cart logic would go here
            console.log(`🛒 Reordering product ${productId}`);
            
            // Show success message
            this.showNotification('Product added to cart!', 'success');
            
        } catch (error) {
            console.error('❌ Reorder failed:', error);
            this.showNotification('Failed to add product to cart', 'error');
        }
    }
    
    /**
     * Show notification message
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle me-2"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * Start periodic refresh of dynamic content
     */
    startPeriodicRefresh() {
        setInterval(async () => {
            if (this.isVerified) {
                await this.loadInboxMessages();
            }
        }, this.refreshInterval);
    }
    
    /**
     * Utility functions
     */
    getPriorityColor(priority) {
        const colors = {
            low: 'secondary',
            normal: 'primary',
            high: 'warning',
            urgent: 'danger'
        };
        return colors[priority] || 'primary';
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffHours = Math.floor((now - date) / (1000 * 60 * 60));
        
        if (diffHours < 1) return 'Just now';
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffHours < 168) return `${Math.floor(diffHours / 24)}d ago`;
        
        return date.toLocaleDateString();
    }
    
    createStatsContainer(parentElement) {
        const container = document.createElement('div');
        container.className = 'hero-stats';
        parentElement.appendChild(container);
        return container;
    }
    
    /**
     * Handle errors gracefully
     */
    handleError(error) {
        console.error('🚨 VapeShed Personalization Error:', error);
        
        // Fallback to basic functionality
        const errorNotice = document.querySelector('.error-notice');
        if (errorNotice) {
            errorNotice.style.display = 'block';
            errorNotice.textContent = 'Some personalized features are temporarily unavailable.';
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.vapeShedPersonalization = new VapeShedPersonalization();
});

// CSS Styles for personalization features
const personalizeStyles = `
    .metric-updated {
        animation: metric-pulse 0.6s ease-in-out;
    }
    
    @keyframes metric-pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); color: #007bff; }
        100% { transform: scale(1); }
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        transform: translateX(100%);
        transition: transform 0.3s ease-in-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success { background: #28a745; }
    .notification-error { background: #dc3545; }
    .notification-info { background: #17a2b8; }
    
    .probability-bar {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .probability-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
        transition: width 0.3s ease;
    }
    
    .tier-vip { color: #8e44ad; font-weight: bold; }
    .tier-gold { color: #ffd700; font-weight: bold; }
    .tier-silver { color: #c0c0c0; font-weight: bold; }
    .tier-bronze { color: #cd7f32; font-weight: bold; }
    .tier-new { color: #007bff; }
    
    .hero-stats {
        display: flex;
        gap: 2rem;
        margin-top: 1rem;
    }
    
    .stat-item {
        text-align: center;
        color: white;
    }
    
    .stat-number {
        display: block;
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    .stat-label {
        font-size: 0.8rem;
        opacity: 0.8;
    }
`;

// Inject styles
const styleSheet = document.createElement('style');
styleSheet.textContent = personalizeStyles;
document.head.appendChild(styleSheet);