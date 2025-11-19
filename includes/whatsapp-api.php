<?php
/**
 * WhatsApp Business API Integration for JuaKali Lend
 * Supports notifications, alerts, and customer communication via WhatsApp
 */

class WhatsAppAPI {
    private $apiVersion = 'v18.0';
    private $baseUrl;
    private $accessToken;
    private $phoneNumberId;
    private $businessAccountId;
    private $webhookVerifyToken;
    private $db;

    public function __construct($database = null) {
        $this->db = $database ?? Database::getInstance();
        $this->loadConfiguration();
    }

    private function loadConfiguration() {
        try {
            $config = $this->db->fetchOne('SELECT * FROM system_settings WHERE setting_key = "whatsapp_config"');

            if ($config) {
                $settings = json_decode($config['setting_value'], true);
                $this->accessToken = $settings['access_token'] ?? '';
                $this->phoneNumberId = $settings['phone_number_id'] ?? '';
                $this->businessAccountId = $settings['business_account_id'] ?? '';
                $this->webhookVerifyToken = $settings['webhook_verify_token'] ?? '';
                $this->baseUrl = $settings['base_url'] ?? 'https://graph.facebook.com';
            }

            // Environment-specific settings
            $environment = $settings['environment'] ?? 'sandbox';

            if ($environment === 'sandbox') {
                $this->baseUrl = 'https://graph.facebook.com';
            } else {
                $this->baseUrl = 'https://graph.facebook.com';
            }

        } catch (Exception $e) {
            error_log('Failed to load WhatsApp configuration: ' . $e->getMessage());
            $this->setDefaults();
        }
    }

    private function setDefaults() {
        $this->accessToken = '';
        $this->phoneNumberId = '';
        $this->businessAccountId = '';
        $this->webhookVerifyToken = 'juakali_whatsapp_webhook_token_2024';
        $this->baseUrl = 'https://graph.facebook.com';
    }

