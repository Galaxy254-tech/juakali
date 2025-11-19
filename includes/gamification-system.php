<?php
/**
 * Gamification and Loyalty Rewards System for JuaKali Lend
 * Encourages user engagement and positive financial behavior
 * Features points, badges, achievements, levels, and rewards
 */

require_once 'config/database.php';

class GamificationSystem {
    private $db;
    private $userId;
    private $userRole;

    // Gamification configuration
    private $pointsConfig = [
        'loan_repayment_on_time' => 50,
        'loan_repayment_early' => 75,
        'loan_application' => 10,
        'profile_completion' => 25,
        'kyc_verification' => 100,
        'referral_success' => 200,
        'daily_login' => 5,
        'weekly_streak' => 50,
        'monthly_streak' => 200,
        'supplier_delivery' => 30,
        'lender_disbursement' => 40,
        'good_credit_score' => 150
    ];

    private $badgeConfig = [
        'first_loan' => [
            'name' => 'First Steps',
            'description' => 'Take your first loan',
            'icon' => '🚀',
            'points' => 50
        ],
        'timely_payer' => [
            'name' => 'Punctual Payer',
            'description' => 'Pay 5 loans on time',
            'icon' => '⏰',
            'points' => 100
        ],
        'early_bird' => [
            'name' => 'Early Bird',
            'description' => 'Pay 3 loans early',
            'icon' => '🐦',
            'points' => 150
        ],
        'credit_champion' => [
            'name' => 'Credit Champion',
            'description' => 'Achieve credit score above 750',
            'icon' => '🏆',
            'points' => 200
        ],
        'referral_master' => [
            'name' => 'Network Builder',
            'description' => 'Refer 5 successful users',
            'icon' => '👥',
            'points' => 300
        ],
        'loyalty_member' => [
            'name' => 'Loyal Member',
            'description' => 'Active for 6 months',
            'icon' => '💎',
            'points' => 250
        ],
        'super_supplier' => [
            'name' => 'Super Supplier',
            'description' => 'Complete 50 successful deliveries',
            'icon' => '📦',
            'points' => 200
        ],
        'star_lender' => [
            'name' => 'Star Lender',
            'description' => 'Disburse 20 successful loans',
            'icon' => '⭐',
            'points' => 300
        ],
        'perfect_month' => [
            'name' => 'Perfect Month',
            'description' => 'No missed payments for 30 days',
            'icon' => '🌟',
            'points' => 175
        ],
        'milestone_10_loans' => [
            'name' => 'Milestone: 10 Loans',
            'description' => 'Complete 10 successful loans',
            'icon' => '🎯',
            'points' => 150
        ]
    ];

    private $levelThresholds = [
        1 => ['name' => 'Bronze', 'min_points' => 0, 'benefits' => ['Basic access']],
        2 => ['name' => 'Silver', 'min_points' => 500, 'benefits' => ['Reduced fees', 'Priority support']],
        3 => ['name' => 'Gold', 'min_points' => 1500, 'benefits' => ['Lower interest rates', 'Exclusive offers']],
        4 => ['name' => 'Platinum', 'min_points' => 3000, 'benefits' => ['Best rates', 'Dedicated support', 'Cashback rewards']],
        5 => ['name' => 'Diamond', 'min_points' => 6000, 'benefits' => ['VIP treatment', 'Special terms', 'Invitation-only events']]
    ];

    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        $this->userId = $userId;

