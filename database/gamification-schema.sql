-- Gamification System Schema for JuaKali Lend
-- Contains tables for points, badges, challenges, achievements, and rewards

-- User Profiles for Gamification
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    current_level INT DEFAULT 1,
    streak_days INT DEFAULT 0,
    last_login_date DATETIME,
    level_updated_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_profile (user_id),
    INDEX idx_total_points (total_points),
    INDEX idx_current_level (current_level),
    INDEX idx_streak_days (streak_days)
);

-- User Levels Configuration
CREATE TABLE IF NOT EXISTS user_levels (
    level_id INT AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(50) NOT NULL,
    level_tier INT NOT NULL,
    min_points_required INT NOT NULL,
    benefits JSON,
    color_scheme VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_level_tier (level_tier),
    INDEX idx_min_points (min_points_required)
);

-- Insert default levels
INSERT IGNORE INTO user_levels (level_id, level_name, level_tier, min_points_required, benefits, color_scheme) VALUES
(1, 'Bronze', 1, 0, '["Basic access", "Standard rates"]', '#CD7F32'),
(2, 'Silver', 2, 500, '["Reduced fees", "Priority support", "5% discount"]', '#C0C0C0'),
(3, 'Gold', 3, 1500, '["Lower interest rates", "Exclusive offers", "10% discount", "Dedicated support"]', '#FFD700'),
(4, 'Platinum', 4, 3000, '["Best rates", "Dedicated support", "Cashback rewards", "15% discount", "VIP features"]', '#E5E4E2'),
(5, 'Diamond', 5, 6000, '["VIP treatment", "Special terms", "Invitation-only events", "20% discount", "Personal manager"]', '#B9F2FF');

-- Badges Configuration
CREATE TABLE IF NOT EXISTS badges (
    badge_code VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    points INT DEFAULT 0,
    category VARCHAR(50),
    unlock_criteria JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
);

-- Insert default badges
INSERT IGNORE INTO badges (badge_code, name, description, icon, points, category, unlock_criteria) VALUES
('first_loan', 'First Steps', 'Take your first loan', '🚀', 50, 'milestone', '{"loans_taken": 1}'),
('timely_payer', 'Punctual Payer', 'Pay 5 loans on time', '⏰', 100, 'payment', '{"on_time_payments": 5}'),
('early_bird', 'Early Bird', 'Pay 3 loans early', '🐦', 150, 'payment', '{"early_payments": 3}'),
('credit_champion', 'Credit Champion', 'Achieve credit score above 750', '🏆', 200, 'credit', '{"min_credit_score": 750}'),
('referral_master', 'Network Builder', 'Refer 5 successful users', '👥', 300, 'social', '{"successful_referrals": 5}'),
('loyalty_member', 'Loyal Member', 'Active for 6 months', '💎', 250, 'loyalty', '{"days_active": 180}'),
('super_supplier', 'Super Supplier', 'Complete 50 successful deliveries', '📦', 200, 'supplier', '{"completed_deliveries": 50}'),
('star_lender', 'Star Lender', 'Disburse 20 successful loans', '⭐', 300, 'lender', '{"disbursed_loans": 20}'),
('perfect_month', 'Perfect Month', 'No missed payments for 30 days', '🌟', 175, 'payment', '{"perfect_month": true}'),
('milestone_10_loans', 'Milestone: 10 Loans', 'Complete 10 successful loans', '🎯', 150, 'milestone', '{"completed_loans": 10}');

-- User Badges Earned
CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_code VARCHAR(50) NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_code) REFERENCES badges(badge_code),
    UNIQUE KEY unique_user_badge (user_id, badge_code),
    INDEX idx_user_id (user_id),
    INDEX idx_earned_at (earned_at)
);

-- User Points Transactions
CREATE TABLE IF NOT EXISTS user_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    points_earned INT NOT NULL,
    multiplier DECIMAL(3,2) DEFAULT 1.00,
    final_points INT NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_final_points (final_points)
);

-- Challenges
CREATE TABLE IF NOT EXISTS challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    challenge_type VARCHAR(100) NOT NULL,
    target_value INT NOT NULL,
    points_reward INT NOT NULL,
    badge_reward VARCHAR(50),
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    eligibility_criteria JSON,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (badge_reward) REFERENCES badges(badge_code),
    INDEX idx_challenge_type (challenge_type),
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
);

-- Challenge Participants
CREATE TABLE IF NOT EXISTS challenge_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    completed_at DATETIME,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_challenge (user_id, challenge_id),
    INDEX idx_challenge_id (challenge_id),
    INDEX idx_completed (completed),
    INDEX idx_progress (progress)
);

