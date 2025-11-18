<?php
/**
 * Blockchain for Loan Security & Transparency
 * Immutable loan agreements and transparent repayment history
 */

class BlockchainIntegration {
    private $db;
    private $blockchain_api;
    
    public function __construct($database) {
        $this->db = $database;
        $this->blockchain_api = getenv('BLOCKCHAIN_API_URL');
    }
    
    /**
     * Create immutable loan agreement on blockchain
     */
    public function createSmartContract($loan_id) {
        $this->db->query("
            SELECT l.*, u.email as lender_email, r.email as retailer_email
            FROM loans l
            JOIN users u ON l.lender_id = u.id
            JOIN users r ON l.retailer_id = r.id
            WHERE l.id = ?
        ");
        $this->db->bind(':id', $loan_id);
        $loan = $this->db->single();
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->blockchain_api . "/contracts/create",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'contract_type' => 'loan_agreement',
                    'lender' => $loan['lender_email'],
                    'borrower' => $loan['retailer_email'],
                    'amount' => $loan['loan_amount'],
                    'interest_rate' => $loan['interest_rate'],
                    'due_date' => $loan['due_date'],
                    'terms' => 'Loan agreement for order #' . $loan['order_id']
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . getenv('BLOCKCHAIN_API_KEY')
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            
            if ($result['success']) {
                // Store blockchain reference
                $this->db->query("
                    UPDATE loans SET blockchain_hash = ? WHERE id = ?
                ");
                $this->db->bind(':hash', $result['contract_hash']);
                $this->db->bind(':id', $loan_id);
                $this->db->execute();
            }
            
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Record repayment on blockchain
     */
    public function recordRepayment($payment_id, $amount) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->blockchain_api . "/transactions/record",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'transaction_type' => 'repayment',
                    'amount' => $amount,
                    'timestamp' => date('c'),
                    'reference' => 'PAYMENT-' . $payment_id
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . getenv('BLOCKCHAIN_API_KEY')
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
