<?php
/**
 * Rate Limiter
 * Prevents brute force attacks and abuse
 */

class RateLimiter {
    private $db;
    private $max_attempts = 5;
    private $lockout_duration = 3600; // 1 hour
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if IP is rate limited
     */
    public function isRateLimited($ip_address) {
        $query = "SELECT attempts, last_attempt FROM rate_limits 
                  WHERE ip_address = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $this->db->query($query);
        $this->db->bind('si', $ip_address, $this->lockout_duration);
        $result = $this->db->single();
        
        if (!$result) {
            return false;
        }
        
        return $result['attempts'] >= $this->max_attempts;
    }
    
    /**
     * Record failed attempt
     */
    public function recordFailedAttempt($ip_address) {
        $query = "INSERT INTO rate_limits (ip_address, attempts, last_attempt) 
                  VALUES (?, 1, NOW())
                  ON DUPLICATE KEY UPDATE 
                  attempts = attempts + 1, last_attempt = NOW()";
        
        $this->db->query($query);
        $this->db->bind('s', $ip_address);
        return $this->db->execute();
    }
    
    /**
     * Reset rate limit
     */
    public function resetRateLimit($ip_address) {
        $query = "DELETE FROM rate_limits WHERE ip_address = ?";
        $this->db->query($query);
        $this->db->bind('s', $ip_address);
        return $this->db->execute();
    }
    
    /**
     * Get remaining attempts
     */
    public function getRemainingAttempts($ip_address) {
        $query = "SELECT attempts FROM rate_limits 
                  WHERE ip_address = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $this->db->query($query);
        $this->db->bind('si', $ip_address, $this->lockout_duration);
        $result = $this->db->single();
        
        if (!$result) {
            return $this->max_attempts;
        }
        
        return max(0, $this->max_attempts - $result['attempts']);
    }
    
    /**
     * Clean up old rate limit records
     */
    public function cleanupOldRecords() {
        $query = "DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $this->db->query($query);
        return $this->db->execute();
    }
}
?>