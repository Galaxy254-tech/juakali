<?php
/**
 * Multi-Channel Payment Gateway Aggregator
 * Supports M-Pesa, Airtel, T-Kash, bank transfers, and cards
 */

class PaymentGateway {
    private $db;
    private $mpesa_api_key;
    private $airtel_api_key;
    private $stripe_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->mpesa_api_key = getenv('MPESA_API_KEY');
        $this->airtel_api_key = getenv('AIRTEL_API_KEY');
        $this->stripe_key = getenv('STRIPE_SECRET_KEY');
    }
    
    /**
     * Process payment through M-Pesa
     */
    public function processMpesaPayment($loan_id, $amount, $phone_number) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'BusinessShortCode' => getenv('MPESA_SHORTCODE'),
                    'Password' => base64_encode(getenv('MPESA_SHORTCODE') . getenv('MPESA_PASSKEY') . date('YmdHis')),
                    'Timestamp' => date('YmdHis'),
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone_number,
                    'PartyB' => getenv('MPESA_SHORTCODE'),
                    'PhoneNumber' => $phone_number,
                    'CallBackURL' => APP_URL . '/api/payments/mpesa-callback.php',
                    'AccountReference' => 'LOAN-' . $loan_id,
                    'TransactionDesc' => 'Loan Repayment'
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->getMpesaToken()
                ],
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                throw new Exception("cURL Error: " . $err);
            }
            
            $result = json_decode($response, true);
            
            // Log payment attempt
            $this->logPayment($loan_id, $amount, 'mpesa', $result['CheckoutRequestID'] ?? null, 'pending');
            
            return $result;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Process payment through Airtel Money
     */
    public function processAirtelPayment($loan_id, $amount, $phone_number) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.airtel.africa/merchant/v1/payments/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'reference' => 'LOAN-' . $loan_id,
                    'subscriber' => ['phone' => $phone_number],
                    'transaction' => [
                        'amount' => $amount,
                        'currency' => 'KES'
                    ]
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->airtel_api_key,
                    "X-Country: KE"
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            $this->logPayment($loan_id, $amount, 'airtel', $result['id'] ?? null, 'pending');
            
            return $result;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Process payment through Stripe (for card payments)
     */
    public function processCardPayment($loan_id, $amount, $token) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.stripe.com/v1/charges",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_USERPWD => $this->stripe_key . ":",
                CURLOPT_POSTFIELDS => http_build_query([
                    'amount' => $amount * 100,
                    'currency' => 'kes',
                    'source' => $token,
                    'description' => 'Loan Repayment - LOAN-' . $loan_id
                ]),
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            $this->logPayment($loan_id, $amount, 'card', $result['id'] ?? null, 'pending');
            
            return $result;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Log payment transaction
     */
    private function logPayment($loan_id, $amount, $method, $transaction_id, $status) {
        $this->db->query("
            INSERT INTO payments (loan_id, amount, payment_method, transaction_id, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $this->db->bind(':loan_id', $loan_id);
        $this->db->bind(':amount', $amount);
        $this->db->bind(':payment_method', $method);
        $this->db->bind(':transaction_id', $transaction_id);
        $this->db->bind(':status', $status);
        $this->db->execute();
    }
    
    /**
     * Get M-Pesa access token
     */
    private function getMpesaToken() {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => getenv('MPESA_CONSUMER_KEY') . ":" . getenv('MPESA_CONSUMER_SECRET'),
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }
}
?>
