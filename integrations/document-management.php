<?php
/**
 * Document Management & E-Signature Integration
 * Digital loan agreements and document storage
 */

class DocumentManagement {
    private $db;
    private $docusign_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->docusign_api_key = getenv('DOCUSIGN_API_KEY');
    }
    
    /**
     * Generate and send loan agreement for e-signature
     */
    public function sendLoanAgreement($loan_id) {
        $this->db->query("
            SELECT l.*, u.email as lender_email, u.first_name as lender_name,
                   r.email as retailer_email, r.first_name as retailer_name
            FROM loans l
            JOIN users u ON l.lender_id = u.id
            JOIN users r ON l.retailer_id = r.id
            WHERE l.id = ?
        ");
        $this->db->bind(':id', $loan_id);
        $loan = $this->db->single();
        
        // Generate PDF agreement
        $agreement_content = $this->generateAgreementPDF($loan);
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://demo.docusign.net/restapi/v2.1/accounts/" . getenv('DOCUSIGN_ACCOUNT_ID') . "/envelopes",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'emailSubject' => 'Loan Agreement - JuaKali Lend',
                    'documents' => [[
                        'documentBase64' => base64_encode($agreement_content),
                        'name' => 'Loan_Agreement_' . $loan_id . '.pdf',
                        'fileExtension' => 'pdf',
                        'documentId' => '1'
                    ]],
                    'recipients' => [
                        'signers' => [
                            [
                                'email' => $loan['lender_email'],
                                'name' => $loan['lender_name'],
                                'recipientId' => '1',
                                'tabs' => [
                                    'signHereTabs' => [['pageNumber' => '1', 'xPosition' => '100', 'yPosition' => '100']]
                                ]
                            ],
                            [
                                'email' => $loan['retailer_email'],
                                'name' => $loan['retailer_name'],
                                'recipientId' => '2',
                                'tabs' => [
                                    'signHereTabs' => [['pageNumber' => '1', 'xPosition' => '100', 'yPosition' => '150']]
                                ]
                            ]
                        ]
                    ],
                    'status' => 'sent'
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $this->docusign_api_key
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            $result = json_decode($response, true);
            return $result;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate loan agreement PDF
     */
    private function generateAgreementPDF($loan) {
        $html = "
        <html>
        <head><title>Loan Agreement</title></head>
        <body>
        <h1>LOAN AGREEMENT</h1>
        <p>This Loan Agreement is entered into on " . date('Y-m-d') . "</p>
        <p><strong>Lender:</strong> " . htmlspecialchars($loan['lender_name']) . "</p>
        <p><strong>Borrower:</strong> " . htmlspecialchars($loan['retailer_name']) . "</p>
        <p><strong>Loan Amount:</strong> KES " . number_format($loan['loan_amount']) . "</p>
        <p><strong>Interest Rate:</strong> " . $loan['interest_rate'] . "%</p>
        <p><strong>Due Date:</strong> " . $loan['due_date'] . "</p>
        <p>The borrower agrees to repay the loan amount with interest by the due date.</p>
        </body>
        </html>";
        
        // Convert HTML to PDF (using a library like TCPDF or mPDF)
        return $html; // Simplified for example
    }
}
?>
