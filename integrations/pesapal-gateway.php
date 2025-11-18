<?php
/**
 * PesaPal Payment Gateway Integration
 * Supports OAuth, transaction processing, and IPN callbacks
 * PHP 8.1+ with modern security practices
 */

declare(strict_types=1);

namespace Integrations;

use PDO;

class PesaPalGateway {
    private PDO $db;
    private string $sandbox_url = 'https://demo.pesapal.com/api/';
    private string $production_url = 'https://www.pesapal.com/api/';
    private string $consumer_key;
    private string $consumer_secret;
    private bool $test_mode;
    private array $supported_currencies = ['KES', 'USD', 'EUR', 'GBP', 'UGX', 'TZS'];
    
    public function __construct(PDO $database, array $config = []) {
        $this->db = $database;
        $this->consumer_key = $config['consumer_key'] ?? $_ENV['PESAPAL_CONSUMER_KEY'] ?? '';
        $this->consumer_secret = $config['consumer_secret'] ?? $_ENV['PESAPAL_CONSUMER_SECRET'] ?? '';
        $this->test_mode = $config['test_mode'] ?? ($_ENV['PESAPAL_TEST_MODE'] === 'true');
        
        if (!$this->consumer_key || !$this->consumer_secret) {
            throw new \Exception('PesaPal credentials not configured');
        }
    }
    
    /**
     * Get the appropriate API base URL
     */
    public function getActionUrl(): string {
        return $this->test_mode ? $this->sandbox_url : $this->production_url;
    }
    
    /**
     * Generate unique transaction ID
     */
    public function generateTransactionId(): string {
        return substr(hash('sha256', mt_rand() . microtime()), 0, 20);
    }
    