-- Rewards Store
CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    reward_type ENUM('discount', 'voucher', 'feature', 'service', 'physical') NOT NULL,
    points_cost INT NOT NULL,
    value DECIMAL(10,2),
    validity_days INT,
    terms_conditions TEXT,
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    stock_quantity INT DEFAULT NULL,
    max_per_user INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_reward_type (reward_type),
    INDEX idx_points_cost (points_cost),
    INDEX idx_is_active (is_active)
);

-- Insert default rewards
INSERT IGNORE INTO rewards (name, description, reward_type, points_cost, value, validity_days, terms_conditions, max_per_user) VALUES
('5% Interest Discount', 'Get 5% discount on your next loan interest rate', 'discount', 500, 5.00, 30, 'Valid for one loan only', 3),
('Processing Fee Waiver', 'Waive all processing fees on your next loan', 'discount', 1000, NULL, 60, 'Valid for one loan up to KES 50,000', 2),
('Priority Support', 'Get priority customer support for 1 month', 'feature', 750, NULL, 30, 'Jump the queue and get dedicated support', 4),
('Credit Score Boost', 'Add 10 points to your credit score', 'feature', 1500, 10.00, 90, 'One-time boost, max once per year', 1),
('Extended Repayment', 'Extend your loan repayment period by 7 days', 'feature', 300, NULL, 60, 'Valid for existing loans only', 6),
('Mobile Data Bundle', 'Get 1GB mobile data bundle', 'service', 200, NULL, 15, 'Partner network providers', 12),
('Business Consultation', '30-minute business consultation session', 'service', 2000, NULL, 90, 'Expert business advice', 2);

-- User Rewards Redemption
CREATE TABLE IF NOT EXISTS user_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    points_spent INT NOT NULL,
    status ENUM('pending', 'active', 'used', 'expired', 'cancelled') DEFAULT 'pending',
    redemption_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    expiry_date DATETIME,
    used_date DATETIME,
    redemption_code VARCHAR(50) UNIQUE,
    notes TEXT,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_redemption_code (redemption_code)
);

-- User Activity Tracking
CREATE TABLE IF NOT EXISTS user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_data JSON,
    activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (activity_date)
);

-- Real-time Notifications Queue
CREATE TABLE IF NOT EXISTS realtime_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    data JSON,
    delivered BOOLEAN DEFAULT FALSE,
    delivered_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_delivered (delivered),
    INDEX idx_notification_type (notification_type)
);

-- Achievement Definitions
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    achievement_type VARCHAR(100) NOT NULL,
    trigger_conditions JSON NOT NULL,
    points_reward INT NOT NULL,
    badge_reward VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (badge_reward) REFERENCES badges(badge_code),
    INDEX idx_achievement_type (achievement_type),
    INDEX idx_is_active (is_active)
);

-- Insert default achievements
INSERT IGNORE INTO achievements (name, description, achievement_type, trigger_conditions, points_reward, badge_reward) VALUES
('Loan Journey Begins', 'Successfully apply for your first loan', 'loan_application', '{"loan_applications": 1}', 25, null),
('Responsible Borrower', 'Complete 5 successful loans', 'loan_completion', '{"completed_loans": 5}', 100, 'timely_payer'),
('Speed Demon', 'Complete loan application in under 5 minutes', 'application_speed', '{"application_time_seconds": 300}', 50, null),
('Social Butterfly', 'Refer 3 friends who successfully join', 'referral_milestone', '{"successful_referrals": 3}', 150, 'referral_master'),
('Morning Person', 'Login before 8 AM for 7 consecutive days', 'login_pattern', '{"consecutive_morning_logins": 7}', 75, null),
('Digital Native', 'Complete all actions via mobile app', 'platform_usage', '{"mobile_actions_percentage": 100}', 50, null),
('Streak Master', 'Maintain 30-day login streak', 'streak_milestone', '{"streak_days": 30}', 200, 'loyalty_member'),
('Perfect Payment History', 'Never miss a payment for 6 months', 'payment_history', '{"perfect_payments_months": 6}', 300, 'perfect_month');

-- User Achievements
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    progress INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    completed_at DATETIME,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id),
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_id (user_id),
    INDEX idx_completed (completed)
);

-- Gamification Settings
CREATE TABLE IF NOT EXISTS gamification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value JSON NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_setting_key (setting_key)
);

-- Insert default settings
INSERT IGNORE INTO gamification_settings (setting_key, setting_value, description) VALUES
('points_multipliers', '{"bronze": 1.0, "silver": 1.2, "gold": 1.5, "platinum": 1.8, "diamond": 2.0}', 'Point multipliers for each level'),
('daily_login_points', '5', 'Points awarded for daily login'),
('streak_bonuses', '{"weekly": 50, "monthly": 200}', 'Bonus points for streak milestones'),
('challenge_rewards', '{"participation": 10, "completion": 100, "top_performer": 200}', 'Challenge reward points'),
('notification_settings', '{"achievements": true, "level_up": true, "badges": true, "challenges": true}', 'Default notification preferences'),
('leaderboard_refresh', '3600', 'Leaderboard refresh interval in seconds'),
('max_challenges_per_user', '5', 'Maximum active challenges per user');

