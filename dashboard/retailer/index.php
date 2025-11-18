<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('retailer');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get user profile
$db->query('SELECT u.*, cs.score, cs.credit_limit, cs.total_borrowed FROM users u 
           LEFT JOIN credit_scores cs ON u.id = cs.retailer_id 
           WHERE u.id = ?');
$db->bind('i', $user_id);
$user = $db->single();

// Get active orders
$db->query('SELECT COUNT(*) as count FROM orders WHERE retailer_id = ? AND status IN ("pending", "confirmed", "shipped")');
$db->bind('i', $user_id);
$active_orders = $db->single()['count'];

// Get active loans
$db->query('SELECT COUNT(*) as count FROM loans WHERE retailer_id = ? AND status IN ("pending", "approved", "disbursed")');
$db->bind('i', $user_id);
$active_loans = $db->single()['count'];

// Get total spent
$db->query('SELECT SUM(total_amount) as total FROM orders WHERE retailer_id = ?');
$db->bind('i', $user_id);
$total_spent = $db->single()['total'] ?? 0;

// Get pending repayments
$db->query('SELECT SUM(amount_due - amount_paid) as total FROM repayment_schedule rs 
           JOIN loans l ON rs.loan_id = l.id 
           WHERE l.retailer_id = ? AND rs.status = "pending"');
$db->bind('i', $user_id);
$pending_repayment = $db->single()['total'] ?? 0;

$db->query('SELECT rs.*, l.loan_amount FROM repayment_schedule rs 
           JOIN loans l ON rs.loan_id = l.id 
           WHERE l.retailer_id = ? AND rs.status IN ("pending", "overdue")
           ORDER BY rs.due_date ASC LIMIT 5');
$db->bind('i', $user_id);
$repayment_schedule = $db->resultSet();

$db->query('SELECT o.*, u.company_name FROM orders o 
           JOIN users u ON o.supplier_id = u.id 
           WHERE o.retailer_id = ? 
           ORDER BY o.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$recent_orders = $db->resultSet();

$db->query('SELECT p.*, l.loan_amount FROM payments p 
           JOIN loans l ON p.loan_id = l.id 
           WHERE l.retailer_id = ? 
           ORDER BY p.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$recent_payments = $db->resultSet();

$db->query('SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$notifications = $db->resultSet();

$db->query('SELECT p.*, u.company_name FROM products p 
           JOIN users u ON p.supplier_id = u.id 
           WHERE p.status = "active" 
           ORDER BY RAND() LIMIT 4');
$recommended_products = $db->resultSet();

$credit_limit = $user['credit_limit'] ?? 10000;
$total_borrowed = $user['total_borrowed'] ?? 0;
$credit_utilization = ($total_borrowed / $credit_limit) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retailer Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            background: #f8fafc;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }
        .sidebar-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 2rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
        }
        .stat-icon {
            font-size: 2rem;
            color: #10b981;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .action-btn {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #1f2937;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
            color: #10b981;
        }
        .action-btn i {
            font-size: 2rem;
            color: #10b981;
            display: block;
            margin-bottom: 0.5rem;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        .card-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        .progress-bar-container {
            margin-bottom: 1rem;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .progress {
            height: 10px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
            transition: width 0.3s ease;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            margin: 0;
        }
        .table thead {
            background: #f8fafc;
        }
        .table th {
            border: none;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }
        .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        .notification-item {
            padding: 1rem;
            border-left: 4px solid #10b981;
            background: #f0fdf4;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .product-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
        }
        .product-image {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        .product-info {
            padding: 1rem;
        }
        .product-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }
        .product-supplier {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h5 style="margin-bottom: 1.5rem; font-weight: 700;"><i class="fas fa-bars"></i> Menu</h5>
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Browse Products</a></li>
                <li><a href="orders.php"><i class="fas fa-box"></i> Orders</a></li>
                <li><a href="repayments.php"><i class="fas fa-credit-card"></i> Repayments</a></li>
                <li><a href="credit-score.php"><i class="fas fa-star"></i> Credit Score</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user['first_name'] ?? 'U', 0, 1)); ?></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="stat-label">Credit Score</div>
                    <div class="stat-value"><?php echo $user['score'] ?? 500; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-label">Credit Limit</div>
                    <div class="stat-value"><?php echo formatCurrency($user['credit_limit'] ?? 10000); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-label">Active Orders</div>
                    <div class="stat-value"><?php echo $active_orders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                    <div class="stat-label">Active Loans</div>
                    <div class="stat-value"><?php echo $active_loans; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-label">Total Spent</div>
                    <div class="stat-value"><?php echo formatCurrency($total_spent); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-label">Pending Repayment</div>
                    <div class="stat-value"><?php echo formatCurrency($pending_repayment); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h3 class="section-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="products.php" class="action-btn">
                    <i class="fas fa-shopping-bag"></i>
                    <strong>Browse Products</strong>
                </a>
                <a href="orders.php" class="action-btn">
                    <i class="fas fa-box"></i>
                    <strong>My Orders</strong>
                </a>
                <a href="repayments.php" class="action-btn">
                    <i class="fas fa-credit-card"></i>
                    <strong>Repayments</strong>
                </a>
                <a href="credit-score.php" class="action-btn">
                    <i class="fas fa-star"></i>
                    <strong>Credit Score</strong>
                </a>
            </div>

            <!-- Credit Limit Progress -->
            <div class="card-section">
                <h3 class="section-title">Credit Limit Progress</h3>
                <div class="progress-bar-container">
                    <div class="progress-label">
                        <span>Credit Utilization</span>
                        <span><?php echo number_format($credit_utilization, 1); ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-fill" style="width: <?php echo min($credit_utilization, 100); ?>%"></div>
                    </div>
                    <small class="text-muted">
                        KES <?php echo formatCurrency($total_borrowed); ?> of KES <?php echo formatCurrency($credit_limit); ?> used
                    </small>
                </div>
            </div>

            <!-- Repayment Schedule -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Upcoming Repayments</h3>
                <?php if ($repayment_schedule): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Due Date</th>
                                    <th>Amount Due</th>
                                    <th>Amount Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repayment_schedule as $schedule): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($schedule['due_date'])); ?></td>
                                        <td><?php echo formatCurrency($schedule['amount_due']); ?></td>
                                        <td><?php echo formatCurrency($schedule['amount_paid']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $schedule['status']; ?>">
                                                <?php echo ucfirst($schedule['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No upcoming repayments.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Orders (Order History) -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Orders</h3>
                <?php if ($recent_orders): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['company_name']); ?></td>
                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No orders yet.</p>
                <?php endif; ?>
            </div>

            <!-- Recent Payments (Payment History) -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-receipt"></i> Payment History</h3>
                <?php if ($recent_payments): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $payment['status']; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No payments yet.</p>
                <?php endif; ?>
            </div>

            <!-- Notifications -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-bell"></i> Recent Notifications</h3>
                <?php if ($notifications): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item">
                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                            <p style="margin: 0.5rem 0 0; color: #4b5563;">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>
                            <small class="text-muted">
                                <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No new notifications.</p>
                <?php endif; ?>
            </div>

            <!-- Recommended Products -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-lightbulb"></i> Recommended Products</h3>
                <?php if ($recommended_products): ?>
                    <div class="product-grid">
                        <?php foreach ($recommended_products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-supplier"><?php echo htmlspecialchars($product['company_name']); ?></div>
                                    <div class="product-price">KES <?php echo number_format($product['price'], 2); ?></div>
                                    <a href="products.php" style="color: #10b981; text-decoration: none; font-size: 0.875rem;">
                                        <i class="fas fa-arrow-right"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recommended products available.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
