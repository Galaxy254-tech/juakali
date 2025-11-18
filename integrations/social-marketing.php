<?php
/**
 * Social Media & Marketing Automation Integration
 * Targeted advertising and customer engagement
 */

class SocialMarketing {
    private $db;
    private $facebook_api_key;
    private $mailchimp_api_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->facebook_api_key = getenv('FACEBOOK_API_KEY');
        $this->mailchimp_api_key = getenv('MAILCHIMP_API_KEY');
    }
    
    /**
     * Create targeted Facebook ad campaign
     */
    public function createFacebookCampaign($campaign_name, $target_audience, $budget) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://graph.facebook.com/v12.0/me/campaigns",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => http_build_query([
                    'name' => $campaign_name,
                    'objective' => 'LINK_CLICKS',
                    'special_ad_categories' => ['CREDIT'],
                    'daily_budget' => $budget * 100,
                    'access_token' => $this->facebook_api_key
                ]),
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Send marketing email campaign
     */
    public function sendEmailCampaign($segment, $subject, $content) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://us1.api.mailchimp.com/3.0/campaigns",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode([
                    'type' => 'regular',
                    'recipients' => ['segment_opts' => ['saved_segment_id' => $segment]],
                    'settings' => [
                        'subject_line' => $subject,
                        'from_name' => 'JuaKali Lend',
                        'reply_to' => 'support@juakalilend.com'
                    ],
                    'content' => ['html' => $content]
                ]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Basic " . base64_encode("user:" . $this->mailchimp_api_key)
                ],
            ]);
            
            $response = curl_exec($curl);
            curl_close($curl);
            
            return json_decode($response, true);
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
