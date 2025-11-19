// Enhanced Service Worker for JuaKali Lend PWA
const CACHE_VERSION = 'v2.0.1';
const STATIC_CACHE = `juakali-static-${CACHE_VERSION}`;
const API_CACHE = `juakali-api-${CACHE_VERSION}`;
const IMAGE_CACHE = `juakali-images-${CACHE_VERSION}`;

// Cache strategies with fallbacks
const cacheStrategies = {
    static: [
        '/',
        '/mobile/index.php',
        '/mobile/manifest.json',
        '/assets/css/custom.css',
        '/assets/css/mobile.css',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
    ],
    api: [
        '/api/',
        'https://juakali-lend.test/api/'
    ],
    images: [
        '/assets/images/',
        'data:image/'
    ]
};

// Network timeout configuration
const NETWORK_TIMEOUT = 10000; // 10 seconds
const API_TIMEOUT = 15000; // 15 seconds

// Install Service Worker with comprehensive caching
self.addEventListener('install', event => {
    console.log(`SW: Installing version ${CACHE_VERSION}`);

    event.waitUntil(
        Promise.all([
            // Cache static assets
            caches.open(STATIC_CACHE).then(cache => {
                console.log('SW: Caching static assets');
                return cache.addAll(cacheStrategies.static);
            }),
            // Pre-cache critical API endpoints
            cacheCriticalAPIs(),
            // Pre-cache common images
            cacheCommonImages()
        ]).then(() => {
            console.log('SW: Installation complete');
            return self.skipWaiting();
        }).catch(error => {
            console.error('SW: Installation failed:', error);
        })
    );
});

// Cache critical API endpoints during installation
async function cacheCriticalAPIs() {
    const cache = await caches.open(API_CACHE);
    const criticalAPIs = [
        '/api/users/login.php',
        '/api/notifications/list.php',
        '/api/analytics/dashboard.php'
    ];

    for (const api of criticalAPIs) {
        try {
            const response = await fetch(api, { method: 'GET', timeout: 5000 });
            if (response.ok) {
                await cache.put(api, response);
            }
        } catch (error) {
            console.warn(`SW: Failed to cache API ${api}:`, error);
        }
    }
}

// Cache common images during installation
async function cacheCommonImages() {
    const cache = await caches.open(IMAGE_CACHE);
    const commonImages = [
        '/assets/images/icon-192.png',
        '/assets/images/icon-512.png',
        '/assets/images/logo.png'
    ];

    for (const image of commonImages) {
        try {
            const response = await fetch(image, { timeout: 3000 });
            if (response.ok) {
                await cache.put(image, response);
            }
        } catch (error) {
            console.warn(`SW: Failed to cache image ${image}:`, error);
        }
    }
}

// Activate Service Worker with cache cleanup
self.addEventListener('activate', event => {
    console.log(`SW: Activating version ${CACHE_VERSION}`);

    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (!cacheName.includes(CACHE_VERSION)) {
                        console.log(`SW: Deleting old cache: ${cacheName}`);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('SW: Activation complete');
            return self.clients.claim();
        }).then(() => {
            // Notify all clients about the update
            return self.clients.matchAll().then(clients => {
                clients.forEach(client => {
                    client.postMessage({
                        type: 'SW_UPDATED',
                        version: CACHE_VERSION
                    });
                });
            });
        })
    );
});

// Network requests with intelligent routing and fallbacks
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // Skip non-GET requests for caching
    if (request.method !== 'GET') {
        return;
    }

    // Route requests to appropriate handlers
    if (isAPIRequest(request)) {
        event.respondWith(handleAPIRequest(request));
    } else if (isImageRequest(request)) {
        event.respondWith(handleImageRequest(request));
    } else if (isStaticRequest(request)) {
        event.respondWith(handleStaticRequest(request));
    } else {
        event.respondWith(handleDynamicRequest(request));
    }
});

