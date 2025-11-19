<?php
/**
 * Lender Dashboard - Comprehensive loan management and profit tracking
 * Real-time analytics, risk assessment, and portfolio management
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a lender
if (!isLoggedIn() || $_SESSION['user_role'] !== 'lender') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$lenderId = $_SESSION['user_id'];

// Get dashboard data
$portfolioStats = getPortfolioStatistics($db, $lenderId);
$profitAnalytics = getProfitAnalytics($db, $lenderId);
$riskMetrics = getRiskMetrics($db, $lenderId);
$activeLoans = getActiveLoans($db, $lenderId);
$recentTransactions = getRecentTransactions($db, $lenderId);
$performanceMetrics = getLenderPerformanceMetrics($db, $lenderId);

function getPortfolioStatistics($db, $lenderId) {
    return $db->fetchOne("
        SELECT
            COUNT(DISTINCT l.id) as total_loans,
            COUNT(DISTINCT CASE WHEN l.status = 'active' THEN l.id END) as active_loans,
            COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.id END) as completed_loans,
            COUNT(DISTINCT CASE WHEN l.status = 'defaulted' THEN l.id END) as defaulted_loans,
            COALESCE(SUM(l.loan_amount), 0) as total_disbursed,
            COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END), 0) as active_portfolio,
            COALESCE(SUM(CASE WHEN l.status = 'completed' THEN l.total_repayment ELSE 0 END), 0) as total_repaid,
            COUNT(DISTINCT l.borrower_id) as unique_borrowers
        FROM loans l
        WHERE l.lender_id = ?
        AND l.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ", [$lenderId]);
}

function getProfitAnalytics($db, $lenderId) {
    // Monthly profit trend
    $monthlyProfit = $db->fetchAll("
        SELECT
            DATE_FORMAT(l.created_at, '%Y-%m') as month,
            SUM(l.loan_amount) as disbursed,
            COALESCE(SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END), 0) as repaid,
            COALESCE(SUM(l.total_repayment - l.loan_amount), 0) as interest_earned,
            COALESCE(SUM(CASE WHEN l.status = 'defaulted' THEN l.loan_amount ELSE 0 END), 0) as losses
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.lender_id = ?
        AND l.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(l.created_at, '%Y-%m')
        ORDER BY month DESC
    ", [$lenderId]);

    // Today's profit
    $todayProfit = $db->fetchOne("
        SELECT
            COALESCE(SUM(CASE WHEN rs.status = 'completed' AND DATE(rs.paid_at) = CURDATE()
                THEN (rs.amount_paid - l.loan_amount / l.total_repayment * rs.amount_due) ELSE 0 END), 0) as daily_profit,
            COUNT(CASE WHEN DATE(l.created_at) = CURDATE() THEN 1 END) as new_loans_today,
            COALESCE(SUM(CASE WHEN DATE(l.created_at) = CURDATE() THEN l.loan_amount ELSE 0 END), 0) as disbursed_today
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.lender_id = ?
    ", [$lenderId]);

    // Profit metrics
    $profitMetrics = $db->fetchOne("
        SELECT
            COALESCE(SUM(CASE WHEN l.status = 'completed' THEN (l.total_repayment - l.loan_amount) ELSE 0 END), 0) as total_profit,
            COALESCE(SUM(CASE WHEN l.status = 'defaulted' THEN l.loan_amount ELSE 0 END), 0) as total_losses,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN l.interest_rate END), 2) as avg_interest_rate,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN DATEDIFF(l.completed_at, l.created_at) END), 1) as avg_loan_duration_days
        FROM loans l
        WHERE l.lender_id = ?
        AND l.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ", [$lenderId]);

    return [
        'monthly_trend' => $monthlyProfit,
        'today' => $todayProfit,
        'metrics' => $profitMetrics
    ];
}

function getRiskMetrics($db, $lenderId) {
    return $db->fetchOne("
        SELECT
            COUNT(DISTINCT CASE WHEN l.status = 'defaulted' THEN 1 END) as defaulted_loans,
            ROUND(COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) * 100.0 /
                  NULLIF(COUNT(l.id), 0), 2) as default_rate,
            COALESCE(SUM(CASE WHEN l.status = 'defaulted' THEN l.loan_amount ELSE 0 END), 0) as total_defaulted_amount,
            COUNT(DISTINCT CASE WHEN l.status = 'active' AND
                EXISTS(SELECT 1 FROM repayment_schedule rs WHERE rs.loan_id = l.id
                      AND rs.due_date < CURDATE() AND rs.status = 'pending') THEN 1 END) as overdue_loans,
            COALESCE(SUM(CASE WHEN l.status = 'active' AND
                EXISTS(SELECT 1 FROM repayment_schedule rs WHERE rs.loan_id = l.id
                      AND rs.due_date < CURDATE() AND rs.status = 'pending')
                THEN rs.amount_due ELSE 0 END), 0) as overdue_amount,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN l.credit_score END), 0) as avg_credit_score,
            COUNT(DISTINCT CASE WHEN l.credit_score < 500 THEN 1 END) as high_risk_loans
        FROM loans l
        LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
        WHERE l.lender_id = ?
        AND l.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ", [$lenderId]);
}

function getActiveLoans($db, $lenderId) {
    return $db->fetchAll("
        SELECT
            l.*,
            u.name as borrower_name,
            u.phone as borrower_phone,
            u.business_name,
            (
                SELECT MIN(due_date)
                FROM repayment_schedule
                WHERE loan_id = l.id AND status = 'pending'
            ) as next_payment_date,
            (
                SELECT SUM(amount_paid)
                FROM repayment_schedule
                WHERE loan_id = l.id AND status = 'completed'
            ) as total_repaid,
            (
                SELECT COUNT(*)
                FROM repayment_schedule
                WHERE loan_id = l.id AND status = 'pending'
            ) as remaining_payments
        FROM loans l
        JOIN users u ON l.borrower_id = u.id
        WHERE l.lender_id = ? AND l.status = 'active'
        ORDER BY l.created_at DESC
        LIMIT 10
    ", [$lenderId]);
}

function getRecentTransactions($db, $lenderId) {
    return $db->fetchAll("
        SELECT
            t.*,
            u.name as borrower_name,
            l.loan_number,
            CASE
                WHEN t.transaction_type = 'disbursement' THEN 'out'
                ELSE 'in'
            END as cash_flow
        FROM financial_transactions t
        JOIN loans l ON t.loan_id = l.id
        JOIN users u ON l.borrower_id = u.id
        WHERE l.lender_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ", [$lenderId]);
}

function getLenderPerformanceMetrics($db, $lenderId) {
    return $db->fetchOne("
        SELECT
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN l.interest_rate END), 2) as avg_interest_rate,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN
                (l.total_repayment - l.loan_amount) / l.loan_amount * 100 END), 2) as avg_roi,
            ROUND(COUNT(CASE WHEN l.status = 'completed' THEN 1 END) * 100.0 /
                  NULLIF(COUNT(l.id), 0), 2) as completion_rate,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN
                DATEDIFF(l.completed_at, l.created_at) END), 1) as avg_loan_duration,
            ROUND(AVG(CASE WHEN l.status = 'completed' THEN l.credit_score END), 0) as avg_borrower_credit_score
        FROM loans l
        WHERE l.lender_id = ?
        AND l.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    ", [$lenderId]);
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
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .lender-header {
            background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .profit-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }

        .loss-card {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
        }

        .portfolio-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            border-left: 4px solid #6f42c1;
        }

        .portfolio-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .risk-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .risk-low { background: #28a745; }
        .risk-medium { background: #ffc107; }
        .risk-high { background: #fd7e14; }
        .risk-critical { background: #dc3545; }

        .profit-metric {
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }

        .profit-metric .value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }

        .profit-metric .label {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .loan-status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }

        .cash-flow-positive {
            color: #28a745;
            font-weight: bold;
        }

        .cash-flow-negative {
            color: #dc3545;
            font-weight: bold;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="lender-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-chart-line"></i> Lender Dashboard</h1>
                    <p class="mb-0">Portfolio Management & Profit Analytics</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block text-end">
                        <div class="h3 mb-1">KES <?php echo number_format($profitAnalytics['metrics']['total_profit'], 0); ?></div>
                        <div class="small">Total Profit (12 months)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Portfolio Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="portfolio-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h4 mb-1"><?php echo $portfolioStats['active_loans']; ?></div>
                            <div class="text-muted small">Active Loans</div>
                            <div class="text-info small">
                                <?php echo $portfolioStats['completed_loans']; ?> completed
                            </div>
                        </div>
                        <i class="fas fa-hand-holding-usd fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="portfolio-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h4 mb-1">KES <?php echo number_format($portfolioStats['active_portfolio'], 0); ?></div>
                            <div class="text-muted small">Active Portfolio</div>
                            <div class="text-success small">
                                <?php echo $portfolioStats['unique_borrowers']; ?> borrowers
                            </div>
                        </div>
                        <i class="fas fa-wallet fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="portfolio-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h4 mb-1"><?php echo round($performanceMetrics['avg_roi'], 1); ?>%</div>
                            <div class="text-muted small">Average ROI</div>
                            <div class="text-warning small">
                                <?php echo $performanceMetrics['avg_interest_rate']; ?>% avg interest
                            </div>
                        </div>
                        <i class="fas fa-percentage fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="portfolio-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="h4 mb-1"><?php echo round($performanceMetrics['completion_rate'], 1); ?>%</div>
                            <div class="text-muted small">Completion Rate</div>
                            <div class="text-danger small">
                                <?php echo $riskMetrics['default_rate']; ?>% default rate
                            </div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-muted opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit & Loss Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="profit-card">
                    <h5><i class="fas fa-chart-line"></i> Profit Summary</h5>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h3">KES <?php echo number_format($profitAnalytics['today']['daily_profit'], 0); ?></div>
                            <div class="small">Today's Profit</div>
                        </div>
                        <div class="col-6">
                            <div class="h3">KES <?php echo number_format($profitAnalytics['metrics']['total_profit'], 0); ?></div>
                            <div class="small">Total Profit</div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small>Interest Earned: KES <?php echo number_format($profitAnalytics['metrics']['total_profit'], 0); ?></small><br>
                        <small>Avg Interest Rate: <?php echo $performanceMetrics['avg_interest_rate']; ?>%</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="loss-card">
                    <h5><i class="fas fa-exclamation-triangle"></i> Risk Metrics</h5>
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h3"><?php echo $riskMetrics['defaulted_loans']; ?></div>
                            <div class="small">Defaulted Loans</div>
                        </div>
                        <div class="col-6">
                            <div class="h3">KES <?php echo number_format($riskMetrics['total_defaulted_amount'], 0); ?></div>
                            <div class="small">Loss Amount</div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small>Default Rate: <?php echo $riskMetrics['default_rate']; ?>%</small><br>
                        <small>Overdue Amount: KES <?php echo number_format($riskMetrics['overdue_amount'], 0); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-trophy"></i> Performance Score</h5>
                        <div class="text-center">
                            <div class="h2 text-primary">
                                <?php
                                $score = calculatePerformanceScore($portfolioStats, $riskMetrics, $performanceMetrics);
                                echo $score;
                                ?>/100
                            </div>
                            <div class="small text-muted">Overall Performance</div>
                        </div>
                        <hr>
                        <div class="performance-breakdown">
                            <div class="d-flex justify-content-between mb-1">
                                <small>Profitability</small>
                                <small><?php echo min(100, round($profitAnalytics['metrics']['total_profit'] / 10000)); ?>%</small>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <small>Risk Management</small>
                                <small><?php echo max(0, 100 - $riskMetrics['default_rate'] * 10); ?>%</small>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small>Completion Rate</small>
                                <small><?php echo $performanceMetrics['completion_rate']; ?>%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-area"></i> Profit & Loss Trend (12 Months)</h5>
                    <canvas id="profitLossChart" height="100"></canvas>
                </div>
            </div>
            <div class="col-md-4">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> Portfolio Distribution</h5>
                    <canvas id="portfolioChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Active Loans -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> Active Loans</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-success" onclick="disburseNewLoan()">
                                <i class="fas fa-plus"></i> New Loan
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewAllLoans()">
                                <i class="fas fa-eye"></i> View All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Borrower</th>
                                        <th>Amount</th>
                                        <th>Interest</th>
                                        <th>Repaid</th>
                                        <th>Next Payment</th>
                                        <th>Risk</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeLoans as $loan): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($loan['borrower_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($loan['business_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>KES <?php echo number_format($loan['loan_amount'], 0); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $loan['interest_rate']; ?>%</span>
                                            </td>
                                            <td>
                                                <div>
                                                    KES <?php echo number_format($loan['total_repaid'], 0); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo round(($loan['total_repaid'] / $loan['total_repayment']) * 100, 1); ?>%
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($loan['next_payment_date']): ?>
                                                    <div>
                                                        <?php echo date('M j', strtotime($loan['next_payment_date'])); ?><br>
                                                        <small class="text-muted"><?php echo $loan['remaining_payments']; ?> left</small>
                                                    </div>
                                                <?php else: ?>
                                                    <small class="text-muted">N/A</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $riskClass = getRiskClass($loan['credit_score']);
                                                echo "<span class=\"risk-indicator risk-{$riskClass}\"></span>";
                                                echo ucfirst($riskClass);
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="viewLoanDetails(<?php echo $loan['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="contactBorrower(<?php echo $loan['borrower_id']; ?>)">
                                                        <i class="fas fa-phone"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" onclick="modifyLoan(<?php echo $loan['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exchange-alt"></i> Recent Transactions</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($transaction['borrower_name']); ?></div>
                                    <small class="text-muted"><?php echo date('M j, H:i', strtotime($transaction['created_at'])); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="cash-flow-<?php echo $transaction['cash_flow']; ?>">
                                        <?php echo $transaction['cash_flow'] === 'in' ? '+' : '-'; ?>
                                        KES <?php echo number_format($transaction['amount'], 0); ?>
                                    </div>
                                    <small class="text-muted"><?php echo ucfirst($transaction['transaction_type']); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loan Details Modal -->
    <div class="modal fade" id="loanDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Loan Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="loanDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profit & Loss Chart
        const profitLossCtx = document.getElementById('profitLossChart').getContext('2d');
        const profitData = <?php echo json_encode(array_reverse($profitAnalytics['monthly_trend'])); ?>;

        new Chart(profitLossCtx, {
            type: 'line',
            data: {
                labels: profitData.map(d => {
                    const date = new Date(d.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Profit',
                    data: profitData.map(d => d.interest_earned),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Losses',
                    data: profitData.map(d => d.losses),
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Disbursed',
                    data: profitData.map(d => d.disbursed),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4,
                    fill: false,
                    yAxisID: 'y1'
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
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Portfolio Distribution Chart
        const portfolioCtx = document.getElementById('portfolioChart').getContext('2d');
        new Chart(portfolioCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Completed', 'Defaulted'],
                datasets: [{
                    data: [
                        <?php echo $portfolioStats['active_loans']; ?>,
                        <?php echo $portfolioStats['completed_loans']; ?>,
                        <?php echo $portfolioStats['defaulted_loans']; ?>
                    ],
                    backgroundColor: ['#007bff', '#28a745', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        function calculatePerformanceScore(portfolio, risk, performance) {
            let score = 0;

            // Profitability (40%)
            score += Math.min(40, portfolio['total_repaid'] / 100000);

            // Low default rate (30%)
            score += Math.max(0, 30 - (risk['default_rate'] * 3));

            // Completion rate (20%)
            score += (performance['completion_rate'] / 100) * 20;

            // Active portfolio management (10%)
            score += portfolio['active_loans'] > 0 ? 10 : 0;

            return Math.min(100, Math.round(score));
        }

        function getRiskClass(creditScore) {
            if ($creditScore >= 700) return 'low';
            if ($creditScore >= 600) return 'medium';
            if ($creditScore >= 500) return 'high';
            return 'critical';
        }

        function viewLoanDetails(loanId) {
            // Load loan details via AJAX
            fetch(`get-loan-details.php?id=${loanId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Loan Information</h6>
                                <p><strong>Loan ID:</strong> #${data.id}</p>
                                <p><strong>Amount:</strong> KES ${data.loan_amount.toLocaleString()}</p>
                                <p><strong>Interest Rate:</strong> ${data.interest_rate}%</p>
                                <p><strong>Status:</strong> ${data.status}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Borrower Information</h6>
                                <p><strong>Name:</strong> ${data.borrower_name}</p>
                                <p><strong>Business:</strong> ${data.business_name}</p>
                                <p><strong>Credit Score:</strong> ${data.credit_score}</p>
                                <p><strong>Phone:</strong> ${data.borrower_phone}</p>
                            </div>
                        </div>
                        <hr>
                        <h6>Repayment Schedule</h6>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Installment</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;

                    if (data.repayment_schedule) {
                        data.repayment_schedule.forEach(payment => {
                            html += `
                                <tr>
                                    <td>${payment.installment_number}</td>
                                    <td>${payment.due_date}</td>
                                    <td>KES ${payment.amount_due.toLocaleString()}</td>
                                    <td><span class="badge bg-${payment.status === 'completed' ? 'success' : 'warning'}">${payment.status}</span></td>
                                </tr>
                            `;
                        });
                    }

                    html += `
                                </tbody>
                            </table>
                        </div>
                    `;

                    document.getElementById('loanDetailsContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('loanDetailsModal')).show();
                });
        }

        function disburseNewLoan() {
            window.location.href = 'disburse-loan.php';
        }

        function viewAllLoans() {
            window.location.href = 'all-loans.php';
        }

        function contactBorrower(borrowerId) {
            // Implementation for contacting borrower
            alert('Contact borrower functionality would be implemented here');
        }

        function modifyLoan(loanId) {
            window.location.href = `modify-loan.php?id=${loanId}`;
        }

        // Auto-refresh data every 2 minutes
        setInterval(() => {
            location.reload();
        }, 120000);
    </script>
</body>
</html>

<?php
function calculatePerformanceScore($portfolio, $risk, $performance) {
    $score = 0;

    // Profitability (40%)
    $score += min(40, $portfolio['total_repaid'] / 100000);

    // Low default rate (30%)
    $score += max(0, 30 - ($risk['default_rate'] * 3));

    // Completion rate (20%)
    $score += ($performance['completion_rate'] / 100) * 20;

    // Active portfolio management (10%)
    $score += $portfolio['active_loans'] > 0 ? 10 : 0;

    return min(100, round($score));
}

function getRiskClass($creditScore) {
    if ($creditScore >= 700) return 'low';
    if ($creditScore >= 600) return 'medium';
    if ($creditScore >= 500) return 'high';
    return 'critical';
}
?>
