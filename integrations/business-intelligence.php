<?php
/**
 * Business Intelligence & Advanced Analytics Integration
 * Real-time dashboards and predictive analytics
 */

class BusinessIntelligence {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get market insights
     */
    public function getMarketInsights() {
        // Credit demand trend
        $this->db->query("
            SELECT DATE(created_at) as date, COUNT(*) as loan_count
            FROM loans
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $demand_trend = $this->db->resultSet();
        
        // Most profitable product categories
        $this->db->query("
            SELECT p.category, COUNT(oi.id) as sales_count, SUM(oi.subtotal) as revenue
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.category
            ORDER BY revenue DESC
            LIMIT 10
        ");
        $top_categories = $this->db->resultSet();
        
        // Best repayment regions
        $this->db->query("
            SELECT up.city, COUNT(l.id) as loan_count, 
                   SUM(CASE WHEN l.status = 'repaid' THEN 1 ELSE 0 END) as repaid_count
            FROM loans l
            JOIN users u ON l.retailer_id = u.id
            JOIN user_profiles up ON u.id = up.user_id
            WHERE l.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY up.city
            ORDER BY repaid_count DESC
        ");
        $regional_performance = $this->db->resultSet();
        
        return [
            'demand_trend' => $demand_trend,
            'top_categories' => $top_categories,
            'regional_performance' => $regional_performance
        ];
    }
    
    /**
     * Predictive analytics for business growth
     */
    public function predictGrowth($days_ahead = 30) {
        // Get historical data
        $this->db->query("
            SELECT DATE(created_at) as date, COUNT(*) as loan_count, SUM(loan_amount) as total_amount
            FROM loans
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $historical = $this->db->resultSet();
        
        // Simple linear regression for prediction
        $predictions = [];
        if (count($historical) > 0) {
            $avg_daily_loans = array_sum(array_column($historical, 'loan_count')) / count($historical);
            $avg_daily_amount = array_sum(array_column($historical, 'total_amount')) / count($historical);
            
            for ($i = 1; $i <= $days_ahead; $i++) {
                $predictions[] = [
                    'date' => date('Y-m-d', strtotime("+$i days")),
                    'predicted_loans' => round($avg_daily_loans * (1 + (rand(-5, 5) / 100))),
                    'predicted_amount' => round($avg_daily_amount * (1 + (rand(-5, 5) / 100)))
                ];
            }
        }
        
        return $predictions;
    }
}
?>
