<?php
/**
 * Supplier Portal Dashboard
 * Comprehensive order management, delivery tracking, and analytics for suppliers
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a supplier
if (!isLoggedIn() || $_SESSION['user_role'] !== 'supplier') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$supplierId = $_SESSION['user_id'];

// Get supplier data
$supplier = $db->fetchOne("SELECT * FROM suppliers WHERE user_id = ?", [$supplierId]);

// Get dashboard statistics
$stats = getSupplierStatistics($db, $supplierId);
$recentOrders = getRecentOrders($db, $supplierId);
$upcomingDeliveries = getUpcomingDeliveries($db, $supplierId);
$earnings = getEarningsData($db, $supplierId);
$performanceMetrics = getPerformanceMetrics($db, $supplierId);

function getSupplierStatistics($db, $supplierId) {
    return $db->fetchOne("
        SELECT
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) as pending_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'in_transit' THEN o.id END) as in_transit_orders,
            COUNT(DISTINCT CASE WHEN o.delivery_confirmed = 1 THEN o.id END) as confirmed_deliveries,
            COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN o.status = 'completed' THEN d.rating END), 0) as avg_rating,
            COUNT(DISTINCT o.retailer_id) as unique_retailers
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.supplier_id = ?
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ", [$supplierId]);
}

function getRecentOrders($db, $supplierId) {
    return $db->fetchAll("
        SELECT
            o.*,
            r.name as retailer_name,
            r.phone as retailer_phone,
            r.business_name,
            d.status as delivery_status,
            d.assigned_agent_id,
            a.name as agent_name,
            d.scheduled_delivery_date,
            d.actual_delivery_time
        FROM orders o
        LEFT JOIN users r ON o.retailer_id = r.id
        LEFT JOIN deliveries d ON o.id = d.order_id
        LEFT JOIN field_agents a ON d.assigned_agent_id = a.id
        WHERE o.supplier_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ", [$supplierId]);
}

function getUpcomingDeliveries($db, $supplierId) {
    return $db->fetchAll("
        SELECT
            o.id as order_id,
            o.order_number,
            r.name as retailer_name,
            r.phone as retailer_phone,
            r.business_address,
            d.scheduled_delivery_date,
            d.delivery_time_slot,
            d.assigned_agent_id,
            a.name as agent_name,
            a.phone as agent_phone,
            o.total_amount,
            o.items
        FROM orders o
        LEFT JOIN users r ON o.retailer_id = r.id
        LEFT JOIN deliveries d ON o.id = d.order_id
        LEFT JOIN field_agents a ON d.assigned_agent_id = a.id
        WHERE o.supplier_id = ?
        AND o.status IN ('approved', 'in_transit')
        AND d.scheduled_delivery_date >= CURDATE()
        ORDER BY d.scheduled_delivery_date ASC
        LIMIT 5
    ", [$supplierId]);
}

function getEarningsData($db, $supplierId) {
    // Monthly earnings trend
    $monthlyEarnings = $db->fetchAll("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total_amount) as revenue,
            COUNT(*) as order_count,
            AVG(total_amount) as avg_order_value
        FROM orders
        WHERE supplier_id = ? AND status = 'completed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$supplierId]);

    // Today's earnings
    $todayEarnings = $db->fetchOne("
        SELECT
            COUNT(*) as orders_today,
            COALESCE(SUM(total_amount), 0) as revenue_today
        FROM orders
        WHERE supplier_id = ? AND status = 'completed'
        AND DATE(completed_at) = CURDATE()
    ", [$supplierId]);

    return [
        'monthly_trend' => $monthlyEarnings,
        'today' => $todayEarnings
    ];
}

function getPerformanceMetrics($db, $supplierId) {
    return $db->fetchOne("
        SELECT
            ROUND(AVG(CASE WHEN o.status = 'completed' THEN
                TIMESTAMPDIFF(HOUR, o.created_at, o.completed_at) END), 1) as avg_fulfillment_hours,
            ROUND(COUNT(CASE WHEN d.delivery_confirmed = 1 THEN 1 END) * 100.0 /
                  NULLIF(COUNT(CASE WHEN o.status = 'completed' THEN 1 END), 0), 2) as delivery_confirmation_rate,
            ROUND(AVG(CASE WHEN d.rating IS NOT NULL THEN d.rating END), 2) as avg_delivery_rating,
            COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
            ROUND(COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) * 100.0 /
                  NULLIF(COUNT(*), 0), 2) as cancellation_rate
        FROM orders o
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.supplier_id = ?
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ", [$supplierId]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .supplier-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            border-left: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #17a2b8; }

        .order-status {
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .delivery-timeline {
            position: relative;
            padding-left: 30px;
        }

        .delivery-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #007bff;
        }

        .rating-stars {
            color: #ffc107;
        }

        .earnings-chart {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="supplier-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-truck"></i> Supplier Dashboard</h1>
                    <p class="mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block text-end">
                        <div class="h4 mb-1">KES <?php echo number_format($stats['total_revenue'], 0); ?></div>
                        <div class="small">Total Revenue (30 days)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo $stats['total_orders']; ?></div>
                            <div class="text-muted small">Total Orders</div>
                            <div class="text-success small">
                                <i class="fas fa-arrow-up"></i> <?php echo $stats['completed_orders']; ?> completed
                            </div>
                        </div>
                        <i class="fas fa-shopping-cart fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo $stats['pending_orders']; ?></div>
                            <div class="text-muted small">Pending Orders</div>
                            <div class="text-info small">
                                <i class="fas fa-clock"></i> Awaiting processing
                            </div>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo $stats['confirmed_deliveries']; ?></div>
                            <div class="text-muted small">Confirmed Deliveries</div>
                            <div class="text-warning small">
                                <i class="fas fa-check-circle"></i> This month
                            </div>
                        </div>
                        <i class="fas fa-truck-loading fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo round($stats['avg_rating'], 1); ?></div>
                            <div class="text-muted small">Average Rating</div>
                            <div class="rating-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($stats['avg_rating']) ? '' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <i class="fas fa-star fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Upcoming Deliveries -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-alt"></i> Upcoming Deliveries</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshDeliveries()">
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingDeliveries)): ?>
                            <p class="text-muted text-center">No upcoming deliveries scheduled</p>
                        <?php else: ?>
                            <div class="delivery-timeline">
                                <?php foreach ($upcomingDeliveries as $delivery): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($delivery['retailer_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($delivery['business_address']); ?></small>
                                                <div class="mt-1">
                                                    <span class="badge bg-primary"><?php echo date('M j, Y', strtotime($delivery['scheduled_delivery_date'])); ?></span>
                                                    <span class="badge bg-info"><?php echo $delivery['delivery_time_slot']; ?></span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted">Order #<?php echo $delivery['order_id']; ?></small><br>
                                                <strong>KES <?php echo number_format($delivery['total_amount'], 0); ?></strong>
                                                <?php if ($delivery['agent_name']): ?>
                                                    <div class="mt-1">
                                                        <small class="text-info">
                                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($delivery['agent_name']); ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewOrderDetails(<?php echo $delivery['order_id']; ?>)">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="confirmDelivery(<?php echo $delivery['order_id']; ?>)">
                                                <i class="fas fa-check"></i> Confirm Delivery
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" onclick="contactAgent(<?php echo $delivery['agent_id']; ?>)">
                                                <i class="fas fa-phone"></i> Contact Agent
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Performance Metrics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6">
                                <div class="h4 text-primary"><?php echo $performanceMetrics['avg_fulfillment_hours']; ?>h</div>
                                <div class="small text-muted">Avg. Fulfillment Time</div>
                            </div>
                            <div class="col-md-6">
                                <div class="h4 text-success"><?php echo $performanceMetrics['delivery_confirmation_rate']; ?>%</div>
                                <div class="small text-muted">Delivery Confirmation Rate</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-md-6">
                                <div class="h4 text-warning"><?php echo $performanceMetrics['cancelled_orders']; ?></div>
                                <div class="small text-muted">Cancelled Orders (30 days)</div>
                            </div>
                            <div class="col-md-6">
                                <div class="h4 text-info"><?php echo $stats['unique_retailers']; ?></div>
                                <div class="small text-muted">Unique Retailers</div>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <div class="h4 rating-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= round($performanceMetrics['avg_delivery_rating']) ? '' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="text-muted"> (<?php echo round($performanceMetrics['avg_delivery_rating'], 1); ?>)</span>
                            </div>
                            <div class="small text-muted">Average Delivery Rating</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Earnings Chart -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="earnings-chart">
                    <h5><i class="fas fa-chart-area"></i> Revenue Trend (12 Months)</h5>
                    <canvas id="earningsChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-coins"></i> Today's Summary</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="h3 text-success mb-2">KES <?php echo number_format($earnings['today']['revenue_today'], 0); ?></div>
                        <div class="small text-muted mb-3">Revenue Today</div>
                        <div class="h4 text-primary mb-2"><?php echo $earnings['today']['orders_today']; ?></div>
                        <div class="small text-muted">Orders Completed</div>
                        <hr>
                        <div class="text-start">
                            <small class="text-muted">Best performing month:</small><br>
                            <strong><?php echo date('F Y', strtotime($earnings['monthly_trend'][0]['month'] . '-01')); ?></strong><br>
                            <small class="text-success">KES <?php echo number_format($earnings['monthly_trend'][0]['revenue'], 0); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> Recent Orders</h5>
                        <div>
                            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="orderFilter" onchange="filterOrders()">
                                <option value="">All Orders</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="in_transit">In Transit</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportOrders()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="ordersTable">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Retailer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Delivery</th>
                                        <th>Agent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo $order['id']; ?></strong><br>
                                                <small class="text-muted"><?php echo date('M j, H:i', strtotime($order['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($order['retailer_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['business_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>KES <?php echo number_format($order['total_amount'], 0); ?></strong>
                                            </td>
                                            <td>
                                                <span class="order-status badge bg-<?php echo getStatusColor($order['status']); ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $order['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order['scheduled_delivery_date']): ?>
                                                    <div>
                                                        <small><?php echo date('M j', strtotime($order['scheduled_delivery_date'])); ?></small>
                                                        <?php if ($order['actual_delivery_time']): ?>
                                                            <br><small class="text-success">
                                                                <i class="fas fa-check"></i> <?php echo date('H:i', strtotime($order['actual_delivery_time'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">Not scheduled</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($order['agent_name']): ?>
                                                    <small><?php echo htmlspecialchars($order['agent_name']); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">Unassigned</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="printOrder(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if ($order['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-success" onclick="acceptOrder(<?php echo $order['id']; ?>)">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Earnings Chart
        const earningsCtx = document.getElementById('earningsChart').getContext('2d');
        const earningsData = <?php echo json_encode(array_reverse($earnings['monthly_trend'])); ?>;

        new Chart(earningsCtx, {
            type: 'line',
            data: {
                labels: earningsData.map(d => {
                    const date = new Date(d.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue',
                    data: earningsData.map(d => d.revenue),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Average Order Value',
                    data: earningsData.map(d => d.avg_order_value),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'approved': 'info',
                'in_transit': 'primary',
                'completed': 'success',
                'cancelled' => 'danger'
            };
            return colors[status] || 'secondary';
        }

        function viewOrderDetails(orderId) {
            // Load order details via AJAX
            fetch('get-order-details.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Order Information</h6>
                                <p><strong>Order #:</strong> ${data.id}</p>
                                <p><strong>Date:</strong> ${data.created_at}</p>
                                <p><strong>Status:</strong> ${data.status}</p>
                                <p><strong>Total Amount:</strong> KES ${data.total_amount.toLocaleString()}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Retailer Information</h6>
                                <p><strong>Name:</strong> ${data.retailer_name}</p>
                                <p><strong>Business:</strong> ${data.business_name}</p>
                                <p><strong>Phone:</strong> ${data.retailer_phone}</p>
                                <p><strong>Address:</strong> ${data.business_address}</p>
                            </div>
                        </div>
                        <hr>
                        <h6>Order Items</h6>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    if (data.items) {
                        data.items.forEach(item => {
                            html += `
                                <tr>
                                    <td>${item.name}</td>
                                    <td>${item.quantity}</td>
                                    <td>KES ${item.unit_price.toLocaleString()}</td>
                                    <td>KES ${(item.quantity * item.unit_price).toLocaleString()}</td>
                                </tr>
                            `;
                        });
                    }

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;

                    document.getElementById('orderDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
                });
        }

        function confirmDelivery(orderId) {
            if (confirm('Are you sure you want to confirm this delivery?')) {
                // Implementation for delivery confirmation
                window.location.href = 'confirm-delivery.php?order_id=' + orderId;
            }
        }

        function acceptOrder(orderId) {
            if (confirm('Accept this order?')) {
                // Implementation for order acceptance
                window.location.href = 'accept-order.php?order_id=' + orderId;
            }
        }

        function printOrder(orderId) {
            window.open('print-order.php?order_id=' + orderId, '_blank');
        }

        function filterOrders() {
            const status = document.getElementById('orderFilter').value;
            const rows = document.querySelectorAll('#ordersTable tbody tr');

            rows.forEach(row => {
                const orderStatus = row.querySelector('.order-status').textContent.trim().toLowerCase();
                const show = !status || orderStatus === status.toLowerCase().replace('_', ' ');
                row.style.display = show ? '' : 'none';
            });
        }

        function refreshDeliveries() {
            location.reload();
        }

        function exportOrders() {
            window.open('export-orders.php', '_blank');
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>

<?php
function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'approved' => 'info',
        'in_transit' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}
?>
