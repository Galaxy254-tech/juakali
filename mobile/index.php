<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/sms-notifications.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../pages/auth/login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if user is on mobile device
function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}

// Get user dashboard data based on role
try {
    $db = Database::getInstance();

    switch ($user_role) {
        case 'retailer':
            $dashboard_data = [
                'active_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE retailer_id = ? AND status IN ("approved", "disbursed", "repaying")', [$user_id]),
                'total_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE retailer_id = ?', [$user_id]),
                'credit_score' => $db->fetchOne('SELECT * FROM credit_scores WHERE retailer_id = ?', [$user_id]),
                'pending_repayments' => $db->fetchAll('SELECT rs.*, l.loan_number FROM repayment_schedule rs JOIN loans l ON rs.loan_id = l.id WHERE l.retailer_id = ? AND rs.status = "pending" AND rs.due_date <= CURDATE() ORDER BY rs.due_date ASC LIMIT 3', [$user_id]),
                'recent_orders' => $db->fetchAll('SELECT o.*, u.company_name as supplier_name FROM orders o JOIN users u ON o.supplier_id = u.id WHERE o.retailer_id = ? ORDER BY o.created_at DESC LIMIT 5', [$user_id])
            ];
            break;

        case 'lender':
            $dashboard_data = [
                'active_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE lender_id = ? AND status IN ("approved", "disbursed", "repaying")', [$user_id]),
                'pending_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE lender_id = ? AND status = "pending"', [$user_id]),
                'total_invested' => $db->fetchColumn('SELECT SUM(loan_amount) FROM loans WHERE lender_id = ? AND status IN ("approved", "disbursed", "repaying", "completed")', [$user_id]) ?? 0,
                'performance' => $db->fetchOne('SELECT * FROM lender_performance WHERE lender_id = ?', [$user_id])
            ];
            break;

        case 'supplier':
            $dashboard_data = [
                'pending_orders' => $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = "pending"', [$user_id]),
                'active_orders' => $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status IN ("confirmed", "processing")', [$user_id]),
                'delivered_today' => $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = "delivered" AND DATE(delivered_at) = CURDATE()', [$user_id]),
                'total_revenue' => $db->fetchColumn('SELECT SUM(total_amount) FROM orders WHERE supplier_id = ? AND status = "delivered"', [$user_id]) ?? 0
            ];
            break;

        default:
            $dashboard_data = [];
    }

} catch (Exception $e) {
    $dashboard_data = [];
}

