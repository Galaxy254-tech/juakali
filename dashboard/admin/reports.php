<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

// Get financial data
$db->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $db->single()['total_users'];

$db->query("SELECT SUM(loan_amount) as total_disbursed FROM loans WHERE status = 'disbursed'");
$total_disbursed = $db->single()['total_disbursed'] ?? 0;

$db->query("SELECT SUM(amount) as total_repaid FROM payments WHERE status = 'completed'");
$total_repaid = $db->single()['total_repaid'] ?? 0;

$db->query("SELECT COUNT(*) as total_loans FROM loans");
$total_loans = $db->single()['total_loans'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-chart-bar"></i> Financial Reports</h2>

        <div class="row g-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Users</h6>
                        <h3 class="text-success"><?php echo number_format($total_users); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Disbursed</h6>
                        <h3 class="text-success">KES <?php echo number_format($total_disbursed, 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Repaid</h6>
                        <h3 class="text-success">KES <?php echo number_format($total_repaid, 0); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Active Loans</h6>
                        <h3 class="text-success"><?php echo number_format($total_loans); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Export Options</h5>
            </div>
            <div class="card-body">
                <button class="btn btn-primary"><i class="fas fa-file-csv"></i> Export to CSV</button>
                <button class="btn btn-primary"><i class="fas fa-file-pdf"></i> Export to PDF</button>
                <button class="btn btn-primary"><i class="fas fa-download"></i> Download Report</button>
            </div>
        </div>
    </div>
</body>
</html>
