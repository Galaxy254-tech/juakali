<?php
/**
 * M-Pesa Integration for JuaKali Lend
 * Supports STK Push, C2B, and B2C payments via Safaricom Daraja API
 */

class M_Pesa {
    private $consumerKey;
    private $consumerSecret;
    private $passkey;
    private $shortcode;
    private $environment; // sandbox or live
    private $accessToken;
    private $tokenExpiry;

    public function __construct() {
        $this->loadConfig();
    }

    private function loadConfig() {
        try {
            $db = Database::getInstance();
            $config = $db->fetchOne('SELECT * FROM system_settings WHERE setting_key = "mpesa_config"');

            if ($config) {
                $settings = json_decode($config['setting_value'], true);
                $this->consumerKey = $settings['consumer_key'] ?? '';
                $this->consumerSecret = $settings['consumer_secret'] ?? '';
                $this->passkey = $settings['passkey'] ?? '';
                $this->shortcode = $settings['shortcode'] ?? '';
                $this->environment = $settings['environment'] ?? 'sandbox';
            } else {
                // Default configuration
                $this->consumerKey = '';
                $this->consumerSecret = '';
                $this->passkey = '';
                $this->shortcode = '';
                $this->environment = 'sandbox';
            }
        } catch (Exception $e) {
            // Use defaults if database connection fails
            $this->consumerKey = '';
            $this->consumerSecret = '';
            $this->passkey = '';
            $this->shortcode = '';
            $this->environment = 'sandbox';
        }
    }

    /**
     * Get OAuth Access Token
     */
    private function getAccessToken() {
        // Check if token is still valid
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry > time()) {
            return $this->accessToken;
        }