// Handle API requests with network-first strategy and offline fallback
async function handleAPIRequest(request) {
    try {
        // Try network first with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), API_TIMEOUT);

        const response = await fetch(request, {
            signal: controller.signal,
            headers: {
                'X-PWA-Request': 'true',
                'X-Cache-Version': CACHE_VERSION
            }
        });

        clearTimeout(timeoutId);

        // Cache successful responses
        if (response.ok) {
            const cache = await caches.open(API_CACHE);
            cache.put(request, response.clone());
            return response;
        }

        // Network failed but got response, return it
        return response;

    } catch (error) {
        console.warn(`SW: Network failed for ${request.url}, trying cache:`, error);

        // Fallback to cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline API response
        return new Response(JSON.stringify({
            success: false,
            message: 'You are currently offline. Please check your internet connection.',
            offline: true,
            timestamp: Date.now()
        }), {
            status: 503,
            statusText: 'Service Unavailable',
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}

// Handle image requests with cache-first strategy
async function handleImageRequest(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const response = await fetch(request, { timeout: NETWORK_TIMEOUT });
        if (response.ok) {
            const cache = await caches.open(IMAGE_CACHE);
            cache.put(request, response.clone());
            return response;
        }
        return response;
    } catch (error) {
        console.warn(`SW: Failed to fetch image ${request.url}:`, error);
        // Return placeholder image or cached alternative
        return new Response('', { status: 404 });
    }
}

// Handle static requests with cache-first strategy
async function handleStaticRequest(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        return cachedResponse;
    }

    try {
        const response = await fetch(request, { timeout: NETWORK_TIMEOUT });
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
            return response;
        }
        return response;
    } catch (error) {
        console.warn(`SW: Failed to fetch static ${request.url}:`, error);
        // Return offline fallback for navigation
        if (request.mode === 'navigate') {
            return caches.match('/mobile/index.php');
        }
        return new Response('Offline', { status: 503 });
    }
}

// Handle dynamic requests with network-first strategy
async function handleDynamicRequest(request) {
    try {
        const response = await fetch(request, { timeout: NETWORK_TIMEOUT });
        return response;
    } catch (error) {
        console.warn(`SW: Failed to fetch dynamic ${request.url}:`, error);

        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/mobile/index.php');
        }

        return new Response('Offline', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Helper functions to determine request type
function isAPIRequest(request) {
    const url = new URL(request.url);
    return url.pathname.startsWith('/api/') ||
           url.hostname.includes('juakali-lend') ||
           url.pathname.includes('.php');
}

function isImageRequest(request) {
    const url = new URL(request.url);
    return request.destination === 'image' ||
           cacheStrategies.images.some(path =>
               url.pathname.startsWith(path) || url.href.startsWith(path)
           ) ||
           url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i);
}

function isStaticRequest(request) {
    const url = new URL(request.url);
    return request.destination === 'script' ||
           request.destination === 'style' ||
           request.destination === 'font' ||
           cacheStrategies.static.some(path =>
               url.pathname === path || url.pathname.startsWith(path)
           );
}

// Background sync for offline actions
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
    if (event.tag === 'sync-offline-data') {
        event.waitUntil(syncOfflineData());
    }
});

// Perform background sync
async function doBackgroundSync() {
    try {
        await self.registration.sync.register('background-sync');
        console.log('SW: Background sync registered');
        return await syncOfflineData();
    } catch (error) {
        console.error('SW: Background sync failed:', error);
    }
}

// Sync offline stored data
async function syncOfflineData() {
    console.log('SW: Syncing offline data...');

    // Get offline actions from IndexedDB
    const offlineActions = await getOfflineActions();

    for (const action of offlineActions) {
        try {
            const response = await fetch(action.url, {
                method: action.method,
                headers: action.headers,
                body: action.body
            });

            if (response.ok) {
                // Action synced successfully, remove from offline storage
                await removeOfflineAction(action.id);
                console.log(`SW: Synced offline action: ${action.type}`);
            }
        } catch (error) {
            console.error(`SW: Failed to sync action ${action.id}:`, error);
        }
    }
}

// IndexedDB helpers for offline storage
async function getOfflineActions() {
    // This would integrate with IndexedDB for offline storage
    // For now, return empty array as placeholder
    return [];
}

async function removeOfflineAction(actionId) {
    // Remove synced action from IndexedDB
    console.log(`SW: Removing synced action ${actionId}`);
}

