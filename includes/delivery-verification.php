<?php
/**
 * Delivery Verification Handler
 * Manages PIN verification, geolocation, and proof uploads
 */
class DeliveryVerification {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create delivery verification record
     * @param int $order_id Order ID
     * @param int $field_agent_id Field Agent ID
     * @return array Verification PIN and QR code
     */
    public function initiateDeliveryVerification($order_id, $field_agent_id) {
        // Generate PIN
        $pin = $this->generatePin();
        
        // Get order details
        $this->db->query('SELECT o.*, dt.tracking_number FROM orders o 
                         LEFT JOIN delivery_tracking dt ON o.id = dt.order_id 
                         WHERE o.id = ?');
        $this->db->bind('i', $order_id);
        $order = $this->db->single();
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Store PIN in session or temporary cache
        // Send PIN via SMS to retailer
        $this->sendDeliveryPin($order, $pin);
        
        // Generate QR code
        $qr_url = \QRCodeGenerator::generateOrderQR($order_id, $order['order_number']);
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order['order_number'],
            'qr_code' => $qr_url,
            'pin_sent' => true,
            'message' => 'Delivery verification initiated. PIN sent to retailer.'
        ];
    }
    
    /**
     * Verify delivery with PIN and geolocation
     * @param int $order_id Order ID
     * @param string $pin PIN provided by retailer
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @param string $proof_photo_path Path to proof photo
     * @return array Verification result
     */
    public function verifyDelivery($order_id, $pin, $latitude, $longitude, $proof_photo_path = null) {
        // Validate PIN (in production, compare with sent PIN)
        if (!$this->validatePin($pin)) {
            return ['success' => false, 'message' => 'Invalid PIN'];
        }
        
        // Validate geolocation
        if (!$this->validateGeolocation($latitude, $longitude)) {
            return ['success' => false, 'message' => 'Invalid location'];
        }
        
        // Update delivery tracking
        $location = $latitude . ',' . $longitude;
        $this->db->query('UPDATE delivery_tracking SET status = ?, current_location = ?, actual_delivery_date = NOW() WHERE order_id = ?');
        $this->db->bind('s', 'delivered');
        $this->db->bind('s', $location);
        $this->db->bind('i', $order_id);
        $this->db->execute();
        
        // Update order status
        $this->db->query('UPDATE orders SET status = ? WHERE id = ?');
        $this->db->bind('s', 'delivered');
        $this->db->bind('i', $order_id);
        $this->db->execute();
        
        // Store proof photo if provided
        if ($proof_photo_path) {
            $this->storeProofPhoto($order_id, $proof_photo_path);
        }
        
        return ['success' => true, 'message' => 'Delivery verified successfully'];
    }
    
    /**
     * Generate delivery PIN
     * @return string 4-digit PIN
     */
    private function generatePin() {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Validate PIN format
     * @param string $pin PIN to validate
     * @return bool
     */
    private function validatePin($pin) {
        return preg_match('/^\d{4}$/', $pin) === 1;
    }
    
    /**
     * Validate geolocation coordinates
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @return bool
     */
    private function validateGeolocation($lat, $lon) {
        return is_numeric($lat) && is_numeric($lon) && 
               $lat >= -90 && $lat <= 90 && 
               $lon >= -180 && $lon <= 180;
    }
    
    /**
     * Send delivery PIN via SMS
     * @param array $order Order details
     * @param string $pin PIN
     */
    private function sendDeliveryPin($order, $pin) {
        // Send SMS to retailer with PIN
        // Implementation: integrate with SMS gateway
        // For now, this is a placeholder
        error_log("Delivery PIN for order {$order['order_number']}: $pin");
    }
    
    /**
     * Store proof of delivery photo
     * @param int $order_id Order ID
     * @param string $photo_path Path to photo
     */
    private function storeProofPhoto($order_id, $photo_path) {
        // Store photo in database or file storage
        $this->db->query('UPDATE delivery_tracking SET current_location = JSON_SET(current_location, "$.proof_photo", ?) WHERE order_id = ?');
        $this->db->bind('s', $photo_path);
        $this->db->bind('i', $order_id);
        $this->db->execute();
    }
}
?>
