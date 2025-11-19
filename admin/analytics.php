<?php
/**
 * Analytics Dashboard
 * Comprehensive business intelligence and reporting interface
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

// Get date range from request
$dateRange = $_GET['date_range'] ?? '30';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime("-$dateRange days"));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get analytics data
$overviewMetrics = getOverviewMetrics($db, $startDate, $endDate);
$loanAnalytics = getLoanAnalytics($db, $startDate, $endDate);
$userAnalytics = getUserAnalytics($db, $startDate, $endDate);
$financialMetrics = getFinancialMetrics($db, $startDate, $endDate);
$riskAnalytics = getRiskAnalytics($db, $startDate, $endDate);
$performanceMetrics = getPerformanceMetrics($db, $startDate, $endDate);
$regionalData = getRegionalAnalytics($db, $startDate, $endDate);

function getOverviewMetrics($db, $startDate, $endDate) {
    $metrics = $db->fetchOne("
        SELECT
            COUNT(DISTINCT u.id) as total_active_users,
            COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id END) as weekly_active_users,
            COUNT(DISTINCT l.id) as total_loans,
            SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END) as active_portfolio_value,
            COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.id END) as completed_loans,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN l.loan_amount END), 2) as avg_loan_size,
            COUNT(DISTINCT s.id) as total_suppliers,
            COUNT(DISTINCT a.id) as active_agents
        FROM users u
        LEFT JOIN loans l ON l.borrower_id = u.id AND l.created_at BETWEEN ? AND ?
        LEFT JOIN suppliers s ON s.created_at BETWEEN ? AND ?
        LEFT JOIN field_agents a ON a.status = 'active'
        WHERE u.created_at <= ?
    ", [$startDate, $endDate, $startDate, $endDate, $endDate]);

    // Calculate growth rates
    $previousStartDate = date('Y-m-d', strtotime($startDate . ' - ' . ceil((strtotime($endDate) - strtotime($startDate)) / 86400) . ' days'));
    $previousEndDate = $startDate;

    $previousMetrics = $db->fetchOne("
        SELECT
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT l.id) as total_loans,
            SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END) as active_portfolio_value
        FROM users u
        LEFT JOIN loans l ON l.borrower_id = u.id AND l.created_at BETWEEN ? AND ?
        WHERE u.created_at <= ?
    ", [$previousStartDate, $previousEndDate, $previousEndDate]);

    $metrics['user_growth_rate'] = $previousMetrics['total_users'] > 0
        ? round((($metrics['total_active_users'] - $previousMetrics['total_users']) / $previousMetrics['total_users']) * 100, 2)
        : 0;

    $metrics['loan_growth_rate'] = $previousMetrics['total_loans'] > 0
        ? round((($metrics['total_loans'] - $previousMetrics['total_loans']) / $previousMetrics['total_loans']) * 100, 2)
        : 0;

    $metrics['portfolio_growth_rate'] = $previousMetrics['active_portfolio_value'] > 0
        ? round((($metrics['active_portfolio_value'] - $previousMetrics['active_portfolio_value']) / $previousMetrics['active_portfolio_value']) * 100, 2)
        : 0;

    return $metrics;
}

function getLoanAnalytics($db, $startDate, $endDate) {
    // Daily loan trends
    $dailyTrends = $db->fetchAll("
        SELECT
            DATE(l.created_at) as date,
            COUNT(*) as loans_count,
            SUM(l.loan_amount) as total_amount,
            AVG(l.loan_amount) as avg_amount,
            COUNT(DISTINCT l.borrower_id) as unique_borrowers
        FROM loans l
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY DATE(l.created_at)
        ORDER BY date ASC
    ", [$startDate, $endDate]);

    // Loan performance by status
    $performanceByStatus = $db->fetchAll("
        SELECT
            l.status,
            COUNT(*) as count,
            SUM(l.loan_amount) as total_amount,
            AVG(l.loan_amount) as avg_amount,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM loans WHERE created_at BETWEEN ? AND ?), 2) as percentage
        FROM loans l
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY l.status
        ORDER BY count DESC
    ", [$startDate, $endDate, $startDate, $endDate]);

    // Loan distribution by amount ranges
    $amountDistribution = $db->fetchAll("
        SELECT
            CASE
                WHEN l.loan_amount <= 5000 THEN '0-5K'
                WHEN l.loan_amount <= 10000 THEN '5K-10K'
                WHEN l.loan_amount <= 25000 THEN '10K-25K'
                WHEN l.loan_amount <= 50000 THEN '25K-50K'
                ELSE '50K+'
            END as amount_range,
            COUNT(*) as count,
            SUM(l.loan_amount) as total_amount,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM loans WHERE created_at BETWEEN ? AND ?), 2) as percentage
        FROM loans l
        WHERE l.created_at BETWEEN ? AND ?
        GROUP BY amount_range
        ORDER BY MIN(l.loan_amount)
    ", [$startDate, $endDate, $startDate, $endDate]);

    return [
        'daily_trends' => $dailyTrends,
        'performance_by_status' => $performanceByStatus,
        'amount_distribution' => $amountDistribution
    ];
}

function getUserAnalytics($db, $startDate, $endDate) {
    // User registration trends
    $registrationTrends = $db->fetchAll("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as new_users,
            SUM(CASE WHEN role = 'retailer' THEN 1 ELSE 0 END) as new_retailers,
            SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) as new_suppliers,
            SUM(CASE WHEN role = 'field_agent' THEN 1 ELSE 0 END) as new_agents
        FROM users
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ", [$startDate, $endDate]);

    // User engagement metrics
    $engagementMetrics = $db->fetchOne("
        SELECT
            COUNT(DISTINCT u.id) as total_users,
            COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN u.id END) as daily_active,
            COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id END) as weekly_active,
            COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) as monthly_active,
            ROUND(AVG(DATEDIFF(NOW(), u.last_login)), 1) as avg_days_since_login
        FROM users u
        WHERE u.created_at <= ?
    ", [$endDate]);

    // User activity distribution
    $activityDistribution = $db->fetchAll("
        SELECT
            u.role,
            COUNT(*) as user_count,
            COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
            ROUND(COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) * 100.0 / COUNT(*), 2) as activity_rate
        FROM users u
        WHERE u.created_at <= ?
        GROUP BY u.role
        ORDER BY user_count DESC
    ", [$endDate]);

    return [
        'registration_trends' => $registrationTrends,
        'engagement_metrics' => $engagementMetrics,
        'activity_distribution' => $activityDistribution
    ];
}

function getFinancialMetrics($db, $startDate, $endDate) {
    // Revenue and repayment metrics
    $revenueMetrics = $db->fetchOne("
        SELECT
            SUM(l.loan_amount) as total_disbursed,
            SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END) as total_repaid,
            SUM(CASE WHEN rs.status = 'completed' THEN (rs.amount_paid - rs.amount_due) ELSE 0 END) as total_interest,
            SUM(CASE WHEN rs.status = 'pending' AND rs.due_date < CURDATE() THEN rs.amount_due ELSE 0 END) as overdue_amount,
            COUNT(CASE WHEN rs.status = 'pending' AND rs.due_date < CURDATE() THEN 1 END) as overdue_loans,
            ROUND(AVG(l.interest_rate), 2) as avg_interest_rate,
            ROUND(SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid END) * 100.0 / NULLIF(SUM(l.loan_amount), 0), 2) as repayment_rate
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.created_at BETWEEN ? AND ?
    ", [$startDate, $endDate]);

    // Cash flow analysis
    $cashFlowData = $db->fetchAll("
        SELECT
            DATE(trans.created_at) as date,
            SUM(CASE WHEN trans.transaction_type = 'disbursement' THEN trans.amount ELSE 0 END) as cash_out,
            SUM(CASE WHEN trans.transaction_type = 'repayment' THEN trans.amount ELSE 0 END) as cash_in,
            SUM(CASE WHEN trans.transaction_type = 'repayment' THEN trans.amount ELSE 0 END) -
            SUM(CASE WHEN trans.transaction_type = 'disbursement' THEN trans.amount ELSE 0 END) as net_cash_flow
        FROM financial_transactions trans
        WHERE trans.created_at BETWEEN ? AND ?
        GROUP BY DATE(trans.created_at)
        ORDER BY date ASC
    ", [$startDate, $endDate]);

    // Profitability analysis
    $profitabilityMetrics = $db->fetchOne("
        SELECT
            SUM(l.loan_amount) as total_portfolio,
            SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END) as total_revenue,
            SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END) - SUM(l.loan_amount) as gross_profit,
            ROUND((SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END) - SUM(l.loan_amount)) * 100.0 / NULLIF(SUM(l.loan_amount), 0), 2) as profit_margin,
            COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) as defaulted_loans,
            ROUND(COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as default_rate
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.created_at BETWEEN ? AND ?
    ", [$startDate, $endDate]);

    return [
        'revenue_metrics' => $revenueMetrics,
        'cash_flow_data' => $cashFlowData,
        'profitability_metrics' => $profitabilityMetrics
    ];
}

function getRiskAnalytics($db, $startDate, $endDate) {
    // Risk distribution
    $riskDistribution = $db->fetchAll("
        SELECT
            cse.risk_category,
            COUNT(*) as user_count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM credit_score_evaluations WHERE created_at BETWEEN ? AND ?), 2) as percentage,
            AVG(cse.credit_score) as avg_credit_score
        FROM credit_score_evaluations cse
        WHERE cse.created_at BETWEEN ? AND ?
        GROUP BY cse.risk_category
        ORDER BY user_count DESC
    ", [$startDate, $endDate, $startDate, $endDate]);

    // Fraud detection trends
    $fraudTrends = $db->fetchAll("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as total_cases,
            COUNT(CASE WHEN risk_level = 'HIGH' THEN 1 END) as high_risk_cases,
            COUNT(CASE WHEN risk_level = 'MEDIUM' THEN 1 END) as medium_risk_cases,
            COUNT(CASE WHEN risk_level = 'LOW' THEN 1 END) as low_risk_cases,
            AVG(risk_score) as avg_risk_score
        FROM fraud_detections
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ", [$startDate, $endDate]);

    // Default analysis
    $defaultAnalysis = $db->fetchOne("
        SELECT
            COUNT(*) as total_loans,
            COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) as defaulted_loans,
            COUNT(CASE WHEN l.status = 'completed' THEN 1 END) as completed_loans,
            ROUND(COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as default_rate,
            AVG(CASE WHEN l.status = 'defaulted' THEN DATEDIFF(l.defaulted_at, l.created_at) END) as avg_days_to_default,
            SUM(CASE WHEN l.status = 'defaulted' THEN l.loan_amount ELSE 0 END) as total_defaulted_amount
        FROM loans l
        WHERE l.created_at BETWEEN ? AND ?
    ", [$startDate, $endDate]);

    return [
        'risk_distribution' => $riskDistribution,
        'fraud_trends' => $fraudTrends,
        'default_analysis' => $defaultAnalysis
    ];
}

function getPerformanceMetrics($db, $startDate, $endDate) {
    return $db->fetchOne("
        SELECT
            COUNT(CASE WHEN response_time < 1000 THEN 1 END) as fast_responses,
            COUNT(CASE WHEN response_time >= 1000 AND response_time < 3000 THEN 1 END) as medium_responses,
            COUNT(CASE WHEN response_time >= 3000 THEN 1 END) as slow_responses,
            ROUND(AVG(response_time), 2) as avg_response_time,
            ROUND(MAX(response_time), 2) as max_response_time,
            COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_count,
            ROUND(COUNT(CASE WHEN status_code >= 400 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as error_rate
        FROM system_logs
        WHERE created_at BETWEEN ? AND ?
        AND log_type = 'API_REQUEST'
    ", [$startDate, $endDate]);
}

function getRegionalAnalytics($db, $startDate, $endDate) {
    return $db->fetchAll("
        SELECT
            u.region,
            u.city,
            COUNT(*) as user_count,
            COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users,
            COUNT(CASE WHEN l.id IS NOT NULL THEN 1 END) as borrowers,
            SUM(CASE WHEN l.id IS NOT NULL THEN l.loan_amount ELSE 0 END) as total_loan_amount,
            AVG(CASE WHEN l.id IS NOT NULL THEN l.loan_amount END) as avg_loan_amount
        FROM users u
        LEFT JOIN loans l ON l.borrower_id = u.id AND l.created_at BETWEEN ? AND ?
        WHERE u.created_at <= ?
        AND (u.region IS NOT NULL OR u.city IS NOT NULL)
        GROUP BY u.region, u.city
        HAVING user_count >= 5
        ORDER BY user_count DESC
        LIMIT 20
    ", [$startDate, $endDate, $endDate]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - JuaKali Lend Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom"></script>
    <style>
        .analytics-dashboard {
            background: #f8f9fa;
        }
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            border-left: 4px solid transparent;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .metric-card.primary { border-left-color: #007bff; }
        .metric-card.success { border-left-color: #28a745; }
        .metric-card.warning { border-left-color: #ffc107; }
        .metric-card.danger { border-left-color: #dc3545; }
        .metric-card.info { border-left-color: #17a2b8; }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .growth-indicator {
            font-size: 0.9rem;
            font-weight: 500;
        }
        .growth-positive { color: #28a745; }
        .growth-negative { color: #dc3545; }
        .growth-neutral { color: #6c757d; }

        .date-range-selector {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .export-buttons {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1000;
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .regional-heatmap {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .regional-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .kpi-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
        }
    </style>
</head>
<body class="analytics-dashboard">
    <!-- Header -->
    <div class="kpi-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
                    <p class="mb-0">Comprehensive business intelligence and insights</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="date-range-selector d-inline-block">
                        <form method="GET" class="d-flex align-items-center gap-3">
                            <div>
                                <label class="form-label mb-0">Quick Range:</label>
                                <select name="date_range" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="7" <?php echo $dateRange == '7' ? 'selected' : ''; ?>>7 Days</option>
                                    <option value="30" <?php echo $dateRange == '30' ? 'selected' : ''; ?>>30 Days</option>
                                    <option value="90" <?php echo $dateRange == '90' ? 'selected' : ''; ?>>90 Days</option>
                                    <option value="365" <?php echo $dateRange == '365' ? 'selected' : ''; ?>>1 Year</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div id="customDateRange" style="display: <?php echo $dateRange === 'custom' ? 'flex' : 'none'; ?>;">
                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $startDate; ?>">
                                <span>to</span>
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $endDate; ?>">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Buttons -->
    <div class="export-buttons">
        <h6 class="mb-3">Export Reports</h6>
        <div class="d-grid gap-2">
            <button class="btn btn-sm btn-outline-primary" onclick="exportReport('overview')">
                <i class="fas fa-file-pdf"></i> Overview PDF
            </button>
            <button class="btn btn-sm btn-outline-success" onclick="exportReport('financial')">
                <i class="fas fa-file-excel"></i> Financial Excel
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="exportReport('detailed')">
                <i class="fas fa-file-csv"></i> Detailed CSV
            </button>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Key Performance Indicators -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="metric-card primary">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo number_format($overviewMetrics['total_active_users']); ?></div>
                            <div class="text-muted small">Active Users</div>
                            <div class="growth-indicator growth-positive">
                                <i class="fas fa-arrow-up"></i> <?php echo $overviewMetrics['user_growth_rate']; ?>%
                            </div>
                        </div>
                        <i class="fas fa-users fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1">KES <?php echo number_format($overviewMetrics['active_portfolio_value'], 0); ?></div>
                            <div class="text-muted small">Portfolio Value</div>
                            <div class="growth-indicator growth-positive">
                                <i class="fas fa-arrow-up"></i> <?php echo $overviewMetrics['portfolio_growth_rate']; ?>%
                            </div>
                        </div>
                        <i class="fas fa-chart-line fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card warning">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo $overviewMetrics['total_loans']; ?></div>
                            <div class="text-muted small">Total Loans</div>
                            <div class="growth-indicator growth-positive">
                                <i class="fas fa-arrow-up"></i> <?php echo $overviewMetrics['loan_growth_rate']; ?>%
                            </div>
                        </div>
                        <i class="fas fa-hand-holding-usd fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo $overviewMetrics['completed_loans']; ?></div>
                            <div class="text-muted small">Completed</div>
                            <div class="growth-indicator growth-neutral">
                                <i class="fas fa-minus"></i> Stable
                            </div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card danger">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1">KES <?php echo number_format($overviewMetrics['avg_loan_size'], 0); ?></div>
                            <div class="text-muted small">Avg Loan Size</div>
                            <div class="growth-indicator growth-neutral">
                                <i class="fas fa-equals"></i> Neutral
                            </div>
                        </div>
                        <i class="fas fa-calculator fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card success">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h3 mb-1"><?php echo $overviewMetrics['weekly_active_users']; ?></div>
                            <div class="text-muted small">Weekly Active</div>
                            <div class="growth-indicator growth-positive">
                                <i class="fas fa-arrow-up"></i> Growing
                            </div>
                        </div>
                        <i class="fas fa-user-check fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-area"></i> Loan Disbursement Trends</div>
                    <div style="height: 350px;">
                        <canvas id="loanTrendsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-pie"></i> Loan Status Distribution</div>
                    <div style="height: 350px;">
                        <canvas id="loanStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-line"></i> User Registration Trends</div>
                    <div style="height: 300px;">
                        <canvas id="userRegistrationChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-bar"></i> Financial Performance</div>
                    <div style="height: 300px;">
                        <canvas id="financialPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Analytics Section -->
        <div class="row">
            <div class="col-md-8">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-shield-alt"></i> Risk Analysis Overview</div>
                    <div style="height: 400px;">
                        <canvas id="riskAnalysisChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-chart-pie"></i> Risk Distribution</div>
                    <div style="height: 400px;">
                        <canvas id="riskDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regional Analytics -->
        <div class="row">
            <div class="col-12">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-map-marked-alt"></i> Regional Performance</div>
                    <div class="regional-heatmap">
                        <?php foreach ($regionalData as $region): ?>
                            <div class="regional-card">
                                <h6><?php echo htmlspecialchars($region['city'] . ', ' . $region['region']); ?></h6>
                                <div class="h4 text-primary"><?php echo $region['user_count']; ?></div>
                                <div class="small text-muted">Users</div>
                                <div class="mt-2">
                                    <small>Active: <?php echo $region['active_users']; ?></small><br>
                                    <small>Borrowers: <?php echo $region['borrowers']; ?></small><br>
                                    <small>Total: KES <?php echo number_format($region['total_loan_amount'], 0); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-tachometer-alt"></i> System Performance</div>
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="h3 text-success"><?php echo $performanceMetrics['fast_responses']; ?></div>
                            <div class="small">Fast Responses (<1s)</div>
                        </div>
                        <div class="col-md-4">
                            <div class="h3 text-info"><?php echo $performanceMetrics['avg_response_time']; ?>ms</div>
                            <div class="small">Avg Response</div>
                        </div>
                        <div class="col-md-4">
                            <div class="h3 text-danger"><?php echo $performanceMetrics['error_rate']; ?>%</div>
                            <div class="small">Error Rate</div>
                        </div>
                    </div>
                    <hr>
                    <div style="height: 200px;">
                        <canvas id="systemPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container">
                    <div class="chart-title"><i class="fas fa-coins"></i> Revenue Analysis</div>
                    <div class="row text-center mb-3">
                        <div class="col-md-6">
                            <div class="h4 text-success">KES <?php echo number_format($financialMetrics['revenue_metrics']['total_repaid'], 0); ?></div>
                            <div class="small">Total Repaid</div>
                        </div>
                        <div class="col-md-6">
                            <div class="h4 text-warning">KES <?php echo number_format($financialMetrics['revenue_metrics']['overdue_amount'], 0); ?></div>
                            <div class="small">Overdue Amount</div>
                        </div>
                    </div>
                    <div style="height: 220px;">
                        <canvas id="revenueAnalysisChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare chart data
        const loanTrendsData = <?php echo json_encode($loanAnalytics['daily_trends']); ?>;
        const userRegistrationData = <?php echo json_encode($userAnalytics['registration_trends']); ?>;
        const cashFlowData = <?php echo json_encode($financialMetrics['cash_flow_data']); ?>;
        const fraudTrendsData = <?php echo json_encode($riskAnalytics['fraud_trends']); ?>;

        // Loan Trends Chart
        const loanTrendsCtx = document.getElementById('loanTrendsChart').getContext('2d');
        new Chart(loanTrendsCtx, {
            type: 'line',
            data: {
                labels: loanTrendsData.map(d => d.date),
                datasets: [{
                    label: 'Daily Loans',
                    data: loanTrendsData.map(d => d.loans_count),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Total Amount',
                    data: loanTrendsData.map(d => d.total_amount),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    zoom: {
                        zoom: {
                            wheel: { enabled: true },
                            pinch: { enabled: true },
                            mode: 'x'
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left'
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // Loan Status Chart
        const loanStatusCtx = document.getElementById('loanStatusChart').getContext('2d');
        new Chart(loanStatusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($loanAnalytics['performance_by_status'], 'status')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($loanAnalytics['performance_by_status'], 'count')); ?>,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // User Registration Chart
        const userRegistrationCtx = document.getElementById('userRegistrationChart').getContext('2d');
        new Chart(userRegistrationCtx, {
            type: 'line',
            data: {
                labels: userRegistrationData.map(d => d.date),
                datasets: [{
                    label: 'New Users',
                    data: userRegistrationData.map(d => d.new_users),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.1)',
                    tension: 0.4
                }, {
                    label: 'New Retailers',
                    data: userRegistrationData.map(d => d.new_retailers),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Financial Performance Chart
        const financialPerformanceCtx = document.getElementById('financialPerformanceChart').getContext('2d');
        new Chart(financialPerformanceCtx, {
            type: 'bar',
            data: {
                labels: cashFlowData.map(d => d.date),
                datasets: [{
                    label: 'Cash In',
                    data: cashFlowData.map(d => d.cash_in),
                    backgroundColor: '#28a745'
                }, {
                    label: 'Cash Out',
                    data: cashFlowData.map(d => d.cash_out),
                    backgroundColor: '#dc3545'
                }, {
                    label: 'Net Cash Flow',
                    data: cashFlowData.map(d => d.net_cash_flow),
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true }
                }
            }
        });

        // Risk Analysis Chart
        const riskAnalysisCtx = document.getElementById('riskAnalysisChart').getContext('2d');
        new Chart(riskAnalysisCtx, {
            type: 'line',
            data: {
                labels: fraudTrendsData.map(d => d.date),
                datasets: [{
                    label: 'High Risk Cases',
                    data: fraudTrendsData.map(d => d.high_risk_cases),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.1)',
                    tension: 0.4
                }, {
                    label: 'Medium Risk Cases',
                    data: fraudTrendsData.map(d => d.medium_risk_cases),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255,193,7,0.1)',
                    tension: 0.4
                }, {
                    label: 'Average Risk Score',
                    data: fraudTrendsData.map(d => d.avg_risk_score),
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23,162,184,0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left'
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        // Risk Distribution Chart
        const riskDistributionCtx = document.getElementById('riskDistributionChart').getContext('2d');
        new Chart(riskDistributionCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($riskAnalytics['risk_distribution'], 'risk_category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($riskAnalytics['risk_distribution'], 'user_count')); ?>,
                    backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // System Performance Chart
        const systemPerformanceCtx = document.getElementById('systemPerformanceChart').getContext('2d');
        new Chart(systemPerformanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Fast', 'Medium', 'Slow'],
                datasets: [{
                    data: [<?php echo $performanceMetrics['fast_responses']; ?>, <?php echo $performanceMetrics['medium_responses']; ?>, <?php echo $performanceMetrics['slow_responses']; ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Revenue Analysis Chart
        const revenueAnalysisCtx = document.getElementById('revenueAnalysisChart').getContext('2d');
        new Chart(revenueAnalysisCtx, {
            type: 'line',
            data: {
                labels: cashFlowData.slice(-7).map(d => d.date),
                datasets: [{
                    label: 'Net Cash Flow',
                    data: cashFlowData.slice(-7).map(d => d.net_cash_flow),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40,167,69,0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Show/hide custom date range
        document.querySelector('select[name="date_range"]').addEventListener('change', function() {
            document.getElementById('customDateRange').style.display =
                this.value === 'custom' ? 'flex' : 'none';
        });

        function exportReport(type) {
            const startDate = '<?php echo $startDate; ?>';
            const endDate = '<?php echo $endDate; ?>';
            window.open(`../api/admin/export-report.php?type=${type}&start_date=${startDate}&end_date=${endDate}`, '_blank');
        }

        // Auto-refresh data every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>