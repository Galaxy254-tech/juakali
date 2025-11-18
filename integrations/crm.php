<?php
/**
 * Call Center & CRM Integration
 * Unified customer communication and support management
 */

class CRMIntegration {
    private $db;
    private $zendesk_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->zendesk_api_key = getenv('ZENDESK_API_KEY');
    }
    
    /**
     * Create support ticket
     */
    public function createTicket($user_id, $subject, $description, $priority = 'normal') {
        $this->db->query("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $this->db->bind(':id', $user_id);
        $user = $this->db->single();
        
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://juakali.zendesk.com/api/v2/tickets.json",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'ticket' => [
                        'subject' => $subject,
                        'description' => $description,
                        'priority' => $priority,
                        'requester' => [
                            'name' => $user['first_name'] . ' ' . $user['last_name'],
                            'email' => $user['email']
                        ]
                    ]
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Basic " . base64_encode(getenv('ZENDESK_EMAIL') . "/token:" . $this->zendesk_api_key)
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
     * Get customer communication history
     */
    public function getCustomerHistory($user_id) {
        $this->db->query("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $this->db->bind(':user_id', $user_id);
        return $this->db->resultSet();
    }
}
?>
