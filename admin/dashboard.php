<?php
/**
 * JuaKali Lend Admin Dashboard
 * Comprehensive analytics and system management interface
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../integrations/credit-scoring.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$creditScoring = new CreditScoringEngine($db);

// Get dashboard statistics
$stats = getDashboardStatistics($db);
$fraudStats = getFraudStatistics($db);
$performanceMetrics = getPerformanceMetrics($db);
$recentActivity = getRecentActivity($db);

// Get alerts and notifications
$alerts = getSystemAlerts($db);
$notifications = getAdminNotifications($db);

function getDashboardStatistics($db) {
    $date_range = date('Y-m-d', strtotime('-30 days'));

    // Basic statistics
    $stats = $db->fetchOne("
        SELECT
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT l.id) as total_loans,
            COUNT(DISTINCT s.id) as total_suppliers,
            COUNT(DISTINCT a.id) as total_agents,
            SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_loans,
            SUM(CASE WHEN l.status = 'completed' THEN 1 ELSE 0 END) as completed_loans,
            SUM(CASE WHEN u.created_at >= ? THEN 1 ELSE 0 END) as new_users_this_month
        FROM users u
        LEFT JOIN loans l ON l.borrower_id = u.id
        LEFT JOIN suppliers s ON 1=1
        LEFT JOIN field_agents a ON 1=1
        WHERE u.created_at >= ? OR u.id IS NOT NULL
    ", [$date_range, $date_range]);

    // Financial statistics
    $financials = $db->fetchOne("
        SELECT
            COALESCE(SUM(l.loan_amount), 0) as total_loan_portfolio,
            COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END), 0) as active_portfolio,
            COALESCE(SUM(rs.amount_paid), 0) as total_repaid,
            COALESCE(SUM(CASE WHEN rs.due_date < CURDATE() AND rs.status = 'pending' THEN rs.amount_due ELSE 0 END), 0) as overdue_amount,
            COALESCE(AVG(l.loan_amount), 0) as avg_loan_size
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.created_at >= ?
    ", [$date_range]);

    // Performance metrics
    $performance = $db->fetchOne("
        SELECT
            ROUND(COUNT(CASE WHEN l.status = 'completed' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id), 0), 2) as completion_rate,
            ROUND(SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid END) * 100.0 / NULLIF(SUM(rs.amount_due), 0), 2) as repayment_rate,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN DATEDIFF(l.completed_at, l.created_at) END), 1) as avg_loan_duration_days
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.created_at >= ?
    ", [$date_range]);

    return array_merge($stats, $financials, $performance);
}

function getFraudStatistics($db) {
    return $db->fetchOne("
        SELECT
            COUNT(CASE WHEN fd.risk_level = 'HIGH' THEN 1 END) as high_risk_cases,
            COUNT(CASE WHEN fd.risk_level = 'MEDIUM' THEN 1 END) as medium_risk_cases,
            COUNT(CASE WHEN fd.risk_level = 'LOW' THEN 1 END) as low_risk_cases,
            COUNT(CASE WHEN fd.status = 'UNDER_REVIEW' THEN 1 END) as cases_under_review,
            COUNT(CASE WHEN fd.status = 'CONFIRMED_FRAUD' THEN 1 END) as confirmed_fraud_cases,
            COUNT(CASE WHEN fd.status = 'FALSE_POSITIVE' THEN 1 END) as false_positives,
            ROUND(AVG(CASE WHEN fd.risk_score IS NOT NULL THEN fd.risk_score END), 2) as avg_risk_score
        FROM fraud_detections fd
        WHERE fd.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
}

function getPerformanceMetrics($db) {
    // System performance metrics
    $systemMetrics = $db->fetchOne("
        SELECT
            COUNT(CASE WHEN response_time < 1000 THEN 1 END) as fast_responses,
            COUNT(CASE WHEN response_time >= 1000 AND response_time < 3000 THEN 1 END) as medium_responses,
            COUNT(CASE WHEN response_time >= 3000 THEN 1 END) as slow_responses,
            ROUND(AVG(response_time), 2) as avg_response_time
        FROM system_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND log_type = 'API_REQUEST'
    ");

    // User engagement metrics
    $engagementMetrics = $db->fetchOne("
        SELECT
            COUNT(DISTINCT user_id) as daily_active_users,
            COUNT(DISTINCT CASE WHEN DATE(created_at) = CURDATE() - INTERVAL 1 DAY THEN user_id END) as yesterday_active_users,
            COUNT(*) as total_sessions,
            ROUND(AVG(session_duration), 2) as avg_session_duration
        FROM user_analytics
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");

    return array_merge($systemMetrics, $engagementMetrics);
}

function getRecentActivity($db) {
    return $db->fetchAll("
        SELECT
            ua.activity_type,
            ua.description,
            ua.created_at,
            u.name as user_name,
            u.role as user_role
        FROM user_analytics ua
        LEFT JOIN users u ON ua.user_id = u.id
        ORDER BY ua.created_at DESC
        LIMIT 10
    ");
}

function getSystemAlerts($db) {
    return $db->fetchAll("
        SELECT
            id,
            alert_type,
            title,
            message,
            severity,
            is_read,
            created_at
        FROM admin_alerts
        WHERE is_read = 0
        ORDER BY severity DESC, created_at DESC
        LIMIT 5
    ");
}

function getAdminNotifications($db) {
    return $db->fetchAll("
        SELECT
            id,
            type,
            title,
            message,
            created_at,
            expires_at
        FROM notifications
        WHERE target_role = 'admin'
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
        LIMIT 3
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }
        .alert-card {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-online { background: #28a745; }
        .status-warning { background: #ffc107; }
        .status-offline { background: #dc3545; }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .quick-action-card {
            text-align: center;
            padding: 1.5rem;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .quick-action-card:hover {
            border-color: #007bff;
            background: #f8f9ff;
        }
        .system-health {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="admin-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                    <p class="mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="system-health">
                        <span class="status-indicator status-online"></span>
                        <span>System Status: Healthy</span>
                        <span class="ms-3">Last Updated: <?php echo date('H:i:s'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Quick Actions & Alerts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-3">
                        <div class="quick-action-card" onclick="window.location.href='users.php'">
                            <i class="fas fa-users fa-2x text-primary mb-3"></i>
                            <h6>Manage Users</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="quick-action-card" onclick="window.location.href='loans.php'">
                            <i class="fas fa-hand-holding-usd fa-2x text-success mb-3"></i>
                            <h6>Loan Management</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="quick-action-card" onclick="window.location.href='fraud-monitoring.php'">
                            <i class="fas fa-shield-alt fa-2x text-warning mb-3"></i>
                            <h6>Fraud Monitoring</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="quick-action-card" onclick="window.location.href='analytics.php'">
                            <i class="fas fa-chart-line fa-2x text-info mb-3"></i>
                            <h6>Analytics</h6>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <?php if (!empty($alerts)): ?>
                    <div class="card alert-card">
                        <div class="card-body">
                            <h6><i class="fas fa-exclamation-triangle"></i> System Alerts</h6>
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert alert-<?php echo $alert['severity'] === 'high' ? 'danger' : 'warning'; ?> alert-sm mb-2">
                                    <strong><?php echo htmlspecialchars($alert['title']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($alert['message']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card metric-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="metric-value"><?php echo number_format($stats['total_users']); ?></div>
                            <div class="metric-label">Total Users</div>
                            <small><i class="fas fa-arrow-up"></i> +<?php echo $stats['new_users_this_month']; ?> this month</small>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card metric-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="metric-value">KES <?php echo number_format($stats['total_loan_portfolio'], 0); ?></div>
                            <div class="metric-label">Loan Portfolio</div>
                            <small>KES <?php echo number_format($stats['active_portfolio'], 0); ?> active</small>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card metric-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="metric-value"><?php echo $stats['completion_rate']; ?>%</div>
                            <div class="metric-label">Completion Rate</div>
                            <small><?php echo $stats['repayment_rate']; ?>% repayment</small>
                        </div>
                        <i class="fas fa-percentage fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card metric-card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="metric-value"><?php echo $fraudStats['high_risk_cases']; ?></div>
                            <div class="metric-label">High Risk Cases</div>
                            <small><?php echo $fraudStats['cases_under_review']; ?> under review</small>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-area"></i> Portfolio Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="portfolioChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Risk Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="riskChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Performance & Recent Activity -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-tachometer-alt"></i> System Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-center">
                                    <div class="h4 text-success"><?php echo $performanceMetrics['fast_responses']; ?></div>
                                    <small>Fast Responses (<1s)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center">
                                    <div class="h4 text-info"><?php echo $performanceMetrics['avg_response_time']; ?>ms</div>
                                    <small>Avg Response Time</small>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($activity['description']); ?></strong>
                                    <small><?php echo date('H:i', strtotime($activity['created_at'])); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($activity['user_name']); ?> (<?php echo ucfirst($activity['user_role']); ?>)
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Portfolio Chart
        const portfolioCtx = document.getElementById('portfolioChart').getContext('2d');
        const portfolioChart = new Chart(portfolioCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(getLast30Days()); ?>,
                datasets: [{
                    label: 'New Loans',
                    data: <?php echo json_encode(getLoanTrendData($db)); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.1)',
                    tension: 0.4
                }, {
                    label: 'Repayments',
                    data: <?php echo json_encode(getRepaymentTrendData($db)); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Risk Distribution Chart
        const riskCtx = document.getElementById('riskChart').getContext('2d');
        const riskChart = new Chart(riskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Low Risk', 'Medium Risk', 'High Risk'],
                datasets: [{
                    data: [<?php echo $fraudStats['low_risk_cases']; ?>, <?php echo $fraudStats['medium_risk_cases']; ?>, <?php echo $fraudStats['high_risk_cases']; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceChart = new Chart(performanceCtx, {
            type: 'bar',
            data: {
                labels: ['Fast (<1s)', 'Medium (1-3s)', 'Slow (>3s)'],
                datasets: [{
                    label: 'API Responses',
                    data: [<?php echo $performanceMetrics['fast_responses']; ?>, <?php echo $performanceMetrics['medium_responses']; ?>, <?php echo $performanceMetrics['slow_responses']; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);

        function getLast30Days() {
            const days = [];
            for (let i = 29; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                days.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            return days;
        }

        <?php
        function getLast30Days() {
            $days = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = date('M j', strtotime("-$i days"));
                $days[] = $date;
            }
            return $days;
        }

        function getLoanTrendData($db) {
            $data = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $count = $db->fetchColumn("SELECT COUNT(*) FROM loans WHERE DATE(created_at) = ?", [$date]);
                $data[] = $count;
            }
            return $data;
        }

        function getRepaymentTrendData($db) {
            $data = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $count = $db->fetchColumn("SELECT COUNT(*) FROM repayment_schedule WHERE DATE(paid_at) = ? AND status = 'completed'", [$date]);
                $data[] = $count;
            }
            return $data;
        }
        ?>
    </script>
</body>
</html>