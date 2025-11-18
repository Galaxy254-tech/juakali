<?php
/**
 * Device Fingerprinting
 * Creates unique device identifiers for security
 */

class DeviceFingerprint {
    /**
     * Generate device fingerprint
     */
    public static function generate() {
        $fingerprint_data = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'accept_encoding' => $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            'ip_address' => self::getClientIP(),
            'timezone' => date_default_timezone_get(),
            'screen_resolution' => $_POST['screen_resolution'] ?? 'unknown',
            'browser_plugins' => $_POST['browser_plugins'] ?? 'unknown'
        ];
        
        return hash('sha256', json_encode($fingerprint_data));
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
    
    /**
     * Get geolocation from IP
     */
    public static function getGeolocation($ip) {
        // Integrate with GeoIP service
        // This is a placeholder
        return [
            'country' => 'KE',
            'city' => 'Nairobi',
            'latitude' => -1.2921,
            'longitude' => 36.8219
        ];
    }
}
?>
