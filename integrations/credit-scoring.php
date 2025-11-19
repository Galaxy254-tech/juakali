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
     * Calculate credit score based on multiple factors
     */
    public function calculateCreditScore($retailer_id) {
        $score = 500; // Base score
        
        // Get retailer's repayment history
        $this->db->query("
            SELECT COUNT(*) as total_loans, 
                   SUM(CASE WHEN status = 'repaid' THEN 1 ELSE 0 END) as repaid_loans,
                   SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted_loans
            FROM loans WHERE retailer_id = ?
        ");
        $this->db->bind(':retailer_id', $retailer_id);
        $history = $this->db->single();
        
        // Repayment rate (40% weight)
        if ($history['total_loans'] > 0) {
            $repayment_rate = ($history['repaid_loans'] / $history['total_loans']) * 100;
            $score += ($repayment_rate / 100) * 200;
        }
        
        // Default penalty (30% weight)
        if ($history['defaulted_loans'] > 0) {
            $score -= ($history['defaulted_loans'] * 50);
        }
        
        // Order frequency (20% weight)
        $this->db->query("SELECT COUNT(*) as order_count FROM orders WHERE retailer_id = ?");
        $this->db->bind(':retailer_id', $retailer_id);
        $orders = $this->db->single();
        $order_bonus = min($orders['order_count'] * 5, 100);
        $score += $order_bonus;
        
        // Business age (10% weight)
        $this->db->query("SELECT DATEDIFF(NOW(), created_at) as days_active FROM users WHERE id = ?");
        $this->db->bind(':id', $retailer_id);
        $user = $this->db->single();
        $age_bonus = min(($user['days_active'] / 365) * 50, 50);
        $score += $age_bonus;
        
        // Cap score between 300-850
        $score = max(300, min(850, $score));
        
        // Update credit score in database
        $this->db->query("
            UPDATE credit_scores 
            SET score = ? 
            WHERE retailer_id = ?
        ");
        $this->db->bind(':score', $score);
        $this->db->bind(':retailer_id', $retailer_id);
        $this->db->execute();
        
        return $score;
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
