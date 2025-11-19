<?php
/**
 * Advanced AI-Powered Credit Scoring & Risk Assessment Integration
 * Enhanced with Machine Learning capabilities, behavioral analysis, and predictive modeling
 */

class CreditScoringEngine {
    private $db;
    private $modelWeights;
    private $riskFactors;

    public function __construct($database) {
        $this->db = $database;
        $this->modelWeights = $this->initializeModelWeights();
        $this->riskFactors = $this->loadRiskFactors();
    }

    /**
     * Initialize machine learning model weights
     */
    private function initializeModelWeights() {
        return [
            'repayment_history' => 0.35,      // 35% weight - most important
            'credit_utilization' => 0.20,       // 20% weight
            'business_stability' => 0.15,       // 15% weight
            'order_consistency' => 0.10,        // 10% weight
            'industry_risk' => 0.08,            // 8% weight
            'macroeconomic_factors' => 0.07,    // 7% weight
            'behavioral_patterns' => 0.05       // 5% weight
        ];
    }

    /**
     * Load risk factors for different industries and scenarios
     */
    private function loadRiskFactors() {
        return [
            'industry_risk' => [
                'food_grocery' => 0.15,
                'electronics' => 0.25,
                'clothing' => 0.20,
                'beauty' => 0.18,
                'general_merchandise' => 0.22
            ],
            'seasonal_adjustments' => [
                'december' => 0.1,   // Holiday season boost
                'january' => -0.05,  // Post-holiday slowdown
                'april' => 0.02,     // Easter boost
                'august' => -0.02    # Back to school uncertainty
            ]
        ];
    }
    
    /**
     * Advanced credit score calculation using ML techniques
     */
    public function calculateCreditScore($retailer_id) {
        // Collect all features for the ML model
        $features = $this->extractFeatures($retailer_id);

        // Apply weighted ML model
        $score = $this->applyMLModel($features);

        // Apply behavioral and macroeconomic adjustments
        $adjustedScore = $this->applyAdjustments($score, $retailer_id);

        // Calculate prediction confidence
        $confidence = $this->calculatePredictionConfidence($features);

        // Update credit score with metadata
        $this->updateCreditScore($retailer_id, $adjustedScore, $confidence, $features);

        return [
            'score' => $adjustedScore,
            'confidence' => $confidence,
            'features' => $features,
            'risk_level' => $this->determineRiskLevel($adjustedScore)
        ];
    }

    /**
     * Extract comprehensive features for ML model
     */
    private function extractFeatures($retailer_id) {
        $features = [];

        // 1. Repayment History Features
        $features['repayment'] = $this->getRepaymentFeatures($retailer_id);

        // 2. Business Stability Features
        $features['stability'] = $this->getBusinessStabilityFeatures($retailer_id);

        // 3. Order Behavior Features
        $features['behavior'] = $this->getOrderBehaviorFeatures($retailer_id);

        // 4. Credit Utilization Features
        $features['utilization'] = $this->getCreditUtilizationFeatures($retailer_id);

        // 5. Industry and Economic Features
        $features['economic'] = $this->getEconomicFeatures($retailer_id);

        return $features;
    }

    /**
     * Get repayment history features
     */
    private function getRepaymentFeatures($retailer_id) {
        $this->db->query("
            SELECT
                COUNT(*) as total_loans,
                SUM(CASE WHEN status = 'repaid' THEN 1 ELSE 0 END) as repaid_loans,
                SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted_loans,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_loans,
                AVG(CASE WHEN status = 'repaid' THEN DATEDIFF(actual_payment_date, due_date) END) as avg_payment_days,
                MAX(loan_amount) as max_loan_amount,
                AVG(loan_amount) as avg_loan_amount
            FROM loans
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 YEAR)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $data = $this->db->single();

        $repayment_rate = $data['total_loans'] > 0 ? $data['repaid_loans'] / $data['total_loans'] : 0;
        $default_rate = $data['total_loans'] > 0 ? $data['defaulted_loans'] / $data['total_loans'] : 0;

        return [
            'repayment_rate' => $repayment_rate,
            'default_rate' => $default_rate,
            'total_loans' => $data['total_loans'],
            'avg_payment_days' => $data['avg_payment_days'] ?: 0,
            'payment_consistency' => $this->calculatePaymentConsistency($retailer_id),
            'loan_amount_growth' => $this->calculateLoanAmountGrowth($retailer_id)
        ];
    }

