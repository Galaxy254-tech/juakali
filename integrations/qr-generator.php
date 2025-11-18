<?php
/**
 * QR Code Generator Integration
 * Generates QR codes for delivery verification and tracking
 */
class QRCodeGenerator {
    private $api_url = 'https://api.qrserver.com/v1/create-qr-code/';
    
    /**
     * Generate QR code for order
     * @param int $order_id Order ID
     * @param string $tracking_number Tracking number
     * @return string QR code image URL
     */
    public static function generateOrderQR($order_id, $tracking_number) {
        $data = [
            'order_id' => $order_id,
            'tracking' => $tracking_number,
            'timestamp' => time()
        ];
        
        $qr_data = json_encode($data);
        $encoded = urlencode(base64_encode($qr_data));
        
        return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . $encoded;
    }
    
    /**
     * Generate QR code with embedded location
     * @param string $latitude Latitude
     * @param string $longitude Longitude
     * @param int $order_id Order ID
     * @return string QR code image URL
     */
    public static function generateLocationQR($latitude, $longitude, $order_id) {
        $location_data = "geo:$latitude,$longitude?z=16&order=$order_id";
        return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($location_data);
    }
    
    /**
     * Generate delivery PIN
     * @return string 4-digit PIN
     */
    public static function generateDeliveryPIN() {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
?>