    /**
     * Create payment request XML
     */
    private function createPaymentRequestXml(
        string $amount,
        string $description,
        string $reference,
        string $first_name,
        string $last_name,
        string $email,
        string $phone_number,
        string $currency = 'KES',
        string $type = 'MERCHANT'
    ): string {
        // Sanitize inputs to prevent XML injection
        $amount = htmlspecialchars($amount, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $reference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
        $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
        $last_name = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $phone_number = htmlspecialchars($phone_number, ENT_QUOTES, 'UTF-8');
        
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<PesapalDirectOrderInfo xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
                        xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
                        Amount="{$amount}" 
                        Description="{$description}" 
                        Type="{$type}" 
                        Reference="{$reference}" 
                        FirstName="{$first_name}" 
                        LastName="{$last_name}" 
                        Email="{$email}" 
                        PhoneNumber="{$phone_number}" 
                        Currency="{$currency}" 
                        xmlns="http://www.pesapal.com" />
XML;
        
        return htmlentities($xml);
    }
    
    /**
     * Process payment and get iframe URL
     */
    public function processPayment(array $paymentData): array {
        try {
            // Validate required fields
            $required = ['amount', 'description', 'invoice_id', 'first_name', 'last_name', 'email', 'phone_number'];
            foreach ($required as $field) {
                if (!isset($paymentData[$field]) || empty($paymentData[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }
            
            // Validate currency
            $currency = $paymentData['currency'] ?? 'KES';
            if (!in_array($currency, $this->supported_currencies, true)) {
                throw new \Exception("Unsupported currency: {$currency}");
            }
            
            // Generate reference and post payment XML
            $reference = $this->generateTransactionId();
            $callbackUrl = $paymentData['callback_url'] ?? sprintf(
                "%s/api/payments/pesapal-callback.php?invoice_id=%d",
                $_ENV['APP_URL'] ?? 'http://localhost',
                $paymentData['invoice_id']
            );
            
            $postXml = $this->createPaymentRequestXml(
                (string)$paymentData['amount'],
                $paymentData['description'],
                $reference,
                $paymentData['first_name'],
                $paymentData['last_name'],
                $paymentData['email'],
                $paymentData['phone_number'],
                $currency
            );
            
            // Create OAuth signature
            $signature = $this->generateSignature($postXml, $callbackUrl);
            
            // Build iframe URL
            $iframeUrl = sprintf(
                "%sPostPesapalDirectOrderV4?oauth_token=%s&oauth_signature=%s&pesapal_request_data=%s",
                $this->getActionUrl(),
                urlencode($signature['oauth_token']),
                urlencode($signature['oauth_signature']),
                urlencode($postXml)
            );
            
            // Store transaction in database
            $stmt = $this->db->prepare("
                INSERT INTO pesapal_transactions 
                (reference_code, amount, currency, notification_type, txn_status, txn_date) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $reference,
                $paymentData['amount'],
                $currency,
                'PAYMENT_REQUEST',
                'PENDING'
            ]);
            
            return [
                'success' => true,
                'iframe_url' => $iframeUrl,
                'reference_code' => $reference
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate OAuth signature using HMAC-SHA1
     */
    private function generateSignature(string $requestData, string $callbackUrl): array {
        $timestamp = time();
        $nonce = md5(uniqid(rand(), true));
        
        // Create base string
        $baseString = implode('&', [
            'GET',
            urlencode($this->getActionUrl() . 'PostPesapalDirectOrderV4'),
            urlencode(implode('&', [
                'oauth_consumer_key=' . $this->consumer_key,
                'oauth_nonce=' . $nonce,
                'oauth_signature_method=HMAC-SHA1',
                'oauth_timestamp=' . $timestamp,
                'oauth_version=1.0',
                'pesapal_request_data=' . urlencode($requestData),
                'oauth_callback=' . urlencode($callbackUrl)
            ]))
        ]);
        
        // Create signing key
        $signingKey = $this->consumer_secret . '&';
        
        // Generate signature
        $signature = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
        
        return [
            'oauth_token' => 'oauth_consumer_key=' . $this->consumer_key .
                           '&oauth_nonce=' . $nonce .
                           '&oauth_signature_method=HMAC-SHA1' .
                           '&oauth_timestamp=' . $timestamp .
                           '&oauth_version=1.0' .
                           '&pesapal_request_data=' . urlencode($requestData) .
                           '&oauth_callback=' . urlencode($callbackUrl),
            'oauth_signature' => $signature
        ];
    }
    
    /**
     * Query payment status from PesaPal
     */
    public function getPaymentStatus(string $merchantReference, string $trackingId): array {
        try {
            $statusUrl = $this->getActionUrl() . 'QueryPaymentDetails';
            
            $token = $this->generateSignature('', $statusUrl);
            
            $queryUrl = $statusUrl . '?' . http_build_query([
                'pesapal_merchant_reference' => $merchantReference,
                'pesapal_transaction_tracking_id' => $trackingId,
                'oauth_consumer_key' => $this->consumer_key,
                'oauth_nonce' => md5(uniqid(rand(), true)),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => time(),
                'oauth_version' => '1.0'
            ]);
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $queryUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode !== 200) {
                throw new \Exception("PesaPal API error: HTTP {$httpCode}");
            }
            
            // Parse status from response
            parse_str($response, $result);
            
            return [
                'success' => true,
                'status' => $result['pesapal_transaction_tracking_id'] ?? 'UNKNOWN',
                'data' => $result
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle IPN callback and update payment status
     */
    public function handleIPNCallback(array $postData): bool {
        try {
            // Verify required IPN parameters
            $required = ['pesapal_notification_type', 'pesapal_transaction_tracking_id', 'pesapal_merchant_reference'];
            foreach ($required as $field) {
                if (!isset($postData[$field])) {
                    throw new \Exception("Missing IPN parameter: {$field}");
                }
            }
            
            $notificationType = $postData['pesapal_notification_type'];
            $trackingId = $postData['pesapal_transaction_tracking_id'];
            $merchantReference = $postData['pesapal_merchant_reference'];
            
            if ($notificationType === 'CHANGE') {
                // Query payment status
                $status = $this->getPaymentStatus($merchantReference, $trackingId);
                
                if ($status['success']) {
                    // Update transaction status
                    $stmt = $this->db->prepare("
                        UPDATE pesapal_transactions 
                        SET tracking_id = ?, 
                            txn_status = ?, 
                            ipn_date = NOW(),
                            flag = TRUE,
                            notification_type = ?
                        WHERE reference_code = ?
                    ");
                    
                    $txnStatus = $this->mapPesapalStatus($status['data']);
                    $stmt->execute([
                        $trackingId,
                        $txnStatus,
                        $notificationType,
                        $merchantReference
                    ]);
                    
                    // If payment successful, mark loan as paid
                    if ($txnStatus === 'PAID') {
                        $this->markPaymentComplete($merchantReference);
                    }
                    
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("PesaPal IPN Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Map PesaPal status to internal status
     */
    private function mapPesapalStatus(array $data): string {
        $status = $data['pesapal_transaction_tracking_id'] ?? 'UNKNOWN';
        
        if (strpos($status, 'COMPLETE') !== false) {
            return 'PAID';
        } elseif (strpos($status, 'PENDING') !== false) {
            return 'PENDING';
        } else {
            return 'FAILED';
        }
    }
    
    /**
     * Mark payment as complete in system
     */
    private function markPaymentComplete(string $merchantReference): void {
        try {
            $stmt = $this->db->prepare("
                SELECT id, loan_id, amount FROM pesapal_transactions 
                WHERE reference_code = ? AND txn_status = 'PAID'
            ");
            $stmt->execute([$merchantReference]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction) {
                // Update payment record
                $stmt = $this->db->prepare("
                    UPDATE payments 
                    SET status = 'completed', payment_date = NOW()
                    WHERE transaction_id = ?
                ");
                $stmt->execute([$merchantReference]);
                
                // Update loan status if fully repaid
                $this->updateLoanStatus($transaction['loan_id']);
            }
        } catch (\Exception $e) {
            error_log("Mark Payment Complete Error: " . $e->getMessage());
        }
    }
    
    /**
     * Update loan repayment status
     */
    private function updateLoanStatus(int $loanId): void {
        try {
            $stmt = $this->db->prepare("
                SELECT SUM(amount_paid) as total_paid, SUM(amount_due) as total_due
                FROM repayment_schedule
                WHERE loan_id = ?
            ");
            $stmt->execute([$loanId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['total_paid'] >= $result['total_due']) {
                $stmt = $this->db->prepare("UPDATE loans SET status = 'repaid' WHERE id = ?");
                $stmt->execute([$loanId]);
            }
        } catch (\Exception $e) {
            error_log("Update Loan Status Error: " . $e->getMessage());
        }
    }
}
