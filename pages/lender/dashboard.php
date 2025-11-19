<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a lender
if (!isLoggedIn() || $_SESSION['role'] !== 'lender') {
    redirect('../pages/auth/login.php');
}

$lender_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle loan approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = sanitize($_POST['loan_id'] ?? '');
    $action = sanitize($_POST['action'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    if (empty($loan_id) || empty($action)) {
        $error = 'Invalid request';
    } else {
        try {
            $db = Database::getInstance();
            $db->beginTransaction();

            switch ($action) {
                case 'approve':
                    // Get loan details
                    $loan = $db->fetchOne('SELECT * FROM loans WHERE id = ? AND status = "pending"', [$loan_id]);
                    if (!$loan) {
                        throw new Exception('Loan not found or already processed');
                    }

                    // Update loan status
                    $db->execute('UPDATE loans SET status = "approved", approved_at = NOW(), approval_notes = ? WHERE id = ?',
                        [$notes, $loan_id]);

                    // Create payment to supplier
                    $payment_number = 'PAY' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                    $db->execute('INSERT INTO payments (payment_number, user_id, order_id, amount, payment_type, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                        [$payment_number, $lender_id, $loan['order_id'], $loan['loan_amount'], 'loan_disbursement', 'mobile_money', 'pending']);

                    // Update order status
                    $db->execute('UPDATE orders SET status = "confirmed", payment_status = "paid" WHERE id = ?', [$loan['order_id']]);

                    // Notify retailer
                    $db->execute('INSERT INTO notifications (user_id, title, message, type, action_url) VALUES (?, ?, ?, ?, ?)',
                        [$loan['retailer_id'], 'Loan Approved', 'Your loan request has been approved! Your goods will be delivered soon.', 'loan_approved', 'pages/retailer/loans.php']);

                    // Create repayment schedule notifications
                    $repayments = $db->fetchAll('SELECT * FROM repayment_schedule WHERE loan_id = ? ORDER BY due_date ASC', [$loan_id]);
                    foreach ($repayments as $repayment) {
                        $db->execute('INSERT INTO notifications (user_id, title, message, type, action_url, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                            [$loan['retailer_id'], 'Payment Reminder', 'Payment of KES ' . number_format($repayment['amount_due'], 2) . ' due on ' . date('M j, Y', strtotime($repayment['due_date'])), 'payment_reminder', 'pages/retailer/repay.php?loan_id=' . $loan_id, $repayment['due_date']]);
                    }

                    $success = 'Loan approved successfully! Payment has been sent to the supplier.';
                    break;

                case 'reject':
                    $db->execute('UPDATE loans SET status = "cancelled", approval_notes = ? WHERE id = ?', [$notes, $loan_id]);

                    // Update order status
                    $db->execute('UPDATE orders SET status = "cancelled" WHERE id = ?', [$loan['order_id']]);

                    // Notify retailer
                    $db->execute('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)',
                        [$loan['retailer_id'], 'Loan Rejected', 'Your loan request was rejected. Reason: ' . $notes, 'loan_rejected']);

                    $success = 'Loan rejected successfully.';
                    break;

                case 'disburse':
                    $db->execute('UPDATE loans SET status = "disbursed", disbursed_at = NOW() WHERE id = ?', [$loan_id]);
                    $success = 'Loan disbursed successfully!';
                    break;
            }

            $db->commit();

        } catch (Exception $e) {
            $db->rollback();
            $error = $e->getMessage();
        }
    }
}

// Get lender statistics
try {
    $db = Database::getInstance();

    // Get or create lender performance record
    $performance = $db->fetchOne('SELECT * FROM lender_performance WHERE lender_id = ?', [$lender_id]);
    if (!$performance) {
        $db->execute('INSERT INTO lender_performance (lender_id, total_invested, total_returned, active_investments, rating) VALUES (?, 0, 0, 0, 5.0)', [$lender_id]);
        $performance = $db->fetchOne('SELECT * FROM lender_performance WHERE lender_id = ?', [$lender_id]);
    }

    // Get loan statistics
    $stats = [
        'total_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE lender_id = ?', [$lender_id]),
        'pending_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE lender_id = ? AND status = "pending"', [$lender_id]),
        'active_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE lender_id = ? AND status IN ("approved", "disbursed", "repaying")', [$lender_id]),
        'completed_loans' => $db->fetchColumn('SELECT COUNT(*) FROM loans WHERE lender_id = ? AND status = "completed"', [$lender_id]),
        'total_invested' => $db->fetchColumn('SELECT SUM(loan_amount) FROM loans WHERE lender_id = ? AND status IN ("approved", "disbursed", "repaying", "completed")', [$lender_id]) ?? 0,
        'total_returns' => $db->fetchColumn('SELECT SUM(total_amount) FROM loans WHERE lender_id = ? AND status = "completed"', [$lender_id]) ?? 0,
        'monthly_profit' => $db->fetchColumn('SELECT SUM(total_amount - loan_amount) FROM loans WHERE lender_id = ? AND status = "completed" AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)', [$lender_id]) ?? 0
    ];

    // Calculate ROI
    $roi = $stats['total_invested'] > 0 ? (($stats['total_returns'] - $stats['total_invested']) / $stats['total_invested']) * 100 : 0;

} catch (Exception $e) {
    $stats = [
        'total_loans' => 0,
        'pending_loans' => 0,
        'active_loans' => 0,
        'completed_loans' => 0,
        'total_invested' => 0,
        'total_returns' => 0,
        'monthly_profit' => 0
    ];
    $roi = 0;
    $performance = ['rating' => 5.0];
}

