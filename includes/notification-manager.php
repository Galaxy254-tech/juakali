<?php
/**
 * Notification Manager
 * Handles sending notifications via multiple channels: SMS, WhatsApp, Email, Push
 */
class NotificationManager {
    private $db;
    private $sms_gateway;
    private $whatsapp_gateway;
    
    public function __construct($database) {
        $this->db = $database;
        $this->sms_gateway = new SMSGateway('mpesa');
        $this->whatsapp_gateway = new WhatsAppGateway();
    }
    
    /**
     * Send order notification
     * @param int $order_id Order ID
     * @param string $notification_type Type of notification
     * @return array
     */
    public function sendOrderNotification($order_id, $notification_type = 'created') {
        $this->db->query('SELECT o.*, u.phone, u.email FROM orders o 
                         JOIN users u ON o.retailer_id = u.id 
                         WHERE o.id = ?');
        $this->db->bind('i', $order_id);
        $order = $this->db->single();
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        $messages = [
            'created' => "Your order #{$order['order_number']} has been created for KES " . number_format($order['total_amount'], 2),
            'confirmed' => "Order #{$order['order_number']} has been confirmed by the supplier",
            'shipped' => "Order #{$order['order_number']} has been shipped",
            'delivered' => "Order #{$order['order_number']} has been delivered"
        ];
        
        $message = $messages[$notification_type] ?? $messages['created'];
        
        // Send notifications
        $results = [];
        
        // Check user preferences
        $this->db->query('SELECT * FROM notification_settings WHERE user_id = ?');
        $this->db->bind('i', $order['retailer_id']);
        $settings = $this->db->single();
        
        if ($settings && $settings['sms_notifications']) {
            $results['sms'] = $this->sms_gateway->sendSMS($order['phone'], $message);
        }
        
        if ($settings && $settings['whatsapp_notifications']) {
            $results['whatsapp'] = $this->whatsapp_gateway->sendMessage($order['phone'], $message);
        }
        
        // Store notification in database
        $this->db->query('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
        $this->db->bind('i', $order['retailer_id']);
        $this->db->bind('s', 'Order ' . ucfirst($notification_type));
        $this->db->bind('s', $message);
        $this->db->bind('s', 'order');
        $this->db->execute();
        
        return ['success' => true, 'message' => 'Notifications sent', 'results' => $results];
    }
    
    /**
     * Send repayment reminder
     * @param int $loan_id Loan ID
     * @return array
     */
    public function sendRepaymentReminder($loan_id) {
        $this->db->query('SELECT rs.*, l.retailer_id, u.phone, u.company_name FROM repayment_schedule rs
                         JOIN loans l ON rs.loan_id = l.id
                         JOIN users u ON l.retailer_id = u.id
                         WHERE rs.loan_id = ? AND rs.status = "pending"
                         ORDER BY rs.due_date ASC LIMIT 1');
        $this->db->bind('i', $loan_id);
        $repayment = $this->db->single();
        
        if (!$repayment) {
            return ['success' => false, 'message' => 'Repayment schedule not found'];
        }
        
        $days_until_due = intval((strtotime($repayment['due_date']) - time()) / 86400);
        
        if ($days_until_due < 0) {
            $message = "URGENT: Payment overdue! Your payment of KES " . number_format($repayment['amount_due'], 2) . " is overdue.";
        } elseif ($days_until_due === 0) {
            $message = "Payment due today! Your payment of KES " . number_format($repayment['amount_due'], 2) . " is due today.";
        } else {
            $message = "Repayment reminder: Your payment of KES " . number_format($repayment['amount_due'], 2) . " is due in $days_until_due days.";
        }
        
        // Send SMS
        $sms_result = $this->sms_gateway->sendSMS($repayment['phone'], $message);
        
        // Send WhatsApp
        $whatsapp_result = $this->whatsapp_gateway->sendMessage($repayment['phone'], $message);
        
        // Store in database
        $this->db->query('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
        $this->db->bind('i', $repayment['retailer_id']);
        $this->db->bind('s', 'Repayment Reminder');
        $this->db->bind('s', $message);
        $this->db->bind('s', 'payment');
        $this->db->execute();
        
        return [
            'success' => true,
            'message' => 'Repayment reminder sent',
            'sms' => $sms_result,
            'whatsapp' => $whatsapp_result
        ];
    }
    
    /**
     * Send delivery verification SMS
     * @param int $order_id Order ID
     * @param string $pin PIN
     * @return array
     */
    public function sendDeliveryVerificationPIN($order_id, $pin) {
        $this->db->query('SELECT o.*, u.phone FROM orders o 
                         JOIN users u ON o.retailer_id = u.id 
                         WHERE o.id = ?');
        $this->db->bind('i', $order_id);
        $order = $this->db->single();
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        $message = "Your delivery PIN is: $pin. Please provide this PIN to confirm delivery.";
        
        return $this->sms_gateway->sendSMS($order['phone'], $message);
    }
    
    /**
     * Send penalty notification
     * @param array $repayment Repayment record
     * @param float $penalty_amount Penalty amount
     * @param int $days_overdue Days overdue
     */
    public function sendPenaltyNotification($repayment, $penalty_amount, $days_overdue) {
        $this->db->query('SELECT phone FROM users WHERE id = ?');
        $this->db->bind('i', $repayment['retailer_id']);
        $user = $this->db->single();
        
        if (!$user) return;
        
        $message = "Late Fee Applied: Your payment is $days_overdue days overdue. A penalty of KES " . number_format($penalty_amount, 2) . " has been applied. New total due: KES " . number_format($repayment['amount_due'] + $penalty_amount, 2);
        
        $this->sms_gateway->sendSMS($user['phone'], $message);
        $this->whatsapp_gateway->sendMessage($user['phone'], $message);
    }
}
?>