        $url = $this->environment === 'live'
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Failed to get access token: HTTP ' . $httpCode);
        }

        $result = json_decode($response, true);

        if (isset($result['access_token'])) {
            $this->accessToken = $result['access_token'];
            $this->tokenExpiry = time() + $result['expires_in'] - 60; // Buffer of 60 seconds
            return $this->accessToken;
        } else {
            throw new Exception('Failed to parse access token response');
        }
    }

    /**
     * Send STK Push to customer
     */
    public function sendSTKPush($phoneNumber, $amount, $accountReference, $transactionDesc, $callbackURL = null) {
        try {
            $token = $this->getAccessToken();

            if (!$callbackURL) {
                $callbackURL = $this->environment === 'live'
                    ? 'https://juakali-lend.com/api/mpesa/callback'
                    : 'https://juakali-lend.test/api/mpesa/callback';
            }

            $url = $this->environment === 'live'
                ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
                : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

            $timestamp = date('YmdHis');
            $password = base64_encode(hash('sha256', $this->shortcode . $this->passkey . $timestamp));

            $requestData = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $this->shortcode,
                'PartyB' => $phoneNumber,
                'PhoneNumber' => $phoneNumber,
                'CallBackURL' => $callbackURL,
                'AccountReference' => $accountReference,
                'TransactionDesc' => $transactionDesc,
                'Remark' => 'JuaKali Lend Payment'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode !== 200) {
                throw new Exception('STK Push request failed: HTTP ' . $httpCode);
            }

            // Log the STK Push request
            $this->logM_PesaTransaction($phoneNumber, $amount, $accountReference, 'stk_push', $result);

            return $result;

        } catch (Exception $e) {
            $this->logM_PesaError($phoneNumber, $amount, $accountReference, 'stk_push', $e->getMessage());
            throw new Exception('STK Push failed: ' . $e->getMessage());
        }
    }

    /**
     * Query STK Push status
     */
    public function querySTKPushStatus($checkoutRequestID) {
        try {
            $token = $this->getAccessToken();

            $url = $this->environment === 'live'
                ? 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'
                : 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';

            $timestamp = date('YmdHis');
            $password = base64_encode(hash('sha256', $this->shortcode . $this->passkey . $timestamp));

            $requestData = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestID
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode !== 200) {
                throw new Exception('STK Push query failed: HTTP ' . $httpCode);
            }

            return $result;

        } catch (Exception $e) {
            throw new Exception('STK Push query failed: ' . $e->getMessage());
        }
    }

    /**
     * Process B2C payment (disbursement to suppliers)
     */
    public function sendB2CPayment($phoneNumber, $amount, $commandID, $remarks, $occasion = '') {
        try {
            $token = $this->getAccessToken();

            $url = $this->environment === 'live'
                ? 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest'
                : 'https://sandbox.safaricom.co.ke/mpesa/b2c/v1/paymentrequest';

            $requestData = [
                'InitiatorName' => 'JuaKaliLend',
                'SecurityCredential' => $this->generateSecurityCredential(),
                'CommandID' => $commandID, // BusinessPayment, SalaryPayment, etc.
                'Amount' => $amount,
                'PartyA' => $this->shortcode,
                'PartyB' => $phoneNumber,
                'Remarks' => $remarks,
                'QueueTimeOutURL' => $this->environment === 'live'
                    ? 'https://juakali-lend.com/api/mpesa/b2c/timeout'
                    : 'https://juakali-lend.test/api/mpesa/b2c/timeout',
                'ResultURL' => $this->environment === 'live'
                    ? 'https://juakali-lend.com/api/mpesa/b2c/result'
                    : 'https://juakali-lend.test/api/mpesa/b2c/result',
                'Occasion' => $occasion
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if ($httpCode !== 200) {
                throw new Exception('B2C payment request failed: HTTP ' . $httpCode);
            }

            // Log the B2C transaction
            $this->logM_PesaTransaction($phoneNumber, $amount, $remarks, 'b2c_payment', $result);

            return $result;

        } catch (Exception $e) {
            $this->logM_PesaError($phoneNumber, $amount, $remarks, 'b2c_payment', $e->getMessage());
            throw new Exception('B2C payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate security credential for B2C
     */
    private function generateSecurityCredential() {
        // This would typically use the B2C initiator password
        // For demo purposes, we'll use a simple implementation
        return base64_encode(hash('sha256', 'B2C_INITIATOR_PASSWORD'));
    }

    /**
     * Format phone number for M-Pesa
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove all non-digit characters
        $phoneNumber = preg_replace('/\D/', '', $phoneNumber);

        // Format to international format
        if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) === '7') {
            return '254' . $phoneNumber;
        } elseif (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 2) === '07') {
            return '254' . substr($phoneNumber, 2);
        } elseif (strlen($phoneNumber) === 12 && substr($phoneNumber, 0, 3) === '254') {
            return $phoneNumber;
        }

        return null;
    }

    /**
     * Send payment request via STK Push
     */
    public function sendPaymentRequest($phoneNumber, $amount, $loanId, $description = null) {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            throw new Exception('Invalid phone number format');
        }

        if (!$description) {
            $description = 'JuaKali Lend Loan Payment';
        }

        $accountReference = 'LOAN' . str_pad($loanId, 6, '0', STR_PAD_LEFT);
        $transactionDesc = $description;

        try {
            $result = $this->sendSTKPush($phoneNumber, $amount, $accountReference, $transactionDesc);

            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                // Save the request for tracking
                $this->savePaymentRequest($result['CheckoutRequestID'], $phoneNumber, $amount, $loanId, $accountReference);
            }

            return $result;

        } catch (Exception $e) {
            throw new Exception('Payment request failed: ' . $e->getMessage());
        }
    }

    /**
     * Send payment to supplier
     */
    public function paySupplier($phoneNumber, $amount, $orderId, $remarks = 'Payment for goods') {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        if (!$phoneNumber) {
            throw new Exception('Invalid phone number format');
        }

        try {
            $result = $this->sendB2CPayment($phoneNumber, $amount, 'BusinessPayment', $remarks, 'Goods Supply');

            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                // Update payment status in database
                $this->updatePaymentStatus($result['ConversationID'], 'processing', $orderId);
            }

            return $result;

        } catch (Exception $e) {
            throw new Exception('Supplier payment failed: ' . $e->getMessage());
        }
    }

    /**
     * Save payment request for tracking
     */
    private function savePaymentRequest($checkoutRequestID, $phoneNumber, $amount, $loanId, $accountReference) {
        try {
            $db = Database::getInstance();
            $db->execute('INSERT INTO mpesa_transactions (checkout_request_id, phone_number, amount, loan_id, account_reference, transaction_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$checkoutRequestID, $phoneNumber, $amount, $loanId, $accountReference, 'stk_push', 'pending']);
        } catch (Exception $e) {
            error_log('Failed to save payment request: ' . $e->getMessage());
        }
    }

    /**
     * Update payment status
     */
    private function updatePaymentStatus($conversationID, $status, $orderId = null) {
        try {
            $db = Database::getInstance();
            $db->execute('UPDATE mpesa_transactions SET status = ?, conversation_id = ?, updated_at = NOW() WHERE checkout_request_id = ?',
                [$status, $conversationID, $orderId]);
        } catch (Exception $e) {
            error_log('Failed to update payment status: ' . $e->getMessage());
        }
    }

    /**
     * Log M-Pesa transaction
     */
    private function logM_PesaTransaction($phoneNumber, $amount, $reference, $type, $response) {
        try {
            $db = Database::getInstance();
            $db->execute('INSERT INTO mpesa_logs (phone_number, amount, reference, transaction_type, response_data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$phoneNumber, $amount, $reference, $type, json_encode($response), 'success']);
        } catch (Exception $e) {
            error_log('Failed to log M-Pesa transaction: ' . $e->getMessage());
        }
    }

    /**
     * Log M-Pesa errors
     */
    private function logM_PesaError($phoneNumber, $amount, $reference, $type, $error) {
        try {
            $db = Database::getInstance();
            $db->execute('INSERT INTO mpesa_logs (phone_number, amount, reference, transaction_type, error_message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$phoneNumber, $amount, $reference, $type, $error, 'failed']);
        } catch (Exception $e) {
            error_log('Failed to log M-Pesa error: ' . $e->getMessage());
        }
    }

    /**
     * Handle M-Pesa callback
     */
    public function handleCallback($callbackData) {
        try {
            $db = Database::getInstance();

            $checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'] ?? null;
            $resultCode = $callbackData['Body']['stkCallback']['ResultCode'] ?? null;
            $resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'] ?? null;
            $transactionID = $callbackData['Body']['stkCallback']['TransactionID'] ?? null;

            if ($checkoutRequestID) {
                // Update transaction status
                if ($resultCode === '0') {
                    $status = 'completed';
                    $db->execute('UPDATE mpesa_transactions SET status = ?, transaction_id = ?, completed_at = NOW() WHERE checkout_request_id = ?',
                        [$status, $transactionID, $checkoutRequestID]);

                    // Get transaction details
                    $transaction = $db->fetchOne('SELECT * FROM mpesa_transactions WHERE checkout_request_id = ?', [$checkoutRequestID]);

                    if ($transaction) {
                        // Update payment status
                        $this->updatePaymentStatus($transactionID, 'completed', $transaction['loan_id']);

                        // Trigger payment completion webhook
                        $this->triggerPaymentCompletion($transaction);
                    }

                } else {
                    $status = 'failed';
                    $db->execute('UPDATE mpesa_transactions SET status = ?, error_message = ?, updated_at = NOW() WHERE checkout_request_id = ?',
                        [$status, $resultDesc, $checkoutRequestID]);
                }
            }

            return [
                'success' => true,
                'resultCode' => $resultCode,
                'resultDesc' => $resultDesc,
                'transactionID' => $transactionID
            ];

        } catch (Exception $e) {
            error_log('Failed to handle M-Pesa callback: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Trigger payment completion webhook
     */
    private function triggerPaymentCompletion($transaction) {
        try {
            $db = Database::getInstance();

            // Update loan repayment schedule
            if ($transaction['loan_id']) {
                $payment = $db->fetchOne('SELECT * FROM repayment_schedule WHERE loan_id = ? AND status = "pending" ORDER BY due_date ASC LIMIT 1', [$transaction['loan_id']]);

                if ($payment) {
                    $db->execute('UPDATE repayment_schedule SET status = "completed", amount_paid = amount_due, paid_at = NOW() WHERE id = ?', [$payment['id']]);

                    // Update loan status if all payments are completed
                    $remaining = $db->fetchColumn('SELECT COUNT(*) FROM repayment_schedule WHERE loan_id = ? AND status = "pending"', [$transaction['loan_id']]);

                    if ($remaining == 0) {
                        $db->execute('UPDATE loans SET status = "completed", completed_at = NOW() WHERE id = ?', [$transaction['loan_id']]);
                    }
                }
            }

            // Send notification to user
            $user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$transaction['user_id']]);
            if ($user) {
                require_once 'sms-notifications.php';
                sendSMSNotification('reminder', $transaction['loan_id'], $user['id']);
            }

        } catch (Exception $e) {
            error_log('Failed to trigger payment completion: ' . $e->getMessage());
        }
    }

    /**
     * Get M-Pesa statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
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
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_transactions,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_transactions
                FROM mpesa_logs
                $whereClause
            ", $params);

            return $stats;

        } catch (Exception $e) {
            return [
                'total_transactions' => 0,
                'successful_transactions' => 0,
                'total_amount' => 0,
                'failed_transactions' => 0
            ];
        }
    }
}

// Utility functions for M-Pesa integration
function sendM_PesaPayment($phoneNumber, $amount, $loanId, $description = null) {
    try {
        $mpesa = new M_Pesa();
        return $mpesa->sendPaymentRequest($phoneNumber, $amount, $loanId, $description);
    } catch (Exception $e) {
        error_log('M-Pesa payment failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function paySupplierM_Pesa($phoneNumber, $amount, $orderId, $remarks = null) {
    try {
        $mpesa = new M_Pesa();
        return $mpesa->paySupplier($phoneNumber, $amount, $orderId, $remarks);
    } catch (Exception $e) {
        error_log('M-Pesa supplier payment failed: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>