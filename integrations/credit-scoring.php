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
    // ========== HELPER METHODS FOR ML SCORING ==========

    /**
     * Calculate payment consistency score
     */
    private function calculatePaymentConsistency($retailer_id) {
        $this->db->query("
            SELECT DATEDIFF(actual_payment_date, due_date) as payment_delay
            FROM repayment_schedule rs
            JOIN payments p ON rs.id = p.repayment_id
            WHERE rs.loan_id IN (SELECT id FROM loans WHERE retailer_id = ?)
            AND rs.status = 'completed'
            ORDER BY payment_delay
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $delays = $this->db->fetchAll();

        if (count($delays) < 2) return 0.5;

        $variance = $this->calculateVariance(array_column($delays, 'payment_delay'));
        $max_variance = 144; // 12 days squared
        return max(0, 1 - ($variance / $max_variance));
    }

    /**
     * Calculate loan amount growth trend
     */
    private function calculateLoanAmountGrowth($retailer_id) {
        $this->db->query("
            SELECT loan_amount, created_at
            FROM loans
            WHERE retailer_id = ? AND status = 'repaid'
            ORDER BY created_at DESC
            LIMIT 6
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $loans = $this->db->fetchAll();

        if (count($loans) < 2) return 0;

        $first = end($loans);
        $last = reset($loans);

        $growth = ($last['loan_amount'] - $first['loan_amount']) / $first['loan_amount'];
        return max(-1, min(1, $growth));
    }

    /**
     * Check if KYC is verified
     */
    private function isKYCVerified($retailer_id) {
        $this->db->query("SELECT kyc_verified FROM users WHERE id = ?");
        $this->db->bind(':id', $retailer_id);
        $result = $this->db->single();
        return $result['kyc_verified'] ?? false;
    }

    /**
     * Check for stable business patterns
     */
    private function hasStableBusinessPattern($retailer_id) {
        $this->db->query("
            SELECT DATE(created_at) as order_date, COUNT(*) as daily_orders
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY DATE(created_at)
            HAVING daily_orders > 0
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $daily_activity = $this->db->fetchAll();

        $active_days = count($daily_activity);
        $total_days = 90; // 3 months

        return ($active_days / $total_days) > 0.3; // Active at least 30% of days
    }

    /**
     * Calculate order regularity
     */
    private function calculateOrderRegularity($patterns) {
        if (empty($patterns)) return 0;

        $entropy = 0;
        $total_orders = array_sum(array_column($patterns, 'order_count'));

        foreach ($patterns as $pattern) {
            $probability = $pattern['order_count'] / $total_orders;
            $entropy -= $probability * log($probability + 1e-10);
        }

        $max_entropy = log(168); // 24 hours * 7 days
        return 1 - ($entropy / $max_entropy);
    }

    /**
     * Get preferred order day
     */
    private function getPreferredOrderDay($patterns) {
        if (empty($patterns)) return null;

        $day_counts = array_fill(1, 7, 0);
        foreach ($patterns as $pattern) {
            $day_counts[$pattern['day_of_week']] += $pattern['order_count'];
        }

        return array_keys($day_counts, max($day_counts))[0];
    }

    /**
     * Get peak ordering hour
     */
    private function getPeakOrderingHour($patterns) {
        if (empty($patterns)) return null;

        $hour_counts = array_fill(0, 23, 0);
        foreach ($patterns as $pattern) {
            $hour_counts[$pattern['hour']] += $pattern['order_count'];
        }

        return array_keys($hour_counts, max($hour_counts))[0];
    }

    /**
     * Calculate weekend vs weekday ratio
     */
    private function calculateWeekdayWeekendRatio($patterns) {
        if (empty($patterns)) return 1;

        $weekend_orders = 0;
        $weekday_orders = 0;

        foreach ($patterns as $pattern) {
            if ($pattern['day_of_week'] == 1 || $pattern['day_of_week'] == 7) {
                $weekend_orders += $pattern['order_count'];
            } else {
                $weekday_orders += $pattern['order_count'];
            }
        }

        return $weekday_orders > 0 ? $weekend_orders / $weekday_orders : 1;
    }

    /**
     * Calculate order size evolution
     */
    private function calculateOrderSizeEvolution($retailer_id) {
        $this->db->query("
            SELECT AVG(total_amount) as avg_size, created_at
            FROM orders
            WHERE retailer_id = ? AND status != 'cancelled'
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY created_at DESC
            LIMIT 6
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $monthly = $this->db->fetchAll();

        if (count($monthly) < 2) return 0;

        $first = end($monthly);
        $last = reset($monthly);

        return $first['avg_size'] > 0 ? ($last['avg_size'] - $first['avg_size']) / $first['avg_size'] : 0;
    }

    /**
     * Calculate variance for consistency measurements
     */
    private function calculateVariance($values) {
        if (empty($values)) return 0;

        $mean = array_sum($values) / count($values);
        $squared_diffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values);

        return array_sum($squared_diffs) / count($values);
    }

    /**
     * Individual scoring methods for each feature category
     */
    private function calculateRepaymentScore($repayment) {
        $score = 500;

        // Perfect repayment rate gives +100 points
        $score += $repayment['repayment_rate'] * 100;

        // Default penalty
        $score -= $repayment['default_rate'] * 200;

        // Consistency bonus
        $score += $repayment['payment_consistency'] * 50;

        // Growth bonus
        $score += $repayment['loan_amount_growth'] * 30;

        return max(300, min(700, $score));
    }

    private function calculateUtilizationScore($utilization) {
        $score = 500;

        // Low utilization is good (< 30%)
        if ($utilization['credit_utilization_rate'] < 0.3) {
            $score += 50;
        } elseif ($utilization['credit_utilization_rate'] > 0.8) {
            $score -= 100;
        }

        // Good repayment history bonus
        if ($utilization['total_repaid'] > 0) {
            $repayment_rate = $utilization['total_repaid'] / $utilization['total_borrowed'];
            $score += $repayment_rate * 50;
        }

        return max(300, min(700, $score));
    }

    private function calculateStabilityScore($stability) {
        $score = 500;

        // Business age bonus
        if ($stability['business_age_days'] > 365) {
            $score += 50;
        } elseif ($stability['business_age_days'] > 90) {
            $score += 25;
        }

        // Order frequency bonus
        if ($stability['order_frequency'] > 10) {
            $score += 30;
        }

        // KYC verification bonus
        if ($stability['kyc_verified']) {
            $score += 40;
        }

        // Stable business pattern bonus
        if ($stability['has_stable_business']) {
            $score += 30;
        }

        return max(300, min(700, $score));
    }

    private function calculateBehaviorScore($behavior) {
        $score = 500;

        // Regular ordering bonus
        $score += $behavior['order_regularity'] * 50;

        // Consistent timing bonus
        if ($behavior['order_regularity'] > 0.7) {
            $score += 30;
        }

        // Growth bonus
        $score += $behavior['order_size_evolution'] * 20;

        return max(300, min(700, $score));
    }

    private function calculateEconomicScore($economic) {
        $score = 500;

        // Industry risk adjustment
        $industry_penalty = $economic['industry_risk_factor'] * 100;
        $score -= $industry_penalty;

        // Seasonal adjustment
        $score += $economic['seasonal_adjustment'] * 100;

        return max(300, min(700, $score));
    }

    private function calculateMacroScore($economic) {
        $score = 500;

        // Inflation impact adjustment
        $score -= $economic['inflation_impact'] * 30;

        // Competitor pressure adjustment
        $score -= $economic['competitor_pressure'] * 20;

        return max(300, min(700, $score));
    }

    private function calculatePatternScore($behavior) {
        $score = 500;

        // Weekend vs weekday pattern normalcy
        if ($behavior['weekend_vs_weekday_ratio'] > 0.2 && $behavior['weekend_vs_weekday_ratio'] < 3) {
            $score += 25;
        }

        // Peak hour normalcy (9-17 is business hours)
        if ($behavior['peak_ordering_hour'] >= 9 && $behavior['peak_ordering_hour'] <= 17) {
            $score += 25;
        }

        return max(300, min(700, $score));
    }

    private function calculateInflationImpact($retailer_id) {
        // Simplified inflation impact calculation
        $this->db->query("
            SELECT AVG(total_amount) as avg_order
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $recent = $this->db->single();

        $this->db->query("
            SELECT AVG(total_amount) as avg_order
            FROM orders
            WHERE retailer_id = ?
            AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 12 MONTH) AND DATE_SUB(NOW(), INTERVAL 6 MONTH)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $historical = $this->db->single();

        if ($historical['avg_order'] > 0) {
            return ($recent['avg_order'] - $historical['avg_order']) / $historical['avg_order'];
        }

        return 0;
    }

    private function assessCompetitorPressure($retailer_id) {
        // Simplified competitor pressure based on order frequency changes
        $this->db->query("
            SELECT COUNT(*) as order_count
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $recent = $this->db->single();

        $this->db->query("
            SELECT COUNT(*) as order_count
            FROM orders
            WHERE retailer_id = ?
            AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 6 MONTH) AND DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $previous = $this->db->single();

        if ($previous['order_count'] > 0) {
            $decline_rate = ($previous['order_count'] - $recent['order_count']) / $previous['order_count'];
            return max(0, $decline_rate);
        }

        return 0;
    }

    private function getRecentActivityScore($retailer_id) {
        $this->db->query("
            SELECT COUNT(*) as recent_actions
            FROM analytics_events
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $this->db->bind(':user_id', $retailer_id);
        $activity = $this->db->single();

        return min(1, $activity['recent_actions'] / 20); // Normalize to 0-1
    }

    private function calculateRelationshipBonus($retailer_id) {
        $this->db->query("SELECT DATEDIFF(NOW(), created_at) as days_active FROM users WHERE id = ?");
        $this->db->bind(':id', $retailer_id);
        $user = $this->db->single();

        $years = $user['days_active'] / 365;
        return min(50, $years * 10); // Max 50 points bonus
    }

    private function calculatePredictionConfidence($features) {
        $confidence = 0.5; // Base confidence

        // More data = higher confidence
        if ($features['repayment']['total_loans'] > 5) $confidence += 0.2;
        if ($features['stability']['business_age_days'] > 180) $confidence += 0.15;
        if ($features['stability']['total_orders'] > 10) $confidence += 0.1;
        if ($features['behavior']['order_regularity'] > 0.5) $confidence += 0.05;

        return min(1.0, $confidence);
    }

    private function determineRiskLevel($score) {
        if ($score >= 750) return 'very_low';
        if ($score >= 700) return 'low';
        if ($score >= 650) return 'medium_low';
        if ($score >= 600) return 'medium';
        if ($score >= 550) return 'medium_high';
        if ($score >= 500) return 'high';
        return 'very_high';
    }

    private function updateCreditScore($retailer_id, $score, $confidence, $features) {
        // Update main credit score
        $this->db->query("
            INSERT INTO credit_scores (retailer_id, score, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE score = ?, updated_at = NOW()
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $this->db->bind(':score', $score);
        $this->db->bind(':score', $score);
        $this->db->execute();

        // Update risk assessment
        $this->db->query("
            INSERT INTO risk_assessments (retailer_id, risk_score, risk_level, fraud_indicators, assessment_date, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                risk_score = ?,
                risk_level = ?,
                fraud_indicators = ?,
                assessment_date = NOW(),
                updated_at = NOW()
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $this->db->bind(':risk_score', 850 - $score); // Inverse risk score
        $this->db->bind(':risk_level', $this->determineRiskLevel($score));
        $this->db->bind(':fraud_indicators', json_encode($features));
        $this->db->bind(':risk_score', 850 - $score);
        $this->db->bind(':risk_level', $this->determineRiskLevel($score));
        $this->db->bind(':fraud_indicators', json_encode($features));
        $this->db->execute();
    }

    // ========== ENHANCED FRAUD DETECTION SYSTEM ==========

    /**
     * Advanced fraud detection with ML capabilities
     */
    public function detectFraud($retailer_id, $order_amount = null, $context = []) {
        $fraud_score = 0;
        $indicators = [];
        $risk_factors = [];

        // 1. Transaction Pattern Analysis
        $transaction_analysis = $this->analyzeTransactionPatterns($retailer_id, $order_amount);
        $fraud_score += $transaction_analysis['score'];
        $indicators = array_merge($indicators, $transaction_analysis['indicators']);

        // 2. Behavioral Analysis
        $behavioral_analysis = $this->analyzeBehavioralPatterns($retailer_id);
        $fraud_score += $behavioral_analysis['score'];
        $indicators = array_merge($indicators, $behavioral_analysis['indicators']);

        // 3. Device and Location Analysis
        $device_analysis = $this->analyzeDevicePatterns($retailer_id, $context);
        $fraud_score += $device_analysis['score'];
        $indicators = array_merge($indicators, $device_analysis['indicators']);

        // 4. Velocity Checks
        $velocity_analysis = $this->analyzeVelocityPatterns($retailer_id);
        $fraud_score += $velocity_analysis['score'];
        $indicators = array_merge($indicators, $velocity_analysis['indicators']);

        // 5. Network Analysis
        $network_analysis = $this->analyzeNetworkPatterns($retailer_id);
        $fraud_score += $network_analysis['score'];
        $indicators = array_merge($indicators, $network_analysis['indicators']);

        // Determine overall risk level
        $risk_level = $this->calculateFraudRiskLevel($fraud_score);

        // Log high-risk events
        if ($fraud_score > 50) {
            $this->logFraudEvent($retailer_id, $fraud_score, $indicators, $risk_level, $context);
        }

        // Trigger automated responses for critical cases
        if ($fraud_score > 80) {
            $this->triggerFraudResponse($retailer_id, $fraud_score, $indicators);
        }

        return [
            'fraud_score' => min(100, $fraud_score),
            'risk_level' => $risk_level,
            'indicators' => $indicators,
            'is_suspicious' => $fraud_score > 50,
            'requires_review' => $fraud_score > 70,
            'auto_reject' => $fraud_score > 85,
            'analysis' => [
                'transaction' => $transaction_analysis,
                'behavioral' => $behavioral_analysis,
                'device' => $device_analysis,
                'velocity' => $velocity_analysis,
                'network' => $network_analysis
            ]
        ];
    }

    /**
     * Analyze transaction patterns for fraud indicators
     */
    private function analyzeTransactionPatterns($retailer_id, $order_amount) {
        $score = 0;
        $indicators = [];

        // Get recent transaction history
        $this->db->query("
            SELECT
                total_amount,
                created_at,
                COUNT(*) as order_count,
                AVG(total_amount) as avg_amount,
                STDDEV(total_amount) as amount_stddev
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $recent = $this->db->single();

        if ($recent['order_count'] > 0) {
            // Unusually large order amount
            if ($order_amount && $recent['avg_amount'] > 0) {
                $deviation = abs($order_amount - $recent['avg_amount']) / $recent['avg_amount'];
                if ($deviation > 3) {
                    $score += 35;
                    $indicators[] = 'unusual_large_order';
                } elseif ($deviation > 2) {
                    $score += 20;
                    $indicators[] = 'suspicious_order_size';
                }
            }

            // Round number orders (potential structuring)
            if ($order_amount && $order_amount % 1000 == 0) {
                $score += 15;
                $indicators[] = 'round_number_order';
            }

            // High transaction frequency
            if ($recent['order_count'] > 50) {
                $score += 25;
                $indicators[] = 'high_frequency_transactions';
            }

            // Consistent transaction amounts (potential automation)
            if ($recent['amount_stddev'] < 100 && $recent['order_count'] > 10) {
                $score += 20;
                $indicators[] = 'consistent_transaction_amounts';
            }
        }

        return ['score' => $score, 'indicators' => $indicators];
    }

    /**
     * Analyze behavioral patterns
     */
    private function analyzeBehavioralPatterns($retailer_id) {
        $score = 0;
        $indicators = [];

        // Unusual timing patterns
        $this->db->query("
            SELECT
                HOUR(created_at) as hour,
                DAYOFWEEK(created_at) as day_of_week,
                COUNT(*) as order_count
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY HOUR(created_at), DAYOFWEEK(created_at)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $timing = $this->db->fetchAll();

        $late_night_orders = 0;
        $early_morning_orders = 0;

        foreach ($timing as $time) {
            if ($time['hour'] < 6 || $time['hour'] > 23) {
                $late_night_orders += $time['order_count'];
            }
        }

        if ($late_night_orders > 5) {
            $score += 20;
            $indicators[] = 'unusual_timing_patterns';
        }

        // Sudden change in order behavior
        $this->db->query("
            SELECT
                COUNT(*) as current_week_orders,
                AVG(total_amount) as current_week_avg
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $current = $this->db->single();

        $this->db->query("
            SELECT
                COUNT(*) as previous_week_orders,
                AVG(total_amount) as previous_week_avg
            FROM orders
            WHERE retailer_id = ?
            AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 WEEK) AND DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $previous = $this->db->single();

        if ($previous['previous_week_orders'] > 0) {
            $order_change = ($current['current_week_orders'] - $previous['previous_week_orders']) / $previous['previous_week_orders'];
            if ($order_change > 5) { // 500% increase
                $score += 30;
                $indicators[] = 'sudden_behavior_change';
            }
        }

        return ['score' => $score, 'indicators' => $indicators];
    }

    /**
     * Analyze device and location patterns
     */
    private function analyzeDevicePatterns($retailer_id, $context) {
        $score = 0;
        $indicators = [];

        // Multiple devices in short time
        $this->db->query("
            SELECT COUNT(DISTINCT device_fingerprint) as device_count
            FROM sessions
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $this->db->bind(':user_id', $retailer_id);
        $devices = $this->db->single();

        if ($devices['device_count'] > 3) {
            $score += 25;
            $indicators[] = 'multiple_devices';
        }

        // IP address changes
        $this->db->query("
            SELECT COUNT(DISTINCT ip_address) as ip_count
            FROM sessions
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $this->db->bind(':user_id', $retailer_id);
        $ips = $this->db->single();

        if ($ips['ip_count'] > 2) {
            $score += 20;
            $indicators[] = 'multiple_ip_addresses';
        }

        // Geographic anomalies (simplified)
        if (isset($context['ip_address'])) {
            $this->db->query("SELECT last_login_ip FROM users WHERE id = ?");
            $this->db->bind(':id', $retailer_id);
            $user = $this->db->single();

            if ($user['last_login_ip'] && $user['last_login_ip'] !== $context['ip_address']) {
                $score += 15;
                $indicators[] = 'ip_address_change';
            }
        }

        return ['score' => $score, 'indicators' => $indicators];
    }

    /**
     * Analyze velocity patterns
     */
    private function analyzeVelocityPatterns($retailer_id) {
        $score = 0;
        $indicators = [];

        // Rapid successive orders
        $this->db->query("
            SELECT COUNT(*) as rapid_orders
            FROM orders
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $rapid = $this->db->single();

        if ($rapid['rapid_orders'] > 10) {
            $score += 40;
            $indicators[] = 'rapid_order_velocity';
        } elseif ($rapid['rapid_orders'] > 5) {
            $score += 25;
            $indicators[] = 'high_order_velocity';
        }

        // Multiple loan applications
        $this->db->query("
            SELECT COUNT(*) as loan_applications
            FROM loans
            WHERE retailer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $loans = $this->db->single();

        if ($loans['loan_applications'] > 3) {
            $score += 35;
            $indicators[] = 'multiple_loan_applications';
        }

        return ['score' => $score, 'indicators' => $indicators];
    }

    /**
     * Analyze network patterns
     */
    private function analyzeNetworkPatterns($retailer_id) {
        $score = 0;
        $indicators = [];

        // Check for shared devices/IPs with known fraudulent accounts
        $this->db->query("
            SELECT DISTINCT s.device_fingerprint, s.ip_address
            FROM sessions s
            JOIN fraud_detection f ON s.user_id = f.user_id
            WHERE f.severity IN ('high', 'critical')
            AND s.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $fraud_devices = $this->db->fetchAll();

        foreach ($fraud_devices as $fraud_device) {
            $this->db->query("
                SELECT COUNT(*) as matches
                FROM sessions
                WHERE user_id = ?
                AND device_fingerprint = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $this->db->bind(':user_id', $retailer_id);
            $this->db->bind(':device_fingerprint', $fraud_device['device_fingerprint']);
            $matches = $this->db->single();

            if ($matches['matches'] > 0) {
                $score += 50;
                $indicators[] = 'device_linked_to_fraud';
                break;
            }
        }

        return ['score' => $score, 'indicators' => $indicators];
    }

    /**
     * Calculate overall fraud risk level
     */
    private function calculateFraudRiskLevel($score) {
        if ($score >= 85) return 'critical';
        if ($score >= 70) return 'high';
        if ($score >= 50) return 'medium';
        if ($score >= 30) return 'low';
        return 'minimal';
    }

    /**
     * Log fraud detection events
     */
    private function logFraudEvent($retailer_id, $score, $indicators, $risk_level, $context) {
        $this->db->query("
            INSERT INTO fraud_detection (
                user_id, event_type, description, severity, status, created_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $this->db->bind(':user_id', $retailer_id);
        $this->db->bind(':event_type', 'ml_fraud_detection');
        $this->db->bind(':description', json_encode([
            'fraud_score' => $score,
            'indicators' => $indicators,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        $this->db->bind(':severity', $risk_level);
        $this->db->execute();
    }

    /**
     * Trigger automated fraud response
     */
    private function triggerFraudResponse($retailer_id, $score, $indicators) {
        // Temporary account hold
        $this->db->query("
            UPDATE users
            SET status = 'suspended', updated_at = NOW()
            WHERE id = ?
        ");
        $this->db->bind(':id', $retailer_id);
        $this->db->execute();

        // Log the action
        $this->db->query("
            INSERT INTO audit_logs (
                user_id, action, entity_type, entity_id,
                old_values, new_values, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $this->db->bind(':user_id', $retailer_id);
        $this->db->bind(':action', 'auto_suspension_fraud_risk');
        $this->db->bind(':entity_type', 'user');
        $this->db->bind(':entity_id', $retailer_id);
        $this->db->bind(':old_values', json_encode(['status' => 'active']));
        $this->db->bind(':new_values', json_encode([
            'status' => 'suspended',
            'fraud_score' => $score,
            'indicators' => $indicators
        ]));
        $this->db->execute();
    }
  
    /**
     * Enhanced dynamic credit limit calculation
     */
    public function calculateCreditLimit($retailer_id) {
        $base_limit = 10000;

        // Get current credit score with full details
        $credit_score_result = $this->calculateCreditScore($retailer_id);
        $score = $credit_score_result['score'];

        // Calculate base limit from credit score
        $score_multiplier = ($score - 300) / 550; // Normalize 300-850 to 0-1
        $limit = $base_limit + ($score_multiplier * 99000); // Up to 100x base

        // Apply industry-specific multipliers
        $features = $credit_score_result['features'];
        $industry = $features['economic']['primary_industry'];
        $industry_multiplier = $this->getIndustryCreditMultiplier($industry);
        $limit *= $industry_multiplier;

        // Business stability adjustment
        $stability_bonus = $this->calculateStabilityBonus($features['stability']);
        $limit += $stability_bonus;

        // Seasonal adjustment
        $seasonal_adjustment = $features['economic']['seasonal_adjustment'] ?? 0;
        $limit *= (1 + $seasonal_adjustment);

        // Repayment capacity analysis
        $capacity = $this->analyzeRepaymentCapacity($retailer_id);
        $limit = min($limit, $capacity['max_affordable']);

        // Risk-based adjustment
        $risk_level = $credit_score_result['risk_level'];
        $risk_adjustment = $this->getRiskAdjustment($risk_level);
        $limit *= $risk_adjustment;

        // Apply regulatory limits
        $regulatory_limit = $this->getRegulatoryLimit($retailer_id);
        $limit = min($limit, $regulatory_limit);

        // Ensure minimum viable limit
        $min_limit = $this->getMinimumCreditLimit($retailer_id);
        $limit = max($min_limit, $limit);

        // Round to nearest 100
        $limit = round($limit / 100) * 100;

        // Update credit limit in database
        $this->updateCreditLimit($retailer_id, $limit, $credit_score_result);

        return [
            'credit_limit' => $limit,
            'base_limit' => $base_limit,
            'multipliers_applied' => [
                'credit_score' => $score_multiplier,
                'industry' => $industry_multiplier,
                'seasonal' => (1 + $seasonal_adjustment),
                'risk' => $risk_adjustment
            ],
            'capacity_analysis' => $capacity,
            'next_review_date' => $this->calculateNextReviewDate($retailer_id)
        ];
    }

    /**
     * Get industry-specific credit multipliers
     */
    private function getIndustryCreditMultiplier($industry) {
        $multipliers = [
            'food_grocery' => 1.2,      // High frequency, stable demand
            'electronics' => 0.9,        // Higher risk, lower margins
            'clothing' => 1.0,           // Seasonal, moderate risk
            'beauty' => 1.1,             // Good margins, repeat business
            'general_merchandise' => 1.05 // Mixed risk profile
        ];

        return $multipliers[$industry] ?? 1.0;
    }

    /**
     * Calculate stability bonus
     */
    private function calculateStabilityBonus($stability) {
        $bonus = 0;

        // Business age bonus
        if ($stability['business_age_days'] > 1095) { // 3+ years
            $bonus += 20000;
        } elseif ($stability['business_age_days'] > 730) { // 2+ years
            $bonus += 15000;
        } elseif ($stability['business_age_days'] > 365) { // 1+ year
            $bonus += 10000;
        }

        // Order frequency bonus
        if ($stability['order_frequency'] > 20) {
            $bonus += 15000;
        } elseif ($stability['order_frequency'] > 10) {
            $bonus += 10000;
        } elseif ($stability['order_frequency'] > 5) {
            $bonus += 5000;
        }

        // KYC verification bonus
        if ($stability['kyc_verified']) {
            $bonus += 25000;
        }

        // Stable business pattern bonus
        if ($stability['has_stable_business']) {
            $bonus += 10000;
        }

        return $bonus;
    }

    /**
     * Analyze repayment capacity
     */
    private function analyzeRepaymentCapacity($retailer_id) {
        $this->db->query("
            SELECT
                AVG(total_amount) as avg_monthly_revenue,
                AVG(total_amount) * 0.1 as estimated_monthly_repayment_capacity
            FROM orders
            WHERE retailer_id = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND status != 'cancelled'
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $revenue = $this->db->single();

        $this->db->query("
            SELECT
                SUM(amount_due) as current_monthly_obligations,
                COUNT(*) as active_loans
            FROM repayment_schedule rs
            JOIN loans l ON rs.loan_id = l.id
            WHERE l.retailer_id = ?
            AND rs.status = 'pending'
            AND rs.due_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $obligations = $this->db->single();

        $monthly_capacity = $revenue['estimated_monthly_repayment_capacity'] ?? 0;
        $current_obligations = $obligations['current_monthly_obligations'] ?? 0;
        $available_capacity = max(0, $monthly_capacity - $current_obligations);

        // Maximum affordable based on 6-month repayment capacity
        $max_affordable = $available_capacity * 6;

        return [
            'estimated_monthly_revenue' => $revenue['avg_monthly_revenue'] ?? 0,
            'monthly_repayment_capacity' => $monthly_capacity,
            'current_obligations' => $current_obligations,
            'available_capacity' => $available_capacity,
            'max_affordable' => $max_affordable,
            'active_loans' => $obligations['active_loans'] ?? 0
        ];
    }

    /**
     * Get risk-based adjustment factor
     */
    private function getRiskAdjustment($risk_level) {
        $adjustments = [
            'very_low' => 1.25,    // Reward low risk
            'low' => 1.15,
            'medium_low' => 1.05,
            'medium' => 1.0,       // Neutral
            'medium_high' => 0.9,
            'high' => 0.75,
            'very_high' => 0.6     // Penalize high risk
        ];

        return $adjustments[$risk_level] ?? 1.0;
    }

    /**
     * Get regulatory limits based on business profile
     */
    private function getRegulatoryLimit($retailer_id) {
        $this->db->query("
            SELECT kyc_verified, business_registration
            FROM users
            WHERE id = ?
        ");
        $this->db->bind(':id', $retailer_id);
        $user = $this->db->single();

        // Different limits based on verification level
        if ($user['kyc_verified'] && $user['business_registration']) {
            return 1000000; // 1M for fully verified
        } elseif ($user['kyc_verified']) {
            return 500000;  // 500K for KYC verified only
        } else {
            return 100000;  // 100K for unverified
        }
    }

    /**
     * Get minimum credit limit
     */
    private function getMinimumCreditLimit($retailer_id) {
        $this->db->query("
            SELECT COUNT(*) as completed_orders
            FROM orders
            WHERE retailer_id = ? AND status = 'delivered'
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $orders = $this->db->single();

        if ($orders['completed_orders'] > 10) {
            return 25000; // Established businesses
        } elseif ($orders['completed_orders'] > 0) {
            return 10000; // Proven businesses
        } else {
            return 5000;  // New businesses
        }
    }

    /**
     * Update credit limit in database
     */
    private function updateCreditLimit($retailer_id, $limit, $credit_score_result) {
        $this->db->query("
            INSERT INTO credit_scores (
                retailer_id, credit_limit, score, updated_at
            ) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                credit_limit = ?,
                score = ?,
                updated_at = NOW()
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $this->db->bind(':credit_limit', $limit);
        $this->db->bind(':score', $credit_score_result['score']);
        $this->db->bind(':credit_limit', $limit);
        $this->db->bind(':score', $credit_score_result['score']);
        $this->db->execute();

        // Log credit limit change
        $this->db->query("
            INSERT INTO audit_logs (
                user_id, action, entity_type, entity_id,
                new_values, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $this->db->bind(':user_id', $retailer_id);
        $this->db->bind(':action', 'credit_limit_updated');
        $this->db->bind(':entity_type', 'credit_scores');
        $this->db->bind(':entity_id', $retailer_id);
        $this->db->bind(':new_values', json_encode([
            'credit_limit' => $limit,
            'credit_score' => $credit_score_result['score'],
            'risk_level' => $credit_score_result['risk_level']
        ]));
        $this->db->execute();
    }

    /**
     * Calculate next review date
     */
    private function calculateNextReviewDate($retailer_id) {
        $this->db->query("
            SELECT COUNT(*) as total_loans,
                   SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaults
            FROM loans WHERE retailer_id = ?
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $history = $this->db->single();

        $total_loans = $history['total_loans'] ?? 0;
        $defaults = $history['defaults'] ?? 0;

        if ($defaults > 0 || $total_loans < 3) {
            // Review sooner for new or risky customers
            return date('Y-m-d', strtotime('+30 days'));
        } elseif ($total_loans < 10) {
            return date('Y-m-d', strtotime('+60 days'));
        } else {
            // Established customers get longer review periods
            return date('Y-m-d', strtotime('+90 days'));
        }
    }

    /**
     * Get comprehensive credit report
     */
    public function getCreditReport($retailer_id) {
        $credit_score = $this->calculateCreditScore($retailer_id);
        $credit_limit = $this->calculateCreditLimit($retailer_id);
        $fraud_analysis = $this->detectFraud($retailer_id);

        return [
            'retailer_id' => $retailer_id,
            'credit_score' => $credit_score,
            'credit_limit' => $credit_limit,
            'fraud_analysis' => $fraud_analysis,
            'generated_at' => date('Y-m-d H:i:s'),
            'next_review_date' => $credit_limit['next_review_date'],
            'recommendations' => $this->generateRecommendations($credit_score, $fraud_analysis)
        ];
    }

    /**
     * Generate recommendations based on credit analysis
     */
    private function generateRecommendations($credit_score, $fraud_analysis) {
        $recommendations = [];

        if ($credit_score['score'] < 500) {
            $recommendations[] = [
                'type' => 'risk_mitigation',
                'message' => 'Consider requiring additional collateral or guarantor',
                'priority' => 'high'
            ];
        }

        if ($fraud_analysis['is_suspicious']) {
            $recommendations[] = [
                'type' => 'fraud_alert',
                'message' => 'Enhanced monitoring and manual review recommended',
                'priority' => 'critical'
            ];
        }

        if ($credit_score['confidence'] < 0.7) {
            $recommendations[] = [
                'type' => 'data_insufficiency',
                'message' => 'More transaction history needed for accurate scoring',
                'priority' => 'medium'
            ];
        }

        if ($credit_score['score'] > 750) {
            $recommendations[] = [
                'type' => 'opportunity',
                'message' => 'Excellent candidate for credit limit increase',
                'priority' => 'low'
            ];
        }

        return $recommendations;
    }
}
?>