-- Gamification Analytics Cache
CREATE TABLE IF NOT EXISTS gamification_analytics_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(100) NOT NULL,
    cache_data JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_cache_key (cache_key),
    INDEX idx_expires_at (expires_at)
);

-- User Gamification Preferences
CREATE TABLE IF NOT EXISTS user_gamification_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_preferences JSON,
    privacy_settings JSON,
    display_preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preferences (user_id)
);

-- User Referrals Table (for gamification)
CREATE TABLE IF NOT EXISTS user_referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referral_id INT NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referral_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_referral (referrer_id, referral_id),
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referral_code (referral_code),
    INDEX idx_status (status)
);

-- Notifications Table (for gamification notifications)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- User Activity Archive Table (for old activity logs)
CREATE TABLE IF NOT EXISTS user_activity_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_data JSON,
    activity_date DATETIME NOT NULL,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (activity_date),
    INDEX idx_archived_at (archived_at)
);

-- Insert default preferences for existing users
INSERT IGNORE INTO user_gamification_preferences (user_id, notification_preferences, privacy_settings, display_preferences)
SELECT u.id,
       '{"achievements": true, "level_up": true, "badges": true, "challenges": true, "leaderboard": true}',
       '{"profile_visible": true, "achievements_visible": true, "leaderboard_visible": true}',
       '{"theme": "light", "animations": true, "sound_effects": false}'
FROM users u
WHERE u.id NOT IN (SELECT user_id FROM user_gamification_preferences);

