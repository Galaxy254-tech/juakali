<?php
/**
 * Penalty Engine
 * Automatically calculates and applies penalties for overdue repayments
 */
class PenaltyEngine {
    private $db;
    private $notification_manager;
    
    // Penalty configuration
    private $config = [
        'daily_late_fee_percentage' => 0.5, // 0.5% per day
        'max_penalty_percentage' => 25, // Maximum penalty is 25% of original amount
        'escalation_days' => [
            7 => 0.5,    // After 7 days: 0.5% per day
            14 => 1.0,   // After 14 days: 1.0% per day
            30 => 2.0    // After 30 days: 2.0% per day
        ],
        'minimum_penalty' => 50 // Minimum penalty is KES 50
    ];
    
    public function __construct($database) {
        $this->db = $database;
        $this->notification_manager = new NotificationManager($database);
    }
    
    /**
     * Process overdue repayments and apply penalties
     * @return array Processing results
     */
    public function processOverdueRepayments() {
        // Get all overdue repayments
        $this->db->query('SELECT rs.*, l.retailer_id, l.loan_amount FROM repayment_schedule rs
                         JOIN loans l ON rs.loan_id = l.id
                         WHERE rs.status IN ("pending", "overdue")
                         AND rs.due_date < CURDATE()');
        $overdue_repayments = $this->db->resultSet();
        
        $results = [];
        foreach ($overdue_repayments as $repayment) {
            $result = $this->calculateAndApplyPenalty($repayment);
            $results[] = $result;
        }
        
        return [
            'success' => true,
            'processed_count' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * Calculate penalty for overdue repayment
     * @param array $repayment Repayment record
     * @return array Penalty calculation
     */
    public function calculateAndApplyPenalty($repayment) {
        $due_date = strtotime($repayment['due_date']);
        $days_overdue = intval((time() - $due_date) / 86400);
        
        if ($days_overdue <= 0) {
            return ['success' => false, 'message' => 'Payment not yet overdue'];
        }
        
        // Calculate penalty based on escalation
        $daily_rate = $this->getEscalatedRate($days_overdue);
        $penalty_amount = ($repayment['amount_due'] * $daily_rate * $days_overdue) / 100;
        
        // Apply minimum and maximum penalties
        $penalty_amount = max($penalty_amount, $this->config['minimum_penalty']);
        $max_penalty = ($repayment['amount_due'] * $this->config['max_penalty_percentage']) / 100;
        $penalty_amount = min($penalty_amount, $max_penalty);
        
        // Update repayment record with penalty
        $this->db->query('UPDATE repayment_schedule SET 
                         amount_due = amount_due + ?,
                         updated_at = NOW()
                         WHERE id = ?');
        $this->db->bind('d', $penalty_amount);
        $this->db->bind('i', $repayment['id']);
        $this->db->execute();
        
        // Create penalty record
        $this->db->query('INSERT INTO penalty_records (repayment_id, loan_id, penalty_amount, days_overdue, reason, applied_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())');
        $this->db->bind('i', $repayment['id']);
        $this->db->bind('i', $repayment['loan_id']);
        $this->db->bind('d', $penalty_amount);
        $this->db->bind('i', $days_overdue);
        $this->db->bind('s', "Late fee: $days_overdue days overdue at " . number_format($daily_rate, 2) . "% per day");
        $this->db->execute();
        
        // Update loan status if severely overdue
        if ($days_overdue > 60) {
            $this->markLoanAsDefaulted($repayment['loan_id']);
        }
        
        // Send notification
        $this->notification_manager->sendPenaltyNotification($repayment, $penalty_amount, $days_overdue);
        
        return [
            'success' => true,
            'repayment_id' => $repayment['id'],
            'days_overdue' => $days_overdue,
            'penalty_amount' => $penalty_amount,
            'new_total_due' => $repayment['amount_due'] + $penalty_amount
        ];
    }
    
    /**
     * Get escalated penalty rate based on days overdue
     * @param int $days Days overdue
     * @return float Penalty rate percentage
     */
    private function getEscalatedRate($days) {
        $rate = $this->config['daily_late_fee_percentage'];
        
        foreach ($this->config['escalation_days'] as $threshold => $escalated_rate) {
            if ($days >= $threshold) {
                $rate = $escalated_rate;
            }
        }
        
        return $rate;
    }
    
    /**
     * Mark loan as defaulted
     * @param int $loan_id Loan ID
     */
    private function markLoanAsDefaulted($loan_id) {
        $this->db->query('UPDATE loans SET status = ? WHERE id = ? AND status != ?');
        $this->db->bind('s', 'defaulted');
        $this->db->bind('i', $loan_id);
        $this->db->bind('s', 'defaulted');
        $this->db->execute();
        
        // Update credit score
        $this->db->query('SELECT retailer_id FROM loans WHERE id = ?');
        $this->db->bind('i', $loan_id);
        $loan = $this->db->single();
        
        if ($loan) {
            $this->db->query('UPDATE credit_scores SET 
                             default_count = default_count + 1,
                             score = GREATEST(score - 50, 0)
                             WHERE retailer_id = ?');
            $this->db->bind('i', $loan['retailer_id']);
            $this->db->execute();
        }
    }
    
    /**
     * Get penalty summary for retailer
     * @param int $retailer_id Retailer ID
     * @return array Penalty summary
     */
    public function getPenaltySummary($retailer_id) {
        $this->db->query('SELECT 
                         COUNT(*) as total_penalties,
                         SUM(penalty_amount) as total_penalty_amount,
                         AVG(penalty_amount) as average_penalty
                         FROM penalty_records pr
                         JOIN repayment_schedule rs ON pr.repayment_id = rs.id
                         JOIN loans l ON pr.loan_id = l.id
                         WHERE l.retailer_id = ?');
        $this->db->bind('i', $retailer_id);
        $summary = $this->db->single();
        
        // Get recent penalties
        $this->db->query('SELECT * FROM penalty_records pr
                         JOIN repayment_schedule rs ON pr.repayment_id = rs.id
                         WHERE rs.loan_id IN (SELECT id FROM loans WHERE retailer_id = ?)
                         ORDER BY pr.applied_at DESC LIMIT 10');
        $this->db->bind('i', $retailer_id);
        $recent_penalties = $this->db->resultSet();
        
        return [
            'summary' => $summary,
            'recent' => $recent_penalties
        ];
    }
    
    /**
     * Waive penalty (admin only)
     * @param int $penalty_id Penalty record ID
     * @param string $reason Reason for waiver
     * @param int $admin_id Admin user ID
     * @return array Result
     */
    public function waivePenalty($penalty_id, $reason, $admin_id) {
        $this->db->query('SELECT * FROM penalty_records WHERE id = ?');
        $this->db->bind('i', $penalty_id);
        $penalty = $this->db->single();
        
        if (!$penalty) {
            return ['success' => false, 'message' => 'Penalty not found'];
        }
        
        // Reduce repayment amount
        $this->db->query('UPDATE repayment_schedule SET amount_due = amount_due - ? WHERE id = ?');
        $this->db->bind('d', $penalty['penalty_amount']);
        $this->db->bind('i', $penalty['repayment_id']);
        $this->db->execute();
        
        // Mark penalty as waived
        $this->db->query('UPDATE penalty_records SET waived = 1, waived_reason = ?, waived_by = ?, waived_at = NOW() WHERE id = ?');
        $this->db->bind('s', $reason);
        $this->db->bind('i', $admin_id);
        $this->db->bind('i', $penalty_id);
        $this->db->execute();
        
        return [
            'success' => true,
            'message' => 'Penalty waived successfully',
            'amount_refunded' => $penalty['penalty_amount']
        ];
    }
}
?>
