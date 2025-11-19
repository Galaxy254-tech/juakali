<?php
/**
 * Gamification System Cron Jobs
 * Handles automated gamification tasks and maintenance
 */

// Set execution time limit
set_time_limit(3600); // 1 hour

// Include required files
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/gamification-system.php';

// Prevent direct web access
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

class GamificationCron {
    private $db;
    private $gamification;
    private $logFile;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->gamification = new GamificationSystem();
        $this->logFile = __DIR__ . '/../logs/gamification-cron.log';
    }

    /**
     * Log cron activity
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Run all gamification cron tasks
     */
    public function runAllTasks() {
        $this->log("Starting gamification cron job execution");

        $startTime = microtime(true);
        $results = [];

        try {
            // Process daily rewards and streaks
            $this->log("Processing daily rewards and streaks");
            $results['daily_rewards'] = $this->processDailyRewards();

            // Process expired challenges
            $this->log("Processing expired challenges");
            $results['expired_challenges'] = $this->processExpiredChallenges();

            // Create monthly challenges
            if (date('d') === '01') {
                $this->log("Creating monthly challenges");
                $results['monthly_challenges'] = $this->createMonthlyChallenges();
            }

            // Check and award automatic achievements
            $this->log("Checking automatic achievements");
            $results['achievements'] = $this->processAutomaticAchievements();

            // Update leaderboards
            $this->log("Updating leaderboards");
            $results['leaderboards'] = $this->updateLeaderboards();

            // Clean up old data
            $this->log("Cleaning up old data");
            $results['cleanup'] = $this->performCleanup();

            // Send gamification reports
            if (date('H') === '09') { // 9 AM
                $this->log("Sending daily gamification reports");
                $results['reports'] = $this->sendDailyReports();
            }

            // Cache frequently accessed data
            $this->log("Caching gamification data");
            $results['caching'] = $this->updateCache();

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $this->log("Gamification cron completed successfully in {$executionTime} seconds");
            $this->log("Results: " . json_encode($results));

            return $results;

        } catch (Exception $e) {
            $this->log("Cron execution failed: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Process daily rewards and streaks
     */
    private function processDailyRewards() {
        $processedCount = 0;
        $streakBonusesAwarded = 0;

        // Get all active users who logged in today
        $todayUsers = $this->db->fetchAll("
            SELECT DISTINCT ua.user_id
            FROM user_activity ua
            WHERE DATE(ua.activity_date) = CURDATE()
              AND ua.activity_type = 'login'
        ");

        foreach ($todayUsers as $user) {
            $userId = $user['user_id'];

            try {
                // Check yesterday's login for streak calculation
                $yesterdayLogin = $this->db->fetchOne("
                    SELECT COUNT(*) as count
                    FROM user_activity
                    WHERE user_id = ?
                      AND activity_type = 'login'
                      AND DATE(activity_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                ", [$userId]);

                // Update streak
                if ($yesterdayLogin['count'] > 0) {
                    $this->db->execute("
                        UPDATE user_profiles
                        SET streak_days = streak_days + 1, last_login_date = NOW()
                        WHERE user_id = ?
                    ", [$userId]);

                    $profile = $this->db->fetchOne("
                        SELECT streak_days FROM user_profiles WHERE user_id = ?
                    ", [$userId]);

                    $streakDays = $profile['streak_days'];

                    // Check for streak bonuses
                    if ($streakDays % 7 === 0) {
                        $this->gamification->awardPoints($userId, 'weekly_streak', [
                            'streak_days' => $streakDays
                        ]);
                        $streakBonusesAwarded++;
                    }

                    if ($streakDays % 30 === 0) {
                        $this->gamification->awardPoints($userId, 'monthly_streak', [
                            'streak_days' => $streakDays
                        ]);
                        $streakBonusesAwarded++;
                    }
                } else {
                    $this->db->execute("
                        UPDATE user_profiles
                        SET streak_days = 1, last_login_date = NOW()
                        WHERE user_id = ?
                    ", [$userId]);
                }

                // Award daily login points
                $this->gamification->awardPoints($userId, 'daily_login');
                $processedCount++;

            } catch (Exception $e) {
                $this->log("Failed to process daily rewards for user {$userId}: " . $e->getMessage(), 'ERROR');
            }
        }

        $this->log("Processed daily rewards for {$processedCount} users, awarded {$streakBonusesAwarded} streak bonuses");
        return ['users_processed' => $processedCount, 'streak_bonuses' => $streakBonusesAwarded];
    }

    /**
     * Process expired challenges
     */
    private function processExpiredChallenges() {
        $expiredCount = 0;
        $completionBonusesAwarded = 0;

        // Get expired challenges
        $expiredChallenges = $this->db->fetchAll("
            SELECT * FROM challenges
            WHERE end_date < NOW() AND status = 'active'
        ");

        foreach ($expiredChallenges as $challenge) {
            try {
                // Get top performers
                $topPerformers = $this->db->fetchAll("
                    SELECT cp.user_id, cp.progress
                    FROM challenge_participants cp
                    WHERE cp.challenge_id = ? AND cp.completed = 0
                    ORDER BY cp.progress DESC
                    LIMIT 3
                ", [$challenge['id']]);

                // Award completion bonuses to top performers
                foreach ($topPerformers as $index => $performer) {
                    $bonusMultiplier = 3 - $index; // 3x, 2x, 1x
                    $bonusPoints = $challenge['points_reward'] * $bonusMultiplier * 0.5;

                    $this->gamification->awardPoints($performer['user_id'], 'challenge_top_performer', [
                        'challenge_id' => $challenge['id'],
                        'challenge_title' => $challenge['title'],
                        'rank' => $index + 1,
                        'bonus_points' => $bonusPoints
                    ]);

                    $completionBonusesAwarded++;
                }

                // Mark challenge as expired
                $this->db->execute("
                    UPDATE challenges SET status = 'expired', updated_at = NOW()
                    WHERE id = ?
                ", [$challenge['id']]);

                $expiredCount++;

            } catch (Exception $e) {
                $this->log("Failed to process expired challenge {$challenge['id']}: " . $e->getMessage(), 'ERROR');
            }
        }

        $this->log("Processed {$expiredCount} expired challenges, awarded {$completionBonusesAwarded} completion bonuses");
        return ['challenges_expired' => $expiredCount, 'completion_bonuses' => $completionBonusesAwarded];
    }

    /**
     * Create monthly challenges
     */
    private function createMonthlyChallenges() {
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
            ],
            [
                'title' => 'Digital Explorer',
                'description' => 'Complete 10 actions via the mobile app',
                'challenge_type' => 'mobile_usage',
                'target_value' => 10,
                'points_reward' => 100,
                'badge_reward' => null
            ]
        ];

        $createdCount = 0;

        foreach ($challenges as $challenge) {
            try {
                // Check if similar challenge already exists
                $existing = $this->db->fetchOne("
                    SELECT COUNT(*) as count
                    FROM challenges
                    WHERE title = ? AND MONTH(start_date) = MONTH(CURDATE())
                ", [$challenge['title']]);

                if ($existing['count'] === 0) {
                    $this->db->execute("
                        INSERT INTO challenges (
                            title, description, challenge_type, target_value,
                            points_reward, badge_reward, start_date, end_date,
                            eligibility_criteria, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ", [
                        $challenge['title'],
                        $challenge['description'],
                        $challenge['challenge_type'],
                        $challenge['target_value'],
                        $challenge['points_reward'],
                        $challenge['badge_reward'],
                        $currentMonth,
                        $nextMonth,
                        json_encode(['min_level' => 1])
                    ]);

                    $createdCount++;
                }
            } catch (Exception $e) {
                $this->log("Failed to create monthly challenge '{$challenge['title']}': " . $e->getMessage(), 'ERROR');
            }
        }

        $this->log("Created {$createdCount} new monthly challenges");
        return ['challenges_created' => $createdCount];
    }

    /**
     * Process automatic achievements
     */
    private function processAutomaticAchievements() {
        $achievementsProcessed = 0;
        $newAchievements = 0;

        // Get all active achievements
        $achievements = $this->db->fetchAll("
            SELECT * FROM achievements WHERE is_active = 1
        ");

        // Get all active users
        $activeUsers = $this->db->fetchAll("
            SELECT id FROM users WHERE status = 'active'
        ");

        foreach ($activeUsers as $user) {
            $userId = $user['id'];

            foreach ($achievements as $achievement) {
                try {
                    // Check if user already has this achievement
                    $hasAchievement = $this->db->fetchOne("
                        SELECT COUNT(*) as count
                        FROM user_achievements
                        WHERE user_id = ? AND achievement_id = ? AND completed = 1
                    ", [$userId, $achievement['id']]);

                    if ($hasAchievement['count'] > 0) {
                        continue;
                    }

                    // Check achievement conditions
                    $conditions = json_decode($achievement['trigger_conditions'], true);
                    if ($this->checkAchievementConditions($userId, $conditions)) {
                        // Award achievement
                        $this->awardAchievement($userId, $achievement);
                        $newAchievements++;
                    }

                    $achievementsProcessed++;

                } catch (Exception $e) {
                    $this->log("Failed to process achievement {$achievement['id']} for user {$userId}: " . $e->getMessage(), 'ERROR');
                }
            }
        }

        $this->log("Processed {$achievementsProcessed} achievement checks, awarded {$newAchievements} new achievements");
        return ['checks_processed' => $achievementsProcessed, 'achievements_awarded' => $newAchievements];
    }

    /**
     * Check if user meets achievement conditions
     */
    private function checkAchievementConditions($userId, $conditions) {
        foreach ($conditions as $condition => $value) {
            switch ($condition) {
                case 'loan_applications':
                    $count = $this->db->fetchOne("
                        SELECT COUNT(*) as count FROM loans WHERE borrower_id = ?
                    ", [$userId])['count'];
                    if ($count < $value) return false;
                    break;

                case 'completed_loans':
                    $count = $this->db->fetchOne("
                        SELECT COUNT(*) as count FROM loans
                        WHERE borrower_id = ? AND status = 'completed'
                    ", [$userId])['count'];
                    if ($count < $value) return false;
                    break;

                case 'on_time_payments':
                    $count = $this->db->fetchOne("
                        SELECT COUNT(*) as count
                        FROM repayment_schedule rs
                        JOIN loans l ON rs.loan_id = l.id
                        WHERE l.borrower_id = ?
                          AND rs.status = 'paid'
                          AND rs.due_date >= rs.paid_date
                    ", [$userId])['count'];
                    if ($count < $value) return false;
                    break;

                case 'successful_referrals':
                    $count = $this->db->fetchOne("
                        SELECT COUNT(*) as count
                        FROM user_referrals ur
                        JOIN users u ON ur.referral_id = u.id
                        WHERE ur.referrer_id = ? AND u.status = 'active'
                    ", [$userId])['count'];
                    if ($count < $value) return false;
                    break;

                case 'days_active':
                    $user = $this->db->fetchOne("
                        SELECT created_at FROM users WHERE id = ?
                    ", [$userId]);
                    $daysActive = $user ? (new DateTime($user['created_at']))->diff(new DateTime())->days : 0;
                    if ($daysActive < $value) return false;
                    break;

                case 'streak_days':
                    $profile = $this->db->fetchOne("
                        SELECT streak_days FROM user_profiles WHERE user_id = ?
                    ", [$userId]);
                    if (!$profile || $profile['streak_days'] < $value) return false;
                    break;

                case 'consecutive_morning_logins':
                    // This would require more complex logic to track consecutive days
                    break;

                default:
                    $this->log("Unknown achievement condition: {$condition}", 'WARNING');
            }
        }

        return true;
    }

    /**
     * Award achievement to user
     */
    private function awardAchievement($userId, $achievement) {
        $this->db->execute("
            INSERT INTO user_achievements (
                user_id, achievement_id, completed, completed_at
            ) VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
            completed = 1, completed_at = NOW()
        ", [$userId, $achievement['id']]);

        // Award points
        $this->gamification->awardPoints($userId, 'achievement_unlocked', [
            'achievement_id' => $achievement['id'],
            'achievement_name' => $achievement['name'],
            'points' => $achievement['points_reward']
        ]);

        // Award badge if specified
        if ($achievement['badge_reward']) {
            $this->gamification->awardBadge($userId, $achievement['badge_reward']);
        }

        $this->log("Awarded achievement '{$achievement['name']}' to user {$userId}");
    }

    /**
     * Update leaderboards
     */
    private function updateLeaderboards() {
        $leaderboardsUpdated = 0;

        try {
            // Update materialized views if needed
            $views = ['user_gamification_summary', 'challenge_leaderboard'];

            foreach ($views as $view) {
                $this->db->execute("ALTER VIEW {$view} AS SELECT * FROM {$view}");
                $leaderboardsUpdated++;
            }

            // Cache popular leaderboards
            $periods = ['daily', 'weekly', 'monthly'];
            foreach ($periods as $period) {
                $cacheKey = "leaderboard_points_{$period}";
                $leaderboard = $this->gamification->getLeaderboard('points', $period, 100);

                $this->db->execute("
                    INSERT INTO gamification_analytics_cache (
                        cache_key, cache_data, expires_at
                    ) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                    ON DUPLICATE KEY UPDATE
                    cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)
                ", [
                    $cacheKey,
                    json_encode($leaderboard)
                ]);

                $leaderboardsUpdated++;
            }

        } catch (Exception $e) {
            $this->log("Failed to update leaderboards: " . $e->getMessage(), 'ERROR');
        }

        $this->log("Updated {$leaderboardsUpdated} leaderboard caches");
        return ['leaderboards_updated' => $leaderboardsUpdated];
    }

    /**
     * Perform cleanup tasks
     */
    private function performCleanup() {
        $cleanupStats = [];

        try {
            // Clean old notifications (older than 30 days)
            $oldNotifications = $this->db->execute("
                DELETE FROM realtime_notifications
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND delivered = 1
            ");
            $cleanupStats['old_notifications'] = $oldNotifications;

            // Clean expired cache entries
            $expiredCache = $this->db->execute("
                DELETE FROM gamification_analytics_cache
                WHERE expires_at < NOW()
            ");
            $cleanupStats['expired_cache'] = $expiredCache;

            // Archive old activity logs (older than 1 year)
            $archivedActivity = $this->db->execute("
                INSERT INTO user_activity_archive
                SELECT * FROM user_activity
                WHERE activity_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ");
            $this->db->execute("
                DELETE FROM user_activity
                WHERE activity_date < DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ");
            $cleanupStats['archived_activity'] = $archivedActivity;

            // Optimize tables
            $tables = ['user_points', 'user_badges', 'challenge_participants', 'user_activity'];
            foreach ($tables as $table) {
                $this->db->execute("OPTIMIZE TABLE {$table}");
            }
            $cleanupStats['tables_optimized'] = count($tables);

        } catch (Exception $e) {
            $this->log("Cleanup failed: " . $e->getMessage(), 'ERROR');
        }

        $this->log("Cleanup completed: " . json_encode($cleanupStats));
        return $cleanupStats;
    }

    /**
     * Send daily gamification reports
     */
    private function sendDailyReports() {
        $reportsSent = 0;

        try {
            // Get users who want daily reports
            $users = $this->db->fetchAll("
                SELECT u.id, u.name, u.email
                FROM users u
                JOIN user_gamification_preferences ugp ON u.id = ugp.user_id
                WHERE u.status = 'active'
                  AND JSON_EXTRACT(ugp.notification_preferences, '$.daily_report') = true
            ");

            foreach ($users as $user) {
                $report = $this->generateDailyReport($user['id']);
                $this->sendEmailReport($user['email'], $user['name'], $report);
                $reportsSent++;
            }

        } catch (Exception $e) {
            $this->log("Failed to send daily reports: " . $e->getMessage(), 'ERROR');
        }

        $this->log("Sent {$reportsSent} daily gamification reports");
        return ['reports_sent' => $reportsSent];
    }

    /**
     * Generate daily report for user
     */
    private function generateDailyReport($userId) {
        $profile = $this->gamification->getUserGamificationProfile($userId);

        return [
            'total_points' => $profile['profile']['total_points'],
            'current_level' => $profile['profile']['level_name'],
            'streak_days' => $profile['profile']['streak_days'],
            'badges_count' => count($profile['badges']),
            'active_challenges' => count(array_filter($profile['user_challenges'] ?? [], function($c) {
                return $c['participation_status'] === 'joined';
            })),
            'points_today' => $this->getPointsToday($userId)
        ];
    }

    /**
     * Get points earned today
     */
    private function getPointsToday($userId) {
        return $this->db->fetchOne("
            SELECT COALESCE(SUM(final_points), 0) as points
            FROM user_points
            WHERE user_id = ? AND DATE(created_at) = CURDATE()
        ", [$userId])['points'];
    }

    /**
     * Send email report (placeholder)
     */
    private function sendEmailReport($email, $name, $report) {
        // Integration with email service would go here
        $this->log("Daily report sent to {$email} for {$name}");
    }

    /**
     * Update cache
     */
    private function updateCache() {
        $cacheUpdated = 0;

        try {
            // Cache popular gamification data
            $cacheData = [
                'total_active_users' => $this->db->fetchOne("
                    SELECT COUNT(*) as count FROM users WHERE status = 'active'
                ")['count'],
                'total_points_awarded' => $this->db->fetchOne("
                    SELECT COALESCE(SUM(final_points), 0) as total FROM user_points
                ")['total'],
                'popular_badges' => $this->db->fetchAll("
                    SELECT b.badge_code, b.name, COUNT(ub.user_id) as times_earned
                    FROM badges b
                    JOIN user_badges ub ON b.badge_code = ub.badge_code
                    GROUP BY b.badge_code, b.name
                    ORDER BY times_earned DESC
                    LIMIT 10
                "),
                'level_distribution' => $this->db->fetchAll("
                    SELECT ul.level_name, COUNT(up.user_id) as user_count
                    FROM user_levels ul
                    LEFT JOIN user_profiles up ON ul.level_id = up.current_level
                    GROUP BY ul.level_id, ul.level_name
                    ORDER BY ul.level_id
                ")
            ];

            foreach ($cacheData as $key => $data) {
                $this->db->execute("
                    INSERT INTO gamification_analytics_cache (
                        cache_key, cache_data, expires_at
                    ) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                    ON DUPLICATE KEY UPDATE
                    cache_data = VALUES(cache_data), expires_at = VALUES(expires_at)
                ", [
                    "gamification_stats_{$key}",
                    json_encode($data)
                ]);

                $cacheUpdated++;
            }

        } catch (Exception $e) {
            $this->log("Cache update failed: " . $e->getMessage(), 'ERROR');
        }

        $this->log("Updated {$cacheUpdated} cache entries");
        return ['cache_entries_updated' => $cacheUpdated];
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    // Check for command line arguments
    $task = $argv[1] ?? 'all';

    $cron = new GamificationCron();

    switch ($task) {
        case 'all':
            $cron->runAllTasks();
            break;

        case 'daily_rewards':
            $cron->processDailyRewards();
            break;

        case 'challenges':
            $cron->processExpiredChallenges();
            break;

        case 'achievements':
            $cron->processAutomaticAchievements();
            break;

        case 'cleanup':
            $cron->performCleanup();
            break;

        default:
            echo "Usage: php gamification-cron.php [task]\n";
            echo "Tasks: all, daily_rewards, challenges, achievements, cleanup\n";
            exit(1);
    }
}
?>