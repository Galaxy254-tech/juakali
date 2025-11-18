<?php
/**
 * JWT Token Handler
 * Handles creation, validation, and refresh of JWT tokens
 */

class JWTHandler {
    private $secret_key;
    private $algorithm = 'HS512';
    private $access_token_expiry = 900; // 15 minutes
    private $refresh_token_expiry = 604800; // 7 days
    
    public function __construct() {
        $this->secret_key = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-in-production';
    }
    
    /**
     * Create JWT token
     */
    public function createToken($user_id, $role, $permissions = [], $device_fingerprint = '') {
        $header = [
            'alg' => $this->algorithm,
            'typ' => 'JWT'
        ];
        
        $payload = [
            'user_id' => $user_id,
            'role' => $role,
            'permissions' => $permissions,
            'device_fingerprint' => $device_fingerprint,
            'iss' => 'juakali-lend',
            'iat' => time(),
            'exp' => time() + $this->access_token_expiry
        ];
        
        return $this->encode($header, $payload);
    }
    
    /**
     * Create refresh token
     */
    public function createRefreshToken($user_id, $device_fingerprint = '') {
        $header = [
            'alg' => $this->algorithm,
            'typ' => 'JWT'
        ];
        
        $payload = [
            'user_id' => $user_id,
            'type' => 'refresh',
            'device_fingerprint' => $device_fingerprint,
            'iss' => 'juakali-lend',
            'iat' => time(),
            'exp' => time() + $this->refresh_token_expiry
        ];
        
        return $this->encode($header, $payload);
    }
    
    /**
     * Encode JWT token
     */
    private function encode($header, $payload) {
        $header_encoded = $this->base64UrlEncode(json_encode($header));
        $payload_encoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac(
            'sha512',
            $header_encoded . '.' . $payload_encoded,
            $this->secret_key,
            true
        );
        $signature_encoded = $this->base64UrlEncode($signature);
        
        return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
    }
    
    /**
     * Verify and decode JWT token
     */
    public function verifyToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Verify signature
        $signature = hash_hmac(
            'sha512',
            $header_encoded . '.' . $payload_encoded,
            $this->secret_key,
            true
        );
        $signature_encoded_check = $this->base64UrlEncode($signature);
        
        if (!hash_equals($signature_encoded, $signature_encoded_check)) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payload_encoded), true);
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 4 - strlen($data) % 4));
    }
}
?>
