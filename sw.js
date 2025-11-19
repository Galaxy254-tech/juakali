/**
 * Service Worker for JuaKali Lend Gamification PWA
 * Provides offline functionality, caching, and background sync
 */

const CACHE_NAME = 'juakali-gamification-v1.0.0';
const STATIC_CACHE_NAME = 'juakali-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'juakali-dynamic-v1.0.0';

// Assets to cache for offline functionality
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/gamification/dashboard-v2.php',
    '/gamification/dashboard.php',
    '/gamification/challenges.php',
    '/gamification/rewards.php',
    '/gamification/leaderboard.php',
    '/manifest.json',
    '/assets/css/gamification-modern.css',
    '/assets/css/admin-dashboard.css',
    '/assets/css/animations.css',
    '/assets/css/pwa.css',
    '/assets/js/gamification-dashboard.js',
    '/assets/js/pwa.js',
    '/assets/js/offline.js',
    '/assets/icons/icon-192x192.png',
    '/assets/icons/icon-512x512.png',
    '/assets/icons/maskable-icon-192x192.png',
    '/assets/icons/maskable-icon-512x512.png',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap',
    'https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/chart.js'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');

    event.waitUntil(
        caches.open(STATIC_CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Failed to cache static assets:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== STATIC_CACHE_NAME &&
                            cacheName !== DYNAMIC_CACHE_NAME &&
                            cacheName !== CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('[SW] Service worker activated');
                return self.clients.claim();
            })
            .catch((error) => {
                console.error('[SW] Error during activation:', error);
            })
    );
});

// Fetch event - handle network requests
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip external CDN requests (let browser handle them)
    if (url.origin !== self.location.origin &&
        !url.hostname.includes('fonts.googleapis.com') &&
        !url.hostname.includes('cdnjs.cloudflare.com') &&
        !url.hostname.includes('cdn.jsdelivr.net')) {
        return;
    }

    event.respondWith(handleRequest(request));
});

// Handle different types of requests
async function handleRequest(request) {
    const url = new URL(request.url);

    try {
        // API requests - network first, then cache
        if (url.pathname.startsWith('/api/') || url.pathname.includes('gamification.php')) {
            return await handleAPIRequest(request);
        }

        // Static assets - cache first
        if (STATIC_ASSETS.some(asset => url.pathname === asset || url.pathname.endsWith(asset))) {
            return await handleStaticRequest(request);
        }

        // Pages - network first, then cache
        if (url.pathname.endsWith('.php') || url.pathname === '/') {
            return await handlePageRequest(request);
        }

        // Images - cache first with network update
        if (url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg)$/i)) {
            return await handleImageRequest(request);
        }

        // Default - network first
        return await fetch(request);

    } catch (error) {
        console.error('[SW] Error handling request:', error);
        return await getOfflineResponse(request);
    }
}

// Handle API requests with network-first strategy
async function handleAPIRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);

        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;

    } catch (error) {
        // Network failed - try cache
        console.log('[SW] Network failed for API request, trying cache:', request.url);

        const cachedResponse = await caches.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline response for API requests
        return new Response(JSON.stringify({
            success: false,
            message: 'Offline - please check your internet connection',
            offline: true
        }), {
            status: 503,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }
}

// Handle static requests with cache-first strategy
async function handleStaticRequest(request) {
    const cachedResponse = await caches.match(request);

    if (cachedResponse) {
        // Update cache in background
        updateCache(request);
        return cachedResponse;
    }

    // Not in cache, try network
    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;

    } catch (error) {
        console.error('[SW] Failed to fetch static asset:', request.url);
        throw error;
    }
}

// Handle page requests with network-first strategy
async function handlePageRequest(request) {
    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;

    } catch (error) {
        console.log('[SW] Network failed for page, trying cache:', request.url);

        const cachedResponse = await caches.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline page
        return caches.match('/offline.html') || new Response(getOfflineHTML(), {
            headers: {
                'Content-Type': 'text/html'
            }
        });
    }
}

// Handle image requests with cache-first strategy
async function handleImageRequest(request) {
    const cachedResponse = await caches.match(request);

    if (cachedResponse) {
        // Update cache in background if it's older than 1 day
        const cacheDate = cachedResponse.headers.get('sw-cache-date');
        if (!cacheDate || (Date.now() - parseInt(cacheDate)) > 86400000) {
            updateCache(request);
        }
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            const responseToCache = networkResponse.clone();
            responseToCache.headers.set('sw-cache-date', Date.now().toString());
            cache.put(request, responseToCache);
        }

        return networkResponse;

    } catch (error) {
        console.error('[SW] Failed to fetch image:', request.url);
        throw error;
    }
}

// Update cache in background
async function updateCache(request) {
    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse);
        }
    } catch (error) {
        // Silently fail background updates
        console.log('[SW] Background update failed:', request.url);
    }
}

