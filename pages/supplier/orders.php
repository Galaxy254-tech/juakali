<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a supplier
if (!isLoggedIn() || $_SESSION['role'] !== 'supplier') {
    redirect('../pages/auth/login.php');
}

$supplier_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = sanitize($_POST['order_id'] ?? '');
    $action = sanitize($_POST['action'] ?? '');

    if (empty($order_id) || empty($action)) {
        $error = 'Invalid request';
    } else {
        try {
            $db = Database::getInstance();

            switch ($action) {
                case 'confirm':
                    $db->execute('UPDATE orders SET status = "confirmed" WHERE id = ? AND supplier_id = ?', [$order_id, $supplier_id]);
                    $success = 'Order confirmed successfully!';
                    break;

                case 'ready_for_delivery':
                    $delivery_code = strtoupper(substr(md5(uniqid()), 0, 6));
                    $db->execute('UPDATE orders SET status = "processing", delivery_code = ? WHERE id = ? AND supplier_id = ?', [$delivery_code, $order_id, $supplier_id]);
                    $success = 'Order marked as ready for delivery. Delivery code: ' . $delivery_code;
                    break;

                case 'delivered':
                    $db->execute('UPDATE orders SET status = "delivered", delivered_at = NOW() WHERE id = ? AND supplier_id = ?', [$order_id, $supplier_id]);
                    $success = 'Order marked as delivered!';
                    break;

                case 'cancel':
                    $reason = sanitize($_POST['reason'] ?? '');
                    $db->execute('UPDATE orders SET status = "cancelled" WHERE id = ? AND supplier_id = ?', [$order_id, $supplier_id]);
                    // Create notification for retailer
                    $order = $db->fetchOne('SELECT * FROM orders WHERE id = ?', [$order_id]);
                    $db->execute('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)',
                        [$order['retailer_id'], 'Order Cancelled', 'Your order has been cancelled. Reason: ' . $reason, 'order_status']);
                    $success = 'Order cancelled successfully!';
                    break;
            }

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get orders for this supplier
try {
    $db = Database::getInstance();
    $orders = $db->fetchAll('
        SELECT o.*, u.first_name, u.last_name, u.company_name as retailer_name, u.phone as retailer_phone
        FROM orders o
        JOIN users u ON o.retailer_id = u.id
        WHERE o.supplier_id = ?
        ORDER BY o.created_at DESC
    ', [$supplier_id]);

    // Get order items for each order
    foreach ($orders as &$order) {
        $order['items'] = $db->fetchAll('
            SELECT oi.*, p.name, p.description, p.image_url
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ', [$order['id']]);
    }

} catch (Exception $e) {
    $orders = [];
}

// Get statistics
try {
    $stats = [
        'total_orders' => $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE supplier_id = ?', [$supplier_id]),
        'pending_orders' => $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = "pending"', [$supplier_id]),
        'delivered_today' => $db->fetchColumn('SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = "delivered" AND DATE(delivered_at) = CURDATE()', [$supplier_id]),
        'total_revenue' => $db->fetchColumn('SELECT SUM(total_amount) FROM orders WHERE supplier_id = ? AND status = "delivered"', [$supplier_id]) ?? 0
    ];
} catch (Exception $e) {
    $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'delivered_today' => 0,
        'total_revenue' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Supplier Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .supplier-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1200px;
            overflow: hidden;
        }
        .supplier-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid #bbf7d0;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }
        .order-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.1);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-confirmed {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-processing {
            background: #e0e7ff;
            color: #3730a3;
        }
        .status-delivered {
            background: #d1fae5;
            color: #065f46;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-confirm {
            background: #3b82f6;
            color: white;
        }
        .btn-confirm:hover {
            background: #2563eb;
            color: white;
        }
        .btn-ready {
            background: #10b981;
            color: white;
        }
        .btn-ready:hover {
            background: #059669;
            color: white;
        }
        .btn-delivered {
            background: #065f46;
            color: white;
        }
        .btn-delivered:hover {
            background: #047857;
            color: white;
        }
        .delivery-code {
            background: #fef3c7;
            border: 2px solid #fbbf24;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            font-family: monospace;
            font-size: 1.25rem;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .order-timeline {
            position: relative;
            padding-left: 2rem;
        }
        .order-timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.75rem;
            top: 0.25rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e5e7eb;
            border: 2px solid white;
        }
        .timeline-item.completed::before {
            background: #10b981;
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: 2px solid #e5e7eb;
            background: white;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        .filter-btn:hover, .filter-btn.active {
            border-color: #10b981;
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="supplier-container">
            <div class="supplier-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="fas fa-truck"></i> Supplier Portal</h1>
                        <p>Manage your orders and deliveries</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h4><?php echo htmlspecialchars($_SESSION['company_name']); ?></h4>
                        <small><?php echo htmlspecialchars($_SESSION['email']); ?></small>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                            <h3><?php echo $stats['total_orders']; ?></h3>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h3><?php echo $stats['pending_orders']; ?></h3>
                            <small class="text-muted">Pending Orders</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h3><?php echo $stats['delivered_today']; ?></h3>
                            <small class="text-muted">Delivered Today</small>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-money-bill-wave fa-2x text-info mb-2"></i>
                            <h3>KES <?php echo number_format($stats['total_revenue'], 0); ?></h3>
                            <small class="text-muted">Total Revenue</small>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="mb-4">
                    <button class="filter-btn active" onclick="filterOrders('all')">All Orders</button>
                    <button class="filter-btn" onclick="filterOrders('pending')">Pending</button>
                    <button class="filter-btn" onclick="filterOrders('confirmed')">Confirmed</button>
                    <button class="filter-btn" onclick="filterOrders('processing')">Processing</button>
                    <button class="filter-btn" onclick="filterOrders('delivered')">Delivered</button>
                </div>

                <!-- Orders List -->
                <div id="ordersList">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                            <h4>No orders yet</h4>
                            <p class="text-muted">When retailers place orders, they will appear here</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card" data-status="<?php echo $order['status']; ?>">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5>Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y H:i', strtotime($order['created_at'])); ?>
                                                </small>
                                            </div>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>

                                        <!-- Customer Info -->
                                        <div class="mb-3 p-3 bg-light rounded">
                                            <h6><i class="fas fa-user"></i> Customer Information</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong>Name:</strong> <?php echo htmlspecialchars($order['retailer_name']); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($order['retailer_phone']); ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Order Total:</strong> KES <?php echo number_format($order['total_amount'], 2); ?><br>
                                                    <strong>Items:</strong> <?php echo count($order['items']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Order Items -->
                                        <div class="mb-3">
                                            <h6><i class="fas fa-box"></i> Order Items</h6>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                        <small class="text-muted d-block"><?php echo htmlspecialchars($item['description']); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo $item['quantity']; ?> × KES <?php echo number_format($item['unit_price'], 2); ?></small><br>
                                                        <strong>KES <?php echo number_format($item['total_price'], 2); ?></strong>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Delivery Code -->
                                        <?php if ($order['delivery_code']): ?>
                                            <div class="delivery-code mb-3">
                                                <i class="fas fa-qrcode"></i> Delivery Code<br>
                                                <?php echo htmlspecialchars($order['delivery_code']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- Order Timeline -->
                                        <div class="order-timeline">
                                            <div class="timeline-item completed">
                                                <strong>Order Placed</strong><br>
                                                <small><?php echo date('M j, H:i', strtotime($order['created_at'])); ?></small>
                                            </div>
                                            <?php if ($order['status'] !== 'pending'): ?>
                                                <div class="timeline-item completed">
                                                    <strong>Order Confirmed</strong><br>
                                                    <small>You confirmed this order</small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($order['status'] === 'processing' || $order['status'] === 'delivered'): ?>
                                                <div class="timeline-item completed">
                                                    <strong>Ready for Delivery</strong><br>
                                                    <small>Delivery code generated</small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($order['status'] === 'delivered'): ?>
                                                <div class="timeline-item completed">
                                                    <strong>Delivered</strong><br>
                                                    <small><?php echo date('M j, H:i', strtotime($order['delivered_at'])); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="d-grid gap-2">
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <button class="btn btn-action btn-confirm" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'confirm')">
                                                    <i class="fas fa-check"></i> Confirm Order
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($order['status'] === 'confirmed'): ?>
                                                <button class="btn btn-action btn-ready" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'ready_for_delivery')">
                                                    <i class="fas fa-truck"></i> Ready for Delivery
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($order['status'] === 'processing'): ?>
                                                <button class="btn btn-action btn-delivered" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')">
                                                    <i class="fas fa-check-circle"></i> Mark as Delivered
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                                <button class="btn btn-action btn-danger" onclick="cancelOrder(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-times"></i> Cancel Order
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="cancelForm">
                        <input type="hidden" name="order_id" id="cancelOrderId">
                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for cancellation</label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" onclick="submitCancel()">Cancel Order</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterOrders(status) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            // Filter orders
            const orders = document.querySelectorAll('.order-card');
            orders.forEach(order => {
                if (status === 'all' || order.dataset.status === status) {
                    order.style.display = 'block';
                } else {
                    order.style.display = 'none';
                }
            });
        }

        function updateOrderStatus(orderId, action) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="order_id" value="${orderId}">
                <input type="hidden" name="action" value="${action}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function cancelOrder(orderId) {
            document.getElementById('cancelOrderId').value = orderId;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }

        function submitCancel() {
            const form = document.getElementById('cancelForm');
            const formData = new FormData(form);
            formData.append('action', 'cancel');

            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>