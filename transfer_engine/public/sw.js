/**
 * VapeShed PWA Service Worker
 * Advanced caching strategy for mobile-first experience
 * Optimized for 70% mobile traffic with offline capabilities
 * 
 * Features:
 * - Smart caching strategies for different resource types
 * - Offline product browsing with cached data
 * - Background sync for orders and favorites
 * - Push notification support
 * - Network-first for dynamic content, cache-first for static assets
 * 
 * @version 1.0.0
 * @author VapeShed Development Team
 * @date 2025-01-27
 */

const CACHE_VERSION = 'vapeshed-pwa-v1.2.0';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const API_CACHE = `${CACHE_VERSION}-api`;
const IMAGE_CACHE = `${CACHE_VERSION}-images`;

// Cache duration in milliseconds
const CACHE_DURATIONS = {
    static: 7 * 24 * 60 * 60 * 1000, // 1 week
    dynamic: 24 * 60 * 60 * 1000,    // 1 day
    api: 5 * 60 * 1000,              // 5 minutes
    images: 30 * 24 * 60 * 60 * 1000 // 30 days
};

// Static assets to cache on install
const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/assets/css/vapeshed-mobile.css',
    '/assets/js/vapeshed-personalization.js',
    '/assets/js/mobile-experience-engine.js',
    '/assets/images/logo-vapeshed.png',
    '/assets/pwa/icon-192x192.png',
    '/assets/pwa/icon-512x512.png',
    '/dashboard',
    '/stores',
    '/products',
    '/offline.html'
];

// API endpoints to cache
const API_PATTERNS = [
    /\/api\/products/,
    /\/api\/customer/,
    /\/api\/recommendations/,
    /\/api\/search/,
    /\/api\/categories/
];

// Image patterns to cache
const IMAGE_PATTERNS = [
    /\.(?:png|jpg|jpeg|gif|webp|svg|ico)$/i,
    /\/images\//,
    /\/uploads\//,
    /\/media\//
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('[SW] Installing VapeShed PWA Service Worker v1.2.0');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Pre-caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting())
            .catch(error => {
                console.error('[SW] Failed to cache static assets:', error);
            })
    );
});

// Activate event - clean old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating VapeShed PWA Service Worker');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        // Delete old cache versions
                        if (cacheName.startsWith('vapeshed-pwa-') && 
                            cacheName !== STATIC_CACHE && 
                            cacheName !== DYNAMIC_CACHE && 
                            cacheName !== API_CACHE && 
                            cacheName !== IMAGE_CACHE) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Taking control of all pages');
                return self.clients.claim();
            })
    );
});

// Fetch event - intelligent caching strategies
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests and extension requests
    if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
        return;
    }
    
    // Handle different resource types with appropriate strategies
    if (isStaticAsset(url)) {
        event.respondWith(handleStaticAssets(request));
    } else if (isApiRequest(url)) {
        event.respondWith(handleApiRequests(request));
    } else if (isImageRequest(url)) {
        event.respondWith(handleImageRequests(request));
    } else {
        event.respondWith(handleDynamicContent(request));
    }
});

// Background Sync - handle offline actions
self.addEventListener('sync', event => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    switch (event.tag) {
        case 'add-to-favorites':
            event.waitUntil(syncFavorites());
            break;
        case 'track-analytics':
            event.waitUntil(syncAnalytics());
            break;
        case 'update-profile':
            event.waitUntil(syncProfile());
            break;
        default:
            console.log('[SW] Unknown sync tag:', event.tag);
    }
});

// Push notifications
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
    
    const options = {
        body: 'You have new recommendations waiting!',
        icon: '/assets/pwa/icon-192x192.png',
        badge: '/assets/pwa/badge-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            url: '/dashboard'
        },
        actions: [
            {
                action: 'view',
                title: 'View Now',
                icon: '/assets/pwa/action-view.png'
            },
            {
                action: 'dismiss',
                title: 'Later',
                icon: '/assets/pwa/action-dismiss.png'
            }
        ]
    };
    
    if (event.data) {
        const payload = event.data.json();
        options.body = payload.message || options.body;
        options.data.url = payload.url || options.data.url;
    }
    
    event.waitUntil(
        self.registration.showNotification('The Vape Shed', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked:', event.action);
    
    event.notification.close();
    
    if (event.action === 'view') {
        event.waitUntil(
            clients.openWindow(event.notification.data.url)
        );
    }
});

// Utility functions
function isStaticAsset(url) {
    return url.pathname.match(/\.(css|js|woff|woff2|ttf|eot)$/i) ||
           STATIC_ASSETS.includes(url.pathname);
}

function isApiRequest(url) {
    return API_PATTERNS.some(pattern => pattern.test(url.pathname));
}

function isImageRequest(url) {
    return IMAGE_PATTERNS.some(pattern => pattern.test(url.pathname));
}

