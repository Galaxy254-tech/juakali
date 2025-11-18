<?php
/**
 * Gamification Engine
 * Achievements, badges, and rewards
 */

class Gamification {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Award achievement
     */
    public function awardAchievement($user_id, $achievement_type) {
        $achievements = [
            'first_loan' => ['points' => 100, 'badge' => 'First Loan'],
            'five_loans' => ['points' => 250, 'badge' => 'Loan Master'],
            'perfect_repayment' => ['points' => 500, 'badge' => 'Perfect Record'],
            'high_credit_score' => ['points' => 300, 'badge' => 'Credit Star']
        ];
        
        if (!isset($achievements[$achievement_type])) {
            return false;
        }
        
        $achievement = $achievements[$achievement_type];
        
        // Award points
        $this->db->query("
            UPDATE users SET loyalty_points = loyalty_points + ?
            WHERE id = ?
        ");
        $this->db->bind(':points', $achievement['points']);
        $this->db->bind(':id', $user_id);
        $this->db->execute();
        
        // Log achievement
        $this->db->query("
            INSERT INTO user_achievements (user_id, achievement_type, badge_name, points_awarded)
            VALUES (?, ?, ?, ?)
        ");
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':achievement_type', $achievement_type);
        $this->db->bind(':badge_name', $achievement['badge']);
        $this->db->bind(':points', $achievement['points']);
        $this->db->execute();
        
        return true;
    }
    
    /**
     * Get user achievements
     */
    public function getUserAchievements($user_id) {
        $this->db->query("
            SELECT * FROM user_achievements WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $this->db->bind(':user_id', $user_id);
        return $this->db->resultSet();
    }
}
?>
