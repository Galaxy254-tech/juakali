<?php
/**
 * SMS Notification System for JuaKali Lend
 * Supports multiple SMS gateways (Africa's Talking, Twilio, local providers)
 */

class SMSNotification {
    private $provider;
    private $config;

    public function __construct($provider = 'africastalking') {
        $this->provider = $provider;
        $this->loadConfig();
    }

    private function loadConfig() {
        // Load configuration from database or config file
        try {
            $db = Database::getInstance();
            $config = $db->fetchOne('SELECT * FROM system_settings WHERE setting_key = "sms_config"');
            if ($config) {
                $this->config = json_decode($config['setting_value'], true);
            } else {
                // Default configuration
                $this->config = [
                    'africastalking' => [
                        'username' => '',
                        'api_key' => '',
                        'sender_id' => 'JuaKaliLend'
                    ],
                    'twilio' => [
                        'account_sid' => '',
                        'auth_token' => '',
                        'from_number' => ''
                    ]
                ];
            }
        } catch (Exception $e) {
            $this->config = [];
        }
    }

    /**
     * Send SMS message
     */
    public function sendSMS($phoneNumber, $message, $priority = 'normal') {
        try {
            // Validate phone number
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            if (!$this->validatePhoneNumber($phoneNumber)) {
                throw new Exception('Invalid phone number format');
            }

            // Log SMS attempt
            $this->logSMS($phoneNumber, $message, $priority);

            switch ($this->provider) {
                case 'africastalking':
                    return $this->sendAfricaTalkingSMS($phoneNumber, $message);
                case 'twilio':
                    return $this->sendTwilioSMS($phoneNumber, $message);
                default:
                    throw new Exception('Unsupported SMS provider');
            }

        } catch (Exception $e) {
            $this->logError($phoneNumber, $message, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send SMS via Africa's Talking API
     */
    private function sendAfricaTalkingSMS($phoneNumber, $message) {
        $config = $this->config['africastalking'];

        if (empty($config['username']) || empty($config['api_key'])) {
            throw new Exception('Africa\'s Talking credentials not configured');
        }

        $url = 'https://api.sandbox.africastalking.com/version1/messaging';

        $data = [
            'username' => $config['username'],
            'to' => $phoneNumber,
            'message' => $message,
            'from' => $config['sender_id']
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'apiKey: ' . $config['api_key']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new Exception('Failed to send SMS: HTTP ' . $httpCode);
        }

        if (isset($result['status']) && $result['status'] === 'success') {
            return [
                'success' => true,
                'messageId' => $result['data']['SMSMessageData']['MessageId'] ?? null,
                'cost' => $result['data']['SMSMessageData']['Cost'] ?? 0
            ];
        } else {
            throw new Exception('SMS sending failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    /**
     * Send SMS via Twilio API
     */
    private function sendTwilioSMS($phoneNumber, $message) {
        $config = $this->config['twilio'];

        if (empty($config['account_sid']) || empty($config['auth_token'])) {
            throw new Exception('Twilio credentials not configured');
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $config['account_sid'] . '/Messages.json';

        $data = [
            'From' => $config['from_number'],
            'To' => $phoneNumber,
            'Body' => $message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $config['account_sid'] . ':' . $config['auth_token']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 201) {
            throw new Exception('Failed to send SMS: HTTP ' . $httpCode);
        }

        if (isset($result['sid'])) {
            return [
                'success' => true,
                'messageId' => $result['sid'],
                'cost' => $result['price'] ?? 0
            ];
        } else {
            throw new Exception('SMS sending failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    /**
     * Send bulk SMS to multiple recipients
     */
    public function sendBulkSMS($phoneNumbers, $message, $priority = 'normal') {
        $results = [];
        $errors = [];

        foreach ($phoneNumbers as $phoneNumber) {
            try {
                $result = $this->sendSMS($phoneNumber, $message, $priority);
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'phone' => $phoneNumber,
                    'error' => $e->getMessage()
                ];
            }

            // Add delay between SMS to avoid rate limiting
            usleep(100000); // 0.1 second delay
        }

        return [
            'success' => $results,
            'errors' => $errors,
            'total_sent' => count($results),
            'total_failed' => count($errors)
        ];
    }

    /**
     * Send scheduled SMS
     */
    public function scheduleSMS($phoneNumber, $message, $scheduleTime, $priority = 'normal') {
        try {
            $db = Database::getInstance();

            $db->execute('INSERT INTO scheduled_sms (phone_number, message, schedule_time, priority, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$phoneNumber, $message, $scheduleTime, $priority, 'pending']);

            return [
                'success' => true,
                'schedule_id' => $db->lastInsertId()
            ];

        } catch (Exception $e) {
            throw new Exception('Failed to schedule SMS: ' . $e->getMessage());
        }
    }

    /**
     * Send loan repayment reminder
     */
    public function sendRepaymentReminder($loanId, $userId) {
        try {
            $db = Database::getInstance();

            // Get loan and user details
            $loan = $db->fetchOne('
                SELECT l.*, u.first_name, u.phone
                FROM loans l
                JOIN users u ON l.retailer_id = u.id
                WHERE l.id = ?
            ', [$loanId]);

            if (!$loan) {
                throw new Exception('Loan not found');
            }

            // Get next payment due
            $payment = $db->fetchOne('
                SELECT * FROM repayment_schedule
                WHERE loan_id = ? AND status = "pending" AND due_date <= CURDATE()
                ORDER BY due_date ASC
                LIMIT 1
            ', [$loanId]);

            if (!$payment) {
                return ['success' => false, 'message' => 'No pending payments found'];
            }

            $message = "Hi {$loan['first_name']}, this is a reminder from JuaKali Lend. Your daily payment of KES " .
                     number_format($payment['amount_due'], 2) . " is due today ({$payment['due_date']}). " .
                     "Please pay via M-Pesa Paybill 123456, Account {$loan['loan_number']}. " .
                     "Reply HELP for assistance.";

            return $this->sendSMS($loan['phone'], $message, 'high');

        } catch (Exception $e) {
            throw new Exception('Failed to send repayment reminder: ' . $e->getMessage());
        }
    }

    /**
     * Send loan approval notification
     */
    public function sendLoanApprovalNotification($loanId, $userId) {
        try {
            $db = Database::getInstance();

            $loan = $db->fetchOne('
                SELECT l.*, u.first_name, u.phone
                FROM loans l
                JOIN users u ON l.retailer_id = u.id
                WHERE l.id = ?
            ', [$loanId]);

            if (!$loan) {
                throw new Exception('Loan not found');
            }

            $message = "Congratulations {$loan['first_name']}! Your loan request of KES " .
                     number_format($loan['loan_amount'], 2) . " has been approved. " .
                     "Your goods will be delivered soon. Daily payment: KES " .
                     number_format($loan['total_amount'] / $loan['loan_term'], 2) .
                     " for {$loan['loan_term']} days.";

            return $this->sendSMS($loan['phone'], $message, 'high');

        } catch (Exception $e) {
            throw new Exception('Failed to send approval notification: ' . $e->getMessage());
        }
    }

    /**
     * Send welcome message to new users
     */
    public function sendWelcomeMessage($userId) {
        try {
            $db = Database::getInstance();

            $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);

            if (!$user) {
                throw new Exception('User not found');
            }

            $message = "Welcome to JuaKali Lend, {$user['first_name']}! We're excited to help you grow your business. " .
                     "Complete your KYC verification to unlock higher credit limits. " .
                     "Reply START to begin your loan application.";

            return $this->sendSMS($user['phone'], $message, 'normal');

        } catch (Exception $e) {
            throw new Exception('Failed to send welcome message: ' . $e->getMessage());
        }
    }

    /**
     * Process scheduled SMS
     */
    public function processScheduledSMS() {
        try {
            $db = Database::getInstance();

            // Get pending scheduled SMS that are due
            $scheduled = $db->fetchAll('
                SELECT * FROM scheduled_sms
                WHERE status = "pending" AND schedule_time <= NOW()
                ORDER BY schedule_time ASC
                LIMIT 100
            ');

            $processed = 0;
            $failed = 0;

            foreach ($scheduled as $sms) {
                try {
                    $result = $this->sendSMS($sms['phone_number'], $sms['message'], $sms['priority']);

                    if ($result['success']) {
                        $db->execute('UPDATE scheduled_sms SET status = "sent", sent_at = NOW() WHERE id = ?', [$sms['id']]);
                        $processed++;
                    } else {
                        $db->execute('UPDATE scheduled_sms SET status = "failed", error_message = ?, sent_at = NOW() WHERE id = ?', ['Sending failed', $sms['id']]);
                        $failed++;
                    }

                } catch (Exception $e) {
                    $db->execute('UPDATE scheduled_sms SET status = "failed", error_message = ?, sent_at = NOW() WHERE id = ?', [$e->getMessage(), $sms['id']]);
                    $failed++;
                }
            }

            return [
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($scheduled)
            ];

        } catch (Exception $e) {
            throw new Exception('Failed to process scheduled SMS: ' . $e->getMessage());
        }
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove all non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

        // Add Kenya country code if missing
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) === '7') {
            $phoneNumber = '254' . $phoneNumber;
        } elseif (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 2) === '07') {
            $phoneNumber = '254' . substr($phoneNumber, 2);
        }

        return $phoneNumber;
    }

    /**
     * Validate phone number format
     */
    private function validatePhoneNumber($phoneNumber) {
        // Basic validation for Kenyan phone numbers
        return preg_match('/^254[17]\d{8}$/', $phoneNumber);
    }

    /**
     * Log SMS attempts
     */
    private function logSMS($phoneNumber, $message, $priority) {
        try {
            $db = Database::getInstance();
            $db->execute('INSERT INTO sms_logs (phone_number, message, provider, priority, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$phoneNumber, $message, $this->provider, $priority, 'sent']);
        } catch (Exception $e) {
            // Log error silently
        }
    }

    /**
     * Log SMS errors
     */
    private function logError($phoneNumber, $message, $error) {
        try {
            $db = Database::getInstance();
            $db->execute('INSERT INTO sms_logs (phone_number, message, provider, priority, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$phoneNumber, $message, $this->provider, 'normal', 'failed', $error]);
        } catch (Exception $e) {
            // Log error silently
        }
    }

    /**
     * Get SMS statistics
     */
    public function getSMSStats($startDate = null, $endDate = null) {
        try {
            $db = Database::getInstance();

            $whereClause = '';
            $params = [];

            if ($startDate && $endDate) {
                $whereClause = 'WHERE created_at BETWEEN ? AND ?';
                $params = [$startDate, $endDate];
            }

            $stats = $db->fetchOne("
                SELECT
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successfully_sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'sent' THEN cost ELSE 0 END) as total_cost
                FROM sms_logs
                $whereClause
            ", $params);

            return $stats;

        } catch (Exception $e) {
            return [
                'total_sent' => 0,
                'successfully_sent' => 0,
                'failed' => 0,
                'total_cost' => 0
            ];
        }
    }
}

// Utility function to send SMS notifications
function sendSMSNotification($phoneNumber, $message, $priority = 'normal') {
    try {
        $sms = new SMSNotification();
        return $sms->sendSMS($phoneNumber, $message, $priority);
    } catch (Exception $e) {
        error_log('SMS notification failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Send loan-related notifications
function sendLoanNotification($type, $loanId, $userId = null) {
    try {
        $sms = new SMSNotification();

        switch ($type) {
            case 'approval':
                return $sms->sendLoanApprovalNotification($loanId, $userId);
            case 'reminder':
                return $sms->sendRepaymentReminder($loanId, $userId);
            case 'welcome':
                return $sms->sendWelcomeMessage($userId);
            default:
                throw new Exception('Unknown notification type: ' . $type);
        }

    } catch (Exception $e) {
        error_log('Loan notification failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>