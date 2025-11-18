<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'lender') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

$db->query("SELECT * FROM lender_performance WHERE lender_id = ?");
$db->bind(':lender', $_SESSION['user_id']);
$perf = $db->single();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investor Dashboard - Lender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-chart-line"></i> Investor Performance Metrics</h2>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Investment Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Default Rate</small>
                            <h4><?php echo $perf['default_rate'] ?? 0; ?>%</h4>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Rating</small>
                            <h4><?php echo $perf['rating'] ?? 5; ?>/5 <i class="fas fa-star text-warning"></i></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