// Get offline response
async function getOfflineResponse(request) {
    const url = new URL(request.url);

    // API requests
    if (url.pathname.startsWith('/api/')) {
        return new Response(JSON.stringify({
            success: false,
            message: 'Offline - please check your internet connection',
            offline: true
        }), {
            status: 503,
            headers: {
                'Content-Type': 'application/json'
            }
        });
    }

    // Pages
    if (url.pathname.endsWith('.php')) {
        const offlineResponse = await caches.match('/offline.html');
        if (offlineResponse) {
            return offlineResponse;
        }
    }

    // Default offline response
    return new Response(getOfflineHTML(), {
        status: 503,
        headers: {
            'Content-Type': 'text/html'
        }
    });
}

// Offline HTML page
function getOfflineHTML() {
    return `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Offline - JuaKali Lend</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #0F0F1E 0%, #1A1A2E 100%);
                color: #FFFFFF;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 20px;
                text-align: center;
            }
            .offline-container {
                max-width: 400px;
                padding: 40px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 20px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }
            .offline-icon {
                font-size: 4rem;
                margin-bottom: 20px;
                opacity: 0.7;
            }
            .offline-title {
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 10px;
            }
            .offline-message {
                color: #B8BCC8;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .retry-button {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .retry-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            }
            .offline-features {
                margin-top: 30px;
                padding-top: 30px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
            .feature-list {
                list-style: none;
                text-align: left;
            }
            .feature-list li {
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .feature-list li::before {
                content: '✓';
                color: #10B981;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="offline-container">
            <div class="offline-icon">
                <i class="fas fa-wifi-slash"></i>
            </div>
            <h1 class="offline-title">You're Offline</h1>
            <p class="offline-message">
                Please check your internet connection and try again.
                Some features may be available in offline mode.
            </p>
            <button class="retry-button" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i>
                Retry
            </button>
            <div class="offline-features">
                <h3>Available Offline:</h3>
                <ul class="feature-list">
                    <li>View cached dashboard</li>
                    <li>Check your achievements</li>
                    <li>Browse saved content</li>
                    <li>View offline leaderboards</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    `;
}

// Background sync for gamification data
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync event:', event.tag);

    if (event.tag === 'gamification-sync') {
        event.waitUntil(syncGamificationData());
    }
});

// Sync gamification data when online
async function syncGamificationData() {
    try {
        // Sync user profile
        const profileResponse = await fetch('/api/gamification.php?action=profile', {
            headers: {
                'Authorization': 'Bearer ' + (await getAuthToken())
            }
        });

        if (profileResponse.ok) {
            const profileData = await profileResponse.json();
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put('/api/gamification.php?action=profile', profileResponse);
        }

        // Sync challenges
        const challengesResponse = await fetch('/api/gamification.php?action=challenges', {
            headers: {
                'Authorization': 'Bearer ' + (await getAuthToken())
            }
        });

        if (challengesResponse.ok) {
            const challengesData = await challengesResponse.json();
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put('/api/gamification.php?action=challenges', challengesResponse);
        }

        console.log('[SW] Gamification data synced successfully');

    } catch (error) {
        console.error('[SW] Failed to sync gamification data:', error);
    }
}

// Push notification handler
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received:', event);

    if (!event.data) {
        return;
    }

    const data = event.data.json();

    const options = {
        body: data.body || 'New notification from JuaKali Lend',
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/badge-72x72.png',
        tag: data.tag || 'gamification',
        renotify: true,
        requireInteraction: data.requireInteraction || false,
        actions: data.actions || [
            {
                action: 'open',
                title: 'Open App'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ],
        data: {
            url: data.url || '/gamification/dashboard-v2.php',
            ...data.data
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'JuaKali Lend', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event);

    event.notification.close();

    const action = event.action;
    const notificationData = event.notification.data || {};

    if (action === 'dismiss') {
        return;
    }

    // Handle notification clicks
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Focus existing window if available
                for (const client of clientList) {
                    if (client.url.includes(notificationData.url) && 'focus' in client) {
                        return client.focus();
                    }
                }

                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(notificationData.url || '/gamification/dashboard-v2.php');
                }
            })
    );
});

// Periodic background sync (experimental)
self.addEventListener('periodicsync', (event) => {
    console.log('[SW] Periodic sync event:', event.tag);

    if (event.tag === 'gamification-periodic-sync') {
        event.waitUntil(syncGamificationData());
    }
});

// Get authentication token (simplified)
async function getAuthToken() {
    // In a real implementation, this would retrieve the stored auth token
    // For now, return a placeholder
    return 'placeholder-token';
}

// Message handler for client communication
self.addEventListener('message', (event) => {
    console.log('[SW] Message received from client:', event.data);

    switch (event.data.type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'CLIENTS_CLAIM':
            self.clients.claim();
            break;

        case 'CACHE_UPDATE':
            updateCache(new Request(event.data.url));
            break;

        case 'GET_VERSION':
            event.ports[0].postMessage({
                version: CACHE_NAME
            });
            break;

        default:
            console.log('[SW] Unknown message type:', event.data.type);
    }
});

// Cleanup old caches periodically
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    // Keep only current caches
                    if (cacheName !== STATIC_CACHE_NAME &&
                        cacheName !== DYNAMIC_CACHE_NAME &&
                        cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Error handling
self.addEventListener('error', (event) => {
    console.error('[SW] Service worker error:', event.error);
});

self.addEventListener('unhandledrejection', (event) => {
    console.error('[SW] Unhandled promise rejection:', event.reason);
});