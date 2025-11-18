<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('field_agent');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get all retailers
$db->query('SELECT DISTINCT u.*, cs.score, cs.credit_limit FROM users u
           LEFT JOIN credit_scores cs ON u.id = cs.retailer_id
           WHERE u.role = "retailer" AND u.status = "active"
           ORDER BY u.created_at DESC');
$retailers = $db->resultSet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retailers - Field Agent - JuaKali Lend</title>
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
                <li><a href="retailers.php" class="active"><i class="fas fa-users"></i> Retailers</a></li>
                <li><a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a></li>
                <li><a href="verification.php"><i class="fas fa-check-circle"></i> Verify Deliveries</a></li>
                <li><a href="earnings.php"><i class="fas fa-money-bill"></i> My Earnings</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-users"></i> Assigned Retailers</h1>
            </div>

            <div class="card-section">
                <?php if ($retailers): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Business Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Credit Score</th>
                                    <th>Credit Limit</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($retailers as $retailer): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($retailer['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($retailer['first_name'] . ' ' . $retailer['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($retailer['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($retailer['email']); ?></td>
                                        <td><?php echo $retailer['score'] ?? 'N/A'; ?></td>
                                        <td>KES <?php echo number_format($retailer['credit_limit'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $retailer['status']; ?>">
                                                <?php echo ucfirst($retailer['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="#" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#retailerModal<?php echo $retailer['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No retailers found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
