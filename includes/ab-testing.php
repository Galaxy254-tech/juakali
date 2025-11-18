<?php
/**
 * A/B Testing Framework
 * Feature rollout and conversion optimization
 */

class ABTesting {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Assign user to test variant
     */
    public function assignVariant($user_id, $test_name) {
        // Check if user already assigned
        $this->db->query("
            SELECT variant FROM ab_tests WHERE user_id = ? AND test_name = ?
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':test_name', $test_name);
        $existing = $this->db->single();
        
        if ($existing) {
            return $existing['variant'];
        }
        
        // Randomly assign to variant A or B
        $variant = rand(0, 1) ? 'A' : 'B';
        
        $this->db->query("
            INSERT INTO ab_tests (user_id, test_name, variant)
            VALUES (?, ?, ?)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':test_name', $test_name);
        $this->db->bind(':variant', $variant);
        $this->db->execute();
        
        return $variant;
    }
    
    /**
     * Track conversion
     */
    public function trackConversion($user_id, $test_name, $conversion_value) {
        $this->db->query("
            UPDATE ab_tests SET conversions = conversions + 1, conversion_value = conversion_value + ?
            WHERE user_id = ? AND test_name = ?
        ");
        $this->db->bind(':value', $conversion_value);
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':test_name', $test_name);
        $this->db->execute();
    }
}
?>
