<?php
/**
 * QR Code Delivery Confirmation System
 * Generates and manages QR codes for secure delivery verification
 */

class QRDeliverySystem {
    private $db;
    private $qrBasePath;

    public function __construct($database) {
        $this->db = $database;
        $this->qrBasePath = __DIR__ . '/../assets/qr-codes/';

        // Ensure QR codes directory exists
        if (!file_exists($this->qrBasePath)) {
            mkdir($this->qrBasePath, 0755, true);
        }
    }

    /**
     * Generate QR code for delivery verification
     */
    public function generateDeliveryQR($order_id, $agent_id) {
        try {
            // Generate unique verification data
            $verification_data = [
                'order_id' => $order_id,
                'agent_id' => $agent_id,
                'timestamp' => time(),
                'nonce' => bin2hex(random_bytes(16)),
                'type' => 'delivery_verification'
            ];

            // Create verification hash
            $verification_hash = hash('sha256', json_encode($verification_data));
            $verification_code = strtoupper(substr($verification_hash, 0, 8));

            // Generate QR code data
            $qr_data = [
                'v' => $verification_code,
                'o' => $order_id,
                'a' => $agent_id,
                't' => time(),
                's' => $this->generateSignature($verification_data)
            ];

            $qr_string = base64_encode(json_encode($qr_data));

            // Generate QR code image
            $qr_image_path = $this->generateQRImage($qr_string, $order_id);

            // Save verification record
            $this->saveVerificationRecord($order_id, $agent_id, $verification_code, $qr_image_path);

            return [
                'success' => true,
                'verification_code' => $verification_code,
                'qr_data' => $qr_string,
                'qr_image_path' => $qr_image_path,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify QR code for delivery confirmation
     */
    public function verifyDeliveryQR($qr_data, $agent_location = null) {
        try {
            // Decode QR data
            $decoded_data = json_decode(base64_decode($qr_data), true);

            if (!$decoded_data) {
                throw new Exception('Invalid QR code format');
            }

            // Validate QR code structure
            $required_fields = ['v', 'o', 'a', 't', 's'];
            foreach ($required_fields as $field) {
                if (!isset($decoded_data[$field])) {
                    throw new Exception('Invalid QR code structure');
                }
            }

            $verification_code = $decoded_data['v'];
            $order_id = $decoded_data['o'];
            $agent_id = $decoded_data['a'];
            $timestamp = $decoded_data['t'];
            $signature = $decoded_data['s'];

            // Check QR code expiration (7 days)
            if (time() - $timestamp > 7 * 24 * 60 * 60) {
                throw new Exception('QR code has expired');
            }

            // Verify signature
            $verification_data = [
                'order_id' => $order_id,
                'agent_id' => $agent_id,
                'timestamp' => $timestamp,
                'type' => 'delivery_verification'
            ];

            if (!$this->verifySignature($verification_data, $signature)) {
                throw new Exception('Invalid QR code signature');
            }

            // Check verification record in database
            $verification = $this->db->fetchOne("
                SELECT * FROM delivery_verifications
                WHERE order_id = ? AND agent_id = ? AND verification_code = ?
                AND status = 'active'
                ORDER BY created_at DESC
                LIMIT 1
            ", [$order_id, $agent_id, $verification_code]);

            if (!$verification) {
                throw new Exception('Verification record not found or inactive');
            }

            // Check if delivery is already verified
            $delivery = $this->db->fetchOne("
                SELECT * FROM delivery_tracking
                WHERE order_id = ? AND assigned_agent_id = ?
            ", [$order_id, $agent_id]);

            if (!$delivery) {
                throw new Exception('Delivery assignment not found');
            }

            if ($delivery['status'] === 'delivered') {
                throw new Exception('Delivery already verified');
            }

            // Get order details
            $order = $this->db->fetchOne("
                SELECT o.*, r.company_name as retailer_name, r.phone as retailer_phone,
                       r.address as retailer_address, s.company_name as supplier_name
                FROM orders o
                JOIN users r ON o.retailer_id = r.id
                JOIN users s ON o.supplier_id = s.id
                WHERE o.id = ?
            ", [$order_id]);

            if (!$order) {
                throw new Exception('Order not found');
            }

            return [
                'success' => true,
                'verification_code' => $verification_code,
                'order' => $order,
                'delivery' => $delivery,
                'verification' => $verification
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Complete delivery verification with QR code
     */
    public function completeDeliveryWithQR($qr_data, $completion_data) {
        try {
            // Verify QR code first
            $verification = $this->verifyDeliveryQR($qr_data, $completion_data['location'] ?? null);

            if (!$verification['success']) {
                return $verification;
            }

            $order_id = $verification['order']['id'];
            $agent_id = $completion_data['agent_id'];
            $verification_code = $verification['verification_code'];

            // Extract completion data
            $recipient_name = $completion_data['recipient_name'] ?? '';
            $recipient_phone = $completion_data['recipient_phone'] ?? '';
            $delivery_notes = $completion_data['delivery_notes'] ?? '';
            $actual_location = $completion_data['location'] ?? '';
            $photos = $completion_data['photos'] ?? [];
            $signature_data = $completion_data['signature'] ?? '';

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Update delivery tracking
                $this->db->query("
                    UPDATE delivery_tracking
                    SET status = 'delivered',
                        actual_delivery_date = NOW(),
                        delivery_notes = ?,
                        recipient_name = ?,
                        recipient_phone = ?,
                        actual_location = ?,
                        photos = ?,
                        recipient_signature = ?,
                        verified_by_qr = 1,
                        updated_at = NOW()
                    WHERE order_id = ? AND assigned_agent_id = ?
                ");
                $this->db->bind(1, $delivery_notes);
                $this->db->bind(2, $recipient_name);
                $this->db->bind(3, $recipient_phone);
                $this->db->bind(4, $actual_location);
                $this->db->bind(5, json_encode($photos));
                $this->db->bind(6, $signature_data);
                $this->db->bind(7, $order_id);
                $this->db->bind(8, $agent_id);
                $this->db->execute();

                // Update order status
                $this->db->query("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?", [$order_id]);

                // Mark verification code as used
                $this->db->query("
                    UPDATE delivery_verifications
                    SET status = 'used', used_at = NOW()
                    WHERE verification_code = ? AND order_id = ? AND agent_id = ?
                ", [$verification_code, $order_id, $agent_id]);

                // Award agent earnings
                $earnings = $this->calculateDeliveryEarnings($order_id);
                $this->db->query("
                    INSERT INTO agent_earnings (agent_id, order_id, amount, type, status, created_at)
                    VALUES (?, ?, ?, 'delivery_fee', 'earned', NOW())
                ", [$agent_id, $order_id, $earnings]);

                // Log QR verification
                $this->db->query("
                    INSERT INTO qr_verification_logs (
                        order_id, agent_id, verification_code, qr_data,
                        completion_data, verification_time, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $order_id,
                    $agent_id,
                    $verification_code,
                    $qr_data,
                    json_encode($completion_data)
                ]);

                // Commit transaction
                $this->db->commit();

                // Send notifications
                $this->sendDeliveryNotifications($verification['order'], $verification_code);

                return [
                    'success' => true,
                    'message' => 'Delivery completed successfully with QR verification',
                    'earnings_awarded' => $earnings,
                    'verification_code' => $verification_code
                ];

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate QR code image
     */
    private function generateQRImage($data, $order_id) {
        // For this implementation, we'll use a simple approach
        // In production, you'd use a proper QR code library

        $filename = 'delivery_' . $order_id . '_' . time() . '.png';
        $filepath = $this->qrBasePath . $filename;

        // Generate a simple placeholder QR code representation
        // In production, replace this with actual QR code generation
        $qr_size = 200;
        $image = imagecreatetruecolor($qr_size, $qr_size);

        // Create a simple pattern (placeholder)
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        imagefill($image, 0, 0, $white);

        // Create a simple grid pattern as placeholder
        for ($x = 0; $x < $qr_size; $x += 10) {
            for ($y = 0; $y < $qr_size; $y += 10) {
                if (rand(0, 1)) {
                    imagefilledrectangle($image, $x, $y, $x + 8, $y + 8, $black);
                }
            }

        imagepng($image, $filepath);
        imagedestroy($image);

        return 'assets/qr-codes/' . $filename;
    }

    /**
     * Generate digital signature
     */
    private function generateSignature($data) {
        $secret_key = 'juakali_delivery_secret_2025'; // In production, use environment variable
        return hash_hmac('sha256', json_encode($data), $secret_key);
    }

    /**
     * Verify digital signature
     */
    private function verifySignature($data, $signature) {
        $secret_key = 'juakali_delivery_secret_2025'; // In production, use environment variable
        $expected_signature = $this->generateSignature($data);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Save verification record to database
     */
    private function saveVerificationRecord($order_id, $agent_id, $verification_code, $qr_image_path) {
        $this->db->query("
            INSERT INTO delivery_verifications (
                order_id, agent_id, verification_code, qr_image_path,
                status, expires_at, created_at
            ) VALUES (?, ?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 7 DAY), NOW())
        ");
        $this->db->bind(1, $order_id);
        $this->db->bind(2, $agent_id);
        $this->db->bind(3, $verification_code);
        $this->db->bind(4, $qr_image_path);
        $this->db->execute();
    }

    /**
     * Calculate delivery earnings
     */
    private function calculateDeliveryEarnings($order_id) {
        $order = $this->db->fetchOne("SELECT total_amount FROM orders WHERE id = ?", [$order_id]);

        if (!$order) {
            return 100; // Base fee
        }

        // Earnings calculation based on order value
        $base_fee = 100;
        $percentage_fee = $order['total_amount'] * 0.02; // 2% of order value

        return max($base_fee, min($base_fee + $percentage_fee, 500)); // Max 500
    }

    /**
     * Send delivery notifications
     */
    private function sendDeliveryNotifications($order, $verification_code) {
        try {
            // Send SMS to retailer
            $sms = new SMSNotification();
            $message = "Hi {$order['retailer_name']}, your order #{$order['id']} has been delivered successfully. Verification code: {$verification_code}. Thank you for using JuaKali Lend!";
            $sms->sendSMS($order['retailer_phone'], $message, 'high');

            // Send notification to supplier
            $this->db->query("
                INSERT INTO notifications (
                    user_id, title, message, type, is_read, created_at
                ) VALUES (?, ?, ?, ?, FALSE, NOW())
            ");
            $this->db->bind(1, $order['supplier_id']);
            $this->db->bind(2, 'Order Delivered');
            $this->db->bind(3, "Order #{$order['id']} to {$order['retailer_name']} has been delivered successfully.");
            $this->db->bind(4, 'order');
            $this->db->execute();

        } catch (Exception $e) {
            error_log('Failed to send delivery notifications: ' . $e->getMessage());
        }
    }

    /**
     * Get QR code verification statistics
     */
    public function getQRVerificationStats($date_range = null) {
        $where_clause = '';
        $params = [];

        if ($date_range) {
            $where_clause = 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[] = $date_range;
        }

        $stats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_verifications,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_verifications,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_verifications,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_verifications,
                COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as verifications_today,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, used_at)) as avg_completion_time_minutes
            FROM delivery_verifications
            $where_clause
        ", $params);

        return [
            'total_verifications' => $stats['total_verifications'] ?? 0,
            'used_verifications' => $stats['used_verifications'] ?? 0,
            'active_verifications' => $stats['active_verifications'] ?? 0,
            'expired_verifications' => $stats['expired_verifications'] ?? 0,
            'verifications_today' => $stats['verifications_today'] ?? 0,
            'avg_completion_time_minutes' => round($stats['avg_completion_time_minutes'] ?? 0, 2),
            'usage_rate' => $stats['total_verifications'] > 0 ?
                round(($stats['used_verifications'] / $stats['total_verifications']) * 100, 2) : 0
        ];
    }

    /**
     * Clean up expired QR codes
     */
    public function cleanupExpiredQRCodes() {
        $this->db->query("
            UPDATE delivery_verifications
            SET status = 'expired'
            WHERE expires_at < NOW() AND status = 'active'
        ");
        $this->db->execute();

        return $this->db->rowCount();
    }
}

// Database table creation for QR delivery system
function createQRDeliveryTables($db) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS delivery_verifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            agent_id INT NOT NULL,
            verification_code VARCHAR(10) UNIQUE NOT NULL,
            qr_image_path VARCHAR(255),
            status ENUM('active', 'used', 'expired') DEFAULT 'active',
            expires_at DATETIME NOT NULL,
            used_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (order_id),
            INDEX idx_agent (agent_id),
            INDEX idx_verification_code (verification_code),
            INDEX idx_status (status),
            INDEX idx_expires (expires_at)
        )",

        "CREATE TABLE IF NOT EXISTS qr_verification_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id INT NOT NULL,
            agent_id INT NOT NULL,
            verification_code VARCHAR(10) NOT NULL,
            qr_data TEXT NOT NULL,
            completion_data JSON,
            verification_time DATETIME NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (order_id),
            INDEX idx_agent (agent_id),
            INDEX idx_verification_code (verification_code),
            INDEX idx_verification_time (verification_time)
        )",

        "CREATE TABLE IF NOT EXISTS agent_earnings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            agent_id INT NOT NULL,
            order_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            type ENUM('delivery_fee', 'bonus', 'penalty') DEFAULT 'delivery_fee',
            status ENUM('earned', 'pending', 'paid') DEFAULT 'earned',
            paid_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agent (agent_id),
            INDEX idx_order (order_id),
            INDEX idx_status (status),
            INDEX idx_type (type),
            INDEX idx_created (created_at)
        )"
    ];

    foreach ($tables as $sql) {
        $db->query($sql);
        $db->execute();
    }
}
?>