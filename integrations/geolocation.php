<?php
/**
 * Geolocation & Mapping Services Integration
 * Location-based lending and delivery optimization
 */

class GeolocationServices {
    private $db;
    private $google_maps_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->google_maps_key = getenv('GOOGLE_MAPS_API_KEY');
    }
    
    /**
     * Get local market intelligence
     */
    public function getLocalMarketIntelligence($latitude, $longitude, $radius_km = 5) {
        // Get nearby retailers
        $this->db->query("
            SELECT u.id, u.company_name, up.city, cs.credit_limit, cs.score
            FROM users u
            JOIN user_profiles up ON u.id = up.user_id
            JOIN credit_scores cs ON u.id = cs.retailer_id
            WHERE u.role = 'retailer' AND u.status = 'active'
            LIMIT 20
        ");
        $nearby_retailers = $this->db->resultSet();
        
        // Get popular products in area
        $this->db->query("
            SELECT p.name, p.category, COUNT(oi.id) as sales_count
            FROM products p
            JOIN order_items oi ON p.id = oi.product_id
            WHERE p.status = 'active'
            GROUP BY p.id
            ORDER BY sales_count DESC
            LIMIT 10
        ");
        $popular_products = $this->db->resultSet();
        
        // Get average credit limit in area
        $this->db->query("
            SELECT AVG(cs.credit_limit) as avg_limit, AVG(cs.score) as avg_score
            FROM credit_scores cs
            JOIN users u ON cs.retailer_id = u.id
            WHERE u.status = 'active'
        ");
        $area_stats = $this->db->single();
        
        return [
            'nearby_retailers' => $nearby_retailers,
            'popular_products' => $popular_products,
            'area_statistics' => $area_stats
        ];
    }
    
    /**
     * Find nearby suppliers
     */
    public function findNearbySuppliers($latitude, $longitude, $radius_km = 10) {
        $this->db->query("
            SELECT u.id, u.company_name, up.city, sr.average_rating, sr.on_time_delivery_rate
            FROM users u
            JOIN user_profiles up ON u.id = up.user_id
            LEFT JOIN supplier_ratings sr ON u.id = sr.supplier_id
            WHERE u.role = 'supplier' AND u.status = 'active'
            ORDER BY sr.average_rating DESC
            LIMIT 20
        ");
        return $this->db->resultSet();
    }
}
?>
