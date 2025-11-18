<?php
/**
 * Accounting & Financial Management Integration
 * Automated bookkeeping and financial reporting
 */

class AccountingSync {
    private $db;
    private $xero_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->xero_api_key = getenv('XERO_API_KEY');
    }
    
    /**
     * Sync loan disbursement to accounting system
     */
    public function syncLoanDisbursement($loan_id) {
        $this->db->query("
            SELECT l.*, u.company_name, r.company_name as retailer_name
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
                CURLOPT_URL => "https://api.xero.com/api.xro/2.0/Invoices",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'Invoices' => [[
                        'Type' => 'ACCREC',
                        'Contact' => ['Name' => $loan['retailer_name']],
                        'LineItems' => [[
                            'Description' => 'Loan Disbursement',
                            'Quantity' => 1,
                            'UnitAmount' => $loan['loan_amount'],
                            'AccountCode' => '200'
                        ]],
                        'DueDate' => date('Y-m-d', strtotime($loan['due_date'])),
                        'Reference' => 'LOAN-' . $loan_id
                    ]]
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->xero_api_key
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate financial report
     */
    public function generateFinancialReport($start_date, $end_date) {
        $this->db->query("
            SELECT 
                SUM(CASE WHEN status = 'disbursed' THEN loan_amount ELSE 0 END) as total_disbursed,
                SUM(CASE WHEN status = 'repaid' THEN loan_amount ELSE 0 END) as total_repaid,
                COUNT(CASE WHEN status = 'defaulted' THEN 1 END) as default_count,
                AVG(interest_rate) as avg_interest_rate
            FROM loans
            WHERE created_at BETWEEN ? AND ?
        ");
        $this->db->bind(':start_date', $start_date);
        $this->db->bind(':end_date', $end_date);
        $report = $this->db->single();
        
        return [
            'period' => ['start' => $start_date, 'end' => $end_date],
            'total_disbursed' => $report['total_disbursed'] ?? 0,
            'total_repaid' => $report['total_repaid'] ?? 0,
            'default_count' => $report['default_count'] ?? 0,
            'avg_interest_rate' => $report['avg_interest_rate'] ?? 0,
            'net_revenue' => ($report['total_repaid'] ?? 0) - ($report['total_disbursed'] ?? 0)
        ];
    }
}
?>
