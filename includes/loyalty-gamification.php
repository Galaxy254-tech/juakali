<?php
/**
 * Loyalty Rewards and Gamification System
 * Encourages user engagement and positive financial behavior through points, badges, achievements
 */

class LoyaltyGamificationSystem {
    private $db;
    private $config;
    private $notifications;

    public function __construct($db, $config = []) {
        $this->db = $db;
        $this->config = array_merge([
            'points_per_ontime_payment' => 50,
            'points_per_early_payment' => 25,
            'points_per_referral' => 100,
            'points_per_complete_profile' => 30,
            'points_per_kyc_completion' => 50,
            'streak_bonus_multiplier' => 1.5,
            'level_up_bonus' => 100,
            'max_daily_earnings' => 500
        ], $config);

        $this->notifications = new NotificationSystem($db);
    }

    /**
     * Initialize gamification system for new user
     */
    public function initializeUserGamification($userId) {
        // Create user gamification profile
        $this->db->insert('user_gamification', [
            'user_id' => $userId,
            'total_points' => 0,
            'current_level' => 1,
            'experience_points' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'badges_earned' => json_encode([]),
            'achievements_unlocked' => json_encode([]),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Award welcome bonus
        $this->awardPoints($userId, 25, 'welcome_bonus', 'Welcome to JuaKali Lend!');

        return $this->getUserGamificationProfile($userId);
    }

    /**
     * Award points to user
     */
    public function awardPoints($userId, $points, $reason, $description = '') {
        $profile = $this->getUserGamificationProfile($userId);
        if (!$profile) {
            $this->initializeUserGamification($userId);
            $profile = $this->getUserGamificationProfile($userId);
        }

        // Check daily earning limit
        $todayPoints = $this->getTodayPointsEarned($userId);
        if ($todayPoints + $points > $this->config['max_daily_earnings']) {
            $points = $this->config['max_daily_earnings'] - $todayPoints;
            if ($points <= 0) {
                return ['success' => false, 'message' => 'Daily points limit reached'];
            }
        }

        // Apply streak bonus if applicable
        $finalPoints = $this->applyStreakBonus($userId, $points, $reason);

        // Update user points
        $newTotalPoints = $profile['total_points'] + $finalPoints;
        $newExperience = $profile['experience_points'] + $finalPoints;

        // Check for level up
        $newLevel = $this->calculateLevel($newExperience);
        $levelUpBonus = 0;
        if ($newLevel > $profile['current_level']) {
            $levelUpBonus = $this->config['level_up_bonus'] * ($newLevel - $profile['current_level']);
            $newTotalPoints += $levelUpBonus;
            $newExperience += $levelUpBonus;
        }

        $this->db->update('user_gamification', [
            'total_points' => $newTotalPoints,
            'current_level' => $newLevel,
            'experience_points' => $newExperience,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = ?', [$userId]);

        // Record points transaction
        $this->db->insert('points_transactions', [
            'user_id' => $userId,
            'points' => $finalPoints,
            'reason' => $reason,
            'description' => $description,
            'transaction_date' => date('Y-m-d H:i:s')
        ]);

        // Check for new achievements
        $this->checkAchievements($userId);

        // Check for new badges
        $this->checkBadges($userId);

        // Send notification
        $message = "You earned {$finalPoints} points! {$description}";
        if ($levelUpBonus > 0) {
            $message .= " Level up bonus: {$levelUpBonus} points!";
        }
        $this->notifications->sendNotification($userId, 'points_earned', $message);

        return [
            'success' => true,
            'points_awarded' => $finalPoints,
            'total_points' => $newTotalPoints,
            'level' => $newLevel,
            'level_up_bonus' => $levelUpBonus
        ];
    }

    /**
     * Award points for on-time loan payment
     */
    public function awardOnTimePaymentPoints($userId, $loanId, $paymentAmount) {
        $basePoints = $this->config['points_per_ontime_payment'];

        // Bonus points for larger payments
        $amountBonus = min(floor($paymentAmount / 1000) * 10, 50);
        $totalPoints = $basePoints + $amountBonus;

        return $this->awardPoints(
            $userId,
            $totalPoints,
            'ontime_payment',
            "On-time payment of KES " . number_format($paymentAmount)
        );
    }

    /**
     * Award points for early loan payment
     */
    public function awardEarlyPaymentPoints($userId, $loanId, $paymentAmount, $daysEarly) {
        $basePoints = $this->config['points_per_early_payment'];

        // Bonus points for early payment
        $earlyBonus = min($daysEarly * 5, 30);
        $amountBonus = min(floor($paymentAmount / 1000) * 8, 40);
        $totalPoints = $basePoints + $earlyBonus + $amountBonus;

        return $this->awardPoints(
            $userId,
            $totalPoints,
            'early_payment',
            "Early payment by {$daysEarly} days - KES " . number_format($paymentAmount)
        );
    }

    /**
     * Award points for referrals
     */
    public function awardReferralPoints($referrerId, $referredUserId) {
        return $this->awardPoints(
            $referrerId,
            $this->config['points_per_referral'],
            'successful_referral',
            "Successfully referred a new user"
        );
    }

    /**
     * Award points for completing profile
     */
    public function awardProfileCompletionPoints($userId) {
        return $this->awardPoints(
            $userId,
            $this->config['points_per_complete_profile'],
            'profile_completion',
            "Completed user profile"
        );
    }

    /**
     * Award points for KYC completion
     */
    public function awardKYCCompletionPoints($userId) {
        return $this->awardPoints(
            $userId,
            $this->config['points_per_kyc_completion'],
            'kyc_completion',
            "Completed KYC verification"
        );
    }

    /**
     * Update user streak
     */
    public function updateUserStreak($userId, $activityType) {
        $profile = $this->getUserGamificationProfile($userId);
        if (!$profile) return false;

        $lastActivity = $this->getLastUserActivity($userId, $activityType);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $currentStreak = $profile['current_streak'];
        $longestStreak = $profile['longest_streak'];

        if ($lastActivity && $lastActivity['activity_date'] === $yesterday) {
            // Continue streak
            $currentStreak++;
        } elseif ($lastActivity && $lastActivity['activity_date'] === $today) {
            // Already updated today
            return $currentStreak;
        } else {
            // Reset streak
            $currentStreak = 1;
        }

        if ($currentStreak > $longestStreak) {
            $longestStreak = $currentStreak;
        }

        $this->db->update('user_gamification', [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = ?', [$userId]);

        // Record streak activity
        $this->db->insert('user_streaks', [
            'user_id' => $userId,
            'activity_type' => $activityType,
            'streak_count' => $currentStreak,
            'activity_date' => $today
        ]);

        return $currentStreak;
    }

    /**
     * Apply streak bonus to points
     */
    private function applyStreakBonus($userId, $points, $reason) {
        if (in_array($reason, ['ontime_payment', 'early_payment'])) {
            $profile = $this->getUserGamificationProfile($userId);
            if ($profile['current_streak'] >= 3) {
                return floor($points * $this->config['streak_bonus_multiplier']);
            }
        }
        return $points;
    }

    /**
     * Calculate user level based on experience points
     */
    private function calculateLevel($experience) {
        // Level formula: level = floor(sqrt(experience / 100)) + 1
        return floor(sqrt($experience / 100)) + 1;
    }

    /**
     * Check and award achievements
     */
    private function checkAchievements($userId) {
        $profile = $this->getUserGamificationProfile($userId);
        $achievements = json_decode($profile['achievements_unlocked'] ?: '[]', true);

        // Define achievement criteria
        $achievementCriteria = [
            'first_loan' => ['type' => 'loan_count', 'value' => 1, 'points' => 50],
            'regular_borrower' => ['type' => 'loan_count', 'value' => 5, 'points' => 100],
            'frequent_borrower' => ['type' => 'loan_count', 'value' => 10, 'points' => 200],
            'loyal_customer' => ['type' => 'loan_count', 'value' => 25, 'points' => 500],

            'early_bird' => ['type' => 'early_payments', 'value' => 3, 'points' => 75],
            'punctual_payer' => ['type' => 'ontime_payments', 'value' => 10, 'points' => 150],
            'perfect_payment' => ['type' => 'perfect_payment_streak', 'value' => 5, 'points' => 200],

            'points_collector' => ['type' => 'total_points', 'value' => 500, 'points' => 100],
            'points_master' => ['type' => 'total_points', 'value' => 1000, 'points' => 200],
            'points_legend' => ['type' => 'total_points', 'value' => 5000, 'points' => 1000],

            'streak_starter' => ['type' => 'longest_streak', 'value' => 3, 'points' => 50],
            'streak_master' => ['type' => 'longest_streak', 'value' => 7, 'points' => 150],
            'streak_legend' => ['type' => 'longest_streak', 'value' => 30, 'points' => 500],

            'level_5' => ['type' => 'level', 'value' => 5, 'points' => 100],
            'level_10' => ['type' => 'level', 'value' => 10, 'points' => 250],
            'level_20' => ['type' => 'level', 'value' => 20, 'points' => 750],

            'kyc_verified' => ['type' => 'kyc_status', 'value' => 'verified', 'points' => 50],
            'profile_complete' => ['type' => 'profile_completion', 'value' => 100, 'points' => 30],

            'referral_champion' => ['type' => 'successful_referrals', 'value' => 5, 'points' => 200],
            'community_builder' => ['type' => 'successful_referrals', 'value' => 10, 'points' => 500]
        ];

        $userStats = $this->getUserGamificationStats($userId);
        $newAchievements = [];

        foreach ($achievementCriteria as $achievementId => $criteria) {
            if (!in_array($achievementId, $achievements)) {
                $userValue = $userStats[$criteria['type']] ?? 0;

                if ($userValue >= $criteria['value']) {
                    $achievements[] = $achievementId;
                    $newAchievements[] = [
                        'id' => $achievementId,
                        'name' => $this->getAchievementName($achievementId),
                        'description' => $this->getAchievementDescription($achievementId),
                        'points' => $criteria['points']
                    ];

                    // Award achievement bonus points
                    $this->awardPoints(
                        $userId,
                        $criteria['points'],
                        'achievement',
                        "Unlocked achievement: " . $this->getAchievementName($achievementId)
                    );
                }
            }
        }

        if (!empty($newAchievements)) {
            $this->db->update('user_gamification', [
                'achievements_unlocked' => json_encode($achievements),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [$userId]);

            // Send achievement notifications
            foreach ($newAchievements as $achievement) {
                $this->notifications->sendNotification(
                    $userId,
                    'achievement_unlocked',
                    "🏆 Achievement Unlocked: {$achievement['name']}! +{$achievement['points']} points"
                );
            }
        }

        return $newAchievements;
    }

    /**
     * Check and award badges
     */
    private function checkBadges($userId) {
        $profile = $this->getUserGamificationProfile($userId);
        $badges = json_decode($profile['badges_earned'] ?: '[]', true);

        // Define badge criteria
        $badgeCriteria = [
            'bronze_borrower' => ['type' => 'total_loans', 'value' => 3, 'tier' => 'bronze'],
            'silver_borrower' => ['type' => 'total_loans', 'value' => 10, 'tier' => 'silver'],
            'gold_borrower' => ['type' => 'total_loans', 'value' => 25, 'tier' => 'gold'],
            'platinum_borrower' => ['type' => 'total_loans', 'value' => 50, 'tier' => 'platinum'],
            'diamond_borrower' => ['type' => 'total_loans', 'value' => 100, 'tier' => 'diamond'],

            'early_payment_specialist' => ['type' => 'early_payment_rate', 'value' => 80, 'tier' => 'silver'],
            'payment_master' => ['type' => 'ontime_payment_rate', 'value' => 95, 'tier' => 'gold'],

            'trust_score_bronze' => ['type' => 'trust_score', 'value' => 70, 'tier' => 'bronze'],
            'trust_score_silver' => ['type' => 'trust_score', 'value' => 80, 'tier' => 'silver'],
            'trust_score_gold' => ['type' => 'trust_score', 'value' => 90, 'tier' => 'gold'],
            'trust_score_platinum' => ['type' => 'trust_score', 'value' => 95, 'tier' => 'platinum'],

            'community_hero' => ['type' => 'referral_count', 'value' => 10, 'tier' => 'gold'],
            'kyc_champion' => ['type' => 'kyc_verified', 'value' => 1, 'tier' => 'bronze']
        ];

        $userStats = $this->getUserGamificationStats($userId);
        $newBadges = [];

        foreach ($badgeCriteria as $badgeId => $criteria) {
            if (!in_array($badgeId, $badges)) {
                $userValue = $userStats[$criteria['type']] ?? 0;

                if ($userValue >= $criteria['value']) {
                    $badges[] = $badgeId;
                    $newBadges[] = [
                        'id' => $badgeId,
                        'name' => $this->getBadgeName($badgeId),
                        'description' => $this->getBadgeDescription($badgeId),
                        'tier' => $criteria['tier'],
                        'icon' => $this->getBadgeIcon($badgeId, $criteria['tier'])
                    ];
                }
            }
        }

        if (!empty($newBadges)) {
            $this->db->update('user_gamification', [
                'badges_earned' => json_encode($badges),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'user_id = ?', [$userId]);

            // Send badge notifications
            foreach ($newBadges as $badge) {
                $this->notifications->sendNotification(
                    $userId,
                    'badge_earned',
                    "🎖️ New Badge: {$badge['name']} ({$badge['tier']})"
                );
            }
        }

        return $newBadges;
    }

    /**
     * Redeem points for rewards
     */
    public function redeemPoints($userId, $rewardId) {
        $profile = $this->getUserGamificationProfile($userId);
        if (!$profile) {
            return ['success' => false, 'message' => 'Gamification profile not found'];
        }

        $reward = $this->getAvailableReward($rewardId);
        if (!$reward) {
            return ['success' => false, 'message' => 'Reward not available'];
        }

        if ($profile['total_points'] < $reward['points_required']) {
            return ['success' => false, 'message' => 'Insufficient points'];
        }

        // Check if user has already redeemed this reward
        if ($this->hasRedeemedReward($userId, $rewardId)) {
            return ['success' => false, 'message' => 'Reward already redeemed'];
        }

        // Deduct points
        $newTotalPoints = $profile['total_points'] - $reward['points_required'];
        $this->db->update('user_gamification', [
            'total_points' => $newTotalPoints,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'user_id = ?', [$userId]);

        // Record redemption
        $this->db->insert('points_redemptions', [
            'user_id' => $userId,
            'reward_id' => $rewardId,
            'points_used' => $reward['points_required'],
            'reward_data' => json_encode($reward),
            'redemption_date' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ]);

        // Process reward based on type
        $this->processRewardRedemption($userId, $reward);

        // Send notification
        $this->notifications->sendNotification(
            $userId,
            'reward_redeemed',
            "🎁 Reward redeemed: {$reward['name']} (-{$reward['points_required']} points)"
        );

        return [
            'success' => true,
            'reward' => $reward,
            'points_deducted' => $reward['points_required'],
            'remaining_points' => $newTotalPoints
        ];
    }

    /**
     * Process reward redemption
     */
    private function processRewardRedemption($userId, $reward) {
        switch ($reward['type']) {
            case 'discount':
                // Apply discount to next loan
                $this->applyLoanDiscount($userId, $reward['value']);
                break;

            case 'cashback':
                // Add cashback to user wallet
                $this->addCashbackToWallet($userId, $reward['value']);
                break;

            case 'fee_waiver':
                // Waive processing fees
                $this->waiveProcessingFees($userId, $reward['value']);
                break;

            case 'priority_support':
                // Grant priority support access
                $this->grantPrioritySupport($userId, $reward['duration_days']);
                break;

            case 'exclusive_access':
                // Grant access to exclusive features
                $this->grantExclusiveAccess($userId, $reward['feature']);
                break;
        }
    }

    /**
     * Get user gamification profile
     */
    public function getUserGamificationProfile($userId) {
        return $this->db->fetchOne("
            SELECT * FROM user_gamification
            WHERE user_id = ?
        ", [$userId]);
    }

    /**
     * Get user gamification statistics
     */
    public function getUserGamificationStats($userId) {
        $stats = [];

        // Loan statistics
        $loanStats = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_loans,
                COUNT(CASE WHEN payment_date < due_date THEN 1 END) as early_payments,
                COUNT(CASE WHEN payment_date <= due_date THEN 1 END) as ontime_payments,
                COUNT(CASE WHEN payment_date > due_date THEN 1 END) as late_payments,
                SUM(CASE WHEN payment_date <= due_date THEN 1 ELSE 0 END) / COUNT(*) * 100 as ontime_payment_rate,
                SUM(CASE WHEN payment_date < due_date THEN 1 ELSE 0 END) / COUNT(*) * 100 as early_payment_rate
            FROM loans
            WHERE user_id = ? AND status = 'completed'
        ", [$userId]);

        $stats = array_merge($stats, $loanStats ?: []);

        // Referral statistics
        $referralStats = $this->db->fetchOne("
            SELECT COUNT(*) as successful_referrals
            FROM user_referrals
            WHERE referrer_id = ? AND status = 'completed'
        ", [$userId]);

        $stats = array_merge($stats, $referralStats ?: []);

        // Profile completion
        $profile = $this->db->fetchOne("
            SELECT
                CASE
                    WHEN phone IS NOT NULL AND email IS NOT NULL AND
                         id_number IS NOT NULL AND address IS NOT NULL
                    THEN 100 ELSE 50 END as profile_completion
            FROM users
            WHERE id = ?
        ", [$userId]);

        $stats = array_merge($stats, $profile ?: []);

        // KYC status
        $kycStatus = $this->db->fetchOne("
            SELECT status as kyc_status
            FROM kyc_verifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ", [$userId]);

        $stats = array_merge($stats, $kycStatus ?: []);

        // Trust score (calculated based on various factors)
        $stats['trust_score'] = $this->calculateTrustScore($stats);

        return $stats;
    }

    /**
     * Calculate user trust score
     */
    private function calculateTrustScore($stats) {
        $score = 50; // Base score

        // Payment history (40% weight)
        if (isset($stats['ontime_payment_rate'])) {
            $score += ($stats['ontime_payment_rate'] - 50) * 0.4;
        }

        // Loan count (20% weight)
        if (isset($stats['total_loans'])) {
            $score += min($stats['total_loans'] * 2, 40) * 0.2;
        }

        // Profile completion (15% weight)
        if (isset($stats['profile_completion'])) {
            $score += ($stats['profile_completion'] - 50) * 0.3;
        }

        // KYC verification (15% weight)
        if (isset($stats['kyc_status']) && $stats['kyc_status'] === 'verified') {
            $score += 15;
        }

        // Referrals (10% weight)
        if (isset($stats['successful_referrals'])) {
            $score += min($stats['successful_referrals'] * 5, 50) * 0.2;
        }

        return min(max($score, 0), 100);
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard($type = 'points', $limit = 50, $period = 'all_time') {
        $whereClause = '';
        $periodClause = '';

        switch ($period) {
            case 'monthly':
                $periodClause = "WHERE g.updated_at >= DATE_FORMAT(NOW(), '%Y-%m-01')";
                break;
            case 'weekly':
                $periodClause = "WHERE g.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
        }

        $orderBy = '';
        switch ($type) {
            case 'points':
                $orderBy = 'ORDER BY g.total_points DESC';
                break;
            case 'level':
                $orderBy = 'ORDER BY g.current_level DESC, g.experience_points DESC';
                break;
            case 'streak':
                $orderBy = 'ORDER BY g.longest_streak DESC, g.current_streak DESC';
                break;
            case 'trust_score':
                // Would need to join with calculated trust scores
                $orderBy = 'ORDER BY g.total_points DESC'; // Fallback
                break;
        }

        return $this->db->fetchAll("
            SELECT
                g.*,
                u.name,
                u.phone,
                u.profile_image,
                @row_num := @row_num + 1 as rank
            FROM user_gamification g
            JOIN users u ON g.user_id = u.id
            CROSS JOIN (SELECT @row_num := 0) r
            $periodClause
            $orderBy
            LIMIT ?
        ", [$limit]);
    }

    /**
     * Get user's rank on leaderboard
     */
    public function getUserRank($userId, $type = 'points') {
        $orderBy = '';
        switch ($type) {
            case 'points':
                $orderBy = 'total_points DESC';
                break;
            case 'level':
                $orderBy = 'current_level DESC, experience_points DESC';
                break;
            case 'streak':
                $orderBy = 'longest_streak DESC, current_streak DESC';
                break;
        }

        $result = $this->db->fetchOne("
            SELECT COUNT(*) + 1 as rank
            FROM user_gamification g
            WHERE (
                SELECT COUNT(*)
                FROM user_gamification g2
                WHERE (g2.current_level, g2.experience_points) > (g.current_level, g.experience_points)
            ) < (
                SELECT COUNT(*)
                FROM user_gamification g3
                WHERE (g3.current_level, g3.experience_points) > (SELECT current_level, experience_points FROM user_gamification WHERE user_id = ?)
            )
        ", [$userId]);

        return $result['rank'] ?? 1;
    }

    /**
     * Get available rewards
     */
    public function getAvailableRewards() {
        return [
            [
                'id' => 'discount_5_percent',
                'name' => '5% Discount on Next Loan',
                'description' => 'Get 5% off your next loan interest',
                'type' => 'discount',
                'value' => 5,
                'points_required' => 200,
                'category' => 'loan_discounts',
                'expiry_days' => 30
            ],
            [
                'id' => 'discount_10_percent',
                'name' => '10% Discount on Next Loan',
                'description' => 'Get 10% off your next loan interest',
                'type' => 'discount',
                'value' => 10,
                'points_required' => 350,
                'category' => 'loan_discounts',
                'expiry_days' => 30
            ],
            [
                'id' => 'fee_waiver',
                'name' => 'Processing Fee Waiver',
                'description' => 'Waive processing fees on your next loan',
                'type' => 'fee_waiver',
                'value' => 100,
                'points_required' => 150,
                'category' => 'fee_waivers',
                'expiry_days' => 45
            ],
            [
                'id' => 'cashback_100',
                'name' => 'KES 100 Cashback',
                'description' => 'Get KES 100 cashback to your wallet',
                'type' => 'cashback',
                'value' => 100,
                'points_required' => 250,
                'category' => 'cashback',
                'expiry_days' => 60
            ],
            [
                'id' => 'priority_support_7_days',
                'name' => 'Priority Support (7 Days)',
                'description' => 'Get priority customer support for 7 days',
                'type' => 'priority_support',
                'value' => 7,
                'points_required' => 180,
                'category' => 'premium_features',
                'expiry_days' => 14
            ],
            [
                'id' => 'exclusive_access_analytics',
                'name' => 'Advanced Analytics Access',
                'description' => 'Access advanced financial analytics for 30 days',
                'type' => 'exclusive_access',
                'value' => 'advanced_analytics',
                'points_required' => 400,
                'category' => 'premium_features',
                'expiry_days' => 30
            ]
        ];
    }

    /**
     * Get specific reward details
     */
    private function getAvailableReward($rewardId) {
        $rewards = $this->getAvailableRewards();
        foreach ($rewards as $reward) {
            if ($reward['id'] === $rewardId) {
                return $reward;
            }
        }
        return null;
    }

    /**
     * Check if user has redeemed reward
     */
    private function hasRedeemedReward($userId, $rewardId) {
        return $this->db->fetchOne("
            SELECT 1 FROM points_redemptions
            WHERE user_id = ? AND reward_id = ? AND status != 'expired'
        ", [$userId, $rewardId]) !== false;
    }

    /**
     * Get today's points earned
     */
    private function getTodayPointsEarned($userId) {
        $result = $this->db->fetchOne("
            SELECT COALESCE(SUM(points), 0) as today_points
            FROM points_transactions
            WHERE user_id = ? AND DATE(transaction_date) = CURDATE()
        ", [$userId]);

        return $result['today_points'] ?? 0;
    }

    /**
     * Get last user activity
     */
    private function getLastUserActivity($userId, $activityType) {
        return $this->db->fetchOne("
            SELECT activity_date
            FROM user_streaks
            WHERE user_id = ? AND activity_type = ?
            ORDER BY activity_date DESC
            LIMIT 1
        ", [$userId, $activityType]);
    }

    /**
     * Helper methods for achievements and badges
     */
    private function getAchievementName($achievementId) {
        $names = [
            'first_loan' => 'First Steps',
            'regular_borrower' => 'Regular Borrower',
            'frequent_borrower' => 'Frequent Borrower',
            'loyal_customer' => 'Loyal Customer',
            'early_bird' => 'Early Bird',
            'punctual_payer' => 'Punctual Payer',
            'perfect_payment' => 'Perfect Payment Record',
            'points_collector' => 'Points Collector',
            'points_master' => 'Points Master',
            'points_legend' => 'Points Legend',
            'streak_starter' => 'Streak Starter',
            'streak_master' => 'Streak Master',
            'streak_legend' => 'Streak Legend',
            'level_5' => 'Level 5 Achieved',
            'level_10' => 'Level 10 Achieved',
            'level_20' => 'Level 20 Achieved',
            'kyc_verified' => 'KYC Verified',
            'profile_complete' => 'Profile Complete',
            'referral_champion' => 'Referral Champion',
            'community_builder' => 'Community Builder'
        ];

        return $names[$achievementId] ?? 'Unknown Achievement';
    }

    private function getAchievementDescription($achievementId) {
        $descriptions = [
            'first_loan' => 'Take your first loan',
            'regular_borrower' => 'Complete 5 loans',
            'frequent_borrower' => 'Complete 10 loans',
            'loyal_customer' => 'Complete 25 loans',
            'early_bird' => 'Make 3 early payments',
            'punctual_payer' => 'Make 10 on-time payments',
            'perfect_payment' => 'Maintain 5-payment perfect streak',
            'points_collector' => 'Accumulate 500 points',
            'points_master' => 'Accumulate 1000 points',
            'points_legend' => 'Accumulate 5000 points',
            'streak_starter' => 'Achieve 3-day streak',
            'streak_master' => 'Achieve 7-day streak',
            'streak_legend' => 'Achieve 30-day streak',
            'level_5' => 'Reach level 5',
            'level_10' => 'Reach level 10',
            'level_20' => 'Reach level 20',
            'kyc_verified' => 'Complete KYC verification',
            'profile_complete' => 'Complete your profile',
            'referral_champion' => 'Refer 5 successful users',
            'community_builder' => 'Refer 10 successful users'
        ];

        return $descriptions[$achievementId] ?? 'Unknown achievement';
    }

    private function getBadgeName($badgeId) {
        $names = [
            'bronze_borrower' => 'Bronze Borrower',
            'silver_borrower' => 'Silver Borrower',
            'gold_borrower' => 'Gold Borrower',
            'platinum_borrower' => 'Platinum Borrower',
            'diamond_borrower' => 'Diamond Borrower',
            'early_payment_specialist' => 'Early Payment Specialist',
            'payment_master' => 'Payment Master',
            'trust_score_bronze' => 'Trust Score: Bronze',
            'trust_score_silver' => 'Trust Score: Silver',
            'trust_score_gold' => 'Trust Score: Gold',
            'trust_score_platinum' => 'Trust Score: Platinum',
            'community_hero' => 'Community Hero',
            'kyc_champion' => 'KYC Champion'
        ];

        return $names[$badgeId] ?? 'Unknown Badge';
    }

    private function getBadgeDescription($badgeId) {
        $descriptions = [
            'bronze_borrower' => 'Completed 3+ loans',
            'silver_borrower' => 'Completed 10+ loans',
            'gold_borrower' => 'Completed 25+ loans',
            'platinum_borrower' => 'Completed 50+ loans',
            'diamond_borrower' => 'Completed 100+ loans',
            'early_payment_specialist' => '80%+ early payments',
            'payment_master' => '95%+ on-time payments',
            'trust_score_bronze' => '70+ trust score',
            'trust_score_silver' => '80+ trust score',
            'trust_score_gold' => '90+ trust score',
            'trust_score_platinum' => '95+ trust score',
            'community_hero' => 'Referred 10+ users',
            'kyc_champion' => 'KYC verified'
        ];

        return $descriptions[$badgeId] ?? 'Unknown badge';
    }

    private function getBadgeIcon($badgeId, $tier) {
        $icons = [
            'bronze' => '🥉',
            'silver' => '🥈',
            'gold' => '🥇',
            'platinum' => '💎',
            'diamond' => '💠'
        ];

        return $icons[$tier] ?? '🏅';
    }

    /**
     * Helper methods for reward processing
     */
    private function applyLoanDiscount($userId, $discountPercent) {
        // Implementation for applying loan discount
        $this->db->insert('user_rewards', [
            'user_id' => $userId,
            'reward_type' => 'loan_discount',
            'reward_value' => $discountPercent,
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function addCashbackToWallet($userId, $amount) {
        // Implementation for adding cashback to wallet
        $this->db->insert('wallet_transactions', [
            'user_id' => $userId,
            'amount' => $amount,
            'type' => 'cashback',
            'description' => 'Gamification reward cashback',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function waiveProcessingFees($userId, $waiverPercent) {
        // Implementation for waiving processing fees
        $this->db->insert('user_rewards', [
            'user_id' => $userId,
            'reward_type' => 'fee_waiver',
            'reward_value' => $waiverPercent,
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+45 days')),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function grantPrioritySupport($userId, $durationDays) {
        // Implementation for granting priority support
        $this->db->insert('user_rewards', [
            'user_id' => $userId,
            'reward_type' => 'priority_support',
            'reward_value' => $durationDays,
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$durationDays} days")),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function grantExclusiveAccess($userId, $feature) {
        // Implementation for granting exclusive access
        $this->db->insert('user_rewards', [
            'user_id' => $userId,
            'reward_type' => 'exclusive_access',
            'reward_value' => $feature,
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get gamification analytics
     */
    public function getGamificationAnalytics($dateRange = 30) {
        $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

        return [
            'overview' => $this->getGamificationOverview($dateRange),
            'engagement_metrics' => $this->getEngagementMetrics($startDate),
            'popular_rewards' => $this->getPopularRewards($startDate),
            'achievement_progress' => $this->getAchievementProgress($startDate),
            'user_retention' => $this->getGamificationRetention($startDate)
        ];
    }

    private function getGamificationOverview($dateRange) {
        return $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT ug.user_id) as active_users,
                COALESCE(AVG(ug.total_points), 0) as avg_points,
                COALESCE(AVG(ug.current_level), 0) as avg_level,
                COALESCE(AVG(ug.current_streak), 0) as avg_streak,
                COUNT(DISTINCT CASE WHEN ug.total_points > 0 THEN ug.user_id END) as users_with_points,
                COUNT(DISTINCT CASE WHEN ug.current_level > 1 THEN ug.user_id END) as users_leveled_up,
                COALESCE(SUM(pt.points), 0) as total_points_earned
            FROM user_gamification ug
            LEFT JOIN points_transactions pt ON ug.user_id = pt.user_id
                AND DATE(pt.transaction_date) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        ", [$dateRange]);
    }

    private function getEngagementMetrics($startDate) {
        return $this->db->fetchAll("
            SELECT
                DATE(pt.transaction_date) as date,
                COUNT(DISTINCT pt.user_id) as active_users,
                SUM(pt.points) as total_points,
                AVG(pt.points) as avg_points_per_user
            FROM points_transactions pt
            WHERE pt.transaction_date >= ?
            GROUP BY DATE(pt.transaction_date)
            ORDER BY date DESC
        ", [$startDate]);
    }

    private function getPopularRewards($startDate) {
        return $this->db->fetchAll("
            SELECT
                pr.reward_id,
                JSON_EXTRACT(pr.reward_data, '$.name') as reward_name,
                COUNT(*) as redemption_count,
                SUM(pr.points_used) as total_points_spent
            FROM points_redemptions pr
            WHERE pr.redemption_date >= ?
            GROUP BY pr.reward_id
            ORDER BY redemption_count DESC
            LIMIT 10
        ", [$startDate]);
    }

    private function getAchievementProgress($startDate) {
        return $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT ug.user_id) as total_users,
                COUNT(DISTINCT CASE WHEN JSON_LENGTH(ug.achievements_unlocked) > 0 THEN ug.user_id END) as users_with_achievements,
                COUNT(DISTINCT CASE WHEN JSON_LENGTH(ug.badges_earned) > 0 THEN ug.user_id END) as users_with_badges,
                COALESCE(AVG(JSON_LENGTH(ug.achievements_unlocked)), 0) as avg_achievements,
                COALESCE(AVG(JSON_LENGTH(ug.badges_earned)), 0) as avg_badges
            FROM user_gamification ug
        ");
    }

    private function getGamificationRetention($startDate) {
        return $this->db->fetchAll("
            SELECT
                CASE
                    WHEN pt.points = 25 THEN 'Welcome Bonus'
                    WHEN pt.points <= 50 THEN 'Low Activity'
                    WHEN pt.points <= 150 THEN 'Medium Activity'
                    ELSE 'High Activity'
                END as activity_level,
                COUNT(DISTINCT pt.user_id) as user_count,
                AVG(DATEDIFF(CURDATE(), pt.transaction_date)) as days_since_last_activity
            FROM points_transactions pt
            WHERE pt.transaction_date >= ?
            GROUP BY
                CASE
                    WHEN pt.points = 25 THEN 'Welcome Bonus'
                    WHEN pt.points <= 50 THEN 'Low Activity'
                    WHEN pt.points <= 150 THEN 'Medium Activity'
                    ELSE 'High Activity'
                END
            ORDER BY user_count DESC
        ", [$startDate]);
    }
}

// Gamification database setup
function setupGamificationDatabase($db) {
    $tables = [
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
                INDEX idx_user_gamification_level (current_level)
            )
        ",

        'points_transactions' => "
            CREATE TABLE IF NOT EXISTS points_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                points INT NOT NULL,
                reason VARCHAR(50) NOT NULL,
                description TEXT,
                transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_points_user (user_id),
                INDEX idx_points_date (transaction_date)
            )
        ",

        'user_streaks' => "
            CREATE TABLE IF NOT EXISTS user_streaks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                activity_type VARCHAR(50) NOT NULL,
                streak_count INT NOT NULL,
                activity_date DATE NOT NULL,
                UNIQUE KEY unique_user_activity_date (user_id, activity_type, activity_date),
                INDEX idx_streaks_user (user_id),
                INDEX idx_streaks_date (activity_date)
            )
        ",

        'points_redemptions' => "
            CREATE TABLE IF NOT EXISTS points_redemptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reward_id VARCHAR(50) NOT NULL,
                points_used INT NOT NULL,
                reward_data TEXT,
                redemption_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'processed', 'expired') DEFAULT 'pending',
                expires_at TIMESTAMP NULL,
                INDEX idx_redemptions_user (user_id),
                INDEX idx_redemptions_date (redemption_date)
            )
        ",

        'user_rewards' => "
            CREATE TABLE IF NOT EXISTS user_rewards (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reward_type VARCHAR(50) NOT NULL,
                reward_value VARCHAR(100) NOT NULL,
                status ENUM('active', 'used', 'expired') DEFAULT 'active',
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rewards_user (user_id),
                INDEX idx_rewards_type (reward_type),
                INDEX idx_rewards_status (status)
            )
        ",

        'gamification_events' => "
            CREATE TABLE IF NOT EXISTS gamification_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                event_name VARCHAR(100) NOT NULL,
                description TEXT,
                points_awarded INT DEFAULT 0,
                start_date TIMESTAMP NOT NULL,
                end_date TIMESTAMP NOT NULL,
                status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_events_type (event_type),
                INDEX idx_events_dates (start_date, end_date),
                INDEX idx_events_status (status)
            )
        "
    ];

    foreach ($tables as $tableName => $sql) {
        try {
            $db->query($sql);
            echo "✅ Gamification table '$tableName' created successfully\n";
        } catch (Exception $e) {
            echo "❌ Error creating gamification table '$tableName': " . $e->getMessage() . "\n";
        }
    }

    // Create foreign key constraints
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
        "
    ];

    foreach ($constraints as $constraintName => $sql) {
        try {
            $db->query($sql);
            echo "✅ Gamification constraint '$constraintName' added successfully\n";
        } catch (Exception $e) {
            // Constraint might already exist
            echo "⚠️  Gamification constraint '$constraintName': " . $e->getMessage() . "\n";
        }
    }

    echo "\n🎮 Gamification database setup completed!\n";
}

?>