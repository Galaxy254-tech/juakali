<?php
/**
 * Advanced Analytics and Reporting Engine for JuaKali Lend
 * Comprehensive business intelligence with predictive analytics and custom reports
 */

class AnalyticsEngine {
    private $db;
    private $tenantId;
    private $cacheTimeout = 300; // 5 minutes cache

    public function __construct($database, $tenantId = null) {
        $this->db = $database;
        $this->tenantId = $tenantId;
    }

    /**
     * Generate comprehensive business overview
     */
    public function generateBusinessOverview($dateRange = 30, $filters = []) {
        $cacheKey = "business_overview_" . md5(serialize([$dateRange, $filters]));
        $cached = $this->getCachedData($cacheKey);

        if ($cached) {
            return $cached;
        }

        $overview = [
            'summary_metrics' => $this->getSummaryMetrics($dateRange, $filters),
            'revenue_analytics' => $this->getRevenueAnalytics($dateRange, $filters),
            'portfolio_performance' => $this->getPortfolioPerformance($dateRange, $filters),
            'risk_assessment' => $this->getRiskAssessment($dateRange, $filters),
            'user_analytics' => $this->getUserAnalytics($dateRange, $filters),
            'operational_metrics' => $this->getOperationalMetrics($dateRange, $filters),
            'forecasting' => $this->generateForecasts($dateRange, $filters)
        ];

        $this->cacheData($cacheKey, $overview);
        return $overview;
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        $metrics = $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN u.role = 'retailer' THEN u.id END) as total_retailers,
                COUNT(DISTINCT CASE WHEN u.role = 'supplier' THEN u.id END) as total_suppliers,
                COUNT(DISTINCT CASE WHEN u.role = 'lender' THEN u.id END) as total_lenders,
                COUNT(DISTINCT l.id) as total_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'active' THEN l.id END) as active_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.id END) as completed_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'defaulted' THEN l.id END) as defaulted_loans,
                COALESCE(SUM(l.loan_amount), 0) as total_disbursed,
                COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END), 0) as active_portfolio,
                COALESCE(SUM(CASE WHEN l.status = 'completed' THEN l.total_repayment ELSE 0 END), 0) as total_repaid,
                COALESCE(SUM(CASE WHEN l.status = 'completed' THEN (l.total_repayment - l.loan_amount) ELSE 0 END), 0) as total_interest_earned,
                COALESCE(SUM(CASE WHEN l.status = 'defaulted' THEN l.loan_amount ELSE 0 END), 0) as total_losses
            FROM users u
            LEFT JOIN loans l ON u.id = l.borrower_id
            {$whereClause}
            AND (l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) OR l.created_at IS NULL)
        ", array_merge($params, [$dateRange]));

        // Calculate derived metrics
        $metrics['completion_rate'] = $metrics['total_loans'] > 0
            ? round(($metrics['completed_loans'] / $metrics['total_loans']) * 100, 2)
            : 0;

        $metrics['default_rate'] = $metrics['total_loans'] > 0
            ? round(($metrics['defaulted_loans'] / $metrics['total_loans']) * 100, 2)
            : 0;

        $metrics['roi_percentage'] = $metrics['total_disbursed'] > 0
            ? round(($metrics['total_interest_earned'] / $metrics['total_disbursed']) * 100, 2)
            : 0;

        $metrics['avg_loan_size'] = $metrics['total_loans'] > 0
            ? round($metrics['total_disbursed'] / $metrics['total_loans'], 0)
            : 0;

        return $metrics;
    }

    /**
     * Get revenue analytics
     */
    private function getRevenueAnalytics($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        // Monthly revenue trends
        $monthlyRevenue = $this->db->fetchAll("
            SELECT
                DATE_FORMAT(l.created_at, '%Y-%m') as month,
                COUNT(*) as loan_count,
                COALESCE(SUM(l.loan_amount), 0) as disbursement,
                COALESCE(SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END), 0) as repayments,
                COALESCE(SUM(CASE WHEN l.status = 'completed' THEN (l.total_repayment - l.loan_amount) ELSE 0 END), 0) as interest,
                COALESCE(SUM(CASE WHEN l.status = 'defaulted' THEN l.loan_amount ELSE 0 END), 0) as losses
            FROM loans l
            LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE_FORMAT(l.created_at, '%Y-%m')
            ORDER BY month DESC
        ", array_merge($params, [$dateRange]));

        // Revenue by loan purpose
        $revenueByPurpose = $this->db->fetchAll("
            SELECT
                l.loan_purpose,
                COUNT(*) as count,
                COALESCE(SUM(l.loan_amount), 0) as total_amount,
                COALESCE(SUM(l.total_repayment - l.loan_amount), 0) as total_interest,
                ROUND(AVG(l.interest_rate), 2) as avg_interest_rate
            FROM loans l
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY l.loan_purpose
            ORDER BY total_amount DESC
        ", array_merge($params, [$dateRange]));

        // Daily revenue for last 30 days
        $dailyRevenue = $this->db->fetchAll("
            SELECT
                DATE(l.created_at) as date,
                COUNT(*) as loans_disbursed,
                COALESCE(SUM(l.loan_amount), 0) as daily_disbursement,
                COALESCE(SUM(CASE WHEN rs.status = 'completed' AND DATE(rs.paid_at) = DATE(l.created_at)
                    THEN rs.amount_paid ELSE 0 END), 0) as daily_collections
            FROM loans l
            LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(l.created_at)
            ORDER BY date DESC
        ", $params);

        return [
            'monthly_trends' => $monthlyRevenue,
            'by_purpose' => $revenueByPurpose,
            'daily_breakdown' => $dailyRevenue
        ];
    }

    /**
     * Get portfolio performance metrics
     */
    private function getPortfolioPerformance($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        // Portfolio health metrics
        $healthMetrics = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_portfolio_items,
                COUNT(CASE WHEN l.status = 'active' THEN 1 END) as active_items,
                COUNT(CASE WHEN l.status = 'completed' THEN 1 END) as successful_items,
                COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) at_risk_items,
                ROUND(AVG(CASE WHEN l.status = 'completed' THEN DATEDIFF(l.completed_at, l.created_at) END), 1) as avg_loan_duration_days,
                ROUND(AVG(l.interest_rate), 2) as avg_interest_rate,
                ROUND(AVG(CASE WHEN l.status = 'completed' THEN l.credit_score END), 0) as avg_credit_score
            FROM loans l
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", array_merge($params, [$dateRange]));

        // Performance by loan size brackets
        $performanceBySize = $this->db->fetchAll("
            SELECT
                CASE
                    WHEN l.loan_amount <= 5000 THEN '0-5K'
                    WHEN l.loan_amount <= 15000 THEN '5K-15K'
                    WHEN l.loan_amount <= 50000 THEN '15K-50K'
                    ELSE '50K+'
                END as size_bracket,
                COUNT(*) as count,
                COUNT(CASE WHEN l.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) as defaulted,
                COALESCE(AVG(l.interest_rate), 0) as avg_interest_rate,
                COALESCE(SUM(l.loan_amount), 0) as total_amount
            FROM loans l
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY size_bracket
            ORDER BY MIN(l.loan_amount)
        ", array_merge($params, [$dateRange]));

        // Seasonal performance patterns
        $seasonalPatterns = $this->db->fetchAll("
            SELECT
                CASE
                    WHEN MONTH(l.created_at) IN (12, 1, 2) THEN 'Q1 (Dec-Feb)'
                    WHEN MONTH(l.created_at) IN (3, 4, 5) THEN 'Q2 (Mar-May)'
                    WHEN MONTH(l.created_at) IN (6, 7, 8) THEN 'Q3 (Jun-Aug)'
                    WHEN MONTH(l.created_at) IN (9, 10, 11) THEN 'Q4 (Sep-Nov)'
                END as quarter,
                YEAR(l.created_at) as year,
                COUNT(*) as loan_count,
                COALESCE(SUM(l.loan_amount), 0) as total_amount,
                COUNT(CASE WHEN l.status = 'completed' THEN 1 END) as completed_loans
            FROM loans l
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
            GROUP BY quarter, year
            ORDER BY year DESC, quarter DESC
        ", $params);

        return [
            'health_metrics' => $healthMetrics,
            'performance_by_size' => $performanceBySize,
            'seasonal_patterns' => $seasonalPatterns
        ];
    }

    /**
     * Get comprehensive risk assessment
     */
    private function getRiskAssessment($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        // Risk distribution
        $riskDistribution = $this->db->fetchAll("
            SELECT
                cse.risk_category,
                COUNT(*) as borrower_count,
                COUNT(l.id) as loan_count,
                COALESCE(SUM(l.loan_amount), 0) as total_exposure,
                COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) as defaults,
                ROUND(COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) * 100.0 / NULLIF(COUNT(l.id), 0), 2) as default_rate_by_category
            FROM credit_score_evaluations cse
            LEFT JOIN loans l ON l.borrower_id = cse.user_id AND l.created_at >= cse.created_at
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY cse.risk_category
            ORDER BY cse.risk_category
        ", array_merge($params, [$dateRange]));

        // Early warning indicators
        $earlyWarning = $this->db->fetchOne("
            SELECT
                COUNT(CASE WHEN rs.due_date < CURDATE() AND rs.status = 'pending' THEN 1 END) as overdue_count,
                COUNT(CASE WHEN rs.due_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND rs.status = 'pending' THEN 1 END) as severely_overdue,
                COUNT(CASE WHEN l.status = 'active' AND l.last_payment_date < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as no_recent_payment,
                COUNT(CASE WHEN fd.risk_level = 'HIGH' AND fd.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_fraud_flags,
                COALESCE(SUM(CASE WHEN rs.due_date < CURDATE() AND rs.status = 'pending' THEN rs.total_due ELSE 0 END), 0) as total_overdue_amount
            FROM loans l
            LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
            LEFT JOIN fraud_detections fd ON l.borrower_id = fd.user_id
            {$whereClause}
            AND l.status = 'active'
        ", $params);

        // Concentration risk
        $concentrationRisk = $this->db->fetchAll("
            SELECT
                u.id as user_id,
                u.name as user_name,
                COUNT(l.id) as loan_count,
                COALESCE(SUM(l.loan_amount), 0) as total_exposure,
                ROUND(SUM(l.loan_amount) * 100.0 / (SELECT COALESCE(SUM(l2.loan_amount), 0) FROM loans l2 WHERE l2.status = 'active'), 2) as portfolio_percentage
            FROM users u
            JOIN loans l ON u.id = l.borrower_id
            {$whereClause}
            AND l.status = 'active'
            GROUP BY u.id, u.name
            HAVING total_exposure > 0
            ORDER BY total_exposure DESC
            LIMIT 10
        ", $params);

        return [
            'risk_distribution' => $riskDistribution,
            'early_warning_indicators' => $earlyWarning,
            'concentration_risk' => $concentrationRisk
        ];
    }

    /**
     * Get user analytics
     */
    private function getUserAnalytics($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        // User growth trends
        $userGrowth = $this->db->fetchAll("
            SELECT
                DATE(u.created_at) as date,
                COUNT(*) as new_users,
                COUNT(CASE WHEN u.role = 'retailer' THEN 1 END) as new_retailers,
                COUNT(CASE WHEN u.role = 'supplier' THEN 1 END) as new_suppliers,
                COUNT(CASE WHEN u.role = 'lender' THEN 1 END) as new_lenders
            FROM users u
            {$whereClause}
            AND u.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(u.created_at)
            ORDER BY date DESC
        ", array_merge($params, [$dateRange]));

        // User engagement metrics
        $engagementMetrics = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_users,
                COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as daily_active,
                COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_active,
                COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_active,
                COUNT(CASE WHEN u.last_login < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as inactive_users,
                ROUND(AVG(DATEDIFF(NOW(), u.last_login)), 1) as avg_days_since_login
            FROM users u
            {$whereClause}
        ", $params);

        // Cohort analysis
        $cohortAnalysis = $this->db->fetchAll("
            SELECT
                DATE(u.created_at) as cohort_date,
                COUNT(u.id) as cohort_size,
                COUNT(CASE WHEN l.id IS NOT NULL THEN 1 END) as converted_users,
                COUNT(CASE WHEN l.status = 'completed' THEN 1 END) as successful_users,
                ROUND(COUNT(CASE WHEN l.id IS NOT NULL THEN 1 END) * 100.0 / COUNT(u.id), 2) as conversion_rate,
                ROUND(COUNT(CASE WHEN l.status = 'completed' THEN 1 END) * 100.0 / COUNT(u.id), 2) as success_rate
            FROM users u
            LEFT JOIN loans l ON u.id = l.borrower_id
            {$whereClause}
            AND u.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY DATE(u.created_at)
            ORDER BY cohort_date DESC
        ", $params);

        // Geographic distribution
        $geographicDistribution = $this->db->fetchAll("
            SELECT
                u.region,
                u.city,
                COUNT(*) as user_count,
                COUNT(CASE WHEN u.role = 'retailer' THEN 1 END) as retailer_count,
                COUNT(CASE WHEN u.role = 'supplier' THEN 1 END) as supplier_count,
                COALESCE(SUM(CASE WHEN l.id IS NOT NULL THEN l.loan_amount ELSE 0 END), 0) as total_loan_volume
            FROM users u
            LEFT JOIN loans l ON u.id = l.borrower_id
            {$whereClause}
            AND u.region IS NOT NULL
            GROUP BY u.region, u.city
            HAVING user_count >= 5
            ORDER BY user_count DESC
            LIMIT 20
        ", $params);

        return [
            'growth_trends' => $userGrowth,
            'engagement_metrics' => $engagementMetrics,
            'cohort_analysis' => $cohortAnalysis,
            'geographic_distribution' => $geographicDistribution
        ];
    }

    /**
     * Get operational metrics
     */
    private function getOperationalMetrics($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        // Processing efficiency
        $processingEfficiency = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_applications,
                COUNT(CASE WHEN l.status = 'pending' THEN 1 END) as pending_applications,
                COUNT(CASE WHEN l.status = 'approved' THEN 1 END) as approved_applications,
                COUNT(CASE WHEN l.status = 'rejected' THEN 1 END) as rejected_applications,
                ROUND(AVG(CASE WHEN l.status = 'approved' THEN TIMESTAMPDIFF(HOUR, l.created_at, l.approved_at) END), 1) as avg_approval_hours,
                ROUND(AVG(CASE WHEN l.status = 'completed' THEN TIMESTAMPDIFF(HOUR, l.created_at, l.disbursed_at) END), 1) as avg_disbursement_hours
            FROM loans l
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", array_merge($params, [$dateRange]));

        // Delivery performance (for goods platform)
        $deliveryPerformance = $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT d.id) as total_deliveries,
                COUNT(CASE WHEN d.status = 'completed' THEN 1 END) as completed_deliveries,
                COUNT(CASE WHEN d.status = 'failed' THEN 1 END) as failed_deliveries,
                ROUND(AVG(CASE WHEN d.status = 'completed' THEN d.delivery_time_minutes END), 1) as avg_delivery_time,
                COUNT(DISTINCT d.assigned_agent_id) as active_agents
            FROM deliveries d
            {$whereClause}
            AND d.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", array_merge($params, [$dateRange]));

        // System performance
        $systemPerformance = $this->db->fetchOne("
            SELECT
                COUNT(CASE WHEN response_time < 1000 THEN 1 END) as fast_responses,
                COUNT(CASE WHEN response_time >= 1000 AND response_time < 3000 THEN 1 END) as medium_responses,
                COUNT(CASE WHEN response_time >= 3000 THEN 1 END) as slow_responses,
                ROUND(AVG(response_time), 2) as avg_response_time,
                COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_count,
                ROUND(COUNT(CASE WHEN status_code >= 400 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as error_rate
            FROM system_logs
            WHERE log_type = 'API_REQUEST'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$dateRange]);

        return [
            'processing_efficiency' => $processingEfficiency,
            'delivery_performance' => $deliveryPerformance,
            'system_performance' => $systemPerformance
        ];
    }

    /**
     * Generate predictive forecasts
     */
    private function generateForecasts($dateRange, $filters) {
        // Simple linear regression for forecasting
        $historicalData = $this->getHistoricalTrends($dateRange, $filters);

        $forecasts = [
            'next_month_loans' => $this->forecastLoans($historicalData),
            'next_month_revenue' => $this->forecastRevenue($historicalData),
            'default_probability' => $this->forecastDefaults($historicalData),
            'user_growth' => $this->forecastUserGrowth($historicalData)
        ];

        return $forecasts;
    }

    /**
     * Get historical trends for forecasting
     */
    private function getHistoricalTrends($dateRange, $filters) {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParams($filters, $dateRange);

        return $this->db->fetchAll("
            SELECT
                DATE(l.created_at) as date,
                COUNT(*) as daily_loans,
                COALESCE(SUM(l.loan_amount), 0) as daily_revenue,
                COUNT(CASE WHEN l.status = 'defaulted' THEN 1 END) as daily_defaults,
                COUNT(DISTINCT u.id) as new_users
            FROM loans l
            LEFT JOIN users u ON u.created_at = DATE(l.created_at)
            {$whereClause}
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(l.created_at)
            ORDER BY date ASC
        ", array_merge($params, [$dateRange]));
    }

    /**
     * Forecast loan volume using linear regression
     */
    private function forecastLoans($data) {
        if (count($data) < 7) return ['forecast' => null, 'confidence' => 'low'];

        $dailyLoans = array_column($data, 'daily_loans');
        $trend = $this->calculateLinearTrend($dailyLoans);

        $nextMonthDays = 30;
        $forecast = $trend['intercept'] + ($trend['slope'] * (count($data) + $nextMonthDays));

        return [
            'forecast' => max(0, round($forecast)),
            'confidence' => $trend['r_squared'] > 0.7 ? 'high' : ($trend['r_squared'] > 0.5 ? 'medium' : 'low'),
            'trend' => $trend['slope'] > 0 ? 'increasing' : 'decreasing'
        ];
    }

    /**
     * Forecast revenue using linear regression
     */
    private function forecastRevenue($data) {
        if (count($data) < 7) return ['forecast' => null, 'confidence' => 'low'];

        $dailyRevenue = array_column($data, 'daily_revenue');
        $trend = $this->calculateLinearTrend($dailyRevenue);

        $nextMonthDays = 30;
        $forecast = $trend['intercept'] + ($trend['slope'] * (count($data) + $nextMonthDays));

        return [
            'forecast' => max(0, round($forecast)),
            'confidence' => $trend['r_squared'] > 0.7 ? 'high' : ($trend['r_squared'] > 0.5 ? 'medium' : 'low'),
            'trend' => $trend['slope'] > 0 ? 'increasing' : 'decreasing'
        ];
    }

    /**
     * Forecast default probability
     */
    private function forecastDefaults($data) {
        if (count($data) < 7) return ['probability' => null, 'confidence' => 'low'];

        $totalLoans = array_sum(array_column($data, 'daily_loans'));
        $totalDefaults = array_sum(array_column($data, 'daily_defaults'));

        $recentPeriod = array_slice($data, -7);
        $recentDefaults = array_sum(array_column($recentPeriod, 'daily_defaults'));
        $recentLoans = array_sum(array_column($recentPeriod, 'daily_loans'));

        $recentDefaultRate = $recentLoans > 0 ? ($recentDefaults / $recentLoans) : 0;
        $historicalDefaultRate = $totalLoans > 0 ? ($totalDefaults / $totalLoans) : 0;

        return [
            'probability' => round($recentDefaultRate * 100, 2),
            'historical_average' => round($historicalDefaultRate * 100, 2),
            'trend' => $recentDefaultRate > $historicalDefaultRate ? 'worsening' : 'improving'
        ];
    }

    /**
     * Forecast user growth
     */
    private function forecastUserGrowth($data) {
        if (count($data) < 7) return ['forecast' => null, 'confidence' => 'low'];

        $dailyUsers = array_column($data, 'new_users');
        $trend = $this->calculateLinearTrend($dailyUsers);

        $nextMonthDays = 30;
        $forecast = $trend['intercept'] + ($trend['slope'] * (count($data) + $nextMonthDays));

        return [
            'forecast' => max(0, round($forecast)),
            'confidence' => $trend['r_squared'] > 0.7 ? 'high' : ($trend['r_squared'] > 0.5 ? 'medium' : 'low'),
            'trend' => $trend['slope'] > 0 ? 'growing' : 'declining'
        ];
    }

    /**
     * Calculate linear trend (slope, intercept, R²)
     */
    private function calculateLinearTrend($data) {
        $n = count($data);
        if ($n < 2) return ['slope' => 0, 'intercept' => 0, 'r_squared' => 0];

        $sumX = array_sum(range(0, $n - 1));
        $sumY = array_sum($data);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $i * $data[$i];
            $sumX2 += $i * $i;
            $sumY2 += $data[$i] * $data[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Calculate R²
        $meanY = $sumY / $n;
        $ssTotal = 0;
        $ssResidual = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $i;
            $ssTotal += pow($data[$i] - $meanY, 2);
            $ssResidual += pow($data[$i] - $predicted, 2);
        }

        $rSquared = $ssTotal > 0 ? 1 - ($ssResidual / $ssTotal) : 0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared
        ];
    }

    /**
     * Build WHERE clause for filters
     */
    private function buildWhereClause($filters) {
        $whereClause = "WHERE 1=1";

        if ($this->tenantId) {
            $whereClause .= " AND (l.tenant_id = {$this->tenantId} OR l.tenant_id IS NULL)";
        }

        if (!empty($filters['lender_id'])) {
            $whereClause .= " AND l.lender_id = " . (int)$filters['lender_id'];
        }

        if (!empty($filters['borrower_id'])) {
            $whereClause .= " AND l.borrower_id = " . (int)$filters['borrower_id'];
        }

        if (!empty($filters['loan_status'])) {
            $whereClause .= " AND l.status = '" . $this->db->escape($filters['loan_status']) . "'";
        }

        if (!empty($filters['user_role'])) {
            $whereClause .= " AND u.role = '" . $this->db->escape($filters['user_role']) . "'";
        }

        return $whereClause;
    }

    /**
     * Build parameters array
     */
    private function buildParams($filters, $dateRange) {
        $params = [];

        if (!empty($filters['lender_id'])) {
            $params[] = $filters['lender_id'];
        }

        if (!empty($filters['borrower_id'])) {
            $params[] = $filters['borrower_id'];
        }

        if (!empty($filters['loan_status'])) {
            $params[] = $filters['loan_status'];
        }

        if (!empty($filters['user_role'])) {
            $params[] = $filters['user_role'];
        }

        return $params;
    }

    /**
     * Cache data
     */
    private function cacheData($key, $data) {
        // In a real implementation, use Redis or Memcached
        $_SESSION['analytics_cache'][$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Get cached data
     */
    private function getCachedData($key) {
        if (isset($_SESSION['analytics_cache'][$key])) {
            $cached = $_SESSION['analytics_cache'][$key];
            if (time() - $cached['timestamp'] < $this->cacheTimeout) {
                return $cached['data'];
            }
        }
        return null;
    }

    /**
     * Generate custom report
     */
    public function generateCustomReport($reportConfig) {
        $reportType = $reportConfig['type'] ?? 'standard';
        $metrics = $reportConfig['metrics'] ?? [];
        $dateRange = $reportConfig['date_range'] ?? 30;
        $filters = $reportConfig['filters'] ?? [];

        switch ($reportType) {
            case 'executive_summary':
                return $this->generateExecutiveSummary($dateRange, $filters);
            case 'regional_performance':
                return $this->generateRegionalReport($dateRange, $filters);
            case 'user_behavior':
                return $this->generateUserBehaviorReport($dateRange, $filters);
            case 'financial_audit':
                return $this->generateFinancialAuditReport($dateRange, $filters);
            default:
                return $this->generateStandardReport($metrics, $dateRange, $filters);
        }
    }

    /**
     * Generate executive summary report
     */
    private function generateExecutiveSummary($dateRange, $filters) {
        $overview = $this->generateBusinessOverview($dateRange, $filters);

        return [
            'title' => 'Executive Summary Report',
            'period' => "Last {$dateRange} days",
            'key_metrics' => [
                'total_revenue' => $overview['summary_metrics']['total_repaid'],
                'profit_margin' => $overview['summary_metrics']['roi_percentage'],
                'portfolio_size' => $overview['summary_metrics']['active_portfolio'],
                'completion_rate' => $overview['summary_metrics']['completion_rate'],
                'default_rate' => $overview['summary_metrics']['default_rate']
            ],
            'revenue_trend' => array_slice($overview['revenue_analytics']['monthly_trends'], 0, 6),
            'risk_summary' => $overview['risk_assessment']['early_warning_indicators'],
            'growth_indicators' => $overview['forecasting'],
            'recommendations' => $this->generateExecutiveRecommendations($overview)
        ];
    }

    /**
     * Generate executive recommendations
     */
    private function generateExecutiveRecommendations($overview) {
        $recommendations = [];

        // Revenue recommendations
        if ($overview['summary_metrics']['roi_percentage'] < 15) {
            $recommendations[] = [
                'category' => 'Revenue',
                'priority' => 'high',
                'title' => 'Interest Rate Optimization',
                'description' => 'Current ROI is below target. Consider adjusting interest rates based on risk profiles.'
            ];
        }

        // Risk recommendations
        if ($overview['summary_metrics']['default_rate'] > 10) {
            $recommendations[] = [
                'category' => 'Risk',
                'priority' => 'critical',
                'title' => 'High Default Rate Alert',
                'description' => 'Default rate exceeds 10%. Implement stricter credit scoring and enhanced monitoring.'
            ];
        }

        // Portfolio recommendations
        if ($overview['summary_metrics']['completion_rate'] < 85) {
            $recommendations[] = [
                'category' => 'Operations',
                'priority' => 'medium',
                'title' => 'Process Improvement',
                'description' => 'Low completion rate indicates operational issues. Review loan processing and approval workflows.'
            ];
        }

        return $recommendations;
    }

    /**
     * Export report to different formats
     */
    public function exportReport($reportData, $format = 'json') {
        switch ($format) {
            case 'json':
                return json_encode($reportData, JSON_PRETTY_PRINT);
            case 'csv':
                return $this->exportToCSV($reportData);
            case 'pdf':
                return $this->exportToPDF($reportData);
            case 'excel':
                return $this->exportToExcel($reportData);
            default:
                return $reportData;
        }
    }

    /**
     * Export to CSV format
     */
    private function exportToCSV($data) {
        $csv = '';
        $headers = [];

        // Extract headers from first level of data
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $headers = array_merge($headers, array_keys($value));
            } else {
                $headers[] = $key;
            }
        }

        $csv .= implode(',', $headers) . "\n";

        // This is a simplified CSV export
        // In a real implementation, you'd traverse the data structure properly
        return $csv;
    }

    /**
     * Export to PDF format (placeholder)
     */
    private function exportToPDF($data) {
        // In a real implementation, use a PDF library like TCPDF or FPDF
        return "PDF export functionality would be implemented here";
    }

    /**
     * Export to Excel format (placeholder)
     */
    private function exportToExcel($data) {
        // In a real implementation, use a library like PHPExcel
        return "Excel export functionality would be implemented here";
    }
}
?>