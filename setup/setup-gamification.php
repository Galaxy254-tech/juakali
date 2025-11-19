<?php
/**
 * Gamification Database Setup Script
 * Creates all necessary tables and relationships for the loyalty and gamification system
 */

require_once '../includes/config.php';
require_once '../includes/database.php';

echo "🎮 Setting up JuaKali Lend Gamification Database...\n\n";

try {
    // Initialize database connection
    $db = new Database();

    echo "📊 Creating gamification database tables...\n";

    // Create gamification tables
    setupGamificationDatabase($db);

    echo "\n✅ Gamification database setup completed successfully!\n";
    echo "\n🎯 Next steps:\n";
    echo "1. Test the gamification system by accessing the dashboard\n";
    echo "2. Configure gamification settings in includes/loyalty-gamification.php\n";
    echo "3. Set up automated triggers in loan processing workflows\n";
    echo "4. Customize rewards and achievements based on business requirements\n";

} catch (Exception $e) {
    echo "❌ Error during setup: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}

/**
 * Set up all gamification database tables
 */
function setupGamificationDatabase($db) {
    $tables = [

        // User Gamification Profiles
        'user_gamification' => "
            CREATE TABLE IF NOT EXISTS user_gamification (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                total_points INT DEFAULT 0,
                current_level INT DEFAULT 1,
                experience_points INT DEFAULT 0,
                current_streak INT DEFAULT 0,
                longest_streak INT DEFAULT 0,
                badges_earned TEXT,
                achievements_unlocked TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_gamification_user (user_id),
                INDEX idx_user_gamification_points (total_points),
                INDEX idx_user_gamification_level (current_level),
                INDEX idx_user_gamification_streak (current_streak)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Points Transactions
        'points_transactions' => "
            CREATE TABLE IF NOT EXISTS points_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                points INT NOT NULL,
                reason VARCHAR(50) NOT NULL,
                description TEXT,
                transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reference_id VARCHAR(50),
                reference_type VARCHAR(50),
                INDEX idx_points_user (user_id),
                INDEX idx_points_date (transaction_date),
                INDEX idx_points_reason (reason),
                INDEX idx_points_reference (reference_id, reference_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // User Streaks
        'user_streaks' => "
            CREATE TABLE IF NOT EXISTS user_streaks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                activity_type VARCHAR(50) NOT NULL,
                streak_count INT NOT NULL,
                activity_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_activity_date (user_id, activity_type, activity_date),
                INDEX idx_streaks_user (user_id),
                INDEX idx_streaks_date (activity_date),
                INDEX idx_streaks_type (activity_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Points Redemptions
        'points_redemptions' => "
            CREATE TABLE IF NOT EXISTS points_redemptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reward_id VARCHAR(50) NOT NULL,
                points_used INT NOT NULL,
                reward_data TEXT,
                redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'processed', 'expired', 'cancelled') DEFAULT 'pending',
                expires_at TIMESTAMP NULL,
                processed_at TIMESTAMP NULL,
                notes TEXT,
                INDEX idx_redemptions_user (user_id),
                INDEX idx_redemptions_date (redemption_date),
                INDEX idx_redemptions_status (status),
                INDEX idx_redemptions_reward (reward_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // User Rewards (Active rewards from redemptions)
        'user_rewards' => "
            CREATE TABLE IF NOT EXISTS user_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reward_type VARCHAR(50) NOT NULL,
                reward_value VARCHAR(100) NOT NULL,
                status ENUM('active', 'used', 'expired') DEFAULT 'active',
                redemption_id INT,
                expires_at TIMESTAMP NULL,
                used_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rewards_user (user_id),
                INDEX idx_rewards_type (reward_type),
                INDEX idx_rewards_status (status),
                INDEX idx_rewards_expires (expires_at),
                INDEX idx_rewards_redemption (redemption_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Gamification Events (Special events and challenges)
        'gamification_events' => "
            CREATE TABLE IF NOT EXISTS gamification_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                event_name VARCHAR(100) NOT NULL,
                description TEXT,
                points_reward INT DEFAULT 0,
                start_date TIMESTAMP NOT NULL,
                end_date TIMESTAMP NOT NULL,
                status ENUM('active', 'ended', 'cancelled', 'upcoming') DEFAULT 'upcoming',
                target_audience ENUM('all_users', 'new_users', 'active_users', 'premium_users') DEFAULT 'all_users',
                max_participants INT,
                current_participants INT DEFAULT 0,
                rules TEXT,
                prizes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_events_type (event_type),
                INDEX idx_events_dates (start_date, end_date),
                INDEX idx_events_status (status),
                INDEX idx_events_audience (target_audience)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Event Participations
        'event_participations' => "
            CREATE TABLE IF NOT EXISTS event_participations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                user_id INT NOT NULL,
                participation_status ENUM('joined', 'completed', 'dropped', 'disqualified') DEFAULT 'joined',
                progress_data TEXT,
                score DECIMAL(10,2),
                rank_position INT,
                points_awarded INT DEFAULT 0,
                rewards_earned TEXT,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_event_user (event_id, user_id),
                INDEX idx_participations_event (event_id),
                INDEX idx_participations_user (user_id),
                INDEX idx_participations_status (participation_status),
                INDEX idx_participations_rank (rank_position)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Achievement Definitions
        'achievement_definitions' => "
            CREATE TABLE IF NOT EXISTS achievement_definitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                achievement_key VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                icon VARCHAR(10),
                category VARCHAR(50),
                points_reward INT DEFAULT 0,
                criteria_type VARCHAR(50),
                criteria_value INT,
                criteria_operator ENUM('equals', 'greater_than', 'less_than', 'greater_equal', 'less_equal') DEFAULT 'greater_equal',
                is_active BOOLEAN DEFAULT TRUE,
                is_secret BOOLEAN DEFAULT FALSE,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_achievements_key (achievement_key),
                INDEX idx_achievements_category (category),
                INDEX idx_achievements_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Badge Definitions
        'badge_definitions' => "
            CREATE TABLE IF NOT EXISTS badge_definitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                badge_key VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                icon VARCHAR(10),
                tier ENUM('bronze', 'silver', 'gold', 'platinum', 'diamond') DEFAULT 'bronze',
                category VARCHAR(50),
                requirements TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_badges_key (badge_key),
                INDEX idx_badges_tier (tier),
                INDEX idx_badges_category (category),
                INDEX idx_badges_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Reward Definitions
        'reward_definitions' => "
            CREATE TABLE IF NOT EXISTS reward_definitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reward_key VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                type ENUM('discount', 'cashback', 'fee_waiver', 'priority_support', 'exclusive_access', 'physical_item') NOT NULL,
                value VARCHAR(100),
                points_required INT NOT NULL,
                category VARCHAR(50),
                availability ENUM('always', 'limited', 'seasonal', 'event_only') DEFAULT 'always',
                stock_quantity INT DEFAULT NULL,
                expiry_days INT DEFAULT NULL,
                terms_conditions TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_rewards_key (reward_key),
                INDEX idx_rewards_type (type),
                INDEX idx_rewards_category (category),
                INDEX idx_rewards_points (points_required),
                INDEX idx_rewards_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",

        // Level Definitions
        'level_definitions' => "
            CREATE TABLE IF NOT EXISTS level_definitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level_number INT NOT NULL UNIQUE,
                name VARCHAR(50) NOT NULL,
                description TEXT,
                min_points INT NOT NULL,
                color VARCHAR(7),
                icon VARCHAR(10),
                rewards_unlocked TEXT,
                benefits TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_levels_number (level_number),
                INDEX idx_levels_points (min_points),
                INDEX idx_levels_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    // Create tables
    foreach ($tables as $tableName => $sql) {
        try {
            $db->query($sql);
            echo "✅ Table '$tableName' created successfully\n";
        } catch (Exception $e) {
            echo "❌ Error creating table '$tableName': " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    echo "\n🔗 Adding foreign key constraints...\n";

    // Add foreign key constraints
    $constraints = [
        'fk_gamification_user' => "
            ALTER TABLE user_gamification
            ADD CONSTRAINT fk_gamification_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ",

        'fk_points_user' => "
            ALTER TABLE points_transactions
            ADD CONSTRAINT fk_points_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ",

        'fk_streaks_user' => "
            ALTER TABLE user_streaks
            ADD CONSTRAINT fk_streaks_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ",

        'fk_redemptions_user' => "
            ALTER TABLE points_redemptions
            ADD CONSTRAINT fk_redemptions_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ",

        'fk_rewards_user' => "
            ALTER TABLE user_rewards
            ADD CONSTRAINT fk_rewards_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ",

        'fk_rewards_redemption' => "
            ALTER TABLE user_rewards
            ADD CONSTRAINT fk_rewards_redemption
            FOREIGN KEY (redemption_id) REFERENCES points_redemptions(id) ON DELETE SET NULL
        ",

        'fk_event_participations_event' => "
            ALTER TABLE event_participations
            ADD CONSTRAINT fk_event_participations_event
            FOREIGN KEY (event_id) REFERENCES gamification_events(id) ON DELETE CASCADE
        ",

        'fk_event_participations_user' => "
            ALTER TABLE event_participations
            ADD CONSTRAINT fk_event_participations_user
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        "
    ];

    foreach ($constraints as $constraintName => $sql) {
        try {
            $db->query($sql);
            echo "✅ Constraint '$constraintName' added successfully\n";
        } catch (Exception $e) {
            // Constraint might already exist, so we'll continue
            echo "⚠️  Constraint '$constraintName': " . $e->getMessage() . "\n";
        }
    }

    echo "\n🎯 Inserting default data...\n";

    // Insert default achievement definitions
    insertDefaultAchievements($db);

    // Insert default badge definitions
    insertDefaultBadges($db);

    // Insert default reward definitions
    insertDefaultRewards($db);

    // Insert default level definitions
    insertDefaultLevels($db);

    echo "\n🎉 Gamification database setup completed!\n";
}

/**
 * Insert default achievement definitions
 */
function insertDefaultAchievements($db) {
    $achievements = [
        ['first_loan', 'First Steps', 'Take your first loan', '👶', 'milestone', 50, 'loan_count', 1, 'greater_equal'],
        ['regular_borrower', 'Regular Borrower', 'Complete 5 loans', '💼', 'milestone', 100, 'loan_count', 5, 'greater_equal'],
        ['frequent_borrower', 'Frequent Borrower', 'Complete 10 loans', '📈', 'milestone', 200, 'loan_count', 10, 'greater_equal'],
        ['loyal_customer', 'Loyal Customer', 'Complete 25 loans', '⭐', 'milestone', 500, 'loan_count', 25, 'greater_equal'],

        ['early_bird', 'Early Bird', 'Make 3 early payments', '🌅', 'payment', 75, 'early_payments', 3, 'greater_equal'],
        ['punctual_payer', 'Punctual Payer', 'Make 10 on-time payments', '⏰', 'payment', 150, 'ontime_payments', 10, 'greater_equal'],
        ['perfect_payment', 'Perfect Payment Record', 'Maintain 5-payment perfect streak', '💯', 'payment', 200, 'perfect_payment_streak', 5, 'greater_equal'],

        ['points_collector', 'Points Collector', 'Accumulate 500 points', '💰', 'points', 100, 'total_points', 500, 'greater_equal'],
        ['points_master', 'Points Master', 'Accumulate 1000 points', '🏆', 'points', 200, 'total_points', 1000, 'greater_equal'],
        ['points_legend', 'Points Legend', 'Accumulate 5000 points', '👑', 'points', 1000, 'total_points', 5000, 'greater_equal'],

        ['streak_starter', 'Streak Starter', 'Achieve 3-day streak', '🔥', 'engagement', 50, 'longest_streak', 3, 'greater_equal'],
        ['streak_master', 'Streak Master', 'Achieve 7-day streak', '⚡', 'engagement', 150, 'longest_streak', 7, 'greater_equal'],
        ['streak_legend', 'Streak Legend', 'Achieve 30-day streak', '🌟', 'engagement', 500, 'longest_streak', 30, 'greater_equal'],

        ['level_5', 'Level 5 Achieved', 'Reach level 5', '📊', 'progression', 100, 'level', 5, 'greater_equal'],
        ['level_10', 'Level 10 Achieved', 'Reach level 10', '📈', 'progression', 250, 'level', 10, 'greater_equal'],
        ['level_20', 'Level 20 Achieved', 'Reach level 20', '🚀', 'progression', 750, 'level', 20, 'greater_equal'],

        ['kyc_verified', 'KYC Verified', 'Complete KYC verification', '✅', 'verification', 50, 'kyc_status', 1, 'equals'],
        ['profile_complete', 'Profile Complete', 'Complete your profile', '📝', 'verification', 30, 'profile_completion', 100, 'greater_equal'],

        ['referral_champion', 'Referral Champion', 'Refer 5 successful users', '👥', 'social', 200, 'successful_referrals', 5, 'greater_equal'],
        ['community_builder', 'Community Builder', 'Refer 10 successful users', '🏘️', 'social', 500, 'successful_referrals', 10, 'greater_equal']
    ];

    foreach ($achievements as $achievement) {
        try {
            $db->insert('achievement_definitions', [
                'achievement_key' => $achievement[0],
                'name' => $achievement[1],
                'description' => $achievement[2],
                'icon' => $achievement[3],
                'category' => $achievement[4],
                'points_reward' => $achievement[5],
                'criteria_type' => $achievement[6],
                'criteria_value' => $achievement[7],
                'criteria_operator' => $achievement[8],
                'sort_order' => count($db->fetchAll("SELECT id FROM achievement_definitions")) + 1
            ]);
        } catch (Exception $e) {
            echo "⚠️  Achievement '{$achievement[0]}' may already exist\n";
        }
    }

    echo "✅ Default achievements inserted\n";
}

/**
 * Insert default badge definitions
 */
function insertDefaultBadges($db) {
    $badges = [
        ['bronze_borrower', 'Bronze Borrower', 'Completed 3+ loans', '🥉', 'bronze', 'milestone', 'loan_count >= 3'],
        ['silver_borrower', 'Silver Borrower', 'Completed 10+ loans', '🥈', 'silver', 'milestone', 'loan_count >= 10'],
        ['gold_borrower', 'Gold Borrower', 'Completed 25+ loans', '🥇', 'gold', 'milestone', 'loan_count >= 25'],
        ['platinum_borrower', 'Platinum Borrower', 'Completed 50+ loans', '💎', 'platinum', 'milestone', 'loan_count >= 50'],
        ['diamond_borrower', 'Diamond Borrower', 'Completed 100+ loans', '💠', 'diamond', 'milestone', 'loan_count >= 100'],

        ['early_payment_specialist', 'Early Payment Specialist', '80%+ early payments', '⏰', 'silver', 'payment', 'early_payment_rate >= 80'],
        ['payment_master', 'Payment Master', '95%+ on-time payments', '💰', 'gold', 'payment', 'ontime_payment_rate >= 95'],

        ['trust_score_bronze', 'Trust Score: Bronze', '70+ trust score', '🛡️', 'bronze', 'trust', 'trust_score >= 70'],
        ['trust_score_silver', 'Trust Score: Silver', '80+ trust score', '🛡️', 'silver', 'trust', 'trust_score >= 80'],
        ['trust_score_gold', 'Trust Score: Gold', '90+ trust score', '🛡️', 'gold', 'trust', 'trust_score >= 90'],
        ['trust_score_platinum', 'Trust Score: Platinum', '95+ trust score', '🛡️', 'platinum', 'trust', 'trust_score >= 95'],

        ['community_hero', 'Community Hero', 'Referred 10+ users', '🏆', 'gold', 'social', 'referral_count >= 10'],
        ['kyc_champion', 'KYC Champion', 'KYC verified', '✅', 'bronze', 'verification', 'kyc_verified = 1']
    ];

    foreach ($badges as $badge) {
        try {
            $db->insert('badge_definitions', [
                'badge_key' => $badge[0],
                'name' => $badge[1],
                'description' => $badge[2],
                'icon' => $badge[3],
                'tier' => $badge[4],
                'category' => $badge[5],
                'requirements' => $badge[6],
                'sort_order' => count($db->fetchAll("SELECT id FROM badge_definitions")) + 1
            ]);
        } catch (Exception $e) {
            echo "⚠️  Badge '{$badge[0]}' may already exist\n";
        }
    }

    echo "✅ Default badges inserted\n";
}

/**
 * Insert default reward definitions
 */
function insertDefaultRewards($db) {
    $rewards = [
        ['discount_5_percent', '5% Discount on Next Loan', 'Get 5% off your next loan interest', 'discount', '5', 200, 'loan_discounts', 'always', null, 30],
        ['discount_10_percent', '10% Discount on Next Loan', 'Get 10% off your next loan interest', 'discount', '10', 350, 'loan_discounts', 'always', null, 30],
        ['discount_15_percent', '15% Discount on Next Loan', 'Get 15% off your next loan interest', 'discount', '15', 500, 'loan_discounts', 'always', null, 30],

        ['fee_waiver', 'Processing Fee Waiver', 'Waive processing fees on your next loan', 'fee_waiver', '100', 150, 'fee_waivers', 'always', null, 45],
        ['partial_fee_waiver', 'Partial Fee Waiver', 'Get 50% off processing fees', 'fee_waiver', '50', 75, 'fee_waivers', 'always', null, 30],

        ['cashback_100', 'KES 100 Cashback', 'Get KES 100 cashback to your wallet', 'cashback', '100', 250, 'cashback', 'always', null, 60],
        ['cashback_250', 'KES 250 Cashback', 'Get KES 250 cashback to your wallet', 'cashback', '250', 500, 'cashback', 'always', null, 60],
        ['cashback_500', 'KES 500 Cashback', 'Get KES 500 cashback to your wallet', 'cashback', '500', 900, 'cashback', 'always', null, 60],

        ['priority_support_7_days', 'Priority Support (7 Days)', 'Get priority customer support for 7 days', 'priority_support', '7', 180, 'premium_features', 'always', null, 14],
        ['priority_support_30_days', 'Priority Support (30 Days)', 'Get priority customer support for 30 days', 'priority_support', '30', 500, 'premium_features', 'always', null, 45],

        ['exclusive_access_analytics', 'Advanced Analytics Access', 'Access advanced financial analytics for 30 days', 'exclusive_access', 'advanced_analytics', 400, 'premium_features', 'always', null, 30],
        ['exclusive_access_reports', 'Custom Reports Access', 'Generate custom financial reports', 'exclusive_access', 'custom_reports', 600, 'premium_features', 'always', null, 30],

        ['express_approval', 'Express Loan Approval', 'Skip to front of approval queue (once)', 'exclusive_access', 'express_approval', 300, 'premium_features', 'limited', 50, 90],
        ['higher_loan_limit', 'Increased Loan Limit', 'Increase your maximum loan limit by 20%', 'exclusive_access', 'limit_boost_20', 800, 'premium_features', 'always', null, 180]
    ];

    foreach ($rewards as $reward) {
        try {
            $db->insert('reward_definitions', [
                'reward_key' => $reward[0],
                'name' => $reward[1],
                'description' => $reward[2],
                'type' => $reward[3],
                'value' => $reward[4],
                'points_required' => $reward[5],
                'category' => $reward[6],
                'availability' => $reward[7],
                'stock_quantity' => $reward[8],
                'expiry_days' => $reward[9],
                'sort_order' => count($db->fetchAll("SELECT id FROM reward_definitions")) + 1
            ]);
        } catch (Exception $e) {
            echo "⚠️  Reward '{$reward[0]}' may already exist\n";
        }
    }

    echo "✅ Default rewards inserted\n";
}

/**
 * Insert default level definitions
 */
function insertDefaultLevels($db) {
    $levels = [
        [1, 'Newcomer', 'Just getting started', 0, '#94a3b8', '🌱', 'Welcome bonus points', 'Basic access to features'],
        [2, 'Beginner', 'Learning the ropes', 100, '#60a5fa', '🌿', '5% discount coupon', 'Access to basic tutorials'],
        [3, 'Apprentice', 'Building momentum', 300, '#3b82f6', '🌳', '10% discount coupon', 'Priority email support'],
        [4, 'Skilled', 'Gaining confidence', 600, '#2563eb', '🌲', 'Fee waiver on next loan', 'Access to loan calculator'],
        [5, 'Expert', 'Financially savvy', 1000, '#1d4ed8', '🏔️', '15% discount coupon', 'Advanced analytics access'],
        [6, 'Master', 'Experienced borrower', 1500, '#1e40af', '⛰️', 'Priority support for 30 days', 'Custom repayment schedules'],
        [7, 'Champion', 'Financial champion', 2500, '#7c3aed', '👑', '20% discount coupon', 'Exclusive offers and deals'],
        [8, 'Legend', 'Financial legend', 4000, '#6d28d9', '💎', '25% discount coupon', 'VIP treatment and benefits'],
        [9, 'Titan', 'Financial titan', 6000, '#5b21b6', '🚀', '30% discount coupon', 'Dedicated account manager'],
        [10, 'Mythic', 'Financial mythic', 10000, '#4c1d95', '⭐', 'Ultimate rewards package', 'All platform benefits']
    ];

    foreach ($levels as $level) {
        try {
            $db->insert('level_definitions', [
                'level_number' => $level[0],
                'name' => $level[1],
                'description' => $level[2],
                'min_points' => $level[3],
                'color' => $level[4],
                'icon' => $level[5],
                'rewards_unlocked' => $level[6],
                'benefits' => $level[7]
            ]);
        } catch (Exception $e) {
            echo "⚠️  Level '{$level[0]}' may already exist\n";
        }
    }

    echo "✅ Default levels inserted\n";
}

?>