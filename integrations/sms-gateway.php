<?php
/**
 * SMS Gateway Integration
 * Supports M-Pesa SMS, Twilio, and other SMS providers
 */
class SMSGateway {
    private $api_key;
    private $api_secret;
    private $provider;
    
    public function __construct($provider = 'mpesa') {
        $this->provider = $provider;
        $this->api_key = getenv('SMS_API_KEY') ?: '';
        $this->api_secret = getenv('SMS_API_SECRET') ?: '';
    }
    
    /**
     * Send SMS message
     * @param string $phone_number Recipient phone number
     * @param string $message Message text
     * @param string $sender_id Sender ID
     * @return array Response with success status
     */
    public function sendSMS($phone_number, $message, $sender_id = 'JuaKali') {
        if (empty($phone_number) || empty($message)) {
            return ['success' => false, 'message' => 'Phone number and message required'];
        }
        
        // Format phone number (add country code if needed)
        $phone = $this->formatPhoneNumber($phone_number);
        
        switch ($this->provider) {
            case 'mpesa':
                return $this->sendViaMpesa($phone, $message, $sender_id);
            case 'twilio':
                return $this->sendViaTwilio($phone, $message);
            case 'africastalking':
                return $this->sendViaAfricasTalking($phone, $message, $sender_id);
            default:
                return ['success' => false, 'message' => 'Unknown SMS provider'];
        }
    }
    
    /**
     * Send SMS via M-Pesa/Safaricom
     * @param string $phone Phone number
     * @param string $message Message text
     * @param string $sender_id Sender ID
     * @return array
     */
    private function sendViaMpesa($phone, $message, $sender_id) {
        // M-Pesa STK push or SMS API endpoint
        $url = 'https://api.example.com/sms/send'; // Replace with actual API
        
        $data = [
            'phone' => $phone,
            'message' => $message,
            'sender_id' => $sender_id,
            'api_key' => $this->api_key,
            'timestamp' => time()
        ];
        
        $response = $this->makeRequest($url, $data);
        
        return [
            'success' => isset($response['status']) && $response['status'] === 'success',
            'message' => $response['message'] ?? 'SMS sent',
            'reference_id' => $response['reference_id'] ?? null
        ];
    }
    
    /**
     * Send SMS via Twilio
     * @param string $phone Phone number
     * @param string $message Message text
     * @return array
     */
    private function sendViaTwilio($phone, $message) {
        $account_sid = getenv('TWILIO_ACCOUNT_SID');
        $auth_token = getenv('TWILIO_AUTH_TOKEN');
        $from_number = getenv('TWILIO_FROM_NUMBER');
        
        $url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";
        
        $data = [
            'From' => $from_number,
            'To' => $phone,
            'Body' => $message
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, "$account_sid:$auth_token");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        return [
            'success' => isset($response['sid']),
            'message' => $response['sid'] ? 'SMS sent via Twilio' : 'Failed to send SMS',
            'reference_id' => $response['sid'] ?? null
        ];
    }
    
    /**
     * Send SMS via Africa's Talking
     * @param string $phone Phone number
     * @param string $message Message text
     * @param string $sender_id Sender ID
     * @return array
     */
    private function sendViaAfricasTalking($phone, $message, $sender_id) {
        $url = 'https://api.sandbox.africastalking.com/version1/messaging';
        
        $data = [
            'username' => getenv('AFRICAS_TALKING_USERNAME'),
            'APIkey' => getenv('AFRICAS_TALKING_API_KEY'),
            'recipients' => $phone,
            'message' => $message,
            'bulkSMSMode' => 1,
            'senderId' => $sender_id
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apikey: ' . getenv('AFRICAS_TALKING_API_KEY')
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        return [
            'success' => isset($response['SMSMessageData']),
            'message' => isset($response['SMSMessageData']) ? 'SMS sent via Africa\'s Talking' : 'Failed to send SMS',
            'reference_id' => $response['SMSMessageData']['Recipients'][0]['messageId'] ?? null
        ];
    }
    
    /**
     * Format phone number to international format
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove common separators
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Add country code if missing (assume Kenya +254)
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '0')) {
                $phone = '+254' . substr($phone, 1);
            } else {
                $phone = '+254' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Make HTTP request
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
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        
        return $response ?? [];
    }
}
?>