// Cache-first strategy for static assets
async function handleStaticAssets(request) {
    try {
        const cache = await caches.open(STATIC_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse && !isExpired(cachedResponse, CACHE_DURATIONS.static)) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const responseClone = networkResponse.clone();
            await cache.put(request, responseClone);
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Static asset fetch failed:', error);
        const cache = await caches.open(STATIC_CACHE);
        return await cache.match(request) || new Response('Asset not available offline');
    }
}

// Network-first with cache fallback for API requests
async function handleApiRequests(request) {
    try {
        const networkResponse = await fetch(request, {
            headers: {
                ...request.headers,
                'Cache-Control': 'no-cache'
            }
        });
        
        if (networkResponse.ok) {
            const cache = await caches.open(API_CACHE);
            const responseClone = networkResponse.clone();
            await cache.put(request, responseClone);
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] API request failed, using cache:', error);
        const cache = await caches.open(API_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse && !isExpired(cachedResponse, CACHE_DURATIONS.api)) {
            return cachedResponse;
        }
        
        return new Response(JSON.stringify({
            error: 'Offline',
            message: 'This feature requires internet connection',
            cached: false
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Cache-first for images with long-term storage
async function handleImageRequests(request) {
    try {
        const cache = await caches.open(IMAGE_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse && !isExpired(cachedResponse, CACHE_DURATIONS.images)) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const responseClone = networkResponse.clone();
            await cache.put(request, responseClone);
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Image fetch failed:', error);
        const cache = await caches.open(IMAGE_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return placeholder image
        return fetch('/assets/images/placeholder.png');
    }
}

// Network-first for dynamic content
async function handleDynamicContent(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            const responseClone = networkResponse.clone();
            await cache.put(request, responseClone);
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Dynamic content failed, using cache:', error);
        const cache = await caches.open(DYNAMIC_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse && !isExpired(cachedResponse, CACHE_DURATIONS.dynamic)) {
            return cachedResponse;
        }
        
        // Return offline page
        return caches.match('/offline.html');
    }
}

// Background sync functions
async function syncFavorites() {
    try {
        const favorites = await getStoredData('pendingFavorites');
        
        if (favorites && favorites.length > 0) {
            for (const favorite of favorites) {
                await fetch('/api/favorites', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(favorite)
                });
            }
            
            await clearStoredData('pendingFavorites');
            console.log('[SW] Favorites synced successfully');
        }
    } catch (error) {
        console.error('[SW] Failed to sync favorites:', error);
    }
}

async function syncAnalytics() {
    try {
        const events = await getStoredData('pendingAnalytics');
        
        if (events && events.length > 0) {
            await fetch('/api/analytics/batch', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ events })
            });
            
            await clearStoredData('pendingAnalytics');
            console.log('[SW] Analytics synced successfully');
        }
    } catch (error) {
        console.error('[SW] Failed to sync analytics:', error);
    }
}

async function syncProfile() {
    try {
        const profileData = await getStoredData('pendingProfile');
        
        if (profileData) {
            await fetch('/api/customer/profile', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(profileData)
            });
            
            await clearStoredData('pendingProfile');
            console.log('[SW] Profile synced successfully');
        }
    } catch (error) {
        console.error('[SW] Failed to sync profile:', error);
    }
}

// Utility functions
function isExpired(response, maxAge) {
    const responseDate = new Date(response.headers.get('date'));
    return Date.now() - responseDate.getTime() > maxAge;
}

async function getStoredData(key) {
    return new Promise((resolve) => {
        const channel = new BroadcastChannel('vapeshed-storage');
        
        channel.postMessage({ action: 'get', key });
        
        channel.onmessage = (event) => {
            if (event.data.action === 'response' && event.data.key === key) {
                resolve(event.data.value);
                channel.close();
            }
        };
        
        setTimeout(() => {
            resolve(null);
            channel.close();
        }, 1000);
    });
}

async function clearStoredData(key) {
    const channel = new BroadcastChannel('vapeshed-storage');
    channel.postMessage({ action: 'clear', key });
    channel.close();
}

// Cache cleanup on low storage
if ('storage' in navigator && 'estimate' in navigator.storage) {
    navigator.storage.estimate().then(estimate => {
        const usagePercentage = (estimate.usage / estimate.quota) * 100;
        
        if (usagePercentage > 80) {
            console.log('[SW] Storage usage high, cleaning old caches');
            cleanupOldCaches();
        }
    });
}

async function cleanupOldCaches() {
    const cacheNames = await caches.keys();
    const oldCaches = cacheNames.filter(name => 
        name.startsWith('vapeshed-pwa-') && 
        !name.includes(CACHE_VERSION)
    );
    
    await Promise.all(oldCaches.map(cache => caches.delete(cache)));
    console.log('[SW] Cleaned up old caches:', oldCaches);
}