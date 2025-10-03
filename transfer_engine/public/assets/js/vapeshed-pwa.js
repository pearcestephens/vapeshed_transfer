/**
 * VapeShed PWA Manager
 * Handles Progressive Web App installation, updates, and offline features
 * Integrates with mobile experience engine for seamless app-like experience
 * 
 * Features:
 * - Install prompt management with smart timing
 * - Service worker registration and updates
 * - Offline capability detection
 * - Background sync coordination
 * - Push notification setup
 * - App lifecycle management
 * 
 * @version 1.0.0
 * @requires mobile-experience-engine.js
 * @requires vapeshed-personalization.js
 */

class VapeShedPWA {
    constructor() {
        this.installPrompt = null;
        this.isInstalled = false;
        this.isOnline = navigator.onLine;
        this.serviceWorker = null;
        this.storageChannel = null;
        
        this.init();
    }
    
    async init() {
        console.log('🚀 Initializing VapeShed PWA Manager');
        
        // Check if already installed
        this.checkInstallationStatus();
        
        // Register service worker
        await this.registerServiceWorker();
        
        // Setup install prompt handling
        this.setupInstallPrompt();
        
        // Setup offline/online detection
        this.setupNetworkDetection();
        
        // Setup storage communication
        this.setupStorageChannel();
        
        // Setup push notifications
        this.setupPushNotifications();
        
        // Setup app shortcuts
        this.setupAppShortcuts();
        
        // Initialize update checking
        this.setupUpdateChecking();
        
        console.log('✅ VapeShed PWA Manager initialized');
    }
    