// Get notifications
try {
    $notifications = $db->fetchAll('SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 5', [$user_id]);
} catch (Exception $e) {
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#10b981">
    <meta name="description" content="JuaKali Lend - Mobile Banking for Daily Businesses">

    <title>JuaKali Lend - Mobile</title>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="../assets/images/icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1f2937;
        }

        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            min-height: 100vh;
            background: white;
        }

        .mobile-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .mobile-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .nav-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .user-info {
            text-align: center;
            margin-bottom: 1rem;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #bbf7d0;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.2);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .action-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .action-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: #1f2937;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            border-color: #10b981;
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.2);
            transform: translateY(-2px);
            color: #10b981;
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .action-desc {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .quick-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 2px solid #e5e7eb;
            padding: 1rem;
            display: flex;
            justify-content: space-around;
            z-index: 1000;
        }

        .quick-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #6b7280;
            transition: all 0.3s ease;
            font-size: 0.75rem;
        }

        .quick-btn:hover {
            color: #10b981;
        }

        .quick-btn i {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .notification-badge {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .notification-item {
            background: #f8fafc;
            border-left: 4px solid #10b981;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
        }

        .notification-title {
            font-weight: 600;
            font-size: 0.875rem;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .notification-desc {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .notification-time {
            font-size: 0.625rem;
            color: #9ca3af;
            margin-top: 0.25rem;
        }

        .repayment-reminder {
            background: #fef3c7;
            border: 2px solid #fbbf24;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .repayment-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: #92400e;
        }

        .repayment-due {
            font-size: 0.75rem;
            color: #78350f;
            margin-top: 0.25rem;
        }

        .btn-pay {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-pay:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
            color: white;
        }

        .bottom-nav-padding {
            height: 100px;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .install-prompt {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem 1rem;
            text-align: center;
            position: fixed;
            bottom: 100px;
            left: 0;
            right: 0;
            z-index: 999;
        }

        .install-prompt.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="mobile-container">
        <!-- Header -->
        <div class="mobile-header">
            <div class="mobile-nav">
                <div class="nav-title">
                    <i class="fas fa-leaf"></i> JuaKali Lend
                </div>
                <div>
                    <i class="fas fa-bell"></i>
                    <?php if (!empty($notifications)): ?>
                        <span class="notification-badge"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="user-info">
                <div class="h5"><?php echo htmlspecialchars($_SESSION['first_name']); ?></div>
                <small class="text-white-50">
                    <?php
                    $role_map = [
                        'retailer' => 'Retailer',
                        'lender' => 'Lender',
                        'supplier' => 'Supplier',
                        'admin' => 'Admin'
                    ];
                    echo $role_map[$user_role] ?? 'User';
                    ?>
                </small>
            </div>
        </div>

        <!-- Main Content -->
        <div class="p-3">
            <!-- Pending Repayments Alert -->
            <?php if (!empty($dashboard_data['pending_repayments'])): ?>
                <div class="repayment-reminder pulse">
                    <div class="repayment-amount">
                        <i class="fas fa-exclamation-triangle"></i> Payment Due
                    </div>
                    <div class="repayment-due">
                        <?php
                        $next_payment = $dashboard_data['pending_repayments'][0];
                        echo 'KES ' . number_format($next_payment['amount_due'], 2) . ' - ' . date('M j, Y', strtotime($next_payment['due_date']));
                        ?>
                    </div>
                    <button class="btn-pay" onclick="makePayment(<?php echo $next_payment['id']; ?>)">
                        <i class="fas fa-mobile-alt"></i> Pay Now
                    </button>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <?php if ($user_role === 'retailer'): ?>
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['active_loans']; ?></div>
                        <div class="stat-label">Active Loans</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['credit_score']['score'] ?? 500; ?></div>
                        <div class="stat-label">Credit Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">KES <?php echo number_format($dashboard_data['credit_score']['credit_limit'] ?? 10000, 0); ?></div>
                        <div class="stat-label">Available Credit</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($dashboard_data['pending_repayments']); ?></div>
                        <div class="stat-label">Due Payments</div>
                    </div>
                </div>
            <?php elseif ($user_role === 'lender'): ?>
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['pending_loans']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['active_loans']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">KES <?php echo number_format($dashboard_data['total_invested'], 0); ?></div>
                        <div class="stat-label">Invested</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format(($dashboard_data['performance']['roi'] ?? 0), 1); ?>%</div>
                        <div class="stat-label">ROI</div>
                    </div>
                </div>
            <?php elseif ($user_role === 'supplier'): ?>
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['pending_orders']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['active_orders']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $dashboard_data['delivered_today']; ?></div>
                        <div class="stat-label">Delivered Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">KES <?php echo number_format($dashboard_data['total_revenue'], 0); ?></div>
                        <div class="stat-label">Revenue</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Cards -->
            <?php if ($user_role === 'retailer'): ?>
                <div class="action-cards">
                    <a href="../pages/retailer/request-loan.php" class="action-card">
                        <div class="action-icon text-success">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="action-title">Request Loan</div>
                        <div class="action-desc">Get goods now</div>
                    </a>
                    <a href="../pages/retailer/repayments.php" class="action-card">
                        <div class="action-icon text-primary">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="action-title">Repay</div>
                        <div class="action-desc">Pay your loans</div>
                    </a>
                    <a href="../pages/kyc/upload.php" class="action-card">
                        <div class="action-icon text-warning">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="action-title">KYC</div>
                        <div class="action-desc">Complete verification</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-info">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="action-title">History</div>
                        <div class="action-desc">View past loans</div>
                    </a>
                </div>
            <?php elseif ($user_role === 'lender'): ?>
                <div class="action-cards">
                    <a href="../pages/lender/dashboard.php" class="action-card">
                        <div class="action-icon text-primary">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="action-title">Review</div>
                        <div class="action-desc">Pending loans</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-success">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-title">Analytics</div>
                        <div class="action-desc">Track returns</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-warning">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="action-title">Withdraw</div>
                        <div class="action-desc">Get your returns</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-info">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="action-title">Settings</div>
                        <div class="action-desc">Account settings</div>
                    </a>
                </div>
            <?php elseif ($user_role === 'supplier'): ?>
                <div class="action-cards">
                    <a href="../pages/supplier/orders.php" class="action-card">
                        <div class="action-icon text-primary">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="action-title">Orders</div>
                        <div class="action-desc">Manage deliveries</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-success">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="action-title">Products</div>
                        <div class="action-desc">Add items</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="action-title">Analytics</div>
                        <div class="action-desc">Track sales</div>
                    </a>
                    <a href="#" class="action-card">
                        <div class="action-icon text-info">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="action-title">Settings</div>
                        <div class="action-desc">Account</div>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <?php if ($user_role === 'retailer' && !empty($dashboard_data['recent_orders'])): ?>
                <div class="mt-4">
                    <h5><i class="fas fa-shopping-cart"></i> Recent Orders</h5>
                    <?php foreach ($dashboard_data['recent_orders'] as $order): ?>
                        <div class="notification-item">
                            <div class="notification-title">
                                Order #<?php echo htmlspecialchars($order['order_number']); ?>
                            </div>
                            <div class="notification-desc">
                                <?php echo htmlspecialchars($order['supplier_name']); ?> - KES <?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                            <div class="notification-time">
                                <?php echo date('M j, H:i', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Notifications -->
            <?php if (!empty($notifications)): ?>
                <div class="mt-4">
                    <h5><i class="fas fa-bell"></i> Notifications</h5>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </div>
                            <div class="notification-desc">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </div>
                            <div class="notification-time">
                                <?php echo date('M j, H:i', strtotime($notification['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Navigation -->
        <div class="bottom-nav-padding"></div>
        <div class="quick-actions">
            <a href="../index.php" class="quick-btn">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="#" class="quick-btn">
                <i class="fas fa-search"></i>
                <span>Search</span>
            </a>
            <a href="#" class="quick-btn">
                <i class="fas fa-plus"></i>
                <span>Add</span>
            </a>
            <a href="#" class="quick-btn">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </div>

    <!-- Install PWA Prompt -->
    <div id="installPrompt" class="install-prompt hidden">
        <h4><i class="fas fa-mobile-alt"></i> Install JuaKali Lend</h4>
        <p>Add to home screen for quick access!</p>
        <button class="btn btn-light btn-sm" onclick="installPWA()">
            <i class="fas fa-download"></i> Install
        </button>
        <button class="btn btn-light btn-sm ms-2" onclick="dismissInstallPrompt()">
            <i class="fas fa-times"></i> Later
        </button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enhanced Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/mobile/service-worker-enhanced.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);

                        // Listen for service worker updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            if (newWorker) {
                                newWorker.addEventListener('statechange', () => {
                                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                        showUpdateAvailable();
                                    }
                                });
                            }
                        });

                        // Handle service worker messages
                        navigator.serviceWorker.addEventListener('message', event => {
                            handleServiceWorkerMessage(event);
                        });

                        // Check network status
                        checkNetworkStatus();
                    })
                    .catch(registration => {
                        console.log('SW registration failed: ', registration);
                    });
            });
        }

        // Handle service worker messages
        function handleServiceWorkerMessage(event) {
            const data = event.data;

            switch (data.type) {
                case 'SW_UPDATED':
                    showUpdateAvailable();
                    break;
                case 'ONLINE':
                    showOnlineStatus();
                    break;
                case 'OFFLINE':
                    showOfflineStatus();
                    break;
                case 'NOTIFICATION_CLICKED':
                    handleNotificationClick(data);
                    break;
                case 'CACHE_UPDATED':
                    console.log('Cache updated successfully');
                    break;
            }
        }

        // Show update available notification
        function showUpdateAvailable() {
            const updateBar = document.createElement('div');
            updateBar.className = 'update-notification';
            updateBar.innerHTML = `
                <div class="update-content">
                    <i class="fas fa-download"></i>
                    <span>New version available!</span>
                    <button onclick="updateApp()" class="btn-update">Update</button>
                    <button onclick="dismissUpdate()" class="btn-dismiss">&times;</button>
                </div>
            `;

            document.body.appendChild(updateBar);

            // Auto-hide after 10 seconds
            setTimeout(() => {
                if (updateBar.parentNode) {
                    updateBar.remove();
                }
            }, 10000);
        }

        // Update the app
        function updateApp() {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.controller.postMessage({ type: 'FORCE_REFRESH' });
            }
        }

        // Dismiss update notification
        function dismissUpdate() {
            const updateBar = document.querySelector('.update-notification');
            if (updateBar) {
                updateBar.remove();
            }
        }

        // Check network status
        function checkNetworkStatus() {
            if (!navigator.onLine) {
                showOfflineStatus();
            }
        }

        // Show offline status
        function showOfflineStatus() {
            const statusBar = document.createElement('div');
            statusBar.className = 'offline-status';
            statusBar.innerHTML = `
                <div class="offline-content">
                    <i class="fas fa-wifi-slash"></i>
                    <span>You're offline</span>
                    <small>Some features may not be available</small>
                </div>
            `;

            document.body.appendChild(statusBar);
        }

        // Show online status
        function showOnlineStatus() {
            const statusBar = document.querySelector('.offline-status');
            if (statusBar) {
                statusBar.remove();
            }
        }

        // Handle notification clicks
        function handleNotificationClick(data) {
            console.log('Notification clicked:', data);
            // Handle notification actions
        }

        // Network status monitoring
        window.addEventListener('online', () => {
            showOnlineStatus();
        });

        window.addEventListener('offline', () => {
            showOfflineStatus();
        });

        // Install PWA prompt with enhanced functionality
        let deferredPrompt;
        let installPromptShown = false;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e.prompt;

            // Only show install prompt if not previously dismissed
            if (!installPromptShown && localStorage.getItem('pwa-install-dismissed') !== 'true') {
                showInstallPrompt();
                installPromptShown = true;
            }
        });

        function showInstallPrompt() {
            const prompt = document.getElementById('installPrompt');
            if (prompt) {
                prompt.classList.remove('hidden');
            }
        }

        function dismissInstallPrompt() {
            const prompt = document.getElementById('installPrompt');
            if (prompt) {
                prompt.classList.add('hidden');
            }
            localStorage.setItem('pwa-install-dismissed', 'true');
        }

        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                        // Track installation analytics
                        trackEvent('pwa_installed');
                    } else {
                        console.log('User dismissed the install prompt');
                        trackEvent('pwa_dismissed');
                    }
                    deferredPrompt = null;
                    dismissInstallPrompt();
                });
            }
        }

        // Install PWA prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e.prompt;
            showInstallPrompt();
        });

        function showInstallPrompt() {
            const prompt = document.getElementById('installPrompt');
            if (prompt && prompt.classList.contains('hidden')) {
                prompt.classList.remove('hidden');
            }
        }

        function dismissInstallPrompt() {
            const prompt = document.getElementById('installPrompt');
            if (prompt) {
                prompt.classList.add('hidden');
            }
            localStorage.setItem('pwa-install-dismissed', 'true');
        }

        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    } else {
                        console.log('User dismissed the A2HS prompt');
                    }
                    deferredPrompt = null;
                    dismissInstallPrompt();
                });
            }
        }

        // Check if user has previously dismissed the install prompt
        if (localStorage.getItem('pwa-install-dismissed') === 'true') {
            dismissInstallPrompt();
        }

        // Payment function
        function makePayment(repaymentId) {
            // Initiate M-Pesa STK Push
            window.location.href = `../pages/retailer/make-payment.php?id=${repaymentId}`;
        }

        // Check for pending repayments and send reminders
        <?php if (!empty($dashboard_data['pending_repayments'])): ?>
            <?php foreach ($dashboard_data['pending_repayments'] as $repayment): ?>
                <?php
                $due_date = new DateTime($repayment['due_date']);
                $now = new DateTime();
                $diff = $now->diff($due_date);
                if ($diff->days <= 1): // Within 1 day of due date
                ?>
                    setTimeout(() => {
                        alert('Payment reminder: KES <?php echo number_format($repayment['amount_due'], 2); ?> due on <?php echo date('M j, Y', strtotime($repayment['due_date'])); ?>');
                    }, 2000);
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        // Auto-refresh every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);

        // Handle back button
        window.addEventListener('popstate', (event) => {
            if (event.state === null) {
                history.pushState({}, document.title, window.location.href);
            }
        });

        // Initialize app state
        history.pushState({}, document.title, window.location.href);
    </script>
</body>
</html>