<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check authentication
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Validate token
$db = new Database();
$db->connect();

$db->query('SELECT user_id FROM api_tokens WHERE token = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)');
$db->bind('s', $token);
$tokenData = $db->single();

if (!$tokenData) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

$userId = $tokenData['user_id'];
$db->query('SELECT role FROM users WHERE id = ?');
$db->bind('s', $userId);
$user = $db->single();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$userRole = $user['role'];

// Get request parameters
$period = $_GET['period'] ?? 'month';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$type = $_GET['type'] ?? 'overview';

try {
    switch ($type) {
        case 'overview':
            echo json_encode(getOverviewAnalytics($db, $userId, $userRole, $period, $startDate, $endDate));
            break;

        case 'financial':
            echo json_encode(getFinancialAnalytics($db, $userId, $userRole, $period, $startDate, $endDate));
            break;

        case 'performance':
            echo json_encode(getPerformanceAnalytics($db, $userId, $userRole, $period, $startDate, $endDate));
            break;

        case 'trends':
            echo json_encode(getTrendsAnalytics($db, $userId, $userRole, $period, $startDate, $endDate));
            break;

        case 'predictions':
            echo json_encode(getPredictionsAnalytics($db, $userId, $userRole));
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid analytics type']);
    }
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getOverviewAnalytics($db, $userId, $userRole, $period, $startDate, $endDate) {
    $dateCondition = getDateCondition($period, $startDate, $endDate);

    $analytics = [];

    if ($userRole === 'retailer') {
        // Retailer-specific analytics
        $analytics['credit_score'] = getCreditScore($db, $userId);
        $analytics['available_credit'] = getAvailableCredit($db, $userId);
        $analytics['active_orders'] = getActiveOrdersCount($db, $userId, $dateCondition);
        $analytics['monthly_spending'] = getMonthlySpending($db, $userId, $dateCondition);
        $analytics['payment_history'] = getPaymentHistory($db, $userId, $dateCondition);

    } elseif ($userRole === 'lender') {
        // Lender-specific analytics
        $analytics['total_invested'] = getTotalInvested($db, $userId, $dateCondition);
        $analytics['active_loans'] = getActiveLoansCount($db, $userId, $dateCondition);
        $analytics['returns'] = getReturns($db, $userId, $dateCondition);
        $analytics['repayment_rate'] = getRepaymentRate($db, $userId, $dateCondition);
        $analytics['portfolio_distribution'] = getPortfolioDistribution($db, $userId);

    } elseif ($userRole === 'supplier') {
        // Supplier-specific analytics
        $analytics['total_orders'] = getTotalOrdersCount($db, $userId, $dateCondition);
        $analytics['revenue'] = getRevenue($db, $userId, $dateCondition);
        $analytics['active_products'] = getActiveProductsCount($db, $userId);
        $analytics['top_products'] = getTopProducts($db, $userId, $dateCondition);
        $analytics['order_status_breakdown'] = getOrderStatusBreakdown($db, $userId, $dateCondition);

    } elseif ($userRole === 'admin') {
        // Admin-specific analytics
        $analytics['platform_stats'] = getPlatformStats($db, $dateCondition);
        $analytics['user_growth'] = getUserGrowth($db, $dateCondition);
        $analytics['transaction_volume'] = getTransactionVolume($db, $dateCondition);
        $analytics['system_health'] = getSystemHealth($db);
        $analytics['revenue_streams'] = getRevenueStreams($db, $dateCondition);
    }

    return ['success' => true, 'data' => $analytics];
}

function getFinancialAnalytics($db, $userId, $userRole, $period, $dateCondition) {
    $analytics = [];

    if ($userRole === 'retailer') {
        $analytics['spending_trends'] = getSpendingTrends($db, $userId, $dateCondition);
        $analytics['credit_utilization'] = getCreditUtilization($db, $userId);
        $analytics['payment_schedule'] = getPaymentSchedule($db, $userId);
        $analytics['debt_to_income'] = getDebtToIncome($db, $userId);

    } elseif ($userRole === 'lender') {
        $analytics['investment_returns'] = getInvestmentReturns($db, $userId, $dateCondition);
        $analytics['loan_performance'] = getLoanPerformance($db, $userId, $dateCondition);
        $analytics['risk_assessment'] = getRiskAssessment($db, $userId);
        $analytics['cash_flow'] = getCashFlow($db, $userId, $dateCondition);

    } elseif ($userRole === 'supplier') {
        $analytics['revenue_analytics'] = getRevenueAnalytics($db, $userId, $dateCondition);
        $analytics['profit_margins'] = getProfitMargins($db, $userId, $dateCondition);
        $analytics['inventory_turnover'] = getInventoryTurnover($db, $userId);
        $analytics['customer_analytics'] = getCustomerAnalytics($db, $userId, $dateCondition);

    } elseif ($userRole === 'admin') {
        $analytics['financial_overview'] = getFinancialOverview($db, $dateCondition);
        $analytics['revenue_breakdown'] = getRevenueBreakdown($db, $dateCondition);
        $analytics['cost_analysis'] = getCostAnalysis($db, $dateCondition);
        $analytics['profitability_metrics'] = getProfitabilityMetrics($db, $dateCondition);
    }

    return ['success' => true, 'data' => $analytics];
}

function getPerformanceAnalytics($db, $userId, $userRole, $period, $dateCondition) {
    $analytics = [];

    if ($userRole === 'retailer') {
        $analytics['order_performance'] = getOrderPerformance($db, $userId, $dateCondition);
        $analytics['supplier_ratings'] = getSupplierRatings($db, $userId);
        $analytics['delivery_times'] = getDeliveryTimes($db, $userId, $dateCondition);

    } elseif ($userRole === 'lender') {
        $analytics['loan_performance_metrics'] = getLoanPerformanceMetrics($db, $userId, $dateCondition);
        $analytics['borrower_ratings'] = getBorrowerRatings($db, $userId);
        $analytics['default_rates'] = getDefaultRates($db, $userId, $dateCondition);

    } elseif ($userRole === 'supplier') {
        $analytics['sales_performance'] = getSalesPerformance($db, $userId, $dateCondition);
        $analytics['product_performance'] = getProductPerformance($db, $userId, $dateCondition);
        $analytics['customer_satisfaction'] = getCustomerSatisfaction($db, $userId);

    } elseif ($userRole === 'admin') {
        $analytics['platform_performance'] = getPlatformPerformance($db, $dateCondition);
        $analytics['user_engagement'] = getUserEngagement($db, $dateCondition);
        $analytics['system_performance'] = getSystemPerformance($db);
    }

    return ['success' => true, 'data' => $analytics];
}

function getTrendsAnalytics($db, $userId, $userRole, $period, $dateCondition) {
    $analytics = [];

    // Time series data for trends
    $analytics['time_series'] = getTimeSeriesData($db, $userId, $userRole, $period, $dateCondition);
    $analytics['growth_rates'] = getGrowthRates($db, $userId, $userRole, $dateCondition);
    $analytics['seasonal_patterns'] = getSeasonalPatterns($db, $userId, $userRole);
    $analytics['forecasting'] = getForecastingData($db, $userId, $userRole, $period);

    return ['success' => true, 'data' => $analytics];
}

function getPredictionsAnalytics($db, $userId, $userRole) {
    $analytics = [];

    if ($userRole === 'retailer') {
        $analytics['credit_score_prediction'] = getCreditScorePrediction($db, $userId);
        $analytics['spending_prediction'] = getSpendingPrediction($db, $userId);
        $analytics['default_risk'] = getDefaultRiskPrediction($db, $userId);

    } elseif ($userRole === 'lender') {
        $analytics['investment_returns_prediction'] = getInvestmentReturnsPrediction($db, $userId);
        $analytics['loan_performance_prediction'] = getLoanPerformancePrediction($db, $userId);
        $analytics['portfolio_optimization'] = getPortfolioOptimization($db, $userId);

    } elseif ($userRole === 'supplier') {
        $analytics['demand_forecasting'] = getDemandForecasting($db, $userId);
        $analytics['revenue_prediction'] = getRevenuePrediction($db, $userId);
        $analytics['inventory_optimization'] = getInventoryOptimization($db, $userId);

    } elseif ($userRole === 'admin') {
        $analytics['platform_growth_prediction'] = getPlatformGrowthPrediction($db);
        $analytics['risk_prediction'] = getPlatformRiskPrediction($db);
        $analytics['revenue_forecasting'] = getRevenueForecasting($db);
    }

    return ['success' => true, 'data' => $analytics];
}

// Helper functions
function getDateCondition($period, $startDate, $endDate) {
    if ($startDate && $endDate) {
        return "DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
    }

    switch ($period) {
        case 'day':
            return "DATE(created_at) = CURDATE()";
        case 'week':
            return "WEEK(created_at) = WEEK(NOW()) AND YEAR(created_at) = YEAR(NOW())";
        case 'month':
            return "MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
        case 'quarter':
            return "QUARTER(created_at) = QUARTER(NOW()) AND YEAR(created_at) = YEAR(NOW())";
        case 'year':
            return "YEAR(created_at) = YEAR(NOW())";
        default:
            return "MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())";
    }
}

// Retailer-specific functions
function getCreditScore($db, $userId) {
    $db->query('SELECT credit_score FROM users WHERE id = ?');
    $db->bind('s', $userId);
    $user = $db->single();
    return $user['credit_score'] ?? 0;
}

function getAvailableCredit($db, $userId) {
    // Calculate available credit based on credit limit and current usage
    $db->query('SELECT credit_limit, (SELECT COALESCE(SUM(amount), 0) FROM loans WHERE user_id = ? AND status = "active") as used_credit FROM users WHERE id = ?');
    $db->bind('ss', $userId, $userId);
    $result = $db->single();

    $creditLimit = $result['credit_limit'] ?? 0;
    $usedCredit = $result['used_credit'] ?? 0;

    return max(0, $creditLimit - $usedCredit);
}

function getActiveOrdersCount($db, $userId, $dateCondition) {
    $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status IN ('pending', 'processing', 'shipped') AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['count'] ?? 0;
}

function getMonthlySpending($db, $userId, $dateCondition) {
    $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE user_id = ? AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['total'] ?? 0;
}

function getPaymentHistory($db, $userId, $dateCondition) {
    $db->query("SELECT COUNT(*) as total_payments, SUM(CASE WHEN paid_on_time = 1 THEN 1 ELSE 0 END) as on_time_payments FROM loan_payments WHERE loan_id IN (SELECT id FROM loans WHERE user_id = ?) AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();

    $totalPayments = $result['total_payments'] ?? 0;
    $onTimePayments = $result['on_time_payments'] ?? 0;

    return [
        'total_payments' => $totalPayments,
        'on_time_payments' => $onTimePayments,
        'on_time_rate' => $totalPayments > 0 ? round(($onTimePayments / $totalPayments) * 100, 2) : 0
    ];
}

// Lender-specific functions
function getTotalInvested($db, $userId, $dateCondition) {
    $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM loans WHERE lender_id = ? AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['total'] ?? 0;
}

function getActiveLoansCount($db, $userId, $dateCondition) {
    $db->query("SELECT COUNT(*) as count FROM loans WHERE lender_id = ? AND status = 'active' AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['count'] ?? 0;
}

function getReturns($db, $userId, $dateCondition) {
    $db->query("SELECT COALESCE(SUM(interest_paid), 0) as returns FROM loans WHERE lender_id = ? AND status = 'active' AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['returns'] ?? 0;
}

function getRepaymentRate($db, $userId, $dateCondition) {
    $db->query("SELECT
        COUNT(*) as total_loans,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_loans
    FROM loans WHERE lender_id = ? AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();

    $totalLoans = $result['total_loans'] ?? 0;
    $completedLoans = $result['completed_loans'] ?? 0;

    return $totalLoans > 0 ? round(($completedLoans / $totalLoans) * 100, 2) : 0;
}

function getPortfolioDistribution($db, $userId) {
    $db->query("SELECT
        CASE
            WHEN loan_purpose = 'inventory' THEN 'Inventory'
            WHEN loan_purpose = 'expansion' THEN 'Business Expansion'
            WHEN loan_purpose = 'working_capital' THEN 'Working Capital'
            ELSE 'Other'
        END as category,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM loans WHERE lender_id = ? AND status = 'active'
    GROUP BY loan_purpose");
    $db->bind('s', $userId);
    return $db->resultSet();
}

// Supplier-specific functions
function getTotalOrdersCount($db, $userId, $dateCondition) {
    $db->query("SELECT COUNT(*) as count FROM orders WHERE supplier_id = ? AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['count'] ?? 0;
}

function getRevenue($db, $userId, $dateCondition) {
    $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE supplier_id = ? AND $dateCondition");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['revenue'] ?? 0;
}

function getActiveProductsCount($db, $userId) {
    $db->query("SELECT COUNT(*) as count FROM products WHERE supplier_id = ? AND status = 'active'");
    $db->bind('s', $userId);
    $result = $db->single();
    return $result['count'] ?? 0;
}

function getTopProducts($db, $userId, $dateCondition) {
    $db->query("SELECT
        p.name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.total_price) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE p.supplier_id = ? AND $dateCondition
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
    LIMIT 10");
    $db->bind('s', $userId);
    return $db->resultSet();
}

function getOrderStatusBreakdown($db, $userId, $dateCondition) {
    $db->query("SELECT status, COUNT(*) as count FROM orders WHERE supplier_id = ? AND $dateCondition GROUP BY status");
    $db->bind('s', $userId);
    return $db->resultSet();
}

// Admin-specific functions
function getPlatformStats($db, $dateCondition) {
    $db->query("SELECT
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_users,
        (SELECT COUNT(*) FROM loans WHERE $dateCondition) as total_loans,
        (SELECT COALESCE(SUM(amount), 0) FROM loans WHERE $dateCondition) as total_loan_volume,
        (SELECT COUNT(*) FROM users WHERE role = 'retailer') as total_retailers,
        (SELECT COUNT(*) FROM users WHERE role = 'lender') as total_lenders,
        (SELECT COUNT(*) FROM users WHERE role = 'supplier') as total_suppliers");
    return $db->single();
}

function getUserGrowth($db, $dateCondition) {
    $db->query("SELECT
        DATE(created_at) as date,
        COUNT(*) as new_users
    FROM users
    WHERE $dateCondition
    GROUP BY DATE(created_at)
    ORDER BY date DESC");
    return $db->resultSet();
}

function getTransactionVolume($db, $dateCondition) {
    $db->query("SELECT
        DATE(created_at) as date,
        COALESCE(SUM(amount), 0) as volume,
        COUNT(*) as transaction_count
    FROM loans
    WHERE $dateCondition
    GROUP BY DATE(created_at)
    ORDER BY date DESC");
    return $db->resultSet();
}

function getSystemHealth($db) {
    // Mock system health metrics
    return [
        'api_response_time' => rand(100, 200) . 'ms',
        'database_load' => rand(20, 80) . '%',
        'server_uptime' => '99.9%',
        'active_sessions' => rand(100, 500),
        'error_rate' => rand(0, 2) . '%'
    ];
}

function getRevenueStreams($db, $dateCondition) {
    return [
        'transaction_fees' => rand(100000, 200000),
        'interest_revenue' => rand(500000, 1000000),
        'subscription_fees' => rand(50000, 100000),
        'other_revenue' => rand(10000, 50000)
    ];
}

// Additional helper functions for other analytics types would go here...
// For brevity, I'm including some essential ones

function getTimeSeriesData($db, $userId, $userRole, $period, $dateCondition) {
    // Generate time series data based on user role and period
    $data = [];
    $days = $period === 'week' ? 7 : ($period === 'month' ? 30 : 90);

    for ($i = $days; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $data[] = [
            'date' => $date,
            'value' => rand(1000, 10000) // Mock data
        ];
    }

    return $data;
}

function getGrowthRates($db, $userId, $userRole, $dateCondition) {
    return [
        'daily_growth' => rand(1, 10) . '%',
        'weekly_growth' => rand(5, 25) . '%',
        'monthly_growth' => rand(10, 50) . '%',
        'quarterly_growth' => rand(20, 100) . '%'
    ];
}

function getSeasonalPatterns($db, $userId, $userRole) {
    return [
        'peak_season' => 'Q4',
        'low_season' => 'Q2',
        'seasonal_factor' => rand(80, 120) . '%'
    ];
}

function getForecastingData($db, $userId, $userRole, $period) {
    $forecast = [];
    $days = $period === 'week' ? 7 : ($period === 'month' ? 30 : 90);

    for ($i = 1; $i <= $days; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        $forecast[] = [
            'date' => $date,
            'predicted_value' => rand(1000, 10000),
            'confidence_lower' => rand(800, 9000),
            'confidence_upper' => rand(11000, 12000)
        ];
    }

    return $forecast;
}

// Prediction functions (mock implementations)
function getCreditScorePrediction($db, $userId) {
    return [
        'current_score' => rand(600, 800),
        'predicted_score_3m' => rand(620, 820),
        'predicted_score_6m' => rand(640, 840),
        'confidence' => rand(75, 95) . '%'
    ];
}

function getInvestmentReturnsPrediction($db, $userId) {
    return [
        'current_return_rate' => rand(8, 15) . '%',
        'predicted_return_3m' => rand(9, 16) . '%',
        'predicted_return_6m' => rand(10, 17) . '%',
        'risk_level' => ['Low', 'Medium', 'High'][rand(0, 2)]
    ];
}

function getDemandForecasting($db, $userId) {
    return [
        'current_demand' => rand(100, 500),
        'predicted_demand_3m' => rand(120, 600),
        'predicted_demand_6m' => rand(150, 800),
        'growth_trend' => ['Increasing', 'Stable', 'Decreasing'][rand(0, 2)]
    ];
}

?>