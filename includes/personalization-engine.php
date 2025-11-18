<?php
/**
 * Personalization Engine
 * Behavior-based content and dynamic recommendations
 */

class PersonalizationEngine {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get personalized recommendations
     */
    public function getPersonalizedRecommendations($user_id) {
        // Get user's purchase history
        $this->db->query("
            SELECT p.category, COUNT(*) as purchase_count
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.retailer_id = ?
            GROUP BY p.category
            ORDER BY purchase_count DESC
            LIMIT 3
        ");
        $this->db->bind(':retailer_id', $user_id);
        $favorite_categories = $this->db->resultSet();
        
        // Get recommended products
        $recommendations = [];
        foreach ($favorite_categories as $category) {
            $this->db->query("
                SELECT * FROM products
                WHERE category = ? AND status = 'active'
                ORDER BY RAND()
                LIMIT 3
            ");
            $this->db->bind(':category', $category['category']);
            $products = $this->db->resultSet();
            $recommendations = array_merge($recommendations, $products);
        }
        
        return $recommendations;
    }
    
    /**
     * Get location-specific offers
     */
    public function getLocationSpecificOffers($user_id) {
        $this->db->query("
            SELECT up.city FROM user_profiles up WHERE up.user_id = ?
        ");
        $this->db->bind(':user_id', $user_id);
        $profile = $this->db->single();
        
        if (!$profile) return [];
        
        // Get offers for user's city
        $this->db->query("
            SELECT * FROM promotions
            WHERE target_city = ? OR target_city = 'national'
            AND status = 'active'
            AND NOW() BETWEEN start_date AND end_date
        ");
        $this->db->bind(':city', $profile['city']);
        return $this->db->resultSet();
    }
}
?>
