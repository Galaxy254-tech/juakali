<?php
/**
 * Notification Scheduler
 * Handles scheduled notifications (daily reminders, alerts, etc.)
 */
class NotificationScheduler {
    private $db;
    private $notification_manager;
    
    public function __construct($database) {
        $this->db = $database;
        $this->notification_manager = new NotificationManager($database);
    }
    
    /**
     * Send daily repayment reminders
     * @return array
     */
    public function sendDailyRepaymentReminders() {
        // Get all pending repayments due today or overdue
        $this->db->query('SELECT DISTINCT l.id FROM repayment_schedule rs
                         JOIN loans l ON rs.loan_id = l.id
                         WHERE rs.status IN ("pending", "overdue")
                         AND DATE(rs.due_date) <= CURDATE()');
        $repayments = $this->db->resultSet();
        
        $results = [];
        foreach ($repayments as $repayment) {
            $results[] = $this->notification_manager->sendRepaymentReminder($repayment['id']);
        }
        
        return ['success' => true, 'count' => count($results), 'results' => $results];
    }
    
    /**
     * Send loan delinquency alerts
     * @return array
     */
    public function sendDelinquencyAlerts() {
        // Get overdue repayments (more than 7 days)
        $this->db->query('SELECT DISTINCT l.id, l.retailer_id, u.phone, rs.amount_due, rs.due_date
                         FROM repayment_schedule rs
                         JOIN loans l ON rs.loan_id = l.id
                         JOIN users u ON l.retailer_id = u.id
                         WHERE rs.status = "pending"
                         AND DATEDIFF(CURDATE(), rs.due_date) > 7');
        $overdue_loans = $this->db->resultSet();
        
        $sms_gateway = new SMSGateway('mpesa');
        $results = [];
        
        foreach ($overdue_loans as $loan) {
            $days_overdue = intval((time() - strtotime($loan['due_date'])) / 86400);
            $message = "ALERT: Your loan payment is $days_overdue days overdue. Amount due: KES " . number_format($loan['amount_due'], 2) . ". Please remit payment immediately.";
            
            $result = $sms_gateway->sendSMS($loan['phone'], $message);
            $results[] = $result;
            
            // Store notification
            $this->db->query('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
            $this->db->bind('i', $loan['retailer_id']);
            $this->db->bind('s', 'Loan Delinquency Alert');
            $this->db->bind('s', $message);
            $this->db->bind('s', 'system');
            $this->db->execute();
        }
        
        return ['success' => true, 'count' => count($results), 'results' => $results];
    }
    
    /**
     * Send bulk SMS campaign
     * @param array $user_ids User IDs to send to
     * @param string $message Message to send
     * @return array
     */
    public function sendBulkSMS($user_ids, $message) {
        if (empty($user_ids) || empty($message)) {
            return ['success' => false, 'message' => 'User IDs and message required'];
        }
        
        $sms_gateway = new SMSGateway('mpesa');
        $results = [];
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($user_ids as $user_id) {
            $this->db->query('SELECT phone FROM users WHERE id = ?');
            $this->db->bind('i', $user_id);
            $user = $this->db->single();
            
            if ($user) {
                $result = $sms_gateway->sendSMS($user['phone'], $message);
                if ($result['success']) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
                $results[] = $result;
            }
        }
        
        return [
            'success' => $failed_count === 0,
            'sent' => $sent_count,
            'failed' => $failed_count,
            'total' => count($user_ids),
            'results' => $results
        ];
    }
}
?>