    checkInstallationStatus() {
        // Check if app is installed
        if (window.matchMedia('(display-mode: standalone)').matches ||
            window.matchMedia('(display-mode: fullscreen)').matches ||
            window.navigator.standalone === true) {
            this.isInstalled = true;
            document.body.classList.add('pwa-installed');
            console.log('📱 PWA is installed and running in standalone mode');
        }
        
        // Check if install available
        this.checkInstallAvailability();
    }
    
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/',
                    updateViaCache: 'none'
                });
                
                this.serviceWorker = registration;
                console.log('✅ Service Worker registered successfully');
                
                // Handle service worker updates
                registration.addEventListener('updatefound', () => {
                    console.log('🔄 New Service Worker version found');
                    this.handleServiceWorkerUpdate(registration);
                });
                
                // Check for existing service worker
                if (registration.waiting) {
                    console.log('⏳ Service Worker waiting to activate');
                    this.showUpdateAvailable();
                }
                
                if (registration.active) {
                    console.log('✅ Service Worker is active');
                }
                
            } catch (error) {
                console.error('❌ Service Worker registration failed:', error);
            }
        } else {
            console.warn('⚠️ Service Workers not supported');
        }
    }
    
    setupInstallPrompt() {
        // Listen for beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (event) => {
            console.log('📲 Install prompt available');
            
            // Prevent automatic prompt
            event.preventDefault();
            
            // Store for later use
            this.installPrompt = event;
            
            // Show custom install UI after user engagement
            this.scheduleInstallPrompt();
        });
        
        // Listen for app installed event
        window.addEventListener('appinstalled', () => {
            console.log('🎉 PWA installed successfully');
            this.isInstalled = true;
            document.body.classList.add('pwa-installed');
            this.hideInstallPrompt();
            
            // Track installation
            this.trackEvent('pwa_installed', {
                source: 'install_prompt',
                timestamp: Date.now()
            });
        });
    }
    
    scheduleInstallPrompt() {
        // Smart timing: Show after user has engaged with the site
        const engagementMetrics = this.getEngagementMetrics();
        
        if (engagementMetrics.pageViews >= 3 || 
            engagementMetrics.timeSpent >= 60000 || // 1 minute
            engagementMetrics.interactions >= 5) {
            
            setTimeout(() => {
                this.showInstallPrompt();
            }, 2000);
        } else {
            // Schedule check for later
            setTimeout(() => {
                this.scheduleInstallPrompt();
            }, 30000); // Check every 30 seconds
        }
    }
    
    showInstallPrompt() {
        if (!this.installPrompt || this.isInstalled) return;
        
        const installBanner = document.createElement('div');
        installBanner.id = 'pwa-install-banner';
        installBanner.className = 'pwa-install-banner';
        installBanner.innerHTML = `
            <div class="install-content">
                <div class="install-icon">
                    <img src="/assets/pwa/icon-72x72.png" alt="VapeShed App">
                </div>
                <div class="install-text">
                    <h4>Install VapeShed App</h4>
                    <p>Get faster access and offline browsing</p>
                </div>
                <div class="install-actions">
                    <button class="btn-install" onclick="vapeShedPWA.triggerInstall()">Install</button>
                    <button class="btn-dismiss" onclick="vapeShedPWA.dismissInstall()">Not Now</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(installBanner);
        
        // Animate in
        setTimeout(() => {
            installBanner.classList.add('show');
        }, 100);
    }
    
    async triggerInstall() {
        if (!this.installPrompt) return;
        
        try {
            // Trigger install prompt
            const result = await this.installPrompt.prompt();
            console.log('📱 Install prompt result:', result.outcome);
            
            if (result.outcome === 'accepted') {
                console.log('✅ User accepted install');
                this.trackEvent('pwa_install_accepted');
            } else {
                console.log('❌ User dismissed install');
                this.trackEvent('pwa_install_dismissed');
            }
            
            // Clear the prompt
            this.installPrompt = null;
            this.hideInstallPrompt();
            
        } catch (error) {
            console.error('❌ Install prompt failed:', error);
        }
    }
    
    dismissInstall() {
        this.hideInstallPrompt();
        this.trackEvent('pwa_install_dismissed');
        
        // Don't show again for 7 days
        localStorage.setItem('pwa_install_dismissed', Date.now() + (7 * 24 * 60 * 60 * 1000));
    }
    
    hideInstallPrompt() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) {
            banner.classList.remove('show');
            setTimeout(() => banner.remove(), 300);
        }
    }
    
    checkInstallAvailability() {
        const dismissedUntil = localStorage.getItem('pwa_install_dismissed');
        if (dismissedUntil && Date.now() < parseInt(dismissedUntil)) {
            console.log('⏭️ Install prompt dismissed, waiting until', new Date(parseInt(dismissedUntil)));
            return false;
        }
        return true;
    }
    
    setupNetworkDetection() {
        // Network status detection
        window.addEventListener('online', () => {
            console.log('🌐 Back online');
            this.isOnline = true;
            document.body.classList.remove('offline');
            document.body.classList.add('online');
            
            // Trigger background sync
            this.triggerBackgroundSync();
            
            this.showConnectionStatus('Connected', 'success');
        });
        
        window.addEventListener('offline', () => {
            console.log('📵 Gone offline');
            this.isOnline = false;
            document.body.classList.remove('online');
            document.body.classList.add('offline');
            
            this.showConnectionStatus('Offline Mode', 'warning');
        });
        
        // Initial state
        document.body.classList.add(this.isOnline ? 'online' : 'offline');
    }
    
    setupStorageChannel() {
        // Communication channel with service worker
        this.storageChannel = new BroadcastChannel('vapeshed-storage');
        
        this.storageChannel.addEventListener('message', (event) => {
            const { action, key, value } = event.data;
            
            if (action === 'get') {
                const data = localStorage.getItem(key);
                this.storageChannel.postMessage({
                    action: 'response',
                    key: key,
                    value: data ? JSON.parse(data) : null
                });
            } else if (action === 'clear') {
                localStorage.removeItem(key);
            }
        });
    }
    
    async setupPushNotifications() {
        if (!('Notification' in window) || !this.serviceWorker) {
            console.warn('⚠️ Push notifications not supported');
            return;
        }
        
        // Check permission status
        let permission = Notification.permission;
        
        if (permission === 'default') {
            // Request permission after user engagement
            setTimeout(async () => {
                if (this.getEngagementMetrics().interactions >= 3) {
                    permission = await this.requestNotificationPermission();
                }
            }, 60000); // Wait 1 minute
        }
        
        if (permission === 'granted') {
            await this.subscribeToPushNotifications();
        }
    }
    
    async requestNotificationPermission() {
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            console.log('✅ Notification permission granted');
            this.trackEvent('notification_permission_granted');
            await this.subscribeToPushNotifications();
        } else {
            console.log('❌ Notification permission denied');
            this.trackEvent('notification_permission_denied');
        }
        
        return permission;
    }
    
    async subscribeToPushNotifications() {
        try {
            const subscription = await this.serviceWorker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.getVapidPublicKey()
            });
            
            // Send subscription to server
            await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subscription: subscription,
                    customer_id: this.getCustomerId()
                })
            });
            
            console.log('✅ Push notification subscription created');
            this.trackEvent('push_subscription_created');
            
        } catch (error) {
            console.error('❌ Push subscription failed:', error);
        }
    }
    
    setupAppShortcuts() {
        // Add app shortcuts to home screen
        if ('shortcuts' in navigator) {
            console.log('✅ App shortcuts supported');
        }
        
        // Handle shortcut clicks
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.action === 'shortcut-click') {
                    this.handleShortcutClick(event.data.shortcut);
                }
            });
        }
    }
    
    handleShortcutClick(shortcut) {
        console.log('🔗 App shortcut clicked:', shortcut);
        this.trackEvent('app_shortcut_used', { shortcut });
    }
    
    setupUpdateChecking() {
        // Check for updates periodically
        setInterval(async () => {
            if (this.serviceWorker) {
                await this.serviceWorker.update();
            }
        }, 60000); // Check every minute
    }
    
    handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;
        
        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed') {
                console.log('🔄 New Service Worker installed');
                this.showUpdateAvailable();
            }
        });
    }
    
    showUpdateAvailable() {
        const updateBanner = document.createElement('div');
        updateBanner.id = 'pwa-update-banner';
        updateBanner.className = 'pwa-update-banner';
        updateBanner.innerHTML = `
            <div class="update-content">
                <div class="update-text">
                    <h4>App Update Available</h4>
                    <p>Refresh to get the latest features</p>
                </div>
                <div class="update-actions">
                    <button class="btn-update" onclick="vapeShedPWA.applyUpdate()">Update</button>
                    <button class="btn-dismiss" onclick="vapeShedPWA.dismissUpdate()">Later</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(updateBanner);
        
        setTimeout(() => {
            updateBanner.classList.add('show');
        }, 100);
    }
    
    applyUpdate() {
        // Skip waiting and activate new service worker
        if (this.serviceWorker && this.serviceWorker.waiting) {
            this.serviceWorker.waiting.postMessage({ action: 'skip-waiting' });
        }
        
        // Reload page
        window.location.reload();
    }
    
    dismissUpdate() {
        const banner = document.getElementById('pwa-update-banner');
        if (banner) {
            banner.classList.remove('show');
            setTimeout(() => banner.remove(), 300);
        }
    }
    
    showConnectionStatus(message, type) {
        // Remove existing status
        const existing = document.querySelector('.connection-status');
        if (existing) existing.remove();
        
        const status = document.createElement('div');
        status.className = `connection-status ${type}`;
        status.textContent = message;
        
        document.body.appendChild(status);
        
        // Auto-hide after 3 seconds
        setTimeout(() => {
            status.classList.add('fade-out');
            setTimeout(() => status.remove(), 300);
        }, 3000);
    }
    
    triggerBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            navigator.serviceWorker.ready.then((registration) => {
                registration.sync.register('add-to-favorites');
                registration.sync.register('track-analytics');
                registration.sync.register('update-profile');
            });
        }
    }
    
    // Utility methods
    getEngagementMetrics() {
        return {
            pageViews: parseInt(sessionStorage.getItem('pageViews') || '0'),
            timeSpent: parseInt(sessionStorage.getItem('timeSpent') || '0'),
            interactions: parseInt(sessionStorage.getItem('interactions') || '0')
        };
    }
    
    trackEvent(event, data = {}) {
        // Store analytics event
        const events = JSON.parse(localStorage.getItem('pendingAnalytics') || '[]');
        events.push({
            event: event,
            data: data,
            timestamp: Date.now(),
            url: window.location.href
        });
        localStorage.setItem('pendingAnalytics', JSON.stringify(events));
        
        // If online, send immediately
        if (this.isOnline) {
            this.triggerBackgroundSync();
        }
    }
    
    getVapidPublicKey() {
        // VAPID public key for push notifications
        return 'BKxKz9GXV0KN_5-VDL9K0Tr2Lw8J4TkHJ8K9-K5yYkTrK3PfY9PxSt1D6eF0aW2jQ8L7mN4C5xT9V3rR6sU2fG';
    }
    
    getCustomerId() {
        // Get customer ID from session or localStorage
        return localStorage.getItem('customer_id') || sessionStorage.getItem('customer_id');
    }
    
    // Public API methods
    addToFavorites(productId) {
        const favorites = JSON.parse(localStorage.getItem('pendingFavorites') || '[]');
        favorites.push({
            product_id: productId,
            timestamp: Date.now()
        });
        localStorage.setItem('pendingFavorites', JSON.stringify(favorites));
        
        this.triggerBackgroundSync();
    }
    
    updateProfile(profileData) {
        localStorage.setItem('pendingProfile', JSON.stringify(profileData));
        this.triggerBackgroundSync();
    }
    
    // Offline page detection
    isOfflineCapable() {
        return 'serviceWorker' in navigator && this.serviceWorker;
    }
}

// Initialize PWA manager
let vapeShedPWA;

document.addEventListener('DOMContentLoaded', () => {
    vapeShedPWA = new VapeShedPWA();
    
    // Track page engagement
    let pageLoadTime = Date.now();
    let interactions = 0;
    
    // Track page views
    const pageViews = parseInt(sessionStorage.getItem('pageViews') || '0') + 1;
    sessionStorage.setItem('pageViews', pageViews);
    
    // Track interactions
    ['click', 'touch', 'scroll'].forEach(event => {
        document.addEventListener(event, () => {
            interactions++;
            sessionStorage.setItem('interactions', interactions);
        }, { once: false, passive: true });
    });
    
    // Track time spent
    window.addEventListener('beforeunload', () => {
        const timeSpent = Date.now() - pageLoadTime;
        const totalTime = parseInt(sessionStorage.getItem('timeSpent') || '0') + timeSpent;
        sessionStorage.setItem('timeSpent', totalTime);
    });
});

// Export for global access
window.vapeShedPWA = vapeShedPWA;