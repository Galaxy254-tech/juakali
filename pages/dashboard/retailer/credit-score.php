<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('retailer');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get credit score details
$db->query('SELECT cs.*, u.first_name, u.last_name FROM credit_scores cs JOIN users u ON cs.retailer_id = u.id WHERE cs.retailer_id = ?');
$db->bind('i', $user_id);
$credit = $db->single();

// Get loan history
$db->query('SELECT COUNT(*) as total_loans FROM loans WHERE retailer_id = ?');
$db->bind('i', $user_id);
$loan_stats = $db->single();

$db->query('SELECT COUNT(*) as repaid_loans FROM loans WHERE retailer_id = ? AND status = "repaid"');
$db->bind('i', $user_id);
$repaid_stats = $db->single();

$db->query('SELECT COUNT(*) as defaulted_loans FROM loans WHERE retailer_id = ? AND status = "defaulted"');
$db->bind('i', $user_id);
$defaulted_stats = $db->single();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Score - JuaKali Lend</title>
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
                <li><a href="credit-score.php" class="active"><i class="fas fa-star"></i> Credit Score</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-star"></i> Credit Score</h1>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Your Credit Score</h5>
                        </div>
                        <div class="card-body text-center">
                            <div style="font-size: 4rem; font-weight: 700; color: #10b981; margin: 2rem 0;">
                                <?php echo $credit['score']; ?>
                            </div>
                            <p class="text-muted">Out of 1000</p>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar" style="width: <?php echo ($credit['score'] / 10); ?>%; background: linear-gradient(135deg, #10b981 0%, #34d399 100%);">
                                    <?php echo round(($credit['score'] / 10), 1); ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Credit Limit</h5>
                        </div>
                        <div class="card-body text-center">
                            <div style="font-size: 2.5rem; font-weight: 700; color: #10b981; margin: 2rem 0;">
                                <?php echo formatCurrency($credit['credit_limit']); ?>
                            </div>
                            <p class="text-muted">Available credit</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                        <div class="stat-label">Total Loans</div>
                        <div class="stat-value"><?php echo $loan_stats['total_loans']; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-label">Repaid Loans</div>
                        <div class="stat-value"><?php echo $repaid_stats['repaid_loans']; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-label">Defaulted</div>
                        <div class="stat-value"><?php echo $defaulted_stats['defaulted_loans']; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                        <div class="stat-label">Total Borrowed</div>
                        <div class="stat-value"><?php echo formatCurrency($credit['total_borrowed']); ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