    /**
     * Send a text message via WhatsApp
     */
    public function sendTextMessage($recipientPhone, $message, $templateName = null) {
        try {
            if (empty($this->accessToken) || empty($this->phoneNumberId)) {
                throw new Exception('WhatsApp API not configured');
            }

            // Format phone number
            $formattedPhone = $this->formatPhoneNumber($recipientPhone);

            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            if ($templateName) {
                // Send template message
                $data = [
                    'messaging_product' => 'whatsapp',
                    'to' => $formattedPhone,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => [
                            'code' => 'en'
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    [
                                        'type' => 'text',
                                        'text' => $message
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
            } else {
                // Send simple text message (only for testing)
                $data = [
                    'messaging_product' => 'whatsapp',
                    'to' => $formattedPhone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message
                    ]
                ];
            }

            $response = $this->makeApiRequest('POST', $url, $data);

            // Log the message
            $this->logWhatsAppMessage($formattedPhone, $message, 'text', $response);

            return [
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null,
                'response' => $response
            ];

        } catch (Exception $e) {
            $this->logWhatsAppError($recipientPhone, $message, 'text', $e->getMessage());
            throw new Exception('Failed to send WhatsApp message: ' . $e->getMessage());
        }
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder($userId, $loanId, $amount, $dueDate) {
        try {
            $user = $this->db->fetchOne('SELECT name, phone FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }

            $message = $this->getPaymentReminderTemplate($user['name'], $amount, $dueDate);
            $templateName = 'payment_reminder';

            return $this->sendTextMessage($user['phone'], $message, $templateName);

        } catch (Exception $e) {
            error_log('Failed to send payment reminder: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send loan approval notification
     */
    public function sendLoanApprovalNotification($userId, $loanId, $amount, $terms) {
        try {
            $user = $this->db->fetchOne('SELECT name, phone FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }

            $message = $this->getLoanApprovalTemplate($user['name'], $amount, $terms);
            $templateName = 'loan_approved';

            return $this->sendTextMessage($user['phone'], $message, $templateName);

        } catch (Exception $e) {
            error_log('Failed to send loan approval notification: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send fraud alert to admin
     */
    public function sendFraudAlert($userId, $riskLevel, $description) {
        try {
            $adminPhones = $this->getAdminPhoneNumbers();

            foreach ($adminPhones as $adminPhone) {
                $message = $this->getFraudAlertTemplate($userId, $riskLevel, $description);
                $this->sendTextMessage($adminPhone, $message, 'fraud_alert');
            }

            return ['success' => true];

        } catch (Exception $e) {
            error_log('Failed to send fraud alert: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send delivery notification
     */
    public function sendDeliveryNotification($userId, $orderId, $deliveryStatus, $qrCode = null) {
        try {
            $user = $this->db->fetchOne('SELECT name, phone FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }

            $message = $this->getDeliveryNotificationTemplate($user['name'], $orderId, $deliveryStatus, $qrCode);
            $templateName = 'delivery_update';

            return $this->sendTextMessage($user['phone'], $message, $templateName);

        } catch (Exception $e) {
            error_log('Failed to send delivery notification: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send OTP verification
     */
    public function sendOTPVerification($userId, $otp, $purpose = 'login') {
        try {
            $user = $this->db->fetchOne('SELECT name, phone FROM users WHERE id = ?', [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }

            $message = $this->getOTPTemplate($user['name'], $otp, $purpose);
            $templateName = 'otp_verification';

            return $this->sendTextMessage($user['phone'], $message, $templateName);

        } catch (Exception $e) {
            error_log('Failed to send OTP verification: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send interactive message with buttons
     */
    public function sendInteractiveMessage($recipientPhone, $headerText, $bodyText, $buttons) {
        try {
            $formattedPhone = $this->formatPhoneNumber($recipientPhone);

            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $buttonObjects = [];
            foreach ($buttons as $index => $button) {
                $buttonObjects[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $button['id'] ?? "button_$index",
                        'title' => $button['title']
                    ]
                ];
            }

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $formattedPhone,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'header' => [
                        'type' => 'text',
                        'text' => $headerText
                    ],
                    'body' => [
                        'text' => $bodyText
                    ],
                    'action' => [
                        'buttons' => $buttonObjects
                    ]
                ]
            ];

            $response = $this->makeApiRequest('POST', $url, $data);

            return [
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null,
                'response' => $response
            ];

        } catch (Exception $e) {
            error_log('Failed to send interactive message: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send media message (image, document, etc.)
     */
    public function sendMediaMessage($recipientPhone, $mediaUrl, $mediaType, $caption = '') {
        try {
            $formattedPhone = $this->formatPhoneNumber($recipientPhone);

            $url = "{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $formattedPhone,
                'type' => $mediaType,
                $mediaType => [
                    'link' => $mediaUrl,
                    'caption' => $caption
                ]
            ];

            $response = $this->makeApiRequest('POST', $url, $data);

            return [
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null,
                'response' => $response
            ];

        } catch (Exception $e) {
            error_log('Failed to send media message: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify webhook
     */
    public function verifyWebhook($hubMode, $hubVerifyToken, $hubChallenge) {
        if ($hubMode === 'subscribe' && $hubVerifyToken === $this->webhookVerifyToken) {
            return $hubChallenge;
        }
        return false;
    }

    /**
     * Process incoming webhook messages
     */
    public function processWebhook($webhookData) {
        try {
            if (isset($webhookData['entry'])) {
                foreach ($webhookData['entry'] as $entry) {
                    if (isset($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            if (isset($change['value']['messages'])) {
                                foreach ($change['value']['messages'] as $message) {
                                    $this->processIncomingMessage($message);
                                }
                            }
                        }
                    }
                }
            }

            return ['success' => true, 'message' => 'Webhook processed successfully'];

        } catch (Exception $e) {
            error_log('Failed to process webhook: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processIncomingMessage($message) {
        $phoneNumber = $message['from'] ?? null;
        $messageId = $message['id'] ?? null;
        $timestamp = $message['timestamp'] ?? null;
        $messageType = $message['type'] ?? 'text';

        if (!$phoneNumber || !$messageId) {
            return;
        }

        // Find user by phone number
        $user = $this->db->fetchOne('SELECT id FROM users WHERE phone = ?', [$phoneNumber]);
        if (!$user) {
            // Handle unknown sender
            $this->handleUnknownSender($phoneNumber, $message);
            return;
        }

        // Log the incoming message
        $this->logIncomingMessage($user['id'], $messageId, $messageType, $message, $timestamp);

        // Process message based on type
        switch ($messageType) {
            case 'text':
                $this->processTextMessage($user['id'], $message['text']['body'] ?? '');
                break;
            case 'interactive':
                $this->processInteractiveMessage($user['id'], $message['interactive']);
                break;
            case 'button':
                $this->processButtonResponse($user['id'], $message['button']);
                break;
        }
    }

    private function processTextMessage($userId, $text) {
        $text = strtolower(trim($text));

        // Handle common commands
        switch ($text) {
            case 'balance':
                $this->sendBalanceInfo($userId);
                break;
            case 'loans':
                $this->sendLoanStatus($userId);
                break;
            case 'help':
                $this->sendHelpMessage($userId);
                break;
            case 'stop':
                $this->handleUnsubscribeRequest($userId);
                break;
            default:
                $this->handleUnknownCommand($userId, $text);
                break;
        }
    }

    private function processInteractiveMessage($userId, $interactiveData) {
        if (isset($interactiveData['button_reply'])) {
            $buttonId = $interactiveData['button_reply']['id'];
            $this->handleButtonResponse($userId, $buttonId);
        }
    }

    private function sendBalanceInfo($userId) {
        try {
            $user = $this->db->fetchOne('SELECT name FROM users WHERE id = ?', [$userId]);
            $balance = $this->db->fetchOne("
                SELECT
                    COALESCE(SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END), 0) as total_paid,
                    COALESCE(SUM(CASE WHEN rs.status = 'pending' THEN rs.amount_due ELSE 0 END), 0) as total_due
                FROM repayment_schedule rs
                JOIN loans l ON rs.loan_id = l.id
                WHERE l.borrower_id = ?
            ", [$userId]);

            $message = "Hi {$user['name']}, your loan account summary:\n";
            $message .= "Total Paid: KES " . number_format($balance['total_paid'], 0) . "\n";
            $message .= "Total Due: KES " . number_format($balance['total_due'], 0);

            $this->sendTextMessage($user['phone'], $message);

        } catch (Exception $e) {
            error_log('Failed to send balance info: ' . $e->getMessage());
        }
    }

    private function sendLoanStatus($userId) {
        try {
            $user = $this->db->fetchOne('SELECT name, phone FROM users WHERE id = ?', [$userId]);
            $loans = $this->db->fetchAll("
                SELECT
                    id,
                    loan_amount,
                    status,
                    created_at,
                    (SELECT SUM(amount_due) FROM repayment_schedule WHERE loan_id = loans.id AND status = 'pending') as remaining_amount
                FROM loans
                WHERE borrower_id = ?
                ORDER BY created_at DESC
                LIMIT 3
            ", [$userId]);

            if (empty($loans)) {
                $message = "Hi {$user['name']}, you don't have any active loans.";
            } else {
                $message = "Hi {$user['name']}, your loan status:\n\n";
                foreach ($loans as $loan) {
                    $message .= "Loan ID: {$loan['id']}\n";
                    $message .= "Amount: KES " . number_format($loan['loan_amount'], 0) . "\n";
                    $message .= "Status: {$loan['status']}\n";
                    if ($loan['remaining_amount'] > 0) {
                        $message .= "Remaining: KES " . number_format($loan['remaining_amount'], 0) . "\n";
                    }
                    $message .= "\n";
                }
            }

            $this->sendTextMessage($user['phone'], $message);

        } catch (Exception $e) {
            error_log('Failed to send loan status: ' . $e->getMessage());
        }
    }

    private function sendHelpMessage($userId) {
        try {
            $user = $this->db->fetchOne('SELECT phone FROM users WHERE id = ?', [$userId]);

            $message = "JuaKali Lend Help Menu:\n\n";
            $message .= "• Reply 'balance' to check your loan balance\n";
            $message .= "• Reply 'loans' to see your loan status\n";
            $message .= "• Reply 'stop' to unsubscribe from notifications\n";
            $message .= "• Call our support line for assistance\n\n";
            $message .= "Thank you for banking with JuaKali Lend!";

            $this->sendTextMessage($user['phone'], $message);

        } catch (Exception $e) {
            error_log('Failed to send help message: ' . $e->getMessage());
        }
    }

    private function makeApiRequest($method, $url, $data = null) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("API request failed with HTTP code: $httpCode, Response: $response");
        }

        return json_decode($response, true);
    }

    private function formatPhoneNumber($phoneNumber) {
        // Remove all non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

        // Format to international format for Kenya
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) === '7') {
            return '254' . $phoneNumber;
        } elseif (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 2) === '07') {
            return '254' . substr($phoneNumber, 2);
        } elseif (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '254') {
            return $phoneNumber;
        }

        return $phoneNumber;
    }

    private function logWhatsAppMessage($recipient, $message, $type, $response) {
        try {
            $this->db->execute("
                INSERT INTO whatsapp_logs (recipient_phone, message, message_type, response_data, status, created_at)
                VALUES (?, ?, ?, ?, 'sent', NOW())
            ", [$recipient, $message, $type, json_encode($response)]);
        } catch (Exception $e) {
            error_log('Failed to log WhatsApp message: ' . $e->getMessage());
        }
    }

    private function logWhatsAppError($recipient, $message, $type, $error) {
        try {
            $this->db->execute("
                INSERT INTO whatsapp_logs (recipient_phone, message, message_type, error_message, status, created_at)
                VALUES (?, ?, ?, ?, 'failed', NOW())
            ", [$recipient, $message, $type, $error]);
        } catch (Exception $e) {
            error_log('Failed to log WhatsApp error: ' . $e->getMessage());
        }
    }

    private function logIncomingMessage($userId, $messageId, $type, $messageData, $timestamp) {
        try {
            $this->db->execute("
                INSERT INTO whatsapp_incoming (user_id, message_id, message_type, message_data, timestamp, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$userId, $messageId, $type, json_encode($messageData), $timestamp]);
        } catch (Exception $e) {
            error_log('Failed to log incoming message: ' . $e->getMessage());
        }
    }

    // Template methods
    private function getPaymentReminderTemplate($name, $amount, $dueDate) {
        return "Hi $name, this is a friendly reminder that your loan payment of KES " . number_format($amount, 0) . " is due on $dueDate. Please ensure timely payment to avoid late fees. Thank you from JuaKali Lend.";
    }

    private function getLoanApprovalTemplate($name, $amount, $terms) {
        return "Congratulations $name! Your loan application for KES " . number_format($amount, 0) . " has been approved. Terms: $terms. Funds will be disbursed shortly. Thank you for choosing JuaKali Lend.";
    }

    private function getFraudAlertTemplate($userId, $riskLevel, $description) {
        return "🚨 FRAUD ALERT 🚨\n\nUser ID: $userId\nRisk Level: $riskLevel\nDescription: $description\n\nPlease investigate immediately.";
    }

    private function getDeliveryNotificationTemplate($name, $orderId, $status, $qrCode) {
        $message = "Hi $name, your order #$orderId has been $status.";
        if ($qrCode) {
            $message .= " QR Code: $qrCode";
        }
        return $message;
    }

    private function getOTPTemplate($name, $otp, $purpose) {
        return "Hi $name, your OTP for $purpose is: $otp. This code will expire in 10 minutes. Do not share this code with anyone. Thank you from JuaKali Lend.";
    }

    private function getAdminPhoneNumbers() {
        return $this->db->fetchAllColumn("SELECT phone FROM users WHERE role = 'admin' AND status = 'active'");
    }

    private function handleUnknownSender($phoneNumber, $message) {
        // Log unknown sender and potentially send a standard response
        error_log("Unknown WhatsApp sender: $phoneNumber");
    }

    private function handleUnknownCommand($userId, $command) {
        // Handle unrecognized commands
        $this->sendHelpMessage($userId);
    }

    private function handleButtonResponse($userId, $buttonId) {
        // Handle button responses
        error_log("Button response from user $userId: $buttonId");
    }

    private function handleUnsubscribeRequest($userId) {
        // Handle unsubscribe requests
        try {
            $this->db->execute("
                UPDATE users SET whatsapp_notifications = 0 WHERE id = ?
            ", [$userId]);

            $user = $this->db->fetchOne('SELECT phone FROM users WHERE id = ?', [$userId]);
            $this->sendTextMessage($user['phone'], "You have been unsubscribed from WhatsApp notifications. Reply 'start' to re-subscribe.");
        } catch (Exception $e) {
            error_log('Failed to handle unsubscribe request: ' . $e->getMessage());
        }
    }

    /**
     * Get WhatsApp statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
        try {
            $whereClause = '';
            $params = [];

            if ($startDate && $endDate) {
                $whereClause = 'WHERE created_at BETWEEN ? AND ?';
                $params = [$startDate, $endDate];
            }

            $stats = $this->db->fetchOne("
                SELECT
                    COUNT(*) as total_messages,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_messages,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_messages,
                    COUNT(CASE WHEN message_type = 'text' THEN 1 END) as text_messages,
                    COUNT(CASE WHEN message_type = 'template' THEN 1 END) as template_messages
                FROM whatsapp_logs
                $whereClause
            ", $params);

            return $stats;

        } catch (Exception $e) {
            return [
                'total_messages' => 0,
                'sent_messages' => 0,
                'failed_messages' => 0,
                'text_messages' => 0,
                'template_messages' => 0
            ];
        }
    }
}

// Utility functions for WhatsApp integration
function sendWhatsAppNotification($userId, $type, $data = []) {
    try {
        $whatsapp = new WhatsAppAPI();

        switch ($type) {
            case 'payment_reminder':
                return $whatsapp->sendPaymentReminder($userId, $data['loan_id'], $data['amount'], $data['due_date']);

            case 'loan_approval':
                return $whatsapp->sendLoanApprovalNotification($userId, $data['loan_id'], $data['amount'], $data['terms']);

            case 'fraud_alert':
                return $whatsapp->sendFraudAlert($userId, $data['risk_level'], $data['description']);

            case 'delivery_notification':
                return $whatsapp->sendDeliveryNotification($userId, $data['order_id'], $data['status'], $data['qr_code'] ?? null);

            case 'otp':
                return $whatsapp->sendOTPVerification($userId, $data['otp'], $data['purpose'] ?? 'verification');

            default:
                return ['success' => false, 'error' => 'Unknown notification type'];
        }
    } catch (Exception $e) {
        error_log('WhatsApp notification failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>