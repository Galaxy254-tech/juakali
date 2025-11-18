<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/penalty-engine.php';

requireRole('admin');

$db = new Database();
$db->connect();
$penalty_engine = new PenaltyEngine($db);

$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// Process pending penalties
if ($action === 'process') {
    $result = $penalty_engine->processOverdueRepayments();
    $success = "Processed {$result['processed_count']} overdue repayments";
}

// Waive penalty
if ($action === 'waive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $penalty_id = intval($_POST['penalty_id'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? '');
    
    if ($penalty_id && $reason) {
        $result = $penalty_engine->waivePenalty($penalty_id, $reason, $_SESSION['user_id']);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get penalty statistics
$db->query('SELECT 
           COUNT(*) as total_active_penalties,
           SUM(CASE WHEN waived = 0 THEN penalty_amount ELSE 0 END) as total_pending_penalties,
           SUM(CASE WHEN waived = 1 THEN penalty_amount ELSE 0 END) as total_waived_penalties
           FROM penalty_records');
$penalty_stats = $db->single();

// Get recent penalties
$db->query('SELECT pr.*, l.order_id, o.order_number, u.company_name FROM penalty_records pr
           JOIN loans l ON pr.loan_id = l.id
           JOIN orders o ON l.order_id = o.id
           JOIN users u ON l.retailer_id = u.id
           ORDER BY pr.applied_at DESC LIMIT 20');
$recent_penalties = $db->resultSet();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penalty Management - Admin - JuaKali Lend</title>
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
                <li><a href="penalties.php" class="active"><i class="fas fa-gavel"></i> Penalties</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-gavel"></i> Penalty Management</h1>
                <a href="?action=process" class="btn btn-primary" onclick="return confirm('Process all overdue penalties?')">
                    <i class="fas fa-sync"></i> Process Overdue Penalties
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-label">Active Penalties</div>
                    <div class="stat-value"><?php echo $penalty_stats['total_active_penalties'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                    <div class="stat-label">Pending Penalties</div>
                    <div class="stat-value">KES <?php echo number_format($penalty_stats['total_pending_penalties'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check"></i></div>
                    <div class="stat-label">Waived Penalties</div>
                    <div class="stat-value">KES <?php echo number_format($penalty_stats['total_waived_penalties'] ?? 0, 2); ?></div>
                </div>
            </div>

            <!-- Recent Penalties -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Penalties</h3>
                <?php if ($recent_penalties): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Retailer</th>
                                    <th>Days Overdue</th>
                                    <th>Penalty Amount</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_penalties as $penalty): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($penalty['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($penalty['company_name']); ?></td>
                                        <td><?php echo $penalty['days_overdue']; ?></td>
                                        <td><strong>KES <?php echo number_format($penalty['penalty_amount'], 2); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($penalty['applied_at'])); ?></td>
                                        <td>
                                            <?php if ($penalty['waived']): ?>
                                                <span class="status-badge" style="background: #d1fae5; color: #065f46;">Waived</span>
                                            <?php else: ?>
                                                <span class="status-badge" style="background: #fee2e2; color: #991b1b;">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$penalty['waived']): ?>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#waiveModal<?php echo $penalty['id']; ?>">
                                                    <i class="fas fa-eraser"></i> Waive
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- Waive Modal -->
                                    <div class="modal fade" id="waiveModal<?php echo $penalty['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Waive Penalty</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="waive">
                                                        <input type="hidden" name="penalty_id" value="<?php echo $penalty['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="reason<?php echo $penalty['id']; ?>" class="form-label">Reason for Waiver</label>
                                                            <textarea class="form-control" id="reason<?php echo $penalty['id']; ?>" name="reason" rows="3" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Waive Penalty</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No penalties found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
