<?php
/**
 * Inventory Management System Integration
 * Real-time stock synchronization and forecasting
 */

class InventoryManagement {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check and alert low stock
     */
    public function checkLowStock() {
        $this->db->query("
            SELECT p.id, p.name, p.quantity_available, p.supplier_id
            FROM products p
            WHERE p.quantity_available < 50 AND p.status = 'active'
        ");
        $low_stock_items = $this->db->resultSet();
        
        foreach ($low_stock_items as $item) {
            // Create alert
            $this->db->query("
                INSERT INTO inventory_alerts (product_id, supplier_id, alert_type, threshold_quantity, current_quantity)
                VALUES (?, ?, ?, ?, ?)
            ");
            $this->db->bind(':product_id', $item['id']);
            $this->db->bind(':supplier_id', $item['supplier_id']);
            $this->db->bind(':alert_type', 'low_stock');
            $this->db->bind(':threshold_quantity', 50);
            $this->db->bind(':current_quantity', $item['quantity_available']);
            $this->db->execute();
        }
        
        return $low_stock_items;
    }
    
    /**
     * Forecast inventory needs
     */
    public function forecastInventory($product_id, $days_ahead = 30) {
        // Get historical sales data
        $this->db->query("
            SELECT DATE(oi.created_at) as date, SUM(oi.quantity) as quantity_sold
            FROM order_items oi
            WHERE oi.product_id = ? AND oi.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY DATE(oi.created_at)
            ORDER BY date ASC
        ");
        $this->db->bind(':product_id', $product_id);
        $historical = $this->db->resultSet();
        
        // Calculate average daily sales
        $avg_daily_sales = array_sum(array_column($historical, 'quantity_sold')) / max(count($historical), 1);
        
        // Get current stock
        $this->db->query("SELECT quantity_available FROM products WHERE id = ?");
        $this->db->bind(':id', $product_id);
        $product = $this->db->single();
        
        $forecast = [
            'current_stock' => $product['quantity_available'],
            'avg_daily_sales' => round($avg_daily_sales, 2),
            'projected_stock_out_date' => date('Y-m-d', strtotime("+". floor($product['quantity_available'] / max($avg_daily_sales, 1)) ." days")),
            'recommended_reorder_quantity' => round($avg_daily_sales * 30) // 30 days supply
        ];
        
        return $forecast;
    }
}
?>
