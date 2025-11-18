<?php
/**
 * USSD & Offline Access Integration
 * Feature phone support for users without smartphones
 */

class USSDGateway {
    private $db;
    private $ussd_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->ussd_api_key = getenv('USSD_API_KEY');
    }
    
    /**
     * Handle USSD request
     */
    public function handleUSSDRequest($phone_number, $ussd_input) {
        $session_id = md5($phone_number . time());
        
        // Parse USSD input
        $menu_level = count(explode('*', $ussd_input));
        $response = "";
        
        if ($menu_level == 1) {
            // Main menu
            $response = "CON Welcome to JuaKali Lend\n";
            $response .= "1. Check Loan Status\n";
            $response .= "2. Make Repayment\n";
            $response .= "3. Check Balance\n";
            $response .= "4. Apply for Loan";
        } elseif ($menu_level == 2) {
            $choice = substr($ussd_input, -1);
            
            if ($choice == '1') {
                $response = $this->getLoanStatus($phone_number);
            } elseif ($choice == '2') {
                $response = "CON Enter amount to repay:\n";
            } elseif ($choice == '3') {
                $response = $this->getBalance($phone_number);
            } elseif ($choice == '4') {
                $response = "CON Enter desired loan amount:\n";
            }
        }
        
        return $response;
    }
    
    /**
     * Get loan status via USSD
     */
    private function getLoanStatus($phone_number) {
        $this->db->query("
            SELECT l.id, l.status, l.due_date, l.loan_amount
            FROM loans l
            JOIN users u ON l.retailer_id = u.id
            WHERE u.phone = ?
            ORDER BY l.created_at DESC
            LIMIT 1
        ");
        $this->db->bind(':phone', $phone_number);
        $loan = $this->db->single();
        
        if ($loan) {
            return "END Your loan status: " . $loan['status'] . "\nDue: " . $loan['due_date'];
        }
        
        return "END No active loans found";
    }
    
    /**
     * Get balance via USSD
     */
    private function getBalance($phone_number) {
        $this->db->query("
            SELECT cs.credit_limit, cs.total_borrowed, cs.total_repaid
            FROM credit_scores cs
            JOIN users u ON cs.retailer_id = u.id
            WHERE u.phone = ?
        ");
        $this->db->bind(':phone', $phone_number);
        $credit = $this->db->single();
        
        if ($credit) {
            $available = $credit['credit_limit'] - ($credit['total_borrowed'] - $credit['total_repaid']);
            return "END Available credit: KES " . number_format($available);
        }
        
        return "END No credit account found";
    }
}
?>
