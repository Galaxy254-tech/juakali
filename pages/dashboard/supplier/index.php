<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('supplier');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get user profile
$db->query('SELECT u.* FROM users u WHERE u.id = ?');
$db->bind('i', $user_id);
$user = $db->single();

// Get total products
$db->query('SELECT COUNT(*) as count FROM products WHERE supplier_id = ?');
$db->bind('i', $user_id);
$total_products = $db->single()['count'];

// Get pending orders
$db->query('SELECT COUNT(*) as count FROM orders WHERE supplier_id = ? AND status = "pending"');
$db->bind('i', $user_id);
$pending_orders = $db->single()['count'];

// Get total revenue
$db->query('SELECT SUM(total_amount) as total FROM orders WHERE supplier_id = ? AND status = "delivered"');
$db->bind('i', $user_id);
$total_revenue = $db->single()['total'] ?? 0;

// Get active orders
$db->query('SELECT COUNT(*) as count FROM orders WHERE supplier_id = ? AND status IN ("confirmed", "shipped")');
$db->bind('i', $user_id);
$active_orders = $db->single()['count'];

$db->query('SELECT ia.*, p.name FROM inventory_alerts ia 
           JOIN products p ON ia.product_id = p.id 
           WHERE ia.supplier_id = ? AND ia.status = "active" 
           ORDER BY ia.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$inventory_alerts = $db->resultSet();

$db->query('SELECT o.*, u.company_name FROM orders o 
           JOIN users u ON o.retailer_id = u.id 
           WHERE o.supplier_id = ? 
           ORDER BY o.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$recent_orders = $db->resultSet();

$db->query('SELECT dt.*, o.order_number FROM delivery_tracking dt 
           JOIN orders o ON dt.order_id = o.id 
           WHERE o.supplier_id = ? 
           ORDER BY dt.updated_at DESC LIMIT 5');
$db->bind('i', $user_id);
$delivery_tracking = $db->resultSet();

$db->query('SELECT pr.*, p.name, u.company_name FROM product_reviews pr 
           JOIN products p ON pr.product_id = p.id 
           JOIN users u ON pr.retailer_id = u.id 
           WHERE p.supplier_id = ? 
           ORDER BY pr.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$product_reviews = $db->resultSet();

$db->query('SELECT rr.*, o.order_number, u.company_name FROM return_requests rr 
           JOIN orders o ON rr.order_id = o.id 
           JOIN users u ON rr.retailer_id = u.id 
           WHERE o.supplier_id = ? 
           ORDER BY rr.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$return_requests = $db->resultSet();

$db->query('SELECT * FROM supplier_ratings WHERE supplier_id = ?');
$db->bind('i', $user_id);
$supplier_rating = $db->single();

$db->query('SELECT SUM(total_amount) as total FROM orders WHERE supplier_id = ? AND status = "delivered"');
$db->bind('i', $user_id);
$payment_received = $db->single()['total'] ?? 0;

$db->query('SELECT * FROM products WHERE supplier_id = ? AND quantity_available < 10 ORDER BY quantity_available ASC LIMIT 5');
$db->bind('i', $user_id);
$low_stock_products = $db->resultSet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - JuaKali Lend</title>
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
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-shipped {
            background: #d1fae5;
            color: #065f46;
        }
        .status-delivered {
            background: #d1fae5;
            color: #065f46;
        }
        .status-in_transit {
            background: #d1fae5;
            color: #065f46;
        }
        .status-out_for_delivery {
            background: #fef3c7;
            color: #92400e;
        }
        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        .alert-item {
            padding: 1rem;
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }
        .alert-label {
            font-weight: 600;
            color: #92400e;
        }
        .alert-value {
            color: #6b7280;
            font-size: 0.875rem;
        }
        .rating-stars {
            color: #fbbf24;
            font-size: 1.25rem;
        }
        .metric-box {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #10b981;
        }
        .metric-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        .metric-value {
            font-size: 1.5rem;
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
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> My Products</a></li>
                <li><a href="orders.php"><i class="fas fa-box"></i> Orders</a></li>
                <li><a href="sales.php"><i class="fas fa-chart-bar"></i> Sales</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-store"></i> Supplier Dashboard</h1>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($user['company_name'] ?? $user['first_name']); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user['company_name'] ?? $user['first_name'] ?? 'S', 0, 1)); ?></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                    <div class="stat-label">Total Products</div>
                    <div class="stat-value"><?php echo $total_products; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-label">Pending Orders</div>
                    <div class="stat-value"><?php echo $pending_orders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                    <div class="stat-label">Active Orders</div>
                    <div class="stat-value"><?php echo $active_orders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value"><?php echo formatCurrency($total_revenue); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h3 class="section-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="products.php" class="action-btn">
                    <i class="fas fa-plus-circle"></i>
                    <strong>Manage Products</strong>
                </a>
                <a href="orders.php" class="action-btn">
                    <i class="fas fa-box"></i>
                    <strong>View Orders</strong>
                </a>
                <a href="sales.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <strong>Sales Report</strong>
                </a>
            </div>

            <!-- Supplier Rating -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-star"></i> Supplier Rating</h3>
                <div class="row">
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">Average Rating</div>
                            <div class="metric-value">
                                <?php for ($i = 0; $i < floor($supplier_rating['average_rating'] ?? 5); $i++): ?>
                                    <i class="fas fa-star" style="color: #fbbf24;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">Total Reviews</div>
                            <div class="metric-value"><?php echo $supplier_rating['total_reviews'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">On-Time Delivery</div>
                            <div class="metric-value"><?php echo number_format($supplier_rating['on_time_delivery_rate'] ?? 100, 1); ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">Quality Score</div>
                            <div class="metric-value"><?php echo number_format($supplier_rating['quality_score'] ?? 100, 1); ?>%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Alerts -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-exclamation-circle"></i> Inventory Alerts</h3>
                <?php if ($inventory_alerts): ?>
                    <?php foreach ($inventory_alerts as $alert): ?>
                        <div class="alert-item">
                            <div class="alert-label"><?php echo htmlspecialchars($alert['name']); ?></div>
                            <div class="alert-value">
                                Current Stock: <?php echo $alert['current_quantity']; ?> | 
                                Threshold: <?php echo $alert['threshold_quantity']; ?> | 
                                Type: <?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No inventory alerts.</p>
                <?php endif; ?>
            </div>

            <!-- Order Management -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-box"></i> Recent Orders</h3>
                <?php if ($recent_orders): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Retailer</th>
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

            <!-- Delivery Tracking -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-truck"></i> Delivery Tracking</h3>
                <?php if ($delivery_tracking): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Tracking #</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Est. Delivery</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($delivery_tracking as $delivery): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($delivery['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['tracking_number']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $delivery['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($delivery['current_location']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($delivery['estimated_delivery'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No delivery tracking data.</p>
                <?php endif; ?>
            </div>

            <!-- Product Reviews -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-comments"></i> Product Reviews</h3>
                <?php if ($product_reviews): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Retailer</th>
                                    <th>Rating</th>
                                    <th>Review</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($product_reviews as $review): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($review['name']); ?></td>
                                        <td><?php echo htmlspecialchars($review['company_name']); ?></td>
                                        <td>
                                            <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                                <i class="fas fa-star" style="color: #fbbf24;"></i>
                                            <?php endfor; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(substr($review['review_text'], 0, 50)) . '...'; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No product reviews yet.</p>
                <?php endif; ?>
            </div>

            <!-- Return Requests -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-undo"></i> Return Requests</h3>
                <?php if ($return_requests): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Retailer</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Refund</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($return_requests as $return): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($return['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($return['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($return['reason']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $return['status']; ?>">
                                                <?php echo ucfirst($return['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($return['refund_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No return requests.</p>
                <?php endif; ?>
            </div>

            <!-- Low Stock Products -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-warehouse"></i> Low Stock Products</h3>
                <?php if ($low_stock_products): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Current Stock</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td>
                                            <span class="status-badge status-pending">
                                                <?php echo $product['quantity_available']; ?> units
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($product['price']); ?></td>
                                        <td>
                                            <a href="products.php" style="color: #10b981; text-decoration: none;">
                                                <i class="fas fa-edit"></i> Update
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">All products have sufficient stock.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