// Push notifications with rich interactions
self.addEventListener('push', event => {
    console.log('SW: Push notification received');

    const data = event.data ? JSON.parse(event.data.text()) : {};

    const options = {
        body: data.message || 'New notification from JuaKali Lend',
        icon: '/assets/images/icon-192.png',
        badge: '/assets/images/icon-72.png',
        vibrate: [100, 50, 100],
        tag: data.tag || 'default',
        renotify: true,
        requireInteraction: data.urgent || false,
        data: {
            ...data,
            dateOfArrival: Date.now(),
            url: data.url || '/mobile/'
        },
        actions: [
            {
                action: 'view',
                title: 'View',
                icon: '/assets/images/view.png'
            },
            {
                action: 'dismiss',
                title: 'Dismiss',
                icon: '/assets/images/close.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'JuaKali Lend', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('SW: Notification click received');

    event.notification.close();

    const action = event.action;
    const notificationData = event.notification.data || {};

    if (action === 'view') {
        event.waitUntil(
            clients.openWindow(notificationData.url || '/mobile/')
        );
    }

    // Notify the app about the interaction
    event.waitUntil(
        clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({
                    type: 'NOTIFICATION_CLICKED',
                    action: action,
                    data: notificationData
                });
            });
        })
    );
});

// Periodic background sync for cache updates
self.addEventListener('periodicsync', event => {
    if (event.tag === 'cache-update') {
        event.waitUntil(updateCache());
    }
    if (event.tag === 'cleanup-cache') {
        event.waitUntil(cleanupCache());
    }
});

// Update cache periodically
async function updateCache() {
    try {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll(cacheStrategies.static);
        console.log('SW: Cache updated successfully');
    } catch (error) {
        console.error('SW: Cache update failed:', error);
    }
}

// Clean up old cache entries
async function cleanupCache() {
    try {
        const staticCache = await caches.open(STATIC_CACHE);
        const apiCache = await caches.open(API_CACHE);
        const imageCache = await caches.open(IMAGE_CACHE);

        // Clean up old entries (older than 7 days)
        const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);

        for (const [cacheName, cache] of [['static', staticCache], ['api', apiCache], ['images', imageCache]]) {
            const requests = await cache.keys();
            for (const request of requests) {
                const response = await cache.match(request);
                if (response && response.headers.get('date')) {
                    const responseDate = new Date(response.headers.get('date')).getTime();
                    if (responseDate < sevenDaysAgo) {
                        await cache.delete(request);
                        console.log(`SW: Cleaned up old ${cacheName} cache entry: ${request.url}`);
                    }
                }
            }
        }

        console.log('SW: Cache cleanup completed');
    } catch (error) {
        console.error('SW: Cache cleanup failed:', error);
    }
}

// Handle messages from client
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'CACHE_UPDATE') {
        updateCache();
    }

    if (event.data && event.data.type === 'FORCE_REFRESH') {
        updateCache().then(() => {
            event.ports[0].postMessage({ type: 'CACHE_UPDATED' });
        });
    }
});

// Performance monitoring
self.addEventListener('fetch', event => {
    const start = performance.now();

    event.respondWith(
        fetch(event.request).then(response => {
            const end = performance.now();
            const duration = end - start;

            // Log slow requests
            if (duration > 2000) {
                console.warn(`SW: Slow request: ${event.request.url} took ${duration.toFixed(2)}ms`);
            }

            return response;
        }).catch(error => {
            const end = performance.now();
            const duration = end - start;
            console.error(`SW: Request failed: ${event.request.url} after ${duration.toFixed(2)}ms`, error);
            throw error;
        })
    );
});

// Network status monitoring
self.addEventListener('online', event => {
    console.log('SW: App is online');
    // Sync pending offline actions
    doBackgroundSync();

    // Notify clients about online status
    event.waitUntil(
        clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({ type: 'ONLINE' });
            });
        })
    );
});

self.addEventListener('offline', event => {
    console.log('SW: App is offline');

    // Notify clients about offline status
    event.waitUntil(
        clients.matchAll().then(clients => {
            clients.forEach(client => {
                client.postMessage({ type: 'OFFLINE' });
            });
        })
    );
});

// Cache quota management
self.addEventListener('quotaexceeded', event => {
    console.warn('SW: Cache quota exceeded, cleaning up old entries');
    cleanupCache();
});

// Service Worker error handling
self.addEventListener('error', event => {
    console.error('SW: Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
    console.error('SW: Unhandled promise rejection:', event.reason);
});

console.log(`JuaKali Lend Service Worker v${CACHE_VERSION} loaded successfully`);