-- Create indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_user_points_user_created ON user_points(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_user_points_action ON user_points(action);
CREATE INDEX IF NOT EXISTS idx_user_points_final_points ON user_points(final_points);

CREATE INDEX IF NOT EXISTS idx_user_badges_user_earned ON user_badges(user_id, earned_at);
CREATE INDEX IF NOT EXISTS idx_user_badges_badge_code ON user_badges(badge_code);

CREATE INDEX IF NOT EXISTS idx_challenge_participants_user_completed ON challenge_participants(user_id, completed);
CREATE INDEX IF NOT EXISTS idx_challenge_participants_challenge ON challenge_participants(challenge_id);
CREATE INDEX IF NOT EXISTS idx_challenge_participants_progress ON challenge_participants(progress);

CREATE INDEX IF NOT EXISTS idx_user_rewards_user_status ON user_rewards(user_id, status);
CREATE INDEX IF NOT EXISTS idx_user_rewards_reward_id ON user_rewards(reward_id);
CREATE INDEX IF NOT EXISTS idx_user_rewards_expiry ON user_rewards(expiry_date);
CREATE INDEX IF NOT EXISTS idx_user_rewards_redemption_code ON user_rewards(redemption_code);

CREATE INDEX IF NOT EXISTS idx_user_activity_user_type_date ON user_activity(user_id, activity_type, activity_date);
CREATE INDEX IF NOT EXISTS idx_user_activity_date ON user_activity(activity_date);

CREATE INDEX IF NOT EXISTS idx_challenges_status_dates ON challenges(status, start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_challenges_type ON challenges(challenge_type);

CREATE INDEX IF NOT EXISTS idx_rewards_active ON rewards(is_active);
CREATE INDEX IF NOT EXISTS idx_rewards_cost ON rewards(points_cost);
CREATE INDEX IF NOT EXISTS idx_rewards_type ON rewards(reward_type);

CREATE INDEX IF NOT EXISTS idx_achievements_type ON achievements(achievement_type);
CREATE INDEX IF NOT EXISTS idx_achievements_active ON achievements(is_active);

CREATE INDEX IF NOT EXISTS idx_user_achievements_user_achievement ON user_achievements(user_id, achievement_id);
CREATE INDEX IF NOT EXISTS idx_user_achievements_completed ON user_achievements(completed);

CREATE INDEX IF NOT EXISTS idx_realtime_notifications_user_delivered ON realtime_notifications(user_id, delivered);
CREATE INDEX IF NOT EXISTS idx_realtime_notifications_type ON realtime_notifications(notification_type);

-- Additional performance indexes
CREATE INDEX IF NOT EXISTS idx_user_profiles_points ON user_profiles(total_points);
CREATE INDEX IF NOT EXISTS idx_user_profiles_level ON user_profiles(current_level);
CREATE INDEX IF NOT EXISTS idx_user_profiles_streak ON user_profiles(streak_days);

CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- Create views for common queries
CREATE OR REPLACE VIEW user_gamification_summary AS
SELECT
    u.id as user_id,
    u.name as user_name,
    u.profile_image,
    COALESCE(up.total_points, 0) as total_points,
    COALESCE(up.current_level, 1) as current_level,
    COALESCE(up.streak_days, 0) as streak_days,
    ul.level_name,
    COUNT(DISTINCT ub.id) as badges_earned,
    COUNT(DISTINCT cp.id) as active_challenges,
    COALESCE(SUM(up_points.final_points), 0) as lifetime_points
FROM users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN user_levels ul ON up.current_level = ul.level_id
LEFT JOIN user_badges ub ON u.id = ub.user_id
LEFT JOIN challenge_participants cp ON u.id = cp.user_id AND cp.completed = 0
LEFT JOIN user_points up_points ON u.id = up_points.user_id
WHERE u.status = 'active'
GROUP BY u.id, u.name, u.profile_image, up.total_points, up.current_level, up.streak_days, ul.level_name;

CREATE OR REPLACE VIEW challenge_leaderboard AS
SELECT
    c.id as challenge_id,
    c.title,
    c.challenge_type,
    c.target_value,
    c.points_reward,
    c.end_date,
    cp.user_id,
    u.name as user_name,
    cp.progress,
    CASE
        WHEN cp.progress >= c.target_value THEN 1
        ELSE 0
    END as completed,
    ROW_NUMBER() OVER (PARTITION BY c.id ORDER BY cp.progress DESC, cp.completed_at ASC) as rank
FROM challenges c
JOIN challenge_participants cp ON c.id = cp.challenge_id
JOIN users u ON cp.user_id = u.id
WHERE c.status = 'active'
ORDER BY c.end_date ASC, rank ASC;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS UpdateUserStreak(IN user_id INT)
BEGIN
    DECLARE yesterday_count INT DEFAULT 0;
    DECLARE today_count INT DEFAULT 0;

    -- Check if user logged in yesterday
    SELECT COUNT(*) INTO yesterday_count
    FROM user_activity
    WHERE user_id = user_id
      AND activity_type = 'login'
      AND DATE(activity_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY);

    -- Check if user already logged in today
    SELECT COUNT(*) INTO today_count
    FROM user_activity
    WHERE user_id = user_id
      AND activity_type = 'login'
      AND DATE(activity_date) = CURDATE();

    -- Update streak
    IF today_count = 0 THEN
        INSERT INTO user_activity (user_id, activity_type, activity_date)
        VALUES (user_id, 'login', NOW());

        IF yesterday_count > 0 THEN
            UPDATE user_profiles
            SET streak_days = streak_days + 1, last_login_date = NOW()
            WHERE user_id = user_id;
        ELSE
            UPDATE user_profiles
            SET streak_days = 1, last_login_date = NOW()
            WHERE user_id = user_id;
        END IF;
    END IF;
END //

CREATE PROCEDURE IF NOT EXISTS ProcessLevelUp(IN user_id INT)
BEGIN
    DECLARE current_points INT DEFAULT 0;
    DECLARE current_level INT DEFAULT 1;
    DECLARE new_level INT DEFAULT 1;

    -- Get current user data
    SELECT total_points, current_level INTO current_points, current_level
    FROM user_profiles
    WHERE user_id = user_id;

    -- Calculate new level
    SELECT level_id INTO new_level
    FROM user_levels
    WHERE min_points_required <= current_points
    ORDER BY level_id DESC
    LIMIT 1;

    -- Update if level increased
    IF new_level > current_level THEN
        UPDATE user_profiles
        SET current_level = new_level, level_updated_at = NOW()
        WHERE user_id = user_id;

        -- Insert level up activity
        INSERT INTO user_activity (user_id, activity_type, activity_data)
        VALUES (user_id, 'level_up', JSON_OBJECT(
            'old_level', current_level,
            'new_level', new_level,
            'points', current_points
        ));
    END IF;
END //

DELIMITER ;

-- Create triggers for automatic updates
DELIMITER //

CREATE TRIGGER IF NOT EXISTS after_points_awarded
AFTER INSERT ON user_points
FOR EACH ROW
BEGIN
    -- Update user total points
    UPDATE user_profiles
    SET total_points = (
        SELECT COALESCE(SUM(final_points), 0)
        FROM user_points
        WHERE user_id = NEW.user_id
    )
    WHERE user_id = NEW.user_id;

    -- Check for level up
    CALL ProcessLevelUp(NEW.user_id);
END //

DELIMITER ;

-- Create events for automatic processing
CREATE EVENT IF NOT EXISTS DailyGamificationProcessing
ON SCHEDULE EVERY 1 DAY
STARTS '2024-01-01 00:00:00'
DO
BEGIN
    -- This would be implemented to process daily rewards, streaks, etc.
    -- For now, it's a placeholder for the cron job functionality
END //

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;

COMMIT;