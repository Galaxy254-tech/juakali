<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/loyalty-gamification.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize database and gamification system
$db = new Database();
$gamification = new LoyaltyGamificationSystem($db);
$userId = $_SESSION['user_id'];

// Initialize gamification profile if needed
$profile = $gamification->getUserGamificationProfile($userId);
if (!$profile) {
    $profile = $gamification->initializeUserGamification($userId);
}

// Get user statistics
$userStats = $gamification->getUserGamificationStats($userId);

// Get available rewards
$availableRewards = $gamification->getAvailableRewards();

// Get leaderboard
$leaderboard = $gamification->getLeaderboard('points', 10);

// Get user's rank
$userRank = $gamification->getUserRank($userId, 'points');

// Handle reward redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_reward'])) {
    $rewardId = $_POST['reward_id'];
    $redemptionResult = $gamification->redeemPoints($userId, $rewardId);

    if ($redemptionResult['success']) {
        $successMessage = "Successfully redeemed: {$redemptionResult['reward']['name']}!";
    } else {
        $errorMessage = $redemptionResult['message'];
    }

    // Refresh profile and stats
    $profile = $gamification->getUserGamificationProfile($userId);
    $userStats = $gamification->getUserGamificationStats($userId);
}
$pageTitle = 'Gamification Dashboard - JuaKali Lend';
include '../includes/header.php';
?>

<style>
.gamification-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.dashboard-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.user-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 10px;
}

