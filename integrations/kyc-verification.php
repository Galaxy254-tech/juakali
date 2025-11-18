<?php
/**
 * Government KYC & Identity Verification Integration
 * Real-time ID verification against government databases
 */

class KYCVerification {
    private $db;
    private $gov_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->gov_api_key = getenv('GOV_KYC_API_KEY');
    }
    
    /**
     * Verify national ID against government database
     */
    public function verifyNationalID($user_id, $id_number, $id_type = 'national_id') {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.kyc.gov.ke/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'id_number' => $id_number,
                    'id_type' => $id_type
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->gov_api_key
                ],
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                throw new Exception("Verification failed: " . $err);
            }
            
            $result = json_decode($response, true);
            
            if ($result['verified']) {
                // Update user KYC status
                $this->db->query("UPDATE users SET kyc_verified = TRUE WHERE id = ?");
                $this->db->bind(':id', $user_id);
                $this->db->execute();
                
                // Log KYC document
                $this->db->query("
                    INSERT INTO kyc_documents (user_id, document_type, document_url, status, verified_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $this->db->bind(':user_id', $user_id);
                $this->db->bind(':document_type', $id_type);
                $this->db->bind(':document_url', $id_number);
                $this->db->bind(':status', 'approved');
                $this->db->execute();
            }
            
            return $result;
        } catch (Exception $e) {
            return ['verified' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verify biometric data
     */
    public function verifyBiometric($user_id, $biometric_data) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.biometric.gov.ke/verify",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'biometric_data' => $biometric_data
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->gov_api_key
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            return $result;
        } catch (Exception $e) {
            return ['verified' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check KYC compliance status
     */
    public function checkComplianceStatus($user_id) {
        $this->db->query("
            SELECT COUNT(*) as approved_docs 
            FROM kyc_documents 
            WHERE user_id = ? AND status = 'approved'
        ");
        $this->db->bind(':user_id', $user_id);
        $docs = $this->db->single();
        
        $compliance_score = ($docs['approved_docs'] / 3) * 100; // Assuming 3 required docs
        
        return [
            'compliant' => $compliance_score >= 100,
            'compliance_score' => $compliance_score,
            'approved_documents' => $docs['approved_docs']
        ];
    }
}
?>