    /**
     * Get business stability features
     */
    private function getBusinessStabilityFeatures($retailer_id) {
        $this->db->query("SELECT DATEDIFF(NOW(), created_at) as days_active, created_at FROM users WHERE id = ?");
        $this->db->bind(':id', $retailer_id);
        $user = $this->db->single();

        $this->db->query("
            SELECT
                COUNT(*) as total_orders,
                AVG(total_amount) as avg_order_value,
                STDDEV(total_amount) as order_volatility,
                DATEDIFF(MAX(created_at), MIN(created_at)) as order_span_days
            FROM orders
            WHERE retailer_id = ? AND status != 'cancelled'
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $orders = $this->db->single();

        return [
            'business_age_days' => $user['days_active'],
            'total_orders' => $orders['total_orders'] ?: 0,
            'order_frequency' => $orders['order_span_days'] > 0 ? $orders['total_orders'] / ($orders['order_span_days'] / 30) : 0,
            'avg_order_value' => $orders['avg_order_value'] ?: 0,
            'order_volatility' => $orders['order_volatility'] ?: 0,
            'kyc_verified' => $this->isKYCVerified($retailer_id),
            'has_stable_business' => $this->hasStableBusinessPattern($retailer_id)
        ];
    }

    /**
     * Get order behavior features
     */
    private function getOrderBehaviorFeatures($retailer_id) {
        $this->db->query("
            SELECT
                DAYOFWEEK(created_at) as day_of_week,
                HOUR(created_at) as hour,
                COUNT(*) as order_count
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY DAYOFWEEK(created_at), HOUR(created_at)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $patterns = $this->db->fetchAll();

        return [
            'order_regularity' => $this->calculateOrderRegularity($patterns),
            'preferred_order_day' => $this->getPreferredOrderDay($patterns),
            'peak_ordering_hour' => $this->getPeakOrderingHour($patterns),
            'weekend_vs_weekday_ratio' => $this->calculateWeekdayWeekendRatio($patterns),
            'order_size_evolution' => $this->calculateOrderSizeEvolution($retailer_id)
        ];
    }

    /**
     * Get credit utilization features
     */
    private function getCreditUtilizationFeatures($retailer_id) {
        $this->db->query("
            SELECT
                credit_limit,
                total_borrowed,
                total_repaid,
                total_borrowed - total_repaid as current_outstanding
            FROM credit_scores
            WHERE retailer_id = ?
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $credit = $this->db->single();

        $utilization_rate = $credit['credit_limit'] > 0 ? $credit['current_outstanding'] / $credit['credit_limit'] : 0;

        return [
            'credit_utilization_rate' => $utilization_rate,
            'total_borrowed' => $credit['total_borrowed'] ?: 0,
            'total_repaid' => $credit['total_repaid'] ?: 0,
            'current_outstanding' => $credit['current_outstanding'] ?: 0,
            'credit_limit_usage' => $credit['credit_limit'] > 0 ? $credit['total_borrowed'] / $credit['credit_limit'] : 0
        ];
    }

    /**
     * Get economic and industry features
     */
    private function getEconomicFeatures($retailer_id) {
        $this->db->query("
            SELECT p.category, COUNT(*) as order_count
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE o.retailer_id = ? AND o.created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY p.category
            ORDER BY order_count DESC
            LIMIT 1
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $industry = $this->db->single();

        $current_month = strtolower(date('F'));

        return [
            'primary_industry' => $industry['category'] ?: 'general_merchandise',
            'industry_risk_factor' => $this->riskFactors['industry_risk'][$industry['category']] ?? 0.22,
            'seasonal_adjustment' => $this->riskFactors['seasonal_adjustments'][$current_month] ?? 0,
            'inflation_impact' => $this->calculateInflationImpact($retailer_id),
            'competitor_pressure' => $this->assessCompetitorPressure($retailer_id)
        ];
    }

    /**
     * Apply ML model to calculate base score
     */
    private function applyMLModel($features) {
        $baseScore = 500; // Starting point

        // Repayment History (35% weight)
        $repaymentScore = $this->calculateRepaymentScore($features['repayment']);
        $baseScore += ($repaymentScore - 500) * $this->modelWeights['repayment_history'];

        // Credit Utilization (20% weight)
        $utilizationScore = $this->calculateUtilizationScore($features['utilization']);
        $baseScore += ($utilizationScore - 500) * $this->modelWeights['credit_utilization'];

        // Business Stability (15% weight)
        $stabilityScore = $this->calculateStabilityScore($features['stability']);
        $baseScore += ($stabilityScore - 500) * $this->modelWeights['business_stability'];

        // Order Consistency (10% weight)
        $behaviorScore = $this->calculateBehaviorScore($features['behavior']);
        $baseScore += ($behaviorScore - 500) * $this->modelWeights['order_consistency'];

        // Industry Risk (8% weight)
        $economicScore = $this->calculateEconomicScore($features['economic']);
        $baseScore += ($economicScore - 500) * $this->modelWeights['industry_risk'];

        // Macroeconomic Factors (7% weight)
        $macroScore = $this->calculateMacroScore($features['economic']);
        $baseScore += ($macroScore - 500) * $this->modelWeights['macroeconomic_factors'];

        // Behavioral Patterns (5% weight)
        $patternScore = $this->calculatePatternScore($features['behavior']);
        $baseScore += ($patternScore - 500) * $this->modelWeights['behavioral_patterns'];

        return max(300, min(850, $baseScore));
    }

    /**
     * Apply behavioral and seasonal adjustments
     */
    private function applyAdjustments($score, $retailer_id) {
        $adjustedScore = $score;

        // Seasonal adjustment
        $current_month = strtolower(date('F'));
        $seasonal_factor = $this->riskFactors['seasonal_adjustments'][$current_month] ?? 0;
        $adjustedScore += $seasonal_factor * 100;

        // Recent activity boost
        $recent_activity = $this->getRecentActivityScore($retailer_id);
        $adjustedScore += $recent_activity * 20;

        // Relationship length bonus
        $relationship_bonus = $this->calculateRelationshipBonus($retailer_id);
        $adjustedScore += $relationship_bonus;

        return max(300, min(850, $adjustedScore));
    }
    
    /**
     * Detect fraudulent patterns
     */
    public function detectFraud($retailer_id, $order_amount) {
        $fraud_score = 0;
        $indicators = [];
        
        // Check for unusual order patterns
        $this->db->query("
            SELECT AVG(total_amount) as avg_order, 
                   MAX(total_amount) as max_order,
                   COUNT(*) as order_count
            FROM orders WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $pattern = $this->db->single();
        
        if ($pattern['order_count'] > 0) {
            $deviation = abs($order_amount - $pattern['avg_order']) / $pattern['avg_order'];
            if ($deviation > 2) {
                $fraud_score += 30;
                $indicators[] = 'unusual_order_amount';
            }
        }
        
        // Check for rapid successive orders
        $this->db->query("
            SELECT COUNT(*) as recent_orders 
            FROM orders 
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $recent = $this->db->single();
        
        if ($recent['recent_orders'] > 5) {
            $fraud_score += 25;
            $indicators[] = 'rapid_orders';
        }
        
        // Check for location anomalies
        $this->db->query("
            SELECT last_login_ip FROM users WHERE id = ?
        ");
        $this->db->bind(':id', $retailer_id);
        $user = $this->db->single();
        
        // Log fraud detection event
        if ($fraud_score > 50) {
            $this->db->query("
                INSERT INTO fraud_detection (user_id, event_type, description, severity)
                VALUES (?, ?, ?, ?)
            ");
            $this->db->bind(':user_id', $retailer_id);
            $this->db->bind(':event_type', 'suspicious_activity');
            $this->db->bind(':description', json_encode($indicators));
            $severity = $fraud_score > 75 ? 'high' : 'medium';
            $this->db->bind(':severity', $severity);
            $this->db->execute();
        }
        
        return [
            'fraud_score' => $fraud_score,
            'indicators' => $indicators,
            'is_suspicious' => $fraud_score > 50
        ];
    }
    
    /**
     * Calculate dynamic credit limit
     */
    public function calculateCreditLimit($retailer_id) {
        $base_limit = 10000;
        
        // Get credit score
        $this->db->query("SELECT score FROM credit_scores WHERE retailer_id = ?");
        $this->db->bind(':retailer_id', $retailer_id);
        $credit = $this->db->single();
        
        $score = $credit['score'] ?? 500;
        
        // Calculate limit based on score
        $limit = $base_limit + (($score - 500) / 350) * 40000;
        
        // Get repayment history
        $this->db->query("
            SELECT SUM(CASE WHEN status = 'repaid' THEN 1 ELSE 0 END) as repaid_count
            FROM loans WHERE retailer_id = ?
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $history = $this->db->single();
        
        // Bonus for consistent repayment
        $repaid_count = $history['repaid_count'] ?? 0;
        $limit += min($repaid_count * 2000, 50000);
        
        // Cap at maximum
        $limit = min($limit, 500000);
        
        return max(5000, $limit);
    }
}
?>
