<?php
/**
 * Mobile App Push Notifications Integration
 * Real-time alerts for loans, payments, and updates
 */

class PushNotifications {
    private $db;
    private $firebase_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->firebase_key = getenv('FIREBASE_SERVER_KEY');
    }
    
    /**
     * Send push notification
     */
    public function sendNotification($user_id, $title, $message, $data = []) {
        // Get user's device tokens
        $this->db->query("
            SELECT device_token FROM user_devices WHERE user_id = ? AND is_active = TRUE
        ");
        $this->db->bind(':user_id', $user_id);
        $devices = $this->db->resultSet();
        
        foreach ($devices as $device) {
            $this->sendToDevice($device['device_token'], $title, $message, $data);
        }
        
        // Also save notification to database
        $this->db->query("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, ?)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':title', $title);
        $this->db->bind(':message', $message);
        $this->db->bind(':type', $data['type'] ?? 'system');
        $this->db->execute();
    }
    
    /**
     * Send to specific device
     */
    private function sendToDevice($device_token, $title, $message, $data) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://fcm.googleapis.com/fcm/send",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'to' => $device_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                        'sound' => 'default'
                    ],
                    'data' => $data
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: key=" . $this->firebase_key
                ],
            ]);
            
            curl_exec($curl);
            curl_close($curl);
        } catch (Exception $e) {
            // Log error
        }
    }
}
?>
