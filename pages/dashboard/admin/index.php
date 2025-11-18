<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('admin');

$db = new Database();
$db->connect();

// Get total users
$db->query('SELECT COUNT(*) as count FROM users');
$total_users = $db->single()['count'];

// Get users by role
$db->query('SELECT role, COUNT(*) as count FROM users GROUP BY role');
$users_by_role = $db->resultSet();

// Get total orders
$db->query('SELECT COUNT(*) as count FROM orders');
$total_orders = $db->single()['count'];

// Get total loans
$db->query('SELECT COUNT(*) as count FROM loans');
$total_loans = $db->single()['count'];

// Get total disbursed
$db->query('SELECT SUM(loan_amount) as total FROM loans WHERE status IN ("disbursed", "repaid")');
$total_disbursed = $db->single()['total'] ?? 0;

$db->query('SELECT kd.*, u.company_name, u.first_name, u.last_name FROM kyc_documents kd 
           JOIN users u ON kd.user_id = u.id 
           WHERE kd.status = "pending" 
           ORDER BY kd.created_at DESC LIMIT 5');
$kyc_pending = $db->resultSet();

$db->query('SELECT d.*, u1.company_name as initiator_name, u2.company_name as respondent_name FROM disputes d 
           JOIN users u1 ON d.initiator_id = u1.id 
           JOIN users u2 ON d.respondent_id = u2.id 
           WHERE d.status IN ("open", "in_review") 
           ORDER BY d.created_at DESC LIMIT 5');
$pending_disputes = $db->resultSet();

$db->query('SELECT l.*, u.company_name, u.first_name, u.last_name FROM loans l 
           JOIN users u ON l.retailer_id = u.id 
           WHERE l.status = "disbursed" 
           ORDER BY l.created_at DESC LIMIT 5');
$active_loans = $db->resultSet();

$db->query('SELECT fd.*, u.company_name FROM fraud_detection fd 
           JOIN users u ON fd.user_id = u.id 
           WHERE fd.status = "pending" 
           ORDER BY fd.created_at DESC LIMIT 5');
$fraud_alerts = $db->resultSet();

$db->query('SELECT COUNT(*) as count FROM orders WHERE status = "pending"');
$pending_orders = $db->single()['count'];

$db->query('SELECT COUNT(*) as count FROM loans WHERE status = "defaulted"');
$defaulted_loans = $db->single()['count'];

$db->query('SELECT COUNT(*) as count FROM users WHERE kyc_verified = FALSE');
$unverified_users = $db->single()['count'];

$db->query('SELECT COUNT(*) as count FROM disputes WHERE status IN ("open", "in_review")');
$open_disputes = $db->single()['count'];

$db->query('SELECT al.*, u.company_name FROM audit_logs al 
           LEFT JOIN users u ON al.user_id = u.id 
           ORDER BY al.created_at DESC LIMIT 10');
$audit_logs = $db->resultSet();

$db->query('SELECT COUNT(*) as count FROM users WHERE status = "active"');
$active_users = $db->single()['count'];

$db->query('SELECT COUNT(*) as count FROM payments WHERE status = "failed"');
$failed_payments = $db->single()['count'];

$db->query('SELECT SUM(total_amount) as total FROM orders WHERE status = "delivered"');
$total_revenue = $db->single()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JuaKali Lend</title>
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
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-open {
            background: #fef3c7;
            color: #92400e;
        }
        .status-in_review {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-resolved {
            background: #d1fae5;
            color: #065f46;
        }
        .severity-low {
            background: #d1fae5;
            color: #065f46;
        }
        .severity-medium {
            background: #fef3c7;
            color: #92400e;
        }
        .severity-high {
            background: #fed7aa;
            color: #92400e;
        }
        .severity-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        .alert-item {
            padding: 1rem;
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
            margin-bottom: 0.5rem;
            border-radius: 4px;
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
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="orders.php"><i class="fas fa-box"></i> Orders</a></li>
                <li><a href="loans.php"><i class="fas fa-handshake"></i> Loans</a></li>
                <li><a href="kyc.php"><i class="fas fa-id-card"></i> KYC</a></li>
                <li><a href="disputes.php"><i class="fas fa-gavel"></i> Disputes</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <div class="user-menu">
                    <span>Administrator</span>
                    <div class="user-avatar"><i class="fas fa-shield-alt"></i></div>
                </div>
            </div>

            <!-- System Overview Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-handshake"></i></div>
                    <div class="stat-label">Total Loans</div>
                    <div class="stat-value"><?php echo $total_loans; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">Total Disbursed</div>
                    <div class="stat-value"><?php echo formatCurrency($total_disbursed); ?></div>
                </div>
            </div>

            <!-- System Health Metrics -->
            <h3 class="section-title">System Health</h3>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-label">Active Users</div>
                        <div class="stat-value"><?php echo $active_users; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="stat-label">Pending Orders</div>
                        <div class="stat-value"><?php echo $pending_orders; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-label">Failed Payments</div>
                        <div class="stat-value"><?php echo $failed_payments; ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-value"><?php echo formatCurrency($total_revenue); ?></div>
                    </div>
                </div>
            </div>

            <!-- Users by Role -->
            <h3 class="section-title">User Management - Users by Role</h3>
            <div class="row mb-4">
                <?php if ($users_by_role): ?>
                    <?php foreach ($users_by_role as $role_data): ?>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon"><i class="fas fa-user-circle"></i></div>
                                <div class="stat-label"><?php echo ucfirst($role_data['role']); ?></div>
                                <div class="stat-value"><?php echo $role_data['count']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <h3 class="section-title">Quick Actions</h3>
            <div class="quick-actions">
                <a href="users.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <strong>Manage Users</strong>
                </a>
                <a href="orders.php" class="action-btn">
                    <i class="fas fa-box"></i>
                    <strong>View Orders</strong>
                </a>
                <a href="loans.php" class="action-btn">
                    <i class="fas fa-handshake"></i>
                    <strong>View Loans</strong>
                </a>
                <a href="reports.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <strong>Reports</strong>
                </a>
            </div>

            <!-- KYC Approvals -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-id-card"></i> KYC Approvals Queue</h3>
                <?php if ($kyc_pending): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Document Type</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kyc_pending as $kyc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kyc['company_name'] ?? $kyc['first_name']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $kyc['document_type'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $kyc['status']; ?>">
                                                <?php echo ucfirst($kyc['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($kyc['created_at'])); ?></td>
                                        <td>
                                            <a href="kyc.php" style="color: #10b981; text-decoration: none;">
                                                <i class="fas fa-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No pending KYC approvals.</p>
                <?php endif; ?>
            </div>

            <!-- Loan Monitoring -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-chart-line"></i> Loan Monitoring</h3>
                <?php if ($active_loans): ?>
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
                                <?php foreach ($active_loans as $loan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($loan['company_name']); ?></td>
                                        <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                        <td><?php echo number_format($loan['interest_rate'], 2); ?>%</td>
                                        <td><?php echo date('M d, Y', strtotime($loan['due_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $loan['status']; ?>">
                                                <?php echo ucfirst($loan['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No active loans.</p>
                <?php endif; ?>
            </div>

            <!-- Dispute Resolution -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-gavel"></i> Dispute Resolution</h3>
                <?php if ($pending_disputes): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Initiator</th>
                                    <th>Respondent</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_disputes as $dispute): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dispute['initiator_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dispute['respondent_name']); ?></td>
                                        <td><?php echo htmlspecialchars($dispute['subject']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $dispute['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $dispute['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($dispute['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No pending disputes.</p>
                <?php endif; ?>
            </div>

            <!-- Fraud Detection Alerts -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-shield-alt"></i> Fraud Detection Alerts</h3>
                <?php if ($fraud_alerts): ?>
                    <?php foreach ($fraud_alerts as $alert): ?>
                        <div class="alert-item">
                            <strong><?php echo htmlspecialchars($alert['company_name']); ?></strong>
                            <p style="margin: 0.5rem 0 0; color: #4b5563;">
                                <?php echo htmlspecialchars($alert['description']); ?>
                            </p>
                            <span class="status-badge severity-<?php echo $alert['severity']; ?>">
                                <?php echo ucfirst($alert['severity']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No fraud alerts.</p>
                <?php endif; ?>
            </div>

            <!-- Audit Logs -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-history"></i> Recent Activity Logs</h3>
                <?php if ($audit_logs): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['company_name'] ?? 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No activity logs.</p>
                <?php endif; ?>
            </div>

            <!-- System Settings Quick Access -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-cog"></i> System Settings</h3>
                <div class="row">
                    <div class="col-md-4">
                        <a href="settings.php" class="action-btn" style="display: block; text-decoration: none;">
                            <i class="fas fa-sliders-h"></i>
                            <strong>Configuration</strong>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="settings.php" class="action-btn" style="display: block; text-decoration: none;">
                            <i class="fas fa-database"></i>
                            <strong>Backup & Recovery</strong>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="settings.php" class="action-btn" style="display: block; text-decoration: none;">
                            <i class="fas fa-bell"></i>
                            <strong>Notifications</strong>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
