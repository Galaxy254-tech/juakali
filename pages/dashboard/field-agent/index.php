<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('field_agent');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get agent profile and stats
$db->query('SELECT u.*, cs.score FROM users u 
           LEFT JOIN credit_scores cs ON u.id = cs.retailer_id 
           WHERE u.id = ?');
$db->bind('i', $user_id);
$agent = $db->single();

// Get assigned retailers
$db->query('SELECT DISTINCT r.*, cs.score, cs.credit_limit FROM orders o
           JOIN users r ON o.retailer_id = r.id
           LEFT JOIN credit_scores cs ON r.id = cs.retailer_id
           WHERE o.id IN (SELECT DISTINCT order_id FROM delivery_tracking)
           GROUP BY r.id
           ORDER BY o.created_at DESC LIMIT 10');
$assigned_retailers = $db->resultSet();

// Get today's deliveries
$db->query('SELECT o.*, u.company_name, dt.status, dt.tracking_number FROM orders o
           JOIN delivery_tracking dt ON o.id = dt.order_id
           JOIN users u ON o.supplier_id = u.id
           WHERE DATE(o.delivery_date) = CURDATE()
           ORDER BY dt.status ASC');
$todays_deliveries = $db->resultSet();

// Get pending verifications
$db->query('SELECT o.*, u.company_name FROM orders o
           JOIN users u ON o.supplier_id = u.id
           WHERE o.status IN ("shipped", "out_for_delivery")
           AND o.id NOT IN (SELECT order_id FROM delivery_tracking WHERE status = "delivered")
           LIMIT 5');
$pending_verifications = $db->resultSet();

// Get agent earnings
$db->query('SELECT SUM(CASE WHEN status = "delivered" THEN 100 ELSE 0 END) as total_earnings,
           COUNT(CASE WHEN status = "delivered" THEN 1 END) as deliveries_completed
           FROM delivery_tracking
           WHERE order_id IN (SELECT id FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))');
$earnings = $db->single();

$total_earnings = $earnings['total_earnings'] ?? 0;
$deliveries_completed = $earnings['deliveries_completed'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Agent Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <h5 style="margin-bottom: 1.5rem; font-weight: 700;"><i class="fas fa-bars"></i> Menu</h5>
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="retailers.php"><i class="fas fa-users"></i> Retailers</a></li>
                <li><a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a></li>
                <li><a href="verification.php"><i class="fas fa-check-circle"></i> Verify Deliveries</a></li>
                <li><a href="earnings.php"><i class="fas fa-money-bill"></i> My Earnings</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-chart-line"></i> Field Agent Dashboard</h1>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($agent['first_name'] ?? 'Agent'); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($agent['first_name'] ?? 'F', 0, 1)); ?></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-label">Today's Deliveries</div>
                    <div class="stat-value"><?php echo count($todays_deliveries); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check"></i></div>
                    <div class="stat-label">Completed This Month</div>
                    <div class="stat-value"><?php echo $deliveries_completed; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-label">This Month Earnings</div>
                    <div class="stat-value">KES <?php echo number_format($total_earnings, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-label">Assigned Retailers</div>
                    <div class="stat-value"><?php echo count($assigned_retailers); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h3 class="section-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="deliveries.php" class="action-btn">
                    <i class="fas fa-truck"></i>
                    <strong>View Deliveries</strong>
                </a>
                <a href="verification.php" class="action-btn">
                    <i class="fas fa-check-circle"></i>
                    <strong>Verify Delivery</strong>
                </a>
                <a href="retailers.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <strong>Retailers</strong>
                </a>
                <a href="earnings.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <strong>My Earnings</strong>
                </a>
            </div>

            <!-- Today's Deliveries -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-calendar-check"></i> Today's Deliveries</h3>
                <?php if ($todays_deliveries): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Retailer</th>
                                    <th>Tracking #</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todays_deliveries as $delivery): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($delivery['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['tracking_number']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $delivery['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
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
                    <p class="text-muted">No deliveries scheduled for today.</p>
                <?php endif; ?>
            </div>

            <!-- Pending Verifications -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-exclamation-circle"></i> Pending Verifications</h3>
                <?php if ($pending_verifications): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_verifications as $verify): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($verify['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($verify['company_name']); ?></td>
                                        <td>KES <?php echo number_format($verify['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $verify['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $verify['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="verification.php?order_id=<?php echo $verify['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-check"></i> Verify
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No pending verifications.</p>
                <?php endif; ?>
            </div>

            <!-- Assigned Retailers -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-users-cog"></i> Assigned Retailers</h3>
                <?php if ($assigned_retailers): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Retailer</th>
                                    <th>Phone</th>
                                    <th>Credit Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_retailers as $retailer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($retailer['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($retailer['phone']); ?></td>
                                        <td><?php echo $retailer['score'] ?? 'N/A'; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $retailer['status']; ?>">
                                                <?php echo ucfirst($retailer['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No retailers assigned yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
