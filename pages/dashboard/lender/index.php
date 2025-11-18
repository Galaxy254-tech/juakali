<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('lender');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

// Get user profile
$db->query('SELECT u.* FROM users u WHERE u.id = ?');
$db->bind('i', $user_id);
$user = $db->single();

// Get total loans
$db->query('SELECT COUNT(*) as count FROM loans WHERE lender_id = ?');
$db->bind('i', $user_id);
$total_loans = $db->single()['count'];

// Get active loans
$db->query('SELECT COUNT(*) as count FROM loans WHERE lender_id = ? AND status = "disbursed"');
$db->bind('i', $user_id);
$active_loans = $db->single()['count'];

// Get total disbursed
$db->query('SELECT SUM(loan_amount) as total FROM loans WHERE lender_id = ? AND status IN ("disbursed", "repaid")');
$db->bind('i', $user_id);
$total_disbursed = $db->single()['total'] ?? 0;

// Get pending approvals
$db->query('SELECT COUNT(*) as count FROM loans WHERE lender_id = ? AND status = "pending"');
$db->bind('i', $user_id);
$pending_approvals = $db->single()['count'];

$db->query('SELECT * FROM lender_performance WHERE lender_id = ?');
$db->bind('i', $user_id);
$performance = $db->single();

$db->query('SELECT l.*, u.company_name, u.first_name, u.last_name FROM loans l 
           JOIN users u ON l.retailer_id = u.id 
           WHERE l.lender_id = ? AND l.status = "disbursed" 
           ORDER BY l.created_at DESC LIMIT 5');
$db->bind('i', $user_id);
$active_investments = $db->resultSet();

$db->query('SELECT ra.*, u.company_name FROM risk_assessments ra 
           JOIN users u ON ra.retailer_id = u.id 
           JOIN loans l ON u.id = l.retailer_id 
           WHERE l.lender_id = ? 
           GROUP BY ra.retailer_id 
           ORDER BY ra.risk_score DESC LIMIT 5');
$db->bind('i', $user_id);
$risk_assessments = $db->resultSet();

$db->query('SELECT l.*, u.company_name, u.first_name, u.last_name FROM loans l 
           JOIN users u ON l.retailer_id = u.id 
           WHERE l.lender_id = ? 
           ORDER BY l.created_at DESC LIMIT 10');
$db->bind('i', $user_id);
$investment_history = $db->resultSet();

$db->query('SELECT SUM(p.amount) as total FROM payments p 
           JOIN loans l ON p.loan_id = l.id 
           WHERE l.lender_id = ? AND p.status = "completed"');
$db->bind('i', $user_id);
$total_returns = $db->single()['total'] ?? 0;

$db->query('SELECT COUNT(*) as count FROM loans WHERE lender_id = ? AND status = "defaulted"');
$db->bind('i', $user_id);
$defaulted_loans = $db->single()['count'];
$default_rate = $total_loans > 0 ? ($defaulted_loans / $total_loans) * 100 : 0;

$withdrawal_balance = $total_returns - $total_disbursed;

$db->query('SELECT * FROM system_settings WHERE setting_key = "compliance_status"');
$compliance = $db->single();
$compliance_status = $compliance['setting_value'] ?? 'compliant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lender Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
    <style>
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
            background: #f8fafc;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem 1rem;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }
        .sidebar-menu a {
            display: block;
            padding: 0.75rem 1rem;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 2rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
        }
        .stat-icon {
            font-size: 2rem;
            color: #10b981;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .action-btn {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #1f2937;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
            color: #10b981;
        }
        .action-btn i {
            font-size: 2rem;
            color: #10b981;
            display: block;
            margin-bottom: 0.5rem;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        .card-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        .progress-bar-container {
            margin-bottom: 1rem;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .progress {
            height: 10px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
            transition: width 0.3s ease;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            margin: 0;
        }
        .table thead {
            background: #f8fafc;
        }
        .table th {
            border: none;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }
        .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        .status-disbursed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-repaid {
            background: #d1fae5;
            color: #065f46;
        }
        .status-defaulted {
            background: #fee2e2;
            color: #991b1b;
        }
        .risk-low {
            background: #d1fae5;
            color: #065f46;
        }
        .risk-medium {
            background: #fef3c7;
            color: #92400e;
        }
        .risk-high {
            background: #fed7aa;
            color: #92400e;
        }
        .risk-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        .compliance-compliant {
            background: #d1fae5;
            color: #065f46;
        }
        .compliance-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .compliance-non-compliant {
            background: #fee2e2;
            color: #991b1b;
        }
        .metric-box {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #10b981;
        }
        .metric-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #10b981;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <h5 style="margin-bottom: 1.5rem; font-weight: 700;"><i class="fas fa-bars"></i> Menu</h5>
            <ul class="sidebar-menu">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="loans.php"><i class="fas fa-handshake"></i> Loans</a></li>
                <li><a href="approvals.php"><i class="fas fa-check-circle"></i> Approvals</a></li>
                <li><a href="repayments.php"><i class="fas fa-money-bill"></i> Repayments</a></li>
                <li><a href="portfolio.php"><i class="fas fa-chart-pie"></i> Portfolio</a></li>
                <li><a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-university"></i> Lender Dashboard</h1>
                <div class="user-menu">
                    <span>Welcome, <?php echo htmlspecialchars($user['company_name'] ?? $user['first_name']); ?></span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user['company_name'] ?? $user['first_name'] ?? 'L', 0, 1)); ?></div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                    <div class="stat-label">Total Loans</div>
                    <div class="stat-value"><?php echo $total_loans; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">Pending Approvals</div>
                    <div class="stat-value"><?php echo $pending_approvals; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-sync-alt"></i></div>
                    <div class="stat-label">Active Loans</div>
                    <div class="stat-value"><?php echo $active_loans; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">Total Disbursed</div>
                    <div class="stat-value"><?php echo formatCurrency($total_disbursed); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h3 class="section-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="approvals.php" class="action-btn">
                    <i class="fas fa-check-circle"></i>
                    <strong>Pending Approvals</strong>
                </a>
                <a href="loans.php" class="action-btn">
                    <i class="fas fa-handshake"></i>
                    <strong>View Loans</strong>
                </a>
                <a href="repayments.php" class="action-btn">
                    <i class="fas fa-money-bill"></i>
                    <strong>Repayments</strong>
                </a>
                <a href="portfolio.php" class="action-btn">
                    <i class="fas fa-chart-pie"></i>
                    <strong>Portfolio</strong>
                </a>
            </div>

            <!-- Portfolio Value & Performance -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-chart-line"></i> Portfolio Performance</h3>
                <div class="row">
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">Total Invested</div>
                            <div class="metric-value"><?php echo formatCurrency($total_disbursed); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">Total Returns</div>
                            <div class="metric-value"><?php echo formatCurrency($total_returns); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">ROI</div>
                            <div class="metric-value"><?php echo number_format($performance['roi'] ?? 0, 1); ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-box">
                            <div class="metric-label">Rating</div>
                            <div class="metric-value">
                                <?php for ($i = 0; $i < floor($performance['rating'] ?? 5); $i++): ?>
                                    <i class="fas fa-star" style="color: #fbbf24;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Default Rate & Risk -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-exclamation-triangle"></i> Risk Metrics</h3>
                <div class="row">
                    <div class="col-md-6">
                        <div class="progress-bar-container">
                            <div class="progress-label">
                                <span>Default Rate</span>
                                <span><?php echo number_format($default_rate, 1); ?>%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-fill" style="width: <?php echo min($default_rate, 100); ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="metric-box">
                            <div class="metric-label">Withdrawal Balance</div>
                            <div class="metric-value"><?php echo formatCurrency(max($withdrawal_balance, 0)); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compliance Status -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-shield-alt"></i> Compliance Status</h3>
                <div class="metric-box">
                    <div class="metric-label">Regulatory Compliance</div>
                    <span class="status-badge compliance-<?php echo $compliance_status; ?>">
                        <?php echo ucfirst($compliance_status); ?>
                    </span>
                </div>
            </div>

            <!-- Active Investments -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-chart-pie"></i> Active Investments</h3>
                <?php if ($active_investments): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Borrower</th>
                                    <th>Loan Amount</th>
                                    <th>Interest Rate</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($active_investments as $investment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($investment['company_name']); ?></td>
                                        <td><?php echo formatCurrency($investment['loan_amount']); ?></td>
                                        <td><?php echo number_format($investment['interest_rate'], 2); ?>%</td>
                                        <td><?php echo date('M d, Y', strtotime($investment['due_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $investment['status']; ?>">
                                                <?php echo ucfirst($investment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No active investments.</p>
                <?php endif; ?>
            </div>

            <!-- Risk Assessment -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-chart-bar"></i> Risk Assessment</h3>
                <?php if ($risk_assessments): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Borrower</th>
                                    <th>Risk Score</th>
                                    <th>Risk Level</th>
                                    <th>Assessment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($risk_assessments as $risk): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($risk['company_name']); ?></td>
                                        <td><?php echo $risk['risk_score']; ?>/100</td>
                                        <td>
                                            <span class="status-badge risk-<?php echo $risk['risk_level']; ?>">
                                                <?php echo ucfirst($risk['risk_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($risk['assessment_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No risk assessments available.</p>
                <?php endif; ?>
            </div>

            <!-- Investment History -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-history"></i> Investment History</h3>
                <?php if ($investment_history): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Borrower</th>
                                    <th>Loan Amount</th>
                                    <th>Term (Days)</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($investment_history as $history): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($history['company_name']); ?></td>
                                        <td><?php echo formatCurrency($history['loan_amount']); ?></td>
                                        <td><?php echo $history['loan_term_days']; ?> days</td>
                                        <td>
                                            <span class="status-badge status-<?php echo $history['status']; ?>">
                                                <?php echo ucfirst($history['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($history['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No investment history.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
