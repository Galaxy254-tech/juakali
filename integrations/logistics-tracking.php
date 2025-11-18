<?php
/**
 * Logistics & Delivery Tracking Integration
 * Real-time package tracking and delivery optimization
 */

class LogisticsTracking {
    private $db;
    private $sendy_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->sendy_api_key = getenv('SENDY_API_KEY');
    }
    
    /**
     * Create delivery order with Sendy
     */
    public function createDeliveryOrder($order_id, $pickup_location, $delivery_location, $package_details) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.sendyit.com/v1/shipments",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'api_key' => $this->sendy_api_key,
                    'command' => 'create',
                    'pickup_phonenumber' => $pickup_location['phone'],
                    'pickup_email' => $pickup_location['email'],
                    'pickup_location' => $pickup_location['address'],
                    'delivery_phonenumber' => $delivery_location['phone'],
                    'delivery_email' => $delivery_location['email'],
                    'delivery_location' => $delivery_location['address'],
                    'delivery_comments' => $package_details['notes'] ?? '',
                    'package_size' => $package_details['size'],
                    'package_weight' => $package_details['weight'],
                    'package_description' => $package_details['description'],
                    'instruction' => 'Deliver to recipient only',
                    'reference' => 'ORDER-' . $order_id
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json"
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            
            if ($result['status'] == 'Success') {
                // Update delivery tracking
                $this->db->query("
                    INSERT INTO delivery_tracking (order_id, tracking_number, status)
                    VALUES (?, ?, ?)
                ");
                $this->db->bind(':order_id', $order_id);
                $this->db->bind(':tracking_number', $result['tracking_number']);
                $this->db->bind(':status', 'pending');
                $this->db->execute();
            }
            
            return $result;
        } catch (Exception $e) {
            return ['status' => 'Error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Track delivery status
     */
    public function trackDelivery($tracking_number) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.sendyit.com/v1/shipments/" . $tracking_number,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer " . $this->sendy_api_key
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            return $result;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Optimize delivery routes
     */
    public function optimizeRoutes($orders) {
        $locations = [];
        foreach ($orders as $order) {
            $locations[] = [
                'order_id' => $order['id'],
                'lat' => $order['delivery_lat'],
                'lng' => $order['delivery_lng']
            ];
        }
        
        // Use Google Maps API for route optimization
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://maps.googleapis.com/maps/api/directions/json?" . http_build_query([
                'key' => getenv('GOOGLE_MAPS_API_KEY'),
                'waypoints' => implode('|', array_map(fn($l) => $l['lat'] . ',' . $l['lng'], $locations))
            ]),
            CURLOPT_RETURNTRANSFER => true,
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response, true);
        return $result;
    }
}
?>
