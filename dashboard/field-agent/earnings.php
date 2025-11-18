<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('field_agent');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get earnings summary
$db->query('SELECT 
            COUNT(CASE WHEN status = "delivered" THEN 1 END) as total_deliveries,
            SUM(CASE WHEN status = "delivered" THEN 100 ELSE 0 END) as total_earnings,
            COUNT(CASE WHEN status = "delivered" AND DATE(actual_delivery_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_deliveries,
            SUM(CASE WHEN status = "delivered" AND DATE(actual_delivery_date) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 100 ELSE 0 END) as week_earnings
            FROM delivery_tracking');
$earnings_summary = $db->single();

// Get monthly breakdown
$db->query('SELECT 
            DATE_FORMAT(actual_delivery_date, "%Y-%m") as month,
            COUNT(*) as deliveries,
            SUM(100) as earnings
            FROM delivery_tracking
            WHERE status = "delivered" AND actual_delivery_date IS NOT NULL
            GROUP BY DATE_FORMAT(actual_delivery_date, "%Y-%m")
            ORDER BY month DESC LIMIT 12');
$monthly_earnings = $db->resultSet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - Field Agent - JuaKali Lend</title>
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
                <li><a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a></li>
                <li><a href="verification.php"><i class="fas fa-check-circle"></i> Verify Deliveries</a></li>
                <li><a href="earnings.php" class="active"><i class="fas fa-money-bill"></i> My Earnings</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-chart-bar"></i> My Earnings</h1>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box-open"></i></div>
                    <div class="stat-label">Total Deliveries</div>
                    <div class="stat-value"><?php echo $earnings_summary['total_deliveries'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">Total Earnings</div>
                    <div class="stat-value">KES <?php echo number_format($earnings_summary['total_earnings'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-label">This Week Deliveries</div>
                    <div class="stat-value"><?php echo $earnings_summary['week_deliveries'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-label">This Week Earnings</div>
                    <div class="stat-value">KES <?php echo number_format($earnings_summary['week_earnings'] ?? 0, 2); ?></div>
                </div>
            </div>

            <!-- Monthly Breakdown -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-chart-line"></i> Monthly Earnings Breakdown</h3>
                <?php if ($monthly_earnings): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Deliveries</th>
                                    <th>Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_earnings as $month): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                        <td><?php echo $month['deliveries']; ?></td>
                                        <td>KES <?php echo number_format($month['earnings'] ?? 0, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No earnings data yet.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
