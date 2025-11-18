<?php
/**
 * WhatsApp Gateway Integration
 * Integrates with WhatsApp Business API via Twilio or native API
 */
class WhatsAppGateway {
    private $api_key;
    private $phone_number_id;
    private $business_account_id;
    
    public function __construct() {
        $this->api_key = getenv('WHATSAPP_API_KEY') ?: '';
        $this->phone_number_id = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';
        $this->business_account_id = getenv('WHATSAPP_BUSINESS_ACCOUNT_ID') ?: '';
    }
    
    /**
     * Send WhatsApp message
     * @param string $phone_number Recipient phone number
     * @param string $message Message text
     * @param array $media Media attachments (optional)
     * @return array Response
     */
    public function sendMessage($phone_number, $message, $media = null) {
        $phone = $this->formatPhoneNumber($phone_number);
        
        $url = "https://graph.instagram.com/v18.0/{$this->phone_number_id}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
        ];
        
        if ($media) {
            $payload['type'] = $media['type'] ?? 'document';
            $payload[$media['type']] = [
                'link' => $media['url'],
                'caption' => $message
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['body' => $message];
        }
        
        $response = $this->makeRequest($url, $payload);
        
        return [
            'success' => isset($response['messages'][0]['id']),
            'message' => isset($response['messages'][0]['id']) ? 'WhatsApp message sent' : 'Failed to send',
            'message_id' => $response['messages'][0]['id'] ?? null,
            'error' => $response['error'] ?? null
        ];
    }
    
    /**
     * Send template message
     * @param string $phone_number Recipient phone number
     * @param string $template_name Template name
     * @param array $parameters Template parameters
     * @return array
     */
    public function sendTemplateMessage($phone_number, $template_name, $parameters = []) {
        $phone = $this->formatPhoneNumber($phone_number);
        
        $url = "https://graph.instagram.com/v18.0/{$this->phone_number_id}/messages";
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template_name,
            ]
        ];
        
        if (!empty($parameters)) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(function($param) {
                        return ['type' => 'text', 'text' => $param];
                    }, $parameters)
                ]
            ];
        }
        
        return $this->makeRequest($url, $payload);
    }
    
    /**
     * Format phone number to international format
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '0')) {
                $phone = '254' . substr($phone, 1);
            } else {
                $phone = '254' . $phone;
            }
        } else {
            $phone = substr($phone, 1);
        }
        
        return $phone;
    }
    
    /**
     * Make HTTP request to WhatsApp API
     * @param string $url URL
     * @param array $data Data to send
     * @return array Response
     */
    private function makeRequest($url, $data) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);
        
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        return $response ?? [];
    }
}
?>
