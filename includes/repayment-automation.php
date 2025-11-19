<?php
/**
 * Automated Repayment System with Penalties
 * Intelligent reminders, penalty calculations, and automated collections
 */

class RepaymentAutomation {
    private $db;
    private $penaltyRate = 0.05; // 5% daily penalty
    private $gracePeriodDays = 1;
    private $maxPenaltyDays = 30;

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Process daily repayments automation
     */
    public function processDailyAutomation() {
        $results = [
            'reminders_sent' => 0,
            'penalties_applied' => 0,
            'overdue_notices' => 0,
            'escalations' => 0,
            'auto_collections' => 0
        ];

        try {
            // 1. Send payment reminders
            $results['reminders_sent'] = $this->sendPaymentReminders();

            // 2. Apply penalties for overdue payments
            $results['penalties_applied'] = $this->applyPenalties();

            // 3. Send overdue notices
            $results['overdue_notices'] = $this->sendOverdueNotices();

            // 4. Escalate chronic defaults
            $results['escalations'] = $this->escalateDefaults();

            // 5. Attempt auto collections for eligible accounts
            $results['auto_collections'] = $this->attemptAutoCollections();

            // Log automation results
            $this->logAutomationResults($results);

            return $results;

        } catch (Exception $e) {
            error_log('Repayment automation failed: ' . $e->getMessage());
            return $results;
        }
    }

