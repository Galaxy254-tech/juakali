<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('retailer');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get repayment schedule
$db->query('SELECT rs.*, l.loan_amount, l.interest_rate, u.company_name FROM repayment_schedule rs 
           JOIN loans l ON rs.loan_id = l.id 
           JOIN users u ON l.lender_id = u.id 
           WHERE l.retailer_id = ? 
           ORDER BY rs.due_date ASC');
$db->bind('i', $user_id);
$result = $db->resultSet();
$repayments = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repayments - JuaKali Lend</title>
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
                <li><a href="repayments.php" class="active"><i class="fas fa-credit-card"></i> Repayments</a></li>
                <li><a href="credit-score.php"><i class="fas fa-star"></i> Credit Score</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-credit-card"></i> Repayment Schedule</h1>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Lender</th>
                            <th>Amount Due</th>
                            <th>Amount Paid</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repayments as $repayment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($repayment['company_name']); ?></td>
                                <td><?php echo formatCurrency($repayment['amount_due']); ?></td>
                                <td><?php echo formatCurrency($repayment['amount_paid']); ?></td>
                                <td><?php echo formatDate($repayment['due_date']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $repayment['status'] === 'completed' ? 'success' : ($repayment['status'] === 'overdue' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($repayment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($repayment['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-primary">Pay Now</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
