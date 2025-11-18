<?php
/**
 * Content Management System
 * Dynamic content and promotional banners
 */

class ContentManagement {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get active banners
     */
    public function getActiveBanners() {
        $this->db->query("
            SELECT * FROM banners
            WHERE status = 'active'
            AND NOW() BETWEEN start_date AND end_date
            ORDER BY display_order ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get educational content
     */
    public function getEducationalContent($category = null) {
        $query = "SELECT * FROM educational_content WHERE status = 'published'";
        if ($category) {
            $query .= " AND category = ?";
        }
        $query .= " ORDER BY created_at DESC";
        
        $this->db->query($query);
        if ($category) {
            $this->db->bind(':category', $category);
        }
        return $this->db->resultSet();
    }
}
?>