// Get recent loans
try {
    $recent_loans = $db->fetchAll('
        SELECT l.*, u.first_name, u.last_name, u.company_name as retailer_name, o.order_number
        FROM loans l
        JOIN users u ON l.retailer_id = u.id
        JOIN orders o ON l.order_id = o.id
        WHERE l.lender_id = ?
        ORDER BY l.created_at DESC
        LIMIT 10
    ', [$lender_id]);
} catch (Exception $e) {
    $recent_loans = [];
}

// Get pending loans that need approval
try {
    $pending_loans = $db->fetchAll('
        SELECT l.*, u.first_name, u.last_name, u.company_name as retailer_name, o.order_number, o.total_amount
        FROM loans l
        JOIN users u ON l.retailer_id = u.id
        JOIN orders o ON l.order_id = o.id
        WHERE l.lender_id = ? AND l.status = "pending"
        ORDER BY l.created_at ASC
        LIMIT 5
    ', [$lender_id]);
} catch (Exception $e) {
    $pending_loans = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lender Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .lender-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 1400px;
            overflow: hidden;
        }
        .lender-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 2px solid #bbf7d0;
            transition: all 0.3s ease;
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }
        .loan-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .loan-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.1);
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-disbursed {
            background: #e0e7ff;
            color: #3730a3;
        }
        .status-repaying {
            background: #f3e8ff;
            color: #6b21a8;
        }
        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-approve {
            background: #10b981;
            color: white;
        }
        .btn-approve:hover {
            background: #059669;
            color: white;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        .btn-reject:hover {
            background: #dc2626;
            color: white;
        }
        .profit-positive {
            color: #10b981;
            font-weight: 600;
        }
        .rating-stars {
            color: #fbbf24;
        }
        .quick-action-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fed7aa 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border: 2px solid #fbbf24;
            text-align: center;
            transition: all 0.3s ease;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(251, 191, 36, 0.3);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="lender-container">
            <div class="lender-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1><i class="fas fa-chart-line"></i> Lender Dashboard</h1>
                        <p>Manage your investments and track your returns</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <h4><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h4>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $performance['rating'] ? '' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                            <small class="text-white">Rating: <?php echo number_format($performance['rating'], 1); ?>/5.0</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-hand-holding-usd fa-2x text-primary mb-2"></i>
                            <h3><?php echo $stats['total_loans']; ?></h3>
                            <small class="text-muted">Total Loans</small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h3><?php echo $stats['pending_loans']; ?></h3>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                            <h3><?php echo $stats['active_loans']; ?></h3>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h3><?php echo $stats['completed_loans']; ?></h3>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-wallet fa-2x text-purple mb-2"></i>
                            <h3>KES <?php echo number_format($stats['total_invested'], 0); ?></h3>
                            <small class="text-muted">Total Invested</small>
                        </div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="stats-card">
                            <i class="fas fa-percentage fa-2x text-orange mb-2"></i>
                            <h3><?php echo number_format($roi, 1); ?>%</h3>
                            <small class="text-muted">ROI</small>
                        </div>
                    </div>
                </div>

                <!-- Profit and Performance -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5><i class="fas fa-chart-bar"></i> Monthly Performance</h5>
                                <div class="chart-container">
                                    <canvas id="profitChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5><i class="fas fa-trophy"></i> Investment Summary</h5>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <small class="text-muted">Total Returns</small>
                                        <h4 class="profit-positive">KES <?php echo number_format($stats['total_returns'], 0); ?></h4>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Monthly Profit</small>
                                        <h4 class="profit-positive">KES <?php echo number_format($stats['monthly_profit'], 0); ?></h4>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <small class="text-muted">Active Investments</small>
                                        <h4><?php echo $stats['active_loans']; ?></h4>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Success Rate</small>
                                        <h4><?php
                                        $success_rate = $stats['total_loans'] > 0 ? ($stats['completed_loans'] / $stats['total_loans']) * 100 : 0;
                                        echo number_format($success_rate, 1); ?>%</h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <?php if (!empty($pending_loans)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="quick-action-card">
                            <h5><i class="fas fa-exclamation-triangle"></i> Quick Actions Required</h5>
                            <p>You have <?php echo count($pending_loans); ?> pending loan request<?php echo count($pending_loans) > 1 ? 's' : ''; ?> waiting for your approval.</p>
                            <button class="btn btn-warning" onclick="scrollToPending()">
                                <i class="fas fa-arrow-down"></i> Review Pending Loans
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending Loans -->
                <?php if (!empty($pending_loans)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <h5><i class="fas fa-clock"></i> Pending Loan Requests</h5>
                        <div id="pendingLoans">
                            <?php foreach ($pending_loans as $loan): ?>
                                <div class="loan-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <h6>Loan #<?php echo htmlspecialchars($loan['loan_number']); ?></h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <small class="text-muted">Retailer:</small><br>
                                                    <strong><?php echo htmlspecialchars($loan['retailer_name']); ?></strong>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">Order:</small><br>
                                                    <strong><?php echo htmlspecialchars($loan['order_number']); ?></strong>
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-md-6">
                                                    <small class="text-muted">Amount:</small><br>
                                                    <strong>KES <?php echo number_format($loan['loan_amount'], 2); ?></strong>
                                                </div>
                                                <div class="col-md-6">
                                                    <small class="text-muted">Requested:</small><br>
                                                    <strong><?php echo date('M j, Y H:i', strtotime($loan['created_at'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="btn-group-vertical w-100" role="group">
                                                <button class="btn btn-action btn-approve w-100" onclick="approveLoan(<?php echo $loan['id']; ?>)">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-action btn-reject w-100" onclick="rejectLoan(<?php echo $loan['id']; ?>)">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Loans -->
                <div class="row">
                    <div class="col-12">
                        <h5><i class="fas fa-history"></i> Recent Loans</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Loan #</th>
                                        <th>Retailer</th>
                                        <th>Amount</th>
                                        <th>Interest</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_loans)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-2"></i><br>
                                                No loans yet
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_loans as $loan): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($loan['loan_number']); ?></td>
                                                <td><?php echo htmlspecialchars($loan['retailer_name']); ?></td>
                                                <td>KES <?php echo number_format($loan['loan_amount'], 2); ?></td>
                                                <td>KES <?php echo number_format($loan['total_amount'] - $loan['loan_amount'], 2); ?></td>
                                                <td>KES <?php echo number_format($loan['total_amount'], 2); ?></td>
                                                <td><span class="status-badge status-<?php echo $loan['status']; ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                                                <td><?php echo date('M j, Y', strtotime($loan['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($loan['status'] === 'approved'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="disburseLoan(<?php echo $loan['id']; ?>)">
                                                            <i class="fas fa-money-bill"></i> Disburse
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-info" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Loan Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="approveForm">
                        <input type="hidden" name="loan_id" id="approveLoanId">
                        <div class="mb-3">
                            <label class="form-label">Approval Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add any notes for this approval..."></textarea>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> By approving this loan, you agree to pay the supplier KES <span id="approveAmount">0</span> and receive daily repayments with 5% interest over 10 days.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitApprove()">Approve Loan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Loan Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Loan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm">
                        <input type="hidden" name="loan_id" id="rejectLoanId">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Please provide a reason for rejection..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitReject()">Reject Loan</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize profit chart
        const ctx = document.getElementById('profitChart').getContext('2d');
        const profitChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Monthly Profit',
                    data: [<?php echo $stats['monthly_profit']; ?>, <?php echo $stats['monthly_profit'] * 1.1; ?>, <?php echo $stats['monthly_profit'] * 1.2; ?>, <?php echo $stats['monthly_profit'] * 1.3; ?>],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function approveLoan(loanId, amount) {
            document.getElementById('approveLoanId').value = loanId;
            document.getElementById('approveAmount').textContent = amount.toLocaleString('en-KEN', {minimumFractionDigits: 2});
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        function rejectLoan(loanId) {
            document.getElementById('rejectLoanId').value = loanId;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function submitApprove() {
            const form = document.getElementById('approveForm');
            const formData = new FormData(form);
            formData.append('action', 'approve');

            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        function submitReject() {
            const form = document.getElementById('rejectForm');
            const formData = new FormData(form);
            formData.append('action', 'reject');

            fetch('', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    location.reload();
                }
            });
        }

        function disburseLoan(loanId) {
            if (confirm('Are you sure you want to disburse this loan?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="loan_id" value="${loanId}">
                    <input type="hidden" name="action" value="disburse">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewLoanDetails(loanId) {
            // This would open a detailed view of the loan
            alert('Loan details view would open here');
        }

        function scrollToPending() {
            document.getElementById('pendingLoans').scrollIntoView({ behavior: 'smooth' });
        }

        // Auto-refresh every 60 seconds
        setInterval(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>