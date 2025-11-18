<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('field_agent');

$db = new Database();
$db->connect();

$filter_status = $_GET['status'] ?? 'all';
$query = 'SELECT o.*, u.company_name, dt.status as delivery_status, dt.tracking_number FROM orders o
          JOIN delivery_tracking dt ON o.id = dt.order_id
          JOIN users u ON o.supplier_id = u.id';

if ($filter_status !== 'all') {
    $query .= ' WHERE dt.status = "' . sanitize($filter_status) . '"';
}
$query .= ' ORDER BY o.delivery_date ASC';

$db->query($query);
$deliveries = $db->resultSet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries - Field Agent - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <h5 style="margin-bottom: 1.5rem; font-weight: 700;"><i class="fas fa-bars"></i> Menu</h5>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="retailers.php"><i class="fas fa-users"></i> Retailers</a></li>
                <li><a href="deliveries.php" class="active"><i class="fas fa-truck"></i> Deliveries</a></li>
                <li><a href="verification.php"><i class="fas fa-check-circle"></i> Verify Deliveries</a></li>
                <li><a href="earnings.php"><i class="fas fa-money-bill"></i> My Earnings</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-truck"></i> Deliveries</h1>
            </div>

            <!-- Filter -->
            <div class="card-section" style="margin-bottom: 1rem;">
                <div class="btn-group" role="group">
                    <a href="?status=all" class="btn btn-<?php echo $filter_status === 'all' ? 'primary' : 'secondary'; ?>">All</a>
                    <a href="?status=pending" class="btn btn-<?php echo $filter_status === 'pending' ? 'primary' : 'secondary'; ?>">Pending</a>
                    <a href="?status=in_transit" class="btn btn-<?php echo $filter_status === 'in_transit' ? 'primary' : 'secondary'; ?>">In Transit</a>
                    <a href="?status=out_for_delivery" class="btn btn-<?php echo $filter_status === 'out_for_delivery' ? 'primary' : 'secondary'; ?>">Out for Delivery</a>
                    <a href="?status=delivered" class="btn btn-<?php echo $filter_status === 'delivered' ? 'primary' : 'secondary'; ?>">Delivered</a>
                </div>
            </div>

            <div class="card-section">
                <?php if ($deliveries): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Supplier</th>
                                    <th>Tracking #</th>
                                    <th>Delivery Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deliveries as $delivery): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($delivery['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['tracking_number']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $delivery['delivery_status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $delivery['delivery_status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="verification.php?order_id=<?php echo $delivery['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-check"></i> Verify
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No deliveries found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
