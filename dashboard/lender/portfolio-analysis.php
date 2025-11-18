<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'lender') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

// Get lender performance data
$db->query("SELECT * FROM lender_performance WHERE lender_id = ?");
$db->bind(':lender', $_SESSION['user_id']);
$performance = $db->single();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Analysis - Lender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-chart-pie"></i> Portfolio Analysis</h2>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Invested</h6>
                        <h3 class="text-success">KES <?php echo number_format($performance['total_invested'] ?? 0, 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Returned</h6>
                        <h3 class="text-success">KES <?php echo number_format($performance['total_returned'] ?? 0, 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">ROI</h6>
                        <h3 class="text-success"><?php echo number_format($performance['roi'] ?? 0, 1); ?>%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Active Investments</h6>
                        <h3 class="text-success"><?php echo $performance['active_investments'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
