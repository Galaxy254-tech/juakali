<?php
/**
 * Multi-Factor Authentication Handler
 * Manages SMS OTP, TOTP, biometric verification
 */

class MFAHandler {
    private $db;
    private $otp_expiry = 300; // 5 minutes
    private $max_otp_attempts = 3;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate and send SMS OTP
     */
    public function generateAndSendOTP($user_id, $phone_number) {
        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = date('Y-m-d H:i:s', time() + $this->otp_expiry);
        
        // Store OTP in database
        $query = "INSERT INTO otp_logs (user_id, otp, phone_number, expires_at, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('isss', $user_id, $otp, $phone_number, $expires_at);
        
        if ($stmt->execute()) {
            // Send SMS (integrate with SMS gateway)
            $this->sendSMS($phone_number, "Your JuaKali Lend OTP is: $otp. Valid for 5 minutes.");
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP($user_id, $otp) {
        $query = "SELECT id, attempts FROM otp_logs 
                  WHERE user_id = ? AND otp = ? AND expires_at > NOW() 
                  AND verified = 0 ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('is', $user_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Increment failed attempts
            $this->incrementOTPAttempts($user_id);
            return false;
        }
        
        $row = $result->fetch_assoc();
        
        if ($row['attempts'] >= $this->max_otp_attempts) {
            return false;
        }
        
        // Mark OTP as verified
        $update_query = "UPDATE otp_logs SET verified = 1, verified_at = NOW() WHERE id = ?";
        $update_stmt = $this->db->prepare($update_query);
        $update_stmt->bind_param('i', $row['id']);
        
        return $update_stmt->execute();
    }
    
    /**
     * Increment OTP attempts
     */
    private function incrementOTPAttempts($user_id) {
        $query = "UPDATE otp_logs SET attempts = attempts + 1 
                  WHERE user_id = ? AND verified = 0 AND expires_at > NOW()";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
    }
    
    /**
     * Generate TOTP secret for authenticator app
     */
    public function generateTOTPSecret() {
        $secret = '';
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $secret;
    }
    
    /**
     * Verify TOTP code
     */
    public function verifyTOTP($secret, $code) {
        $time = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $hash = hash_hmac('sha1', pack('N*', 0, $time + $i), $this->base32Decode($secret), true);
            $offset = ord($hash[19]) & 0xf;
            $totp = (((ord($hash[$offset]) & 0x7f) << 24) |
                    ((ord($hash[$offset + 1]) & 0xff) << 16) |
                    ((ord($hash[$offset + 2]) & 0xff) << 8) |
                    (ord($hash[$offset + 3]) & 0xff)) % 1000000;
            
            if ($totp == $code) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($input) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $c = strpos($alphabet, $input[$i]);
            if ($c === false) continue;
            $v = ($v << 5) | $c;
            $vbits += 5;
            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr(($v >> $vbits) & 0xff);
            }
        }
        
        return $output;
    }
    
    /**
     * Send SMS via gateway
     */
    private function sendSMS($phone_number, $message) {
        // Integrate with SMS gateway (Twilio, Africa's Talking, etc.)
        // This is a placeholder
        return true;
    }
}
?>
