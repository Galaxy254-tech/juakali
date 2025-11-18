<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('retailer');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get notifications
$db->query('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$db->bind('i', $user_id);
$result = $db->resultSet();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h5 style="margin-bottom: 1.5rem; font-weight: 700;"><i class="fas fa-bars"></i> Menu</h5>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Browse Products</a></li>
                <li><a href="orders.php"><i class="fas fa-box"></i> Orders</a></li>
                <li><a href="repayments.php"><i class="fas fa-credit-card"></i> Repayments</a></li>
                <li><a href="credit-score.php"><i class="fas fa-star"></i> Credit Score</a></li>
                <li><a href="notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
            </div>

            <div class="row">
                <?php foreach ($notifications as $notification): ?>
                    <div class="col-md-8 mb-3">
                        <div class="card" style="border-left: 4px solid #10b981; <?php echo !$notification['is_read'] ? 'background: rgba(16, 185, 129, 0.05);' : ''; ?>">
                            <div class="card-body">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> <?php echo formatDateTime($notification['created_at']); ?>
                                        </small>
                                    </div>
                                    <span class="badge badge-<?php echo $notification['type']; ?>">
                                        <?php echo ucfirst($notification['type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
