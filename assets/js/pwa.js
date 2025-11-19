/**
 * Progressive Web App JavaScript for JuaKali Lend Gamification
 * Handles PWA installation, offline functionality, and app-like experience
 */

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstallable = false;
        this.isInstalled = false;
        this.swRegistration = null;
        this.isOnline = navigator.onLine;

        this.init();
    }

    async init() {
        console.log('[PWA] Initializing PWA features...');

        // Check if app is running in standalone mode
        this.isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                         window.navigator.standalone === true ||
                         document.referrer.includes('android-app://');

        // Register service worker
        await this.registerServiceWorker();

        // Listen for beforeinstallprompt event
        this.listenForInstallPrompt();

        // Handle online/offline events
        this.setupConnectivityListeners();

        // Initialize app features
        this.initializeAppFeatures();

        // Setup periodic sync
        this.setupPeriodicSync();

        console.log('[PWA] PWA features initialized', {
            isInstalled: this.isInstalled,
            isOnline: this.isOnline
        });
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                console.log('[PWA] Registering service worker...');
                this.swRegistration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });

                console.log('[PWA] Service worker registered successfully');

                // Listen for service worker updates
                this.swRegistration.addEventListener('updatefound', () => {
                    const newWorker = this.swRegistration.installing;
                    console.log('[PWA] New service worker found');

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });

                // Listen for controlling service worker
                navigator.serviceWorker.addEventListener('controllerchange', () => {
                    console.log('[PWA] Service worker controller changed');
                    window.location.reload();
                });

                // Message service worker
                navigator.serviceWorker.addEventListener('message', (event) => {
                    this.handleServiceWorkerMessage(event);
                });

            } catch (error) {
                console.error('[PWA] Service worker registration failed:', error);
            }
        } else {
            console.warn('[PWA] Service workers are not supported');
        }
    }

    listenForInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('[PWA] Install prompt event received');
            e.preventDefault();
            this.deferredPrompt = e;
            this.isInstallable = true;
            this.showInstallButton();
        });

        // Listen for app installed event
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App was installed');
            this.isInstalled = true;
            this.isInstallable = false;
            this.hideInstallButton();
            this.showInstallSuccessNotification();
        });
    }

    setupConnectivityListeners() {
        window.addEventListener('online', () => {
            console.log('[PWA] App is online');
            this.isOnline = true;
            this.hideOfflineNotification();
            this.syncData();
        });

        window.addEventListener('offline', () => {
            console.log('[PWA] App is offline');
            this.isOnline = false;
            this.showOfflineNotification();
        });

        // Monitor connection quality
        if ('connection' in navigator) {
            navigator.connection.addEventListener('change', () => {
                this.updateConnectionQuality();
            });
        }
    }

    initializeAppFeatures() {
        // Initialize theme
        this.initializeTheme();

        // Initialize notifications
        this.initializeNotifications();

        // Initialize offline storage
        this.initializeOfflineStorage();

        // Initialize shortcuts
        this.initializeShortcuts();

        // Initialize sharing
        this.initializeSharing();

        // Initialize screen wake lock (if supported)
        this.initializeWakeLock();
    }

    initializeTheme() {
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('pwa-theme') || 'dark';
        this.setTheme(savedTheme);

        // Listen for system theme changes
        if (window.matchMedia) {
            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
            darkModeQuery.addListener((e) => {
                if (!localStorage.getItem('pwa-theme')) {
                    this.setTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('pwa-theme', theme);
    }

    async initializeNotifications() {
        if ('Notification' in navigator) {
            // Request notification permission
            if (Notification.permission === 'default') {
                try {
                    const permission = await Notification.requestPermission();
                    console.log('[PWA] Notification permission:', permission);
                } catch (error) {
                    console.error('[PWA] Notification permission request failed:', error);
                }
            }

            // Schedule periodic notifications
            this.schedulePeriodicNotifications();
        }
    }

    initializeOfflineStorage() {
        // Initialize IndexedDB for offline data
        if ('indexedDB' in window) {
            this.initIndexedDB();
        }

        // Setup offline data sync
        this.setupOfflineSync();
    }

    async initializeShortcuts() {
        if ('navigator' in window && 'mediaSession' in navigator) {
            // Set up media session for PWA shortcuts
            navigator.mediaSession.metadata = {
                title: 'JuaKali Lend Gamification',
                artist: 'JuaKali Lend',
                album: 'Microfinance Platform',
                artwork: [
                    {
                        src: '/assets/icons/icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png'
                    }
                ]
            };

            // Set up media session actions
            navigator.mediaSession.setActionHandler('play', () => {
                this.openDashboard();
            });

            navigator.mediaSession.setActionHandler('pause', () => {
                this.openChallenges();
            });
        }
    }

    initializeSharing() {
        // Set up Web Share API if available
        if ('share' in navigator) {
            // Add share buttons to relevant pages
            this.addShareButtons();
        }
    }

    async initializeWakeLock() {
        if ('wakeLock' in navigator) {
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
                console.log('[PWA] Wake lock acquired');

                // Release wake lock when page is hidden
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden && this.wakeLock) {
                        this.wakeLock.release();
                        this.wakeLock = null;
                    } else if (!document.hidden && !this.wakeLock) {
                        this.acquireWakeLock();
                    }
                });

            } catch (error) {
                console.log('[PWA] Wake lock not available or failed:', error);
            }
        }
    }

    async acquireWakeLock() {
        try {
            this.wakeLock = await navigator.wakeLock.request('screen');
            console.log('[PWA] Wake lock reacquired');
        } catch (error) {
            console.log('[PWA] Failed to reacquire wake lock:', error);
        }
    }

    // PWA Installation
    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('[PWA] Install prompt not available');
            return false;
        }

        try {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            console.log('[PWA] Install prompt outcome:', outcome);

            this.deferredPrompt = null;
            this.isInstallable = false;
            this.hideInstallButton();

            return outcome === 'accepted';
        } catch (error) {
            console.error('[PWA] Install prompt failed:', error);
            return false;
        }
    }

    showInstallButton() {
        let installButton = document.getElementById('pwa-install-button');

        if (!installButton) {
            installButton = document.createElement('button');
            installButton.id = 'pwa-install-button';
            installButton.className = 'pwa-install-btn';
            installButton.innerHTML = `
                <i class="fas fa-download"></i>
                <span>Install App</span>
            `;
            installButton.addEventListener('click', () => this.promptInstall());

            // Add to page
            document.body.appendChild(installButton);
        }

        installButton.style.display = 'flex';
    }

    hideInstallButton() {
        const installButton = document.getElementById('pwa-install-button');
        if (installButton) {
            installButton.style.display = 'none';
        }
    }

    showInstallSuccessNotification() {
        this.showNotification('App Installed!', 'JuaKali Lend has been successfully installed on your device.', {
            icon: '/assets/icons/icon-192x192.png',
            tag: 'install-success'
        });
    }

    // Notifications
    showNotification(title, body, options = {}) {
        const defaultOptions = {
            icon: '/assets/icons/icon-192x192.png',
            badge: '/assets/icons/badge-72x72.png',
            tag: 'gamification',
            requireInteraction: false,
            silent: false
        };

        const notificationOptions = { ...defaultOptions, ...options, title, body };

        if ('Notification' in navigator && Notification.permission === 'granted') {
            try {
                const notification = new Notification(title, notificationOptions);

                // Auto-close after 5 seconds unless requireInteraction is true
                if (!notificationOptions.requireInteraction) {
                    setTimeout(() => {
                        notification.close();
                    }, 5000);
                }

                return notification;
            } catch (error) {
                console.error('[PWA] Failed to show notification:', error);
            }
        }

        // Fallback to in-app notification
        this.showInAppNotification(title, body, notificationOptions);
    }

    showInAppNotification(title, body, options = {}) {
        // Create or get notification container
        let container = document.getElementById('pwa-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pwa-notification-container';
            container.className = 'pwa-notification-container';
            document.body.appendChild(container);
        }

        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'pwa-notification';
        notification.innerHTML = `
            <div class="pwa-notification-icon">
                <i class="fas ${options.icon || 'fa-info-circle'}"></i>
            </div>
            <div class="pwa-notification-content">
                <div class="pwa-notification-title">${title}</div>
                <div class="pwa-notification-body">${body}</div>
            </div>
            <button class="pwa-notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;

        // Add close functionality
        notification.querySelector('.pwa-notification-close').addEventListener('click', () => {
            notification.remove();
        });

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);

        // Add to container
        container.appendChild(notification);
    }

    showUpdateNotification() {
        const updateCard = document.createElement('div');
        updateCard.className = 'pwa-update-card';
        updateCard.innerHTML = `
            <div class="pwa-update-content">
                <i class="fas fa-download"></i>
                <div>
                    <div class="pwa-update-title">App Update Available</div>
                    <div class="pwa-update-body">A new version of JuaKali Lend is available.</div>
                </div>
                <button class="pwa-update-btn" onclick="pwaManager.updateApp()">
                    Update
                </button>
                <button class="pwa-update-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        document.body.appendChild(updateCard);
    }

    async updateApp() {
        if (this.swRegistration && this.swRegistration.waiting) {
            this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }

        // Remove update card
        const updateCard = document.querySelector('.pwa-update-card');
        if (updateCard) {
            updateCard.remove();
        }
    }

    showOfflineNotification() {
        this.showNotification('You\'re Offline', 'Some features may be unavailable. Please check your internet connection.', {
            icon: 'fa-wifi-slash',
            tag: 'offline',
            requireInteraction: true
        });
    }

    hideOfflineNotification() {
        // Close offline notification if it exists
        if ('serviceWorker' in navigator && 'getRegistrations' in navigator.serviceWorker) {
            navigator.serviceWorker.getRegistrations().then(registrations => {
                registrations.forEach(registration => {
                    registration.getNotifications({ tag: 'offline' }).then(notifications => {
                        notifications.forEach(notification => notification.close());
                    });
                });
            });
        }
    }

    // Offline functionality
    async syncData() {
        if ('serviceWorker' in navigator && this.swRegistration) {
            try {
                await this.swRegistration.sync.register('gamification-sync');
                console.log('[PWA] Background sync registered');
            } catch (error) {
                console.error('[PWA] Failed to register background sync:', error);
            }
        }

        // Also sync immediately if online
        if (this.isOnline) {
            await this.performImmediateSync();
        }
    }

    async performImmediateSync() {
        try {
            // Sync user profile
            await this.fetchWithAuth('/api/gamification.php?action=profile');

            // Sync challenges
            await this.fetchWithAuth('/api/gamification.php?action=challenges');

            // Sync leaderboard
            await this.fetchWithAuth('/api/gamification.php?action=leaderboard');

            console.log('[PWA] Immediate sync completed');

        } catch (error) {
            console.error('[PWA] Immediate sync failed:', error);
        }
    }

    async fetchWithAuth(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + this.getAuthToken()
            }
        };

        const response = await fetch(url, { ...defaultOptions, ...options });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return response.json();
    }

    getAuthToken() {
        // Retrieve authentication token from storage
        return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    }

    // Periodic features
    schedulePeriodicNotifications() {
        // Schedule daily login reminder
        this.scheduleNotification('daily-login', {
            title: 'Daily Login Bonus!',
            body: 'Don\'t forget to claim your daily login points!',
            hour: 9,
            minute: 0
        });

        // Schedule streak reminder
        this.scheduleNotification('streak-reminder', {
            title: 'Keep Your Streak Alive!',
            body: 'You haven\'t logged in today. Keep your streak going!',
            hour: 20,
            minute: 0
        });
    }

    scheduleNotification(id, config) {
        const now = new Date();
        const scheduledTime = new Date();
        scheduledTime.setHours(config.hour, config.minute, 0, 0);

        // If scheduled time has passed today, schedule for tomorrow
        if (scheduledTime <= now) {
            scheduledTime.setDate(scheduledTime.getDate() + 1);
        }

        const delay = scheduledTime.getTime() - now.getTime();

        setTimeout(() => {
            this.showNotification(config.title, config.body, {
                tag: id,
                icon: '/assets/icons/icon-192x192.png'
            });

            // Schedule next occurrence
            this.scheduleNotification(id, config);
        }, delay);
    }

    async setupPeriodicSync() {
        if ('serviceWorker' in navigator && 'periodicSync' in navigator.serviceWorker) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.periodicSync.register('gamification-periodic-sync', {
                    minInterval: 24 * 60 * 60 * 1000 // 24 hours
                });
                console.log('[PWA] Periodic sync registered');
            } catch (error) {
                console.log('[PWA] Periodic sync not supported:', error);
            }
        }
    }

    updateConnectionQuality() {
        if ('connection' in navigator) {
            const connection = navigator.connection;
            console.log('[PWA] Connection quality changed:', {
                effectiveType: connection.effectiveType,
                downlink: connection.downlink,
                rtt: connection.rtt
            });

            // Show notification for poor connection
            if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g') {
                this.showNotification('Slow Connection', 'Your connection seems slow. Some features may be affected.', {
                    icon: 'fa-wifi',
                    tag: 'connection-warning'
                });
            }
        }
    }

    // IndexedDB for offline storage
    async initWithIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('JuakaliPWA', 1);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create object stores
                if (!db.objectStoreNames.contains('userProfile')) {
                    db.createObjectStore('userProfile', { keyPath: 'id' });
                }

                if (!db.objectStoreNames.contains('challenges')) {
                    db.createObjectStore('challenges', { keyPath: 'id' });
                }

                if (!db.objectStoreNames.contains('leaderboard')) {
                    db.createObjectStore('leaderboard', { keyPath: 'id' });
                }

                if (!db.objectStoreNames.contains('achievements')) {
                    db.createObjectStore('achievements', { keyPath: 'id' });
                }
            };
        });
    }

    setupOfflineSync() {
        // Store data for offline use
        this.storeDataForOffline();

        // Sync data when online
        window.addEventListener('online', () => {
            this.syncOfflineData();
        });
    }

    async storeDataForOffline() {
        if (!this.db) return;

        try {
            // Store user profile
            const profileData = await this.fetchWithAuth('/api/gamification.php?action=profile');
            if (profileData.success) {
                const tx = this.db.transaction('userProfile', 'readwrite');
                const store = tx.objectStore('userProfile');
                await store.put(profileData.data);
            }

            // Store challenges
            const challengesData = await this.fetchWithAuth('/api/gamification.php?action=challenges');
            if (challengesData.success) {
                const tx = this.db.transaction('challenges', 'readwrite');
                const store = tx.objectStore('challenges');
                await store.put({ id: 'user-challenges', data: challengesData.data });
            }

        } catch (error) {
            console.error('[PWA] Failed to store data for offline:', error);
        }
    }

    async syncOfflineData() {
        if (!this.db || !this.isOnline) return;

        try {
            // Sync any pending actions
            const pendingActions = await this.getPendingActions();
            for (const action of pendingActions) {
                await this.executePendingAction(action);
            }

        } catch (error) {
            console.error('[PWA] Failed to sync offline data:', error);
        }
    }

    async getPendingActions() {
        if (!this.db) return [];

        return new Promise((resolve) => {
            const tx = this.db.transaction('pendingActions', 'readonly');
            const store = tx.objectStore('pendingActions');
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
        });
    }

    async executePendingAction(action) {
        try {
            const response = await fetch(action.url, action.options);

            if (response.ok) {
                // Remove from pending actions
                const tx = this.db.transaction('pendingActions', 'readwrite');
                const store = tx.objectStore('pendingActions');
                await store.delete(action.id);
            }

        } catch (error) {
            console.error('[PWA] Failed to execute pending action:', error);
        }
    }

    // App shortcuts
    openDashboard() {
        window.location.href = '/gamification/dashboard-v2.php';
    }

    openChallenges() {
        window.location.href = '/gamification/challenges.php';
    }

    addShareButtons() {
        // Add share buttons to relevant pages
        const shareButton = document.createElement('button');
        shareButton.className = 'pwa-share-btn';
        shareButton.innerHTML = '<i class="fas fa-share-alt"></i> Share';
        shareButton.addEventListener('click', () => this.shareContent());

        // Add to page header
        const header = document.querySelector('.navbar-actions');
        if (header) {
            header.appendChild(shareButton);
        }
    }

    async shareContent() {
        const shareData = {
            title: 'JuaKali Lend Gamification',
            text: 'Check out my progress on JuaKali Lend! I\'ve earned tons of points and badges!',
            url: window.location.href
        };

        try {
            if (navigator.share && navigator.canShare(shareData)) {
                await navigator.share(shareData);
            } else {
                // Fallback to copying link
                await navigator.clipboard.writeText(shareData.url);
                this.showNotification('Link Copied!', 'The link has been copied to your clipboard.');
            }
        } catch (error) {
            console.error('[PWA] Share failed:', error);
        }
    }

    // Handle service worker messages
    handleServiceWorkerMessage(event) {
        const message = event.data;

        switch (message.type) {
            case 'CACHE_UPDATED':
                this.showNotification('Content Updated', 'The latest content has been downloaded.');
                break;

            case 'SYNC_COMPLETED':
                this.showNotification('Sync Complete', 'Your data has been synced.');
                break;

            default:
                console.log('[PWA] Unknown message from service worker:', message);
        }
    }
}

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();

    // Make PWA manager globally available
    window.pwaManager = window.pwaManager;

    // Add PWA styles to head
    const pwaStyles = document.createElement('style');
    pwaStyles.textContent = `
        .pwa-install-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 20px;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .pwa-install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }

        .pwa-update-card {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(15, 15, 30, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 16px;
            z-index: 1000;
            max-width: 400px;
            margin: 0 auto;
        }

        .pwa-update-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pwa-update-content i {
            font-size: 24px;
            color: #667eea;
        }

        .pwa-update-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .pwa-update-body {
            font-size: 14px;
            color: #B8BCC8;
        }

        .pwa-update-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            cursor: pointer;
            margin-left: auto;
        }

        .pwa-update-close {
            background: none;
            border: none;
            color: #B8BCC8;
            cursor: pointer;
            padding: 4px;
            margin-left: 8px;
        }

        .pwa-notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pwa-notification {
            background: rgba(15, 15, 30, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            min-width: 300px;
            max-width: 400px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .pwa-notification-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            color: #667eea;
            flex-shrink: 0;
        }

        .pwa-notification-content {
            flex: 1;
        }

        .pwa-notification-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .pwa-notification-body {
            font-size: 14px;
            color: #B8BCC8;
            line-height: 1.4;
        }

        .pwa-notification-close {
            background: none;
            border: none;
            color: #B8BCC8;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .pwa-notification-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .pwa-share-btn {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pwa-share-btn:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        @media (max-width: 768px) {
            .pwa-install-btn {
                bottom: 80px;
                right: 16px;
                left: 16px;
                justify-content: center;
            }

            .pwa-update-card {
                bottom: 80px;
                left: 16px;
                right: 16px;
            }

            .pwa-notification-container {
                top: 10px;
                right: 10px;
                left: 10px;
            }

            .pwa-notification {
                min-width: auto;
                max-width: none;
            }
        }
    `;

    document.head.appendChild(pwaStyles);
});

// Export for global access
window.PWAManager = PWAManager;