    /**
     * Send payment reminders via multiple channels
     */
    public function sendPaymentReminders() {
        $remindersSent = 0;

        // Get payments due tomorrow and today
        $upcomingPayments = $this->db->fetchAll("
            SELECT
                rs.*,
                l.borrower_id,
                l.loan_number,
                l.interest_rate,
                u.name as borrower_name,
                u.phone as borrower_phone,
                u.email as borrower_email,
                u.whatsapp_notifications,
                u.sms_notifications,
                u.email_notifications
            FROM repayment_schedule rs
            JOIN loans l ON rs.loan_id = l.id
            JOIN users u ON l.borrower_id = u.id
            WHERE rs.status = 'pending'
            AND rs.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND rs.reminder_sent = 0
            AND l.status = 'active'
        ");

        foreach ($upcomingPayments as $payment) {
            $sent = false;

            // WhatsApp reminder (primary)
            if ($payment['whatsapp_notifications']) {
                $sent = $this->sendWhatsAppReminder($payment);
            }

            // SMS reminder (backup)
            if (!$sent && $payment['sms_notifications']) {
                $sent = $this->sendSMSReminder($payment);
            }

            // Email reminder (additional)
            if ($payment['email_notifications']) {
                $this->sendEmailReminder($payment);
            }

            if ($sent) {
                // Mark reminder as sent
                $this->db->execute("
                    UPDATE repayment_schedule
                    SET reminder_sent = 1, reminder_sent_at = NOW()
                    WHERE id = ?
                ", [$payment['id']]);

                $remindersSent++;
            }
        }

        return $remindersSent;
    }

    /**
     * Apply penalties for overdue payments
     */
    public function applyPenalties() {
        $penaltiesApplied = 0;

        // Get overdue payments
        $overduePayments = $this->db->fetchAll("
            SELECT
                rs.*,
                l.borrower_id,
                l.loan_number,
                l.loan_amount,
                l.total_repayment,
                u.name as borrower_name,
                u.phone as borrower_phone,
                DATEDIFF(CURDATE(), rs.due_date) as days_overdue
            FROM repayment_schedule rs
            JOIN loans l ON rs.loan_id = l.id
            JOIN users u ON l.borrower_id = u.id
            WHERE rs.status = 'pending'
            AND rs.due_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)
            AND l.status = 'active'
            AND (rs.last_penalty_applied IS NULL OR
                 rs.last_penalty_applied < DATE_SUB(CURDATE(), INTERVAL 1 DAY))
        ", [$this->gracePeriodDays]);

        foreach ($overduePayments as $payment) {
            $daysOverdue = min($payment['days_overdue'], $this->maxPenaltyDays);

            // Calculate penalty amount
            $penaltyAmount = $payment['amount_due'] * $this->penaltyRate;
            $totalPenalty = $penaltyAmount * $daysOverdue;

            if ($totalPenalty > 0) {
                // Create penalty record
                $penaltyId = $this->db->execute("
                    INSERT INTO repayment_penalties (
                        repayment_schedule_id, loan_id, borrower_id,
                        base_amount, penalty_rate, days_overdue, penalty_amount,
                        total_penalty, applied_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $payment['id'], $payment['loan_id'], $payment['borrower_id'],
                    $payment['amount_due'], $this->penaltyRate, $daysOverdue,
                    $penaltyAmount, $totalPenalty
                ]);

                if ($penaltyId) {
                    // Update repayment schedule with penalty
                    $this->db->execute("
                        UPDATE repayment_schedule
                        SET penalty_amount = ?, total_due = amount_due + ?,
                        last_penalty_applied = CURDATE()
                        WHERE id = ?
                    ", [$totalPenalty, $totalPenalty, $payment['id']]);

                    // Send penalty notification
                    $this->sendPenaltyNotification($payment, $totalPenalty, $daysOverdue);

                    $penaltiesApplied++;
                }
            }
        }

        return $penaltiesApplied;
    }

    /**
     * Send overdue notices with increasing urgency
     */
    public function sendOverdueNotices() {
        $noticesSent = 0;

        // Get overdue payments for notice escalation
        $escalationLevels = [
            3 => ['level' => 'first_warning', 'urgency' => 'medium'],
            7 => ['level' => 'second_warning', 'urgency' => 'high'],
            14 => ['level' => 'final_warning', 'urgency' => 'critical'],
            21 => ['level' => 'collection_notice', 'urgency' => 'urgent']
        ];

        foreach ($escalationLevels as $daysOverdue => $config) {
            $payments = $this->db->fetchAll("
                SELECT
                    rs.*,
                    l.borrower_id,
                    l.loan_number,
                    u.name as borrower_name,
                    u.phone as borrower_phone,
                    u.email as borrower_email,
                    COALESCE(rs.penalty_amount, 0) as penalty_amount
                FROM repayment_schedule rs
                JOIN loans l ON rs.loan_id = l.id
                JOIN users u ON l.borrower_id = u.id
                WHERE rs.status = 'pending'
                AND DATEDIFF(CURDATE(), rs.due_date) = ?
                AND l.status = 'active'
                AND rs.{$config['level']}_sent = 0
            ", [$daysOverdue]);

            foreach ($payments as $payment) {
                $sent = $this->sendOverdueNotice($payment, $config);

                if ($sent) {
                    // Mark notice as sent
                    $this->db->execute("
                        UPDATE repayment_schedule
                        SET {$config['level']}_sent = 1, {$config['level']}_sent_at = NOW()
                        WHERE id = ?
                    ", [$payment['id']]);

                    $noticesSent++;
                }
            }
        }

        return $noticesSent;
    }

    /**
     * Escalate chronic defaults to collections
     */
    public function escalateDefaults() {
        $escalations = 0;

        // Get loans with chronic defaults
        $chronicDefaults = $this->db->fetchAll("
            SELECT
                l.*,
                u.name as borrower_name,
                u.phone as borrower_phone,
                COUNT(DISTINCT rs.id) as overdue_payments,
                SUM(CASE WHEN rs.status = 'pending' THEN rs.total_due ELSE 0 END) as total_overdue,
                DATEDIFF(CURDATE(), MIN(rs.due_date)) as max_days_overdue
            FROM loans l
            JOIN users u ON l.borrower_id = u.id
            JOIN repayment_schedule rs ON l.id = rs.loan_id
            WHERE l.status = 'active'
            AND rs.status = 'pending'
            AND rs.due_date < DATE_SUB(CURDATE(), INTERVAL 21 DAY)
            GROUP BY l.id
            HAVING overdue_payments >= 3 OR max_days_overdue >= 30
            AND l.collection_escalated = 0
        ");

        foreach ($chronicDefaults as $loan) {
            // Create collection case
            $caseId = $this->db->execute("
                INSERT INTO collection_cases (
                    loan_id, borrower_id, total_overdue, overdue_count,
                    max_days_overdue, severity, status, assigned_to, created_at
                ) VALUES (?, ?, ?, ?, ?, 'high', 'pending', NULL, NOW())
            ", [
                $loan['id'], $loan['borrower_id'], $loan['total_overdue'],
                $loan['overdue_payments'], $loan['max_days_overdue']
            ]);

            if ($caseId) {
                // Mark loan as escalated
                $this->db->execute("
                    UPDATE loans
                    SET collection_escalated = 1, collection_case_id = ?,
                    collection_escalated_at = NOW()
                    WHERE id = ?
                ", [$caseId, $loan['id']]);

                // Send escalation notification
                $this->sendEscalationNotification($loan);

                $escalations++;
            }
        }

        return $escalations;
    }

    /**
     * Attempt automatic collections for eligible accounts
     */
    public function attemptAutoCollections() {
        $collections = 0;

        // Get accounts eligible for auto-collection
        $eligibleAccounts = $this->db->fetchAll("
            SELECT
                l.*,
                u.name as borrower_name,
                u.phone as borrower_phone,
                u.mpay_bill_number,
                u.auto_payment_enabled,
                SUM(CASE WHEN rs.status = 'pending' THEN rs.total_due ELSE 0 END) as total_due,
                MIN(rs.due_date) as earliest_due_date
            FROM loans l
            JOIN users u ON l.borrower_id = u.id
            JOIN repayment_schedule rs ON l.id = rs.loan_id
            WHERE l.status = 'active'
            AND u.auto_payment_enabled = 1
            AND u.mpay_bill_number IS NOT NULL
            AND rs.status = 'pending'
            AND rs.due_date <= CURDATE()
            AND l.last_auto_collection < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY l.id
            HAVING total_due > 0
        ");

        foreach ($eligibleAccounts as $account) {
            // Attempt auto-collection
            $collectionResult = $this->processAutoCollection($account);

            if ($collectionResult['success']) {
                // Update loan with collection info
                $this->db->execute("
                    UPDATE loans
                    SET last_auto_collection = NOW(), last_auto_collection_amount = ?
                    WHERE id = ?
                ", [$collectionResult['amount_collected'], $account['id']]);

                $collections++;
            }
        }

        return $collections;
    }

    /**
     * Send WhatsApp payment reminder
     */
    private function sendWhatsAppReminder($payment) {
        try {
            require_once 'whatsapp-api.php';
            $whatsapp = new WhatsAppAPI($this->db);

            $message = $this->generateReminderMessage($payment);
            return $whatsapp->sendTextMessage($payment['borrower_phone'], $message, 'payment_reminder');

        } catch (Exception $e) {
            error_log('WhatsApp reminder failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send SMS payment reminder
     */
    private function sendSMSReminder($payment) {
        try {
            require_once 'sms-gateway.php';
            $sms = new SMSGateway();

            $message = $this->generateReminderMessage($payment, true);
            return $sms->send($payment['borrower_phone'], $message);

        } catch (Exception $e) {
            error_log('SMS reminder failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email payment reminder
     */
    private function sendEmailReminder($payment) {
        try {
            $subject = 'Payment Reminder - JuaKali Lend';
            $message = $this->generateEmailReminderMessage($payment);

            $headers = "From: noreply@juakali-lend.com\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            return mail($payment['borrower_email'], $subject, $message, $headers);

        } catch (Exception $e) {
            error_log('Email reminder failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate reminder message
     */
    private function generateReminderMessage($payment, $isSMS = false) {
        $dueDate = date('M j', strtotime($payment['due_date']));
        $amount = number_format($payment['total_due'], 0);

        if ($isSMS) {
            return "JuaKali Lend: Payment of KES {$amount} due on {$dueDate}. Loan #{$payment['loan_number']}. Pay via M-Pesa Paybill 123456 Account {$payment['loan_number']}.";
        }

        return "Hi {$payment['borrower_name']}, this is a friendly reminder that your loan payment of KES {$amount} is due on {$due_date}. Loan #{$payment['loan_number']}. Please ensure timely payment to avoid penalties. Thank you from JuaKali Lend.";
    }

    /**
     * Generate email reminder message
     */
    private function generateEmailReminderMessage($payment) {
        $dueDate = date('l, F j, Y', strtotime($payment['due_date']));
        $amount = number_format($payment['total_due'], 0);

        return "
            <html>
            <body>
                <h2>Payment Reminder - JuaKali Lend</h2>
                <p>Dear {$payment['borrower_name']},</p>
                <p>This is a friendly reminder that your loan payment is due:</p>
                <ul>
                    <li><strong>Amount:</strong> KES {$amount}</li>
                    <li><strong>Due Date:</strong> {$dueDate}</li>
                    <li><strong>Loan Number:</strong> #{$payment['loan_number']}</li>
                </ul>
                <p>You can make payment via:</p>
                <ul>
                    <li>M-Pesa Paybill: 123456</li>
                    <li>Account Number: {$payment['loan_number']}</li>
                    <li>Bank Transfer (details in your dashboard)</li>
                </ul>
                <p>Please ensure timely payment to avoid penalties and maintain good credit standing.</p>
                <p>Thank you for banking with JuaKali Lend.</p>
            </body>
            </html>
        ";
    }

    /**
     * Send penalty notification
     */
    private function sendPenaltyNotification($payment, $penaltyAmount, $daysOverdue) {
        try {
            require_once 'whatsapp-api.php';
            $whatsapp = new WhatsAppAPI($this->db);

            $message = "Hi {$payment['borrower_name']}, a penalty of KES " . number_format($penaltyAmount, 0) . " has been applied to your overdue payment. Your loan is {$daysOverdue} days overdue. Please pay immediately to avoid additional penalties. Loan #{$payment['loan_number']}.";

            return $whatsapp->sendTextMessage($payment['borrower_phone'], $message);

        } catch (Exception $e) {
            error_log('Penalty notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Send overdue notice with escalation
     */
    private function sendOverdueNotice($payment, $config) {
        try {
            require_once 'whatsapp-api.php';
            $whatsapp = new WhatsAppAPI($this->db);

            $urgencyMessages = [
                'first_warning' => 'URGENT: Your payment is now overdue. Please pay immediately.',
                'second_warning' => 'FINAL NOTICE: Your account is seriously overdue. Immediate payment required.',
                'final_warning' => 'CRITICAL: Your account will be sent to collections if not paid within 48 hours.',
                'collection_notice' => 'COLLECTION NOTICE: Your account has been escalated to our collections department.'
            ];

            $message = $urgencyMessages[$config['level']] ?? 'Payment overdue notice.';
            $message .= " Loan #{$payment['loan_number']}. Overdue amount: KES " . number_format($payment['total_due'], 0);

            return $whatsapp->sendTextMessage($payment['borrower_phone'], $message);

        } catch (Exception $e) {
            error_log('Overdue notice failed: ' . $e->getMessage());
        }
    }

    /**
     * Send escalation notification
     */
    private function sendEscalationNotification($loan) {
        try {
            require_once 'whatsapp-api.php';
            $whatsapp = new WhatsAppAPI($this->db);

            $message = "CRITICAL: Your account has been escalated to collections due to chronic non-payment. Total overdue: KES " . number_format($loan['total_overdue'], 0) . ". Please contact us immediately to resolve this issue. Loan #{$loan['loan_number']}.";

            return $whatsapp->sendTextMessage($loan['borrower_phone'], $message);

        } catch (Exception $e) {
            error_log('Escalation notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Process automatic collection
     */
    private function processAutoCollection($account) {
        try {
            require_once 'mpesa.php';
            $mpesa = new M_Pesa();

            // Initiate STK Push for auto-collection
            $result = $mpesa->sendSTKPush(
                $account['mpay_bill_number'],
                $account['total_due'],
                'AUTO' . $account['id'],
                'Auto-payment for loan #' . $account['loan_number']
            );

            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                // Log auto-collection attempt
                $this->db->execute("
                    INSERT INTO auto_collection_attempts (
                        loan_id, borrower_id, amount, checkout_request_id,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, 'initiated', NOW())
                ", [
                    $account['id'], $account['borrower_id'], $account['total_due'],
                    $result['CheckoutRequestID']
                ]);

                return [
                    'success' => true,
                    'amount_collected' => 0, // Will be updated on callback
                    'checkout_request_id' => $result['CheckoutRequestID']
                ];
            }

            return ['success' => false, 'error' => $result['errorMessage'] ?? 'Unknown error'];

        } catch (Exception $e) {
            error_log('Auto-collection failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Log automation results
     */
    private function logAutomationResults($results) {
        try {
            $this->db->execute("
                INSERT INTO repayment_automation_logs (
                    reminders_sent, penalties_applied, overdue_notices,
                    escalations, auto_collections, execution_time, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $results['reminders_sent'],
                $results['penalties_applied'],
                $results['overdue_notices'],
                $results['escalations'],
                $results['auto_collections']
            ]);

        } catch (Exception $e) {
            error_log('Failed to log automation results: ' . $e->getMessage());
        }
    }

    /**
     * Get repayment analytics
     */
    public function getRepaymentAnalytics($lenderId = null, $dateRange = 30) {
        $whereClause = '';
        $params = [$dateRange];

        if ($lenderId) {
            $whereClause = 'AND l.lender_id = ?';
            $params[] = $lenderId;
        }

        return $this->db->fetchAll("
            SELECT
                DATE(rs.created_at) as date,
                COUNT(*) as total_payments,
                SUM(CASE WHEN rs.status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
                SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END) as total_collected,
                SUM(CASE WHEN rs.status = 'pending' AND rs.due_date < CURDATE() THEN rs.total_due ELSE 0 END) as overdue_amount,
                COUNT(CASE WHEN rs.status = 'pending' AND rs.due_date < CURDATE() THEN 1 END) as overdue_count
            FROM repayment_schedule rs
            JOIN loans l ON rs.loan_id = l.id
            WHERE rs.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereClause}
            GROUP BY DATE(rs.created_at)
            ORDER BY date DESC
            LIMIT ?
        ", array_merge($params, [$dateRange]));
    }

    /**
     * Get penalty statistics
     */
    public function getPenaltyStatistics($lenderId = null, $dateRange = 30) {
        $whereClause = '';
        $params = [$dateRange];

        if ($lenderId) {
            $whereClause = 'AND rp.loan_id IN (SELECT id FROM loans WHERE lender_id = ?)';
            $params[] = $lenderId;
        }

        return $this->db->fetchOne("
            SELECT
                COUNT(*) as total_penalties,
                SUM(rp.total_penalty) as total_penalty_amount,
                AVG(rp.days_overdue) as avg_days_overdue,
                COUNT(CASE WHEN rp.days_overdue <= 7 THEN 1 END) as minor_penalties,
                COUNT(CASE WHEN rp.days_overdue > 7 AND rp.days_overdue <= 21 THEN 1 END) as moderate_penalties,
                COUNT(CASE WHEN rp.days_overdue > 21 THEN 1 END) as severe_penalties
            FROM repayment_penalties rp
            WHERE rp.applied_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            {$whereClause}
        ", $params);
    }
}
?>