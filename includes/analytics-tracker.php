<?php
/**
 * Analytics Tracker
 * Event tracking and user behavior analysis
 */

class AnalyticsTracker {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Track event
     */
    public function trackEvent($user_id, $event_type, $event_data = []) {
        $this->db->query("
            INSERT INTO analytics_events (user_id, event_type, event_data, page_url, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':event_type', $event_type);
        $this->db->bind(':event_data', json_encode($event_data));
        $this->db->bind(':page_url', $_SERVER['REQUEST_URI'] ?? '');
        $this->db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
        $this->db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $this->db->execute();
    }
    
    /**
     * Get user cohort analysis
     */
    public function getCohortAnalysis($start_date, $end_date) {
        $this->db->query("
            SELECT DATE(created_at) as cohort_date, COUNT(*) as new_users
            FROM users
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY cohort_date ASC
        ");
        $this->db->bind(':start_date', $start_date);
        $this->db->bind(':end_date', $end_date);
        return $this->db->resultSet();
    }
}
?>