.stat-label {
    color: #6b7280;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.main-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.section-card {
    background: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.section-header {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.section-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.section-content {
    padding: 20px;
}

.progress-bar {
    background: #e5e7eb;
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    height: 100%;
    transition: width 0.5s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
}

.achievement-grid, .badge-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.achievement-item, .badge-item {
    text-align: center;
    padding: 15px;
    background: #f9fafb;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.achievement-item.unlocked, .badge-item.earned {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #f59e0b;
}

.achievement-item:hover, .badge-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.achievement-icon, .badge-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.achievement-name, .badge-name {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.achievement-description, .badge-description {
    font-size: 0.8rem;
    color: #6b7280;
}

.rewards-list {
    max-height: 500px;
    overflow-y: auto;
}

.reward-item {
    background: #f9fafb;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.reward-item:hover {
    border-color: #667eea;
    transform: translateY(-2px);
}

.reward-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.reward-name {
    font-weight: 600;
    color: #1f2937;
    flex: 1;
}

.reward-points {
    background: #667eea;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.reward-description {
    color: #6b7280;
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.reward-category {
    display: inline-block;
    background: #e5e7eb;
    color: #4b5563;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    text-transform: uppercase;
}

.redeem-btn {
    width: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
}

.redeem-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.redeem-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}

.leaderboard-table th,
.leaderboard-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.leaderboard-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #4b5563;
    font-size: 0.9rem;
    text-transform: uppercase;
}

.leaderboard-table tr:hover {
    background: #f9fafb;
}

.rank-badge {
    display: inline-block;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
    font-weight: 700;
    font-size: 0.9rem;
}

.rank-1 { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; }
.rank-2 { background: linear-gradient(135deg, #d1d5db 0%, #9ca3af 100%); color: white; }
.rank-3 { background: linear-gradient(135deg, #f87171 0%, #dc2626 100%); color: white; }
.rank-other { background: #e5e7eb; color: #4b5563; }

.current-user {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    font-weight: 600;
}

.user-avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
}

.alert {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.level-progress {
    margin: 20px 0;
}

.level-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 600;
}

.next-level-info {
    font-size: 0.9rem;
    color: #6b7280;
}

.streak-indicator {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 1024px) {
    .main-content {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .user-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-header {
        padding: 20px;
    }

    .achievement-grid, .badge-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .user-stats {
        grid-template-columns: 1fr;
    }

    .achievement-grid, .badge-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="gamification-dashboard">
    <!-- Success/Error Messages -->
    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="margin-right: 10px;"></i>
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div style="position: relative; z-index: 1;">
            <h1 style="margin-bottom: 10px; font-size: 2.5rem;">🎮 Gamification Center</h1>
            <p style="font-size: 1.1rem; opacity: 0.9;">Track your progress, earn rewards, and climb the leaderboard!</p>
            <div style="margin-top: 20px;">
                <span class="streak-indicator">
                    <i class="fas fa-fire"></i>
                    Current Streak: <?php echo $profile['current_streak']; ?> days
                </span>
            </div>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="user-stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($profile['total_points']); ?></div>
            <div class="stat-label">Total Points</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?php echo $profile['current_level']; ?></div>
            <div class="stat-label">Current Level</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?php echo $profile['longest_streak']; ?></div>
            <div class="stat-label">Longest Streak</div>
        </div>

        <div class="stat-card">
            <div class="stat-value">#<?php echo $userRank; ?></div>
            <div class="stat-label">Your Rank</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?php echo round($userStats['trust_score'] ?? 0, 0); ?>%</div>
            <div class="stat-label">Trust Score</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?php echo count(json_decode($profile['achievements_unlocked'] ?: '[]', true)); ?></div>
            <div class="stat-label">Achievements</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Left Column -->
        <div>
            <!-- Level Progress -->
            <div class="section-card" style="margin-bottom: 30px;">
                <div class="section-header">
                    <h2 class="section-title">📊 Level Progress</h2>
                </div>
                <div class="section-content">
                    <div class="level-progress">
                        <div class="level-info">
                            <span>Level <?php echo $profile['current_level']; ?></span>
                            <span><?php echo $profile['experience_points']; ?> XP</span>
                        </div>
                        <div class="progress-bar">
                            <?php
                            $nextLevelXP = pow($profile['current_level'], 2) * 100;
                            $currentLevelXP = pow($profile['current_level'] - 1, 2) * 100;
                            $progress = ($profile['experience_points'] - $currentLevelXP) / ($nextLevelXP - $currentLevelXP) * 100;
                            ?>
                            <div class="progress-fill" style="width: <?php echo min($progress, 100); ?>%">
                                <?php echo round($progress, 1); ?>%
                            </div>
                        </div>
                        <div class="next-level-info">
                            <?php
                            $pointsToNext = $nextLevelXP - $profile['experience_points'];
                            if ($pointsToNext > 0) {
                                echo $pointsToNext . " XP to Level " . ($profile['current_level'] + 1);
                            } else {
                                echo "Ready for level up!";
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievements -->
            <div class="section-card" style="margin-bottom: 30px;">
                <div class="section-header">
                    <h2 class="section-title">🏆 Achievements</h2>
                </div>
                <div class="section-content">
                    <div class="achievement-grid">
                        <?php
                        $achievements = json_decode($profile['achievements_unlocked'] ?: '[]', true);
                        $allAchievements = [
                            'first_loan' => ['name' => 'First Steps', 'icon' => '👶', 'desc' => 'Take your first loan'],
                            'regular_borrower' => ['name' => 'Regular Borrower', 'icon' => '💼', 'desc' => 'Complete 5 loans'],
                            'frequent_borrower' => ['name' => 'Frequent Borrower', 'icon' => '📈', 'desc' => 'Complete 10 loans'],
                            'loyal_customer' => ['name' => 'Loyal Customer', 'icon' => '⭐', 'desc' => 'Complete 25 loans'],
                            'early_bird' => ['name' => 'Early Bird', 'icon' => '🌅', 'desc' => 'Make 3 early payments'],
                            'punctual_payer' => ['name' => 'Punctual Payer', 'icon' => '⏰', 'desc' => 'Make 10 on-time payments'],
                            'perfect_payment' => ['name' => 'Perfect Payment', 'icon' => '💯', 'desc' => '5-payment perfect streak'],
                            'points_collector' => ['name' => 'Points Collector', 'icon' => '💰', 'desc' => 'Accumulate 500 points'],
                            'points_master' => ['name' => 'Points Master', 'icon' => '🏆', 'desc' => 'Accumulate 1000 points'],
                            'points_legend' => ['name' => 'Points Legend', 'icon' => '👑', 'desc' => 'Accumulate 5000 points'],
                            'streak_starter' => ['name' => 'Streak Starter', 'icon' => '🔥', 'desc' => 'Achieve 3-day streak'],
                            'streak_master' => ['name' => 'Streak Master', 'icon' => '⚡', 'desc' => 'Achieve 7-day streak'],
                            'streak_legend' => ['name' => 'Streak Legend', 'icon' => '🌟', 'desc' => 'Achieve 30-day streak'],
                            'kyc_verified' => ['name' => 'KYC Verified', 'icon' => '✅', 'desc' => 'Complete KYC verification']
                        ];

                        foreach ($allAchievements as $key => $achievement):
                            $isUnlocked = in_array($key, $achievements);
                        ?>
                            <div class="achievement-item <?php echo $isUnlocked ? 'unlocked' : 'locked'; ?>">
                                <div class="achievement-icon"><?php echo $achievement['icon']; ?></div>
                                <div class="achievement-name"><?php echo $achievement['name']; ?></div>
                                <div class="achievement-description"><?php echo $achievement['desc']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Badges -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">🎖️ Badges</h2>
                </div>
                <div class="section-content">
                    <div class="badge-grid">
                        <?php
                        $badges = json_decode($profile['badges_earned'] ?: '[]', true);
                        $allBadges = [
                            'bronze_borrower' => ['name' => 'Bronze Borrower', 'icon' => '🥉', 'tier' => 'bronze'],
                            'silver_borrower' => ['name' => 'Silver Borrower', 'icon' => '🥈', 'tier' => 'silver'],
                            'gold_borrower' => ['name' => 'Gold Borrower', 'icon' => '🥇', 'tier' => 'gold'],
                            'platinum_borrower' => ['name' => 'Platinum Borrower', 'icon' => '💎', 'tier' => 'platinum'],
                            'diamond_borrower' => ['name' => 'Diamond Borrower', 'icon' => '💠', 'tier' => 'diamond'],
                            'early_payment_specialist' => ['name' => 'Early Payment Specialist', 'icon' => '⏰', 'tier' => 'silver'],
                            'payment_master' => ['name' => 'Payment Master', 'icon' => '💰', 'tier' => 'gold'],
                            'kyc_champion' => ['name' => 'KYC Champion', 'icon' => '✅', 'tier' => 'bronze']
                        ];

                        foreach ($allBadges as $key => $badge):
                            $isEarned = in_array($key, $badges);
                        ?>
                            <div class="badge-item <?php echo $isEarned ? 'earned' : 'locked'; ?>">
                                <div class="badge-icon"><?php echo $badge['icon']; ?></div>
                                <div class="badge-name"><?php echo $badge['name']; ?></div>
                                <div class="badge-description" style="text-transform: capitalize; font-size: 0.7rem;">
                                    <?php echo $badge['tier']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Available Rewards -->
            <div class="section-card" style="margin-bottom: 30px;">
                <div class="section-header">
                    <h2 class="section-title">🎁 Redeem Rewards</h2>
                </div>
                <div class="section-content">
                    <div class="rewards-list">
                        <?php foreach ($availableRewards as $reward): ?>
                            <div class="reward-item">
                                <div class="reward-header">
                                    <div class="reward-name"><?php echo htmlspecialchars($reward['name']); ?></div>
                                    <div class="reward-points"><?php echo $reward['points_required']; ?> pts</div>
                                </div>
                                <div class="reward-description"><?php echo htmlspecialchars($reward['description']); ?></div>
                                <div class="reward-category"><?php echo str_replace('_', ' ', $reward['category']); ?></div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="redeem_reward" value="1">
                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                    <button type="submit" class="redeem-btn"
                                            <?php echo ($profile['total_points'] < $reward['points_required']) ? 'disabled' : ''; ?>>
                                        <?php echo ($profile['total_points'] < $reward['points_required']) ? 'Insufficient Points' : 'Redeem Reward'; ?>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">🏅 Leaderboard</h2>
                </div>
                <div class="section-content">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Points</th>
                                <th>Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $index => $user): ?>
                                <tr class="<?php echo ($user['user_id'] == $userId) ? 'current-user' : ''; ?>">
                                    <td>
                                        <span class="rank-badge <?php echo ($index < 3) ? 'rank-' . ($index + 1) : 'rank-other'; ?>">
                                            <?php echo $user['rank']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($user['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo number_format($user['total_points']); ?></strong></td>
                                    <td><?php echo $user['current_level']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Interactive animations and effects
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars on load
    const progressBars = document.querySelectorAll('.progress-fill');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });

    // Add hover effects to cards
    const cards = document.querySelectorAll('.stat-card, .achievement-item, .badge-item');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Smooth scroll for leaderboard
    const leaderboardTable = document.querySelector('.leaderboard-table tbody');
    if (leaderboardTable) {
        const currentUserRow = leaderboardTable.querySelector('.current-user');
        if (currentUserRow) {
            currentUserRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Reward redemption confirmation
    const redeemButtons = document.querySelectorAll('.redeem-btn:not([disabled])');
    redeemButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rewardName = this.closest('.reward-item').querySelector('.reward-name').textContent;
            const confirmRedemption = confirm(`Are you sure you want to redeem: ${rewardName}?`);

            if (!confirmRedemption) {
                e.preventDefault();
            }
        });
    });

    // Add celebration effect for achievements and badges
    const achievementItems = document.querySelectorAll('.achievement-item.unlocked');
    const badgeItems = document.querySelectorAll('.badge-item.earned');

    [...achievementItems, ...badgeItems].forEach((item, index) => {
        setTimeout(() => {
            item.style.animation = 'pulse 0.5s ease-in-out';
        }, index * 100);
    });
});

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
`;
document.head.appendChild(style);
</script>

    <script>
        // Gamification JavaScript
        let currentPoints = <?php echo $profile['profile']['total_points']; ?>;
        let socket = null;

        // Initialize WebSocket for real-time updates
        function initializeWebSocket() {
            if (typeof WebSocket !== 'undefined') {
                socket = new WebSocket('wss://<?php echo $_SERVER['HTTP_HOST']; ?>/ws/gamification');

                socket.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleRealtimeUpdate(data);
                };

                socket.onclose = function() {
                    setTimeout(initializeWebSocket, 5000);
                };
            }
        }

        // Handle real-time updates
        function handleRealtimeUpdate(data) {
            switch(data.type) {
                case 'points_awarded':
                    showPointsAnimation(data.points, data.reason);
                    updatePointsDisplay(data.newTotal);
                    break;
                case 'badge_earned':
                    showBadgeEarnedAnimation(data.badge);
                    break;
                case 'level_up':
                    showLevelUpAnimation(data.newLevel);
                    break;
            }
        }

        // Show points animation
        function showPointsAnimation(points, reason) {
            const notification = document.createElement('div');
            notification.className = 'achievement-notification bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg mb-2 achievement-unlock';
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="fas fa-coins text-2xl"></i>
                    <div>
                        <p class="font-bold">+${points} Points!</p>
                        <p class="text-sm">${reason}</p>
                    </div>
                </div>
            `;

            document.getElementById('achievementNotifications').appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Show badge earned animation
        function showBadgeEarnedAnimation(badge) {
            const notification = document.createElement('div');
            notification.className = 'achievement-notification bg-purple-500 text-white px-6 py-3 rounded-lg shadow-lg mb-2 achievement-unlock';
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="text-3xl">${badge.icon}</div>
                    <div>
                        <p class="font-bold">Badge Earned!</p>
                        <p class="text-sm">${badge.name}</p>
                    </div>
                </div>
            `;

            document.getElementById('achievementNotifications').appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 7000);
        }

        // Update points display
        function updatePointsDisplay(newTotal) {
            currentPoints = newTotal;

            // Update quick points display
            document.getElementById('quickPoints').textContent = newTotal.toLocaleString();

            // Find and update points card with animation
            const pointsCard = document.querySelector('.bg-gradient-to-r.from-yellow-400');
            if (pointsCard) {
                const pointsElement = pointsCard.querySelector('.points-animation');
                if (pointsElement) {
                    pointsElement.style.transform = 'scale(1.2)';
                    setTimeout(() => {
                        pointsElement.textContent = newTotal.toLocaleString();
                        pointsElement.style.transform = 'scale(1)';
                    }, 300);
                }
            }
        }

        // Join challenge
        function joinChallenge(challengeId) {
            fetch('../api/gamification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'join_challenge',
                    challenge_id: challengeId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Challenge joined successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Failed to join challenge', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
            });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
            notification.className = `achievement-notification ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg mb-2 achievement-unlock`;
            notification.innerHTML = `
                <p class="font-semibold">${message}</p>
            `;

            document.getElementById('achievementNotifications').appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Modal functions
        function showAllBadges() {
            document.getElementById('allBadgesModal').classList.remove('hidden');
        }

        function closeAllBadgesModal() {
            document.getElementById('allBadgesModal').classList.add('hidden');
        }

        function showFullLeaderboard() {
            document.getElementById('leaderboardModal').classList.remove('hidden');
        }

        function closeLeaderboardModal() {
            document.getElementById('leaderboardModal').classList.add('hidden');
        }

        function refreshChallenges() {
            location.reload();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeWebSocket();

            // Show welcome animation for returning users
            const lastVisit = localStorage.getItem('lastGamificationVisit');
            const today = new Date().toDateString();

            if (lastVisit !== today) {
                showNotification('Welcome back! You\'ve earned daily login points!', 'success');
                localStorage.setItem('lastGamificationVisit', today);
            }

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAllBadgesModal();
                    closeLeaderboardModal();
                }
                if (e.key === 'l' && e.ctrlKey) {
                    e.preventDefault();
                    showFullLeaderboard();
                }
                if (e.key === 'b' && e.ctrlKey) {
                    e.preventDefault();
                    showAllBadges();
                }
            });
        });

        // Cleanup WebSocket on page unload
        window.addEventListener('beforeunload', function() {
            if (socket) {
                socket.close();
            }
        });
    </script>
</body>
</html>