        if ($userId) {
            $user = $this->db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
            $this->userRole = $user['role'] ?? 'borrower';
        }
    }

    /**
     * Award points to user for specific action
     */
    public function awardPoints($userId, $action, $metadata = []) {
        if (!isset($this->pointsConfig[$action])) {
            return false;
        }

        $points = $this->pointsConfig[$action];

        // Check for multipliers based on user level
        $multiplier = $this->getPointsMultiplier($userId);
        $finalPoints = round($points * $multiplier);

        // Record points transaction
        $this->db->execute("
            INSERT INTO user_points (
                user_id, action, points_earned, multiplier, final_points,
                metadata, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ", [
            $userId, $action, $points, $multiplier, $finalPoints,
            json_encode($metadata)
        ]);

        // Update user total points
        $this->updateUserTotalPoints($userId);

        // Check for new badges
        $this->checkAndAwardBadges($userId);

        // Check for level up
        $this->checkLevelUp($userId);

        return $finalPoints;
    }

    /**
     * Get user's current gamification profile
     */
    public function getUserGamificationProfile($userId) {
        $profile = $this->db->fetchOne("
            SELECT
                up.total_points,
                up.current_level,
                ul.level_name,
                ul.level_tier,
                up.streak_days,
                up.last_login_date,
                (SELECT COUNT(*) FROM user_badges ub WHERE ub.user_id = ?) as badges_earned,
                (SELECT COUNT(*) FROM achievements a WHERE a.user_id = ?) as achievements_unlocked
            FROM user_profiles up
            JOIN user_levels ul ON up.current_level = ul.level_id
            WHERE up.user_id = ?
        ", [$userId, $userId, $userId]);

        if (!$profile) {
            $this->initializeUserProfile($userId);
            return $this->getUserGamificationProfile($userId);
        }

        // Get recent activity
        $recentActivity = $this->db->fetchAll("
            SELECT action, points_earned, created_at
            FROM user_points
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ", [$userId]);

        // Get user badges
        $badges = $this->db->fetchAll("
            SELECT b.badge_code, b.name, b.description, b.icon, b.points,
                   ub.earned_at
            FROM user_badges ub
            JOIN badges b ON ub.badge_code = b.badge_code
            WHERE ub.user_id = ?
            ORDER BY ub.earned_at DESC
        ", [$userId]);

        // Get next level progress
        $nextLevel = $this->getNextLevel($profile['current_level']);
        $pointsToNext = $nextLevel ?
            max(0, $nextLevel['min_points'] - $profile['total_points']) : 0;

        return [
            'profile' => $profile,
            'recent_activity' => $recentActivity,
            'badges' => $badges,
            'next_level' => $nextLevel,
            'points_to_next_level' => $pointsToNext,
            'level_progress' => $this->calculateLevelProgress($profile['total_points'], $profile['current_level'])
        ];
    }

    /**
     * Get leaderboard for competitive ranking
     */
    public function getLeaderboard($type = 'points', $period = 'monthly', $limit = 50) {
        $dateFilter = $this->getDateFilter($period);

        $query = "
            SELECT
                u.id, u.name, u.profile_image,
                COALESCE(SUM(up.final_points), 0) as total_points,
                COUNT(DISTINCT ub.id) as badges_count
            FROM users u
            LEFT JOIN user_points up ON u.id = up.user_id
                AND up.created_at >= $dateFilter
            LEFT JOIN user_badges ub ON u.id = ub.user_id
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY total_points DESC, badges_count DESC
            LIMIT ?
        ";

        $results = $this->db->fetchAll($query, [$limit]);

        // Add ranking
        foreach ($results as $index => &$result) {
            $result['rank'] = $index + 1;
            $result['level'] = $this->getUserLevel($result['id']);
        }

        return $results;
    }

    /**
     * Process daily rewards and streaks
     */
    public function processDailyRewards() {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Get users who logged in today
        $activeUsers = $this->db->fetchAll("
            SELECT DISTINCT user_id FROM user_activity
            WHERE DATE(activity_date) = ? AND activity_type = 'login'
        ", [$today]);

        $rewardsProcessed = 0;

        foreach ($activeUsers as $user) {
            $userId = $user['user_id'];

            // Check if user logged in yesterday (streak calculation)
            $loggedYesterday = $this->db->fetchOne("
                SELECT COUNT(*) as count FROM user_activity
                WHERE user_id = ? AND DATE(activity_date) = ? AND activity_type = 'login'
            ", [$userId, $yesterday]);

            // Update streak
            if ($loggedYesterday['count'] > 0) {
                $this->db->execute("
                    UPDATE user_profiles
                    SET streak_days = streak_days + 1, last_login_date = NOW()
                    WHERE user_id = ?
                ", [$userId]);
            } else {
                $this->db->execute("
                    UPDATE user_profiles
                    SET streak_days = 1, last_login_date = NOW()
                    WHERE user_id = ?
                ", [$userId]);
            }

            // Award daily login points
            $this->awardPoints($userId, 'daily_login');

            // Check for streak bonuses
            $this->awardStreakBonuses($userId);

            $rewardsProcessed++;
        }

        return $rewardsProcessed;
    }

    /**
     * Create and manage challenges
     */
    public function createChallenge($challengeData) {
        $challengeId = $this->db->execute("
            INSERT INTO challenges (
                title, description, challenge_type, target_value,
                points_reward, badge_reward, start_date, end_date,
                eligibility_criteria, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ", [
            $challengeData['title'],
            $challengeData['description'],
            $challengeData['challenge_type'],
            $challengeData['target_value'],
            $challengeData['points_reward'],
            $challengeData['badge_reward'] ?? null,
            $challengeData['start_date'],
            $challengeData['end_date'],
            json_encode($challengeData['eligibility_criteria'] ?? [])
        ]);

        return $challengeId;
    }

    /**
     * Get active challenges for user
     */
    public function getUserChallenges($userId) {
        return $this->db->fetchAll("
            SELECT
                c.*,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM challenge_participants cp
                        WHERE cp.challenge_id = c.id AND cp.user_id = ?
                    ) THEN 'joined'
                    ELSE 'available'
                END as participation_status,
                (
                    SELECT progress FROM challenge_participants cp
                    WHERE cp.challenge_id = c.id AND cp.user_id = ?
                ) as user_progress
            FROM challenges c
            WHERE c.status = 'active'
                AND c.start_date <= NOW()
                AND c.end_date >= NOW()
            ORDER BY c.end_date ASC
        ", [$userId, $userId]);
    }

    /**
     * Join a challenge
     */
    public function joinChallenge($userId, $challengeId) {
        // Check if already joined
        $existing = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM challenge_participants
            WHERE user_id = ? AND challenge_id = ?
        ", [$userId, $challengeId]);

        if ($existing['count'] > 0) {
            return false; // Already joined
        }

        $this->db->execute("
            INSERT INTO challenge_participants (
                user_id, challenge_id, joined_at, progress
            ) VALUES (?, ?, NOW(), 0)
        ", [$userId, $challengeId]);

        return true;
    }

    /**
     * Process challenge progress
     */
    public function updateChallengeProgress($userId, $challengeType, $progressIncrement = 1) {
        $challenges = $this->db->fetchAll("
            SELECT cp.*, c.challenge_type, c.target_value, c.points_reward, c.badge_reward
            FROM challenge_participants cp
            JOIN challenges c ON cp.challenge_id = c.id
            WHERE cp.user_id = ?
                AND c.challenge_type = ?
                AND c.status = 'active'
                AND cp.completed = 0
        ", [$userId, $challengeType]);

        foreach ($challenges as $challenge) {
            $newProgress = min($challenge['progress'] + $progressIncrement, $challenge['target_value']);

            $this->db->execute("
                UPDATE challenge_participants
                SET progress = ?, updated_at = NOW()
                WHERE id = ?
            ", [$newProgress, $challenge['id']]);

            // Check if challenge completed
            if ($newProgress >= $challenge['target_value']) {
                $this->completeChallenge($userId, $challenge);
            }
        }
    }

    /**
     * Complete a challenge and award rewards
     */
    private function completeChallenge($userId, $challenge) {
        $this->db->execute("
            UPDATE challenge_participants
            SET completed = 1, completed_at = NOW()
            WHERE id = ?
        ", [$challenge['id']]);

        // Award points
        $this->awardPoints($userId, 'challenge_completed', [
            'challenge_id' => $challenge['challenge_id'],
            'challenge_title' => $challenge['title'],
            'points' => $challenge['points_reward']
        ]);

        // Award badge if specified
        if ($challenge['badge_reward']) {
            $this->awardBadge($userId, $challenge['badge_reward']);
        }

        // Send notification
        $this->sendChallengeCompletionNotification($userId, $challenge);
    }

    /**
     * Award badge to user
     */
    public function awardBadge($userId, $badgeCode) {
        // Check if user already has this badge
        $existing = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM user_badges
            WHERE user_id = ? AND badge_code = ?
        ", [$userId, $badgeCode]);

        if ($existing['count'] > 0) {
            return false; // Already has badge
        }

        $badgeConfig = $this->badgeConfig[$badgeCode] ?? null;
        if (!$badgeConfig) {
            return false;
        }

        $this->db->execute("
            INSERT INTO user_badges (user_id, badge_code, earned_at)
            VALUES (?, ?, NOW())
        ", [$userId, $badgeCode]);

        // Award badge points
        $this->awardPoints($userId, 'badge_earned', [
            'badge_code' => $badgeCode,
            'badge_name' => $badgeConfig['name'],
            'points' => $badgeConfig['points']
        ]);

        // Send notification
        $this->sendBadgeEarnedNotification($userId, $badgeConfig);

        return true;
    }

    /**
     * Check and award badges based on user achievements
     */
    private function checkAndAwardBadges($userId) {
        $stats = $this->getUserStats($userId);

        // First loan badge
        if ($stats['total_loans'] >= 1) {
            $this->awardBadge($userId, 'first_loan');
        }

        // Timely payer badge
        if ($stats['on_time_payments'] >= 5) {
            $this->awardBadge($userId, 'timely_payer');
        }

        // Early bird badge
        if ($stats['early_payments'] >= 3) {
            $this->awardBadge($userId, 'early_bird');
        }

        // Credit champion badge
        if ($stats['credit_score'] >= 750) {
            $this->awardBadge($userId, 'credit_champion');
        }

        // Referral master badge
        if ($stats['successful_referrals'] >= 5) {
            $this->awardBadge($userId, 'referral_master');
        }

        // Loyalty member badge
        if ($stats['days_active'] >= 180) {
            $this->awardBadge($userId, 'loyalty_member');
        }

        // Milestone badges
        if ($stats['completed_loans'] >= 10) {
            $this->awardBadge($userId, 'milestone_10_loans');
        }

        // Role-specific badges
        if ($this->userRole === 'supplier' && $stats['completed_deliveries'] >= 50) {
            $this->awardBadge($userId, 'super_supplier');
        }

        if ($this->userRole === 'lender' && $stats['disbursed_loans'] >= 20) {
            $this->awardBadge($userId, 'star_lender');
        }
    }

    /**
     * Check if user leveled up
     */
    private function checkLevelUp($userId) {
        $currentProfile = $this->db->fetchOne("
            SELECT total_points, current_level FROM user_profiles WHERE user_id = ?
        ", [$userId]);

        $newLevel = $this->calculateUserLevel($currentProfile['total_points']);

        if ($newLevel > $currentProfile['current_level']) {
            $this->db->execute("
                UPDATE user_profiles
                SET current_level = ?, level_updated_at = NOW()
                WHERE user_id = ?
            ", [$newLevel, $userId]);

            // Award level-up bonus points
            $bonusPoints = $newLevel * 50;
            $this->awardPoints($userId, 'level_up', [
                'new_level' => $newLevel,
                'bonus_points' => $bonusPoints
            ]);

            // Send level-up notification
            $this->sendLevelUpNotification($userId, $newLevel, $bonusPoints);
        }
    }

    /**
     * Calculate user's level based on points
     */
    private function calculateUserLevel($totalPoints) {
        foreach ($this->levelThresholds as $level => $config) {
            if ($totalPoints >= $config['min_points']) {
                $currentLevel = $level;
            }
        }
        return $currentLevel ?? 1;
    }

    /**
     * Get user statistics for badge checking (public method for API)
     */
    public function getUserStats($userId) {
        $user = $this->db->fetchOne("SELECT role, created_at FROM users WHERE id = ?", [$userId]);

        $stats = [
            'total_loans' => 0,
            'completed_loans' => 0,
            'on_time_payments' => 0,
            'early_payments' => 0,
            'credit_score' => 0,
            'successful_referrals' => 0,
            'days_active' => 0,
            'completed_deliveries' => 0,
            'disbursed_loans' => 0
        ];

        if ($user['role'] === 'borrower') {
            $loanStats = $this->db->fetchOne("
                SELECT
                    COUNT(*) as total_loans,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_loans,
                    COALESCE(MAX(credit_score), 0) as credit_score,
                    COUNT(CASE
                        WHEN rs.status = 'paid' AND rs.due_date >= rs.paid_date
                        THEN 1 END) as on_time_payments,
                    COUNT(CASE
                        WHEN rs.status = 'paid' AND rs.paid_date < rs.due_date
                        THEN 1 END) as early_payments
                FROM loans l
                LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
                WHERE l.borrower_id = ?
            ", [$userId]);

            $referralStats = $this->db->fetchOne("
                SELECT COUNT(*) as successful_referrals
                FROM user_referrals ur
                JOIN users u ON ur.referral_id = u.id
                WHERE ur.referrer_id = ? AND u.status = 'active'
            ", [$userId]);

            $stats = array_merge($stats, $loanStats, $referralStats);
        }

        // Calculate days active
        if ($user['created_at']) {
            $stats['days_active'] = (new DateTime($user['created_at']))->diff(new DateTime())->days;
        }

        return $stats;
    }

    /**
     * Award streak bonuses
     */
    private function awardStreakBonuses($userId) {
        $profile = $this->db->fetchOne("
            SELECT streak_days FROM user_profiles WHERE user_id = ?
        ", [$userId]);

        $streakDays = $profile['streak_days'];

        // Weekly streak bonus
        if ($streakDays % 7 === 0) {
            $this->awardPoints($userId, 'weekly_streak', [
                'streak_days' => $streakDays
            ]);
        }

        // Monthly streak bonus
        if ($streakDays % 30 === 0) {
            $this->awardPoints($userId, 'monthly_streak', [
                'streak_days' => $streakDays
            ]);
        }
    }

    /**
     * Get points multiplier based on user level
     */
    private function getPointsMultiplier($userId) {
        $level = $this->getUserLevel($userId);

        $multipliers = [
            1 => 1.0,    // Bronze
            2 => 1.2,    // Silver
            3 => 1.5,    // Gold
            4 => 1.8,    // Platinum
            5 => 2.0     // Diamond
        ];

        return $multipliers[$level] ?? 1.0;
    }

    /**
     * Update user total points
     */
    private function updateUserTotalPoints($userId) {
        $total = $this->db->fetchOne("
            SELECT COALESCE(SUM(final_points), 0) as total
            FROM user_points
            WHERE user_id = ?
        ", [$userId]);

        $this->db->execute("
            INSERT INTO user_profiles (user_id, total_points, current_level, created_at, updated_at)
            VALUES (?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            total_points = VALUES(total_points), updated_at = NOW()
        ", [$userId, $total['total']]);
    }

    /**
     * Initialize user profile for gamification
     */
    private function initializeUserProfile($userId) {
        $this->db->execute("
            INSERT INTO user_profiles (
                user_id, total_points, current_level, streak_days,
                created_at, updated_at
            ) VALUES (?, 0, 1, 0, NOW(), NOW())
        ", [$userId]);
    }

    /**
     * Get user's current level
     */
    private function getUserLevel($userId) {
        $profile = $this->db->fetchOne("
            SELECT ul.level_name, ul.level_tier, up.total_points
            FROM user_profiles up
            JOIN user_levels ul ON up.current_level = ul.level_id
            WHERE up.user_id = ?
        ", [$userId]);

        return $profile ?? ['level_name' => 'Bronze', 'level_tier' => 1, 'total_points' => 0];
    }

    /**
     * Get next level information
     */
    private function getNextLevel($currentLevel) {
        $nextLevelId = $currentLevel + 1;
        return $this->levelThresholds[$nextLevelId] ?? null;
    }

    /**
     * Calculate level progress percentage
     */
    private function calculateLevelProgress($totalPoints, $currentLevel) {
        $currentLevelConfig = $this->levelThresholds[$currentLevel];
        $nextLevelConfig = $this->getNextLevel($currentLevel);

        if (!$nextLevelConfig) {
            return 100; // Max level
        }

        $levelMin = $currentLevelConfig['min_points'];
        $levelMax = $nextLevelConfig['min_points'];
        $pointsInLevel = $totalPoints - $levelMin;
        $levelRange = $levelMax - $levelMin;

        return min(100, max(0, ($pointsInLevel / $levelRange) * 100));
    }

    /**
     * Get date filter for queries
     */
    private function getDateFilter($period) {
        switch ($period) {
            case 'daily':
                return "DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'weekly':
                return "DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'monthly':
                return "DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'yearly':
                return "DATE_SUB(NOW(), INTERVAL 365 DAY)";
            default:
                return "DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }

    /**
     * Send badge earned notification
     */
    private function sendBadgeEarnedNotification($userId, $badgeConfig) {
        $user = $this->db->fetchOne("SELECT name, email, phone FROM users WHERE id = ?", [$userId]);

        $message = "Congratulations {$user['name']}! 🎉\n\n";
        $message .= "You've earned the '{$badgeConfig['name']}' badge!\n";
        $message .= "{$badgeConfig['icon']} {$badgeConfig['description']}\n";
        $message .= "Points earned: {$badgeConfig['points']}\n\n";
        $message .= "Keep up the great work!";

        // Send notification via preferred channels
        $this->sendGamificationNotification($userId, 'Badge Earned!', $message);
    }

    /**
     * Send level up notification
     */
    private function sendLevelUpNotification($userId, $newLevel, $bonusPoints) {
        $user = $this->db->fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
        $levelConfig = $this->levelThresholds[$newLevel];

        $message = "Level Up! 🎊\n\n";
        $message .= "Congratulations {$user['name']}!\n";
        $message .= "You've reached {$levelConfig['name']} level!\n";
        $message .= "Bonus points awarded: {$bonusPoints}\n\n";
        $message .= "New benefits:\n";
        foreach ($levelConfig['benefits'] as $benefit) {
            $message .= "• {$benefit}\n";
        }

        $this->sendGamificationNotification($userId, 'Level Up!', $message);
    }

    /**
     * Send challenge completion notification
     */
    private function sendChallengeCompletionNotification($userId, $challenge) {
        $user = $this->db->fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);

        $message = "Challenge Completed! 🏆\n\n";
        $message .= "Great job {$user['name']}!\n";
        $message .= "You've completed: {$challenge['title']}\n";
        $message .= "Points earned: {$challenge['points_reward']}\n\n";
        $message .= "Ready for the next challenge?";

        $this->sendGamificationNotification($userId, 'Challenge Complete!', $message);
    }

    /**
     * Send gamification notifications via multiple channels
     */
    private function sendGamificationNotification($userId, $title, $message) {
        $user = $this->db->fetchOne("SELECT email, phone, notification_preferences FROM users WHERE id = ?", [$userId]);
        $preferences = json_decode($user['notification_preferences'] ?? '{}', true);

        // In-app notification
        $this->db->execute("
            INSERT INTO notifications (
                user_id, type, title, message, data, created_at
            ) VALUES (?, 'gamification', ?, ?, ?, NOW())
        ", [$userId, $title, $message, json_encode(['priority' => 'high'])]);

        // Email notification
        if ($preferences['email'] ?? true) {
            $this->sendEmailNotification($user['email'], $title, $message);
        }

        // SMS notification
        if ($preferences['sms'] ?? false) {
            $this->sendSMSNotification($user['phone'], $message);
        }

        // WhatsApp notification
        if ($preferences['whatsapp'] ?? true) {
            $this->sendWhatsAppNotification($user['phone'], $message);
        }
    }

    /**
     * Send email notification (placeholder)
     */
    private function sendEmailNotification($email, $title, $message) {
        // Integration with email service
        error_log("Email notification sent to {$email}: {$title}");
    }

    /**
     * Send SMS notification (placeholder)
     */
    private function sendSMSNotification($phone, $message) {
        // Integration with SMS service
        error_log("SMS notification sent to {$phone}: {$message}");
    }

    /**
     * Send WhatsApp notification (placeholder)
     */
    private function sendWhatsAppNotification($phone, $message) {
        // Integration with WhatsApp Business API
        error_log("WhatsApp notification sent to {$phone}: {$message}");
    }

    /**
     * Get gamification analytics for admin
     */
    public function getGamificationAnalytics($dateRange = 30) {
        return [
            'overview' => $this->getGamificationOverview($dateRange),
            'engagement_metrics' => $this->getEngagementMetrics($dateRange),
            'popular_badges' => $this->getPopularBadges($dateRange),
            'level_distribution' => $this->getLevelDistribution(),
            'challenge_performance' => $this->getChallengePerformance($dateRange)
        ];
    }

    /**
     * Get gamification overview statistics
     */
    private function getGamificationOverview($dateRange) {
        return $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT up.user_id) as active_users,
                COUNT(*) as total_points_awarded,
                COUNT(DISTINCT ub.user_id) as badge_earners,
                COUNT(*) as total_badges_earned,
                COUNT(DISTINCT cp.user_id) as challenge_participants
            FROM user_points up
            LEFT JOIN user_badges ub ON ub.earned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            LEFT JOIN challenge_participants cp ON cp.joined_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE up.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$dateRange, $dateRange, $dateRange]);
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics($dateRange) {
        return $this->db->fetchOne("
            SELECT
                AVG(CASE WHEN action = 'daily_login' THEN 1 ELSE 0 END) * 100 as daily_login_rate,
                AVG(up.streak_days) as avg_streak_days,
                COUNT(DISTINCT CASE WHEN up.total_points > 1000 THEN up.user_id END) as power_users,
                COUNT(DISTINCT CASE WHEN up.current_level >= 3 THEN up.user_id END) as advanced_users
            FROM user_profiles up
            WHERE up.updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$dateRange]);
    }

    /**
     * Get most popular badges
     */
    private function getPopularBadges($dateRange) {
        return $this->db->fetchAll("
            SELECT
                b.badge_code, b.name, b.icon,
                COUNT(ub.user_id) as times_earned
            FROM badges b
            JOIN user_badges ub ON b.badge_code = ub.badge_code
            WHERE ub.earned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY b.badge_code, b.name, b.icon
            ORDER BY times_earned DESC
            LIMIT 10
        ", [$dateRange]);
    }

    /**
     * Get level distribution
     */
    private function getLevelDistribution() {
        return $this->db->fetchAll("
            SELECT
                ul.level_name, ul.level_tier,
                COUNT(up.user_id) as user_count,
                ROUND(COUNT(up.user_id) * 100.0 / (SELECT COUNT(*) FROM users WHERE status = 'active'), 2) as percentage
            FROM user_levels ul
            LEFT JOIN user_profiles up ON ul.level_id = up.current_level
            LEFT JOIN users u ON up.user_id = u.id
            WHERE u.status = 'active' OR u.id IS NULL
            GROUP BY ul.level_id, ul.level_name, ul.level_tier
            ORDER BY ul.level_tier
        ");
    }

    /**
     * Get challenge performance metrics
     */
    private function getChallengePerformance($dateRange) {
        return $this->db->fetchAll("
            SELECT
                c.title,
                COUNT(cp.user_id) as participants,
                COUNT(CASE WHEN cp.completed = 1 THEN 1 END) as completions,
                ROUND(COUNT(CASE WHEN cp.completed = 1 THEN 1 END) * 100.0 / COUNT(cp.user_id), 2) as completion_rate,
                AVG(cp.progress) as avg_progress
            FROM challenges c
            LEFT JOIN challenge_participants cp ON c.id = cp.challenge_id
            WHERE c.start_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND c.status = 'active'
            GROUP BY c.id, c.title
            ORDER BY completion_rate DESC
        ", [$dateRange]);
    }

    /**
     * Create monthly challenges automatically
     */
    public function createMonthlyChallenges() {
        $currentMonth = date('Y-m-01');
        $nextMonth = date('Y-m-01', strtotime('+1 month'));

        $challenges = [
            [
                'title' => 'Monthly Repayment Champion',
                'description' => 'Make all your loan payments on time this month',
                'challenge_type' => 'timely_repayments',
                'target_value' => 4,
                'points_reward' => 200,
                'badge_reward' => 'perfect_month'
            ],
            [
                'title' => 'Network Builder',
                'description' => 'Refer 3 new active users this month',
                'challenge_type' => 'referrals',
                'target_value' => 3,
                'points_reward' => 300,
                'badge_reward' => null
            ],
            [
                'title' => 'Credit Improver',
                'description' => 'Increase your credit score by 50 points',
                'challenge_type' => 'credit_improvement',
                'target_value' => 50,
                'points_reward' => 150,
                'badge_reward' => null
            ]
        ];

        foreach ($challenges as $challenge) {
            $this->createChallenge([
                'title' => $challenge['title'],
                'description' => $challenge['description'],
                'challenge_type' => $challenge['challenge_type'],
                'target_value' => $challenge['target_value'],
                'points_reward' => $challenge['points_reward'],
                'badge_reward' => $challenge['badge_reward'],
                'start_date' => $currentMonth,
                'end_date' => $nextMonth,
                'eligibility_criteria' => ['min_level' => 1]
            ]);
        }

        return count($challenges);
    }

    /**
     * Process expired challenges and cleanup
     */
    public function processExpiredChallenges() {
        $expiredChallenges = $this->db->fetchAll("
            SELECT * FROM challenges
            WHERE end_date < NOW() AND status = 'active'
        ");

        foreach ($expiredChallenges as $challenge) {
            // Award completion bonuses to top performers
            $topPerformers = $this->db->fetchAll("
                SELECT cp.user_id, cp.progress
                FROM challenge_participants cp
                WHERE cp.challenge_id = ? AND cp.completed = 0
                ORDER BY cp.progress DESC
                LIMIT 3
            ", [$challenge['id']]);

            foreach ($topPerformers as $index => $performer) {
                $bonusMultiplier = 3 - $index; // 3x, 2x, 1x
                $bonusPoints = $challenge['points_reward'] * $bonusMultiplier * 0.5;

                $this->awardPoints($performer['user_id'], 'challenge_top_performer', [
                    'challenge_id' => $challenge['id'],
                    'rank' => $index + 1,
                    'bonus_points' => $bonusPoints
                ]);
            }

            // Mark challenge as expired
            $this->db->execute("
                UPDATE challenges SET status = 'expired', updated_at = NOW()
                WHERE id = ?
            ", [$challenge['id']]);
        }

        return count($expiredChallenges);
    }

    /**
     * Get user's rewards history
     */
    public function getUserRewardsHistory($userId, $limit = 50) {
        return $this->db->fetchAll("
            (SELECT
                'points' as type,
                action as description,
                final_points as value,
                created_at,
                metadata
            FROM user_points
            WHERE user_id = ?
            )
            UNION ALL
            (SELECT
                'badge' as type,
                CONCAT('Earned: ', b.name) as description,
                b.points as value,
                ub.earned_at as created_at,
                JSON_OBJECT('badge_code', b.badge_code, 'icon', b.icon) as metadata
            FROM user_badges ub
            JOIN badges b ON ub.badge_code = b.badge_code
            WHERE ub.user_id = ?
            )
            UNION ALL
            (SELECT
                'challenge' as type,
                CONCAT('Completed: ', c.title) as description,
                c.points_reward as value,
                cp.completed_at as created_at,
                JSON_OBJECT('challenge_id', c.id, 'progress', cp.progress) as metadata
            FROM challenge_participants cp
            JOIN challenges c ON cp.challenge_id = c.id
            WHERE cp.user_id = ? AND cp.completed = 1
            )
            ORDER BY created_at DESC
            LIMIT ?
        ", [$userId, $userId, $userId, $limit]);
    }

    /**
     * Generate gamification report for admin
     */
    public function generateGamificationReport($format = 'json') {
        $data = [
            'generated_at' => date('Y-m-d H:i:s'),
            'analytics' => $this->getGamificationAnalytics(30),
            'top_performers' => $this->getLeaderboard('points', 'monthly', 10),
            'active_challenges' => $this->db->fetchAll("
                SELECT * FROM challenges WHERE status = 'active'
            "),
            'system_health' => [
                'total_users' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
                'daily_active_users' => $this->db->fetchOne("
                    SELECT COUNT(DISTINCT user_id) as count FROM user_activity
                    WHERE DATE(activity_date) = CURDATE()
                ")['count'],
                'total_points_in_system' => $this->db->fetchOne("
                    SELECT COALESCE(SUM(total_points), 0) as total FROM user_profiles
                ")['total']
            ]
        ];

        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
            // Generate CSV format
            return $this->generateCSVReport($data);
        }

        return $data;
    }

    /**
     * Generate CSV report
     */
    private function generateCSVReport($data) {
        $csv = "Gamification System Report\n";
        $csv .= "Generated: {$data['generated_at']}\n\n";

        // Overview section
        $overview = $data['analytics']['overview'];
        $csv .= "Overview Metrics\n";
        $csv .= "Active Users,{$overview['active_users']}\n";
        $csv .= "Total Points Awarded,{$overview['total_points_awarded']}\n";
        $csv .= "Badge Earners,{$overview['badge_earners']}\n";
        $csv .= "Total Badges Earned,{$overview['total_badges_earned']}\n";
        $csv .= "Challenge Participants,{$overview['challenge_participants']}\n\n";

        return $csv;
    }
}

// Usage examples and integration points

// Initialize gamification for a user
if (isset($_SESSION['user_id'])) {
    $gamification = new GamificationSystem($_SESSION['user_id']);

    // Example: Award points for login
    if (!isset($_SESSION['daily_login_processed'])) {
        $gamification->awardPoints($_SESSION['user_id'], 'daily_login');
        $_SESSION['daily_login_processed'] = true;
    }
}

// API endpoints for gamification features
function handleGamificationAPI($action, $data = []) {
    $gamification = new GamificationSystem($_SESSION['user_id'] ?? null);

    switch ($action) {
        case 'get_profile':
            echo json_encode($gamification->getUserGamificationProfile($_SESSION['user_id']));
            break;

        case 'get_leaderboard':
            $period = $data['period'] ?? 'monthly';
            $limit = $data['limit'] ?? 50;
            echo json_encode($gamification->getLeaderboard('points', $period, $limit));
            break;

        case 'join_challenge':
            $challengeId = $data['challenge_id'] ?? 0;
            $result = $gamification->joinChallenge($_SESSION['user_id'], $challengeId);
            echo json_encode(['success' => $result]);
            break;

        case 'get_challenges':
            echo json_encode($gamification->getUserChallenges($_SESSION['user_id']));
            break;

        case 'get_rewards_history':
            $limit = $data['limit'] ?? 50;
            echo json_encode($gamification->getUserRewardsHistory($_SESSION['user_id'], $limit));
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

// Process gamification on user actions
function processGamificationTriggers($userId, $action, $metadata = []) {
    $gamification = new GamificationSystem();

    // Award points for the action
    $pointsAwarded = $gamification->awardPoints($userId, $action, $metadata);

    // Update challenge progress if applicable
    if (in_array($action, ['loan_repayment_on_time', 'loan_application', 'referral_success'])) {
        $challengeType = str_replace('_', '', $action);
        $gamification->updateChallengeProgress($userId, $challengeType);
    }

    return $pointsAwarded;
}

// Cron job for daily gamification processing
function runDailyGamificationCron() {
    $gamification = new GamificationSystem();

    // Process daily rewards and streaks
    $dailyRewardsProcessed = $gamification->processDailyRewards();

    // Create new monthly challenges if needed
    if (date('d') === '01') {
        $monthlyChallengesCreated = $gamification->createMonthlyChallenges();
    }

    // Process expired challenges
    $expiredChallengesProcessed = $gamification->processExpiredChallenges();

    error_log("Daily gamification cron completed. Rewards: {$dailyRewardsProcessed}, Expired challenges: {$expiredChallengesProcessed}");

    return [
        'daily_rewards_processed' => $dailyRewardsProcessed,
        'expired_challenges_processed' => $expiredChallengesProcessed
    ];
}

?>