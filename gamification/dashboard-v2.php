<?php
/**
 * Enhanced Gamification Dashboard v2.0
 * Modern, beautiful, and interactive user interface
 * for JuaKali Lend Gamification Platform
 */

session_start();
require_once '../includes/auth.php';
require_once '../includes/gamification-system.php';
require_once '../includes/database.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize gamification system
$userId = $_SESSION['user_id'];
$gamification = new GamificationSystem($userId);
$db = Database::getInstance();

// Get user gamification profile
$profile = $gamification->getUserGamificationProfile($userId);
$leaderboard = $gamification->getLeaderboard('points', 'monthly', 10);
$userChallenges = $gamification->getUserChallenges($userId);
$rewards = $gamification->getAvailableRewards($userId);
$achievements = $gamification->getUserAchievements($userId);

// Get user rank
$userRank = 1;
foreach ($leaderboard as $index => $user) {
    if ($user['id'] == $userId) {
        $userRank = $index + 1;
        break;
    }
}

// Calculate additional metrics
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'];
$rankPercentile = round((($totalUsers - $userRank) / $totalUsers) * 100, 1);
$pointsToNextLevel = $profile['points_to_next_level'];
$levelProgress = $profile['level_progress'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification Dashboard - JuaKali Lend</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Styles -->
    <link href="../assets/css/gamification-modern.css" rel="stylesheet">
    <link href="../assets/css/animations.css" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        /* Additional custom styles for this dashboard */
        .hero-section {
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            padding: var(--space-3xl) 0;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.02)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(167, 139, 250, 0.1) 0%, rgba(102, 126, 234, 0.05) 100%);
            animation: float 20s infinite ease-in-out;
        }

        .shape:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -100px;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -50px;
            animation-delay: 5s;
        }

        .shape:nth-child(3) {
            width: 150px;
            height: 150px;
            top: 50%;
            left: 10%;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            33% { transform: translateY(-30px) rotate(120deg); }
            66% { transform: translateY(30px) rotate(240deg); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-top: var(--space-2xl);
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            text-align: center;
            backdrop-filter: blur(10px);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto var(--space-md);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--space-xs);
            background: linear-gradient(135deg, #FFFFFF 0%, #B8BCC8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .achievement-showcase {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .achievement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }

        .achievement-item {
            text-align: center;
            padding: var(--space-md);
            border-radius: var(--radius-lg);
            transition: all var(--transition-normal);
            cursor: pointer;
        }

        .achievement-item:hover {
            background: var(--bg-card-hover);
            transform: scale(1.05);
        }

        .achievement-icon {
            font-size: 2rem;
            margin-bottom: var(--space-sm);
            filter: grayscale(100%);
            opacity: 0.5;
            transition: all var(--transition-normal);
        }

        .achievement-item.unlocked .achievement-icon {
            filter: grayscale(0%);
            opacity: 1;
        }

        .progress-section {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .progress-item {
            margin-bottom: var(--space-lg);
        }

        .progress-item:last-child {
            margin-bottom: 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-sm);
        }

        .progress-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-value {
            font-weight: 700;
            color: var(--text-accent);
        }

        .progress-bar-container {
            background: var(--bg-tertiary);
            border-radius: var(--radius-full);
            height: 8px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar-fill {
            height: 100%;
            background: var(--primary-gradient);
            border-radius: var(--radius-full);
            transition: width 1s ease;
            position: relative;
        }

        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 2s infinite;
        }

        .challenges-section {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .challenge-list {
            display: grid;
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }

        .challenge-item {
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-normal);
        }

        .challenge-item:hover {
            background: var(--bg-card-hover);
            transform: translateX(4px);
        }

        .challenge-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .challenge-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--space-xs);
        }

        .challenge-status {
            padding: var(--space-xs) var(--space-sm);
            background: var(--bg-secondary);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .challenge-status.joined {
            background: var(--success-gradient);
            color: white;
        }

        .rewards-preview {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .rewards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-md);
            margin-top: var(--space-lg);
        }

        .reward-item {
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--space-md);
            text-align: center;
            transition: all var(--transition-normal);
            cursor: pointer;
        }

        .reward-item:hover {
            background: var(--bg-card-hover);
            transform: scale(1.02);
        }

        .reward-icon {
            font-size: 2rem;
            margin-bottom: var(--space-sm);
        }

        .reward-name {
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .reward-cost {
            color: var(--text-accent);
            font-weight: 700;
        }

        .leaderboard-section {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-2xl);
            padding: var(--space-xl);
            margin-top: var(--space-2xl);
        }

        .leaderboard-list {
            display: grid;
            gap: var(--space-sm);
            margin-top: var(--space-lg);
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            transition: all var(--transition-normal);
        }

        .leaderboard-item:hover {
            background: var(--bg-card-hover);
        }

        .leaderboard-item.current-user {
            background: rgba(167, 139, 250, 0.1);
            border-color: var(--text-accent);
        }

        .leaderboard-rank {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: 700;
            background: var(--bg-secondary);
        }

        .leaderboard-rank.top-3 {
            background: var(--warning-gradient);
            color: white;
        }

        .leaderboard-user-info {
            flex: 1;
        }

        .leaderboard-name {
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .leaderboard-level {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .leaderboard-points {
            text-align: right;
            font-weight: 700;
            color: var(--text-accent);
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-up {
            animation: slideInUp 0.6s ease-out;
        }

        .animate-delay-1 { animation-delay: 0.1s; }
        .animate-delay-2 { animation-delay: 0.2s; }
        .animate-delay-3 { animation-delay: 0.3s; }
        .animate-delay-4 { animation-delay: 0.4s; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .hero-section {
                padding: var(--space-xl) 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .stat-card {
                padding: var(--space-lg);
            }

            .stat-value {
                font-size: 2rem;
            }

            .achievement-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .rewards-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .achievement-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="navbar-brand">
                    <a href="../dashboard.php" class="brand-link">
                        <i class="fas fa-arrow-left"></i>
                        <span class="brand-text">JuaKali Lend</span>
                    </a>
                </div>
                <div class="navbar-actions">
                    <div class="user-points">
                        <i class="fas fa-coins"></i>
                        <span id="userPoints"><?php echo number_format($profile['profile']['total_points']); ?></span>
                    </div>
                    <div class="user-streak">
                        <i class="fas fa-fire"></i>
                        <span><?php echo $profile['profile']['streak_days']; ?> days</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>

        <div class="container">
            <div class="hero-content">
                <div class="text-center animate-slide-up">
                    <h1 class="hero-title">
                        Welcome back, <span class="text-gradient"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>! 🎉
                    </h1>
                    <p class="hero-subtitle">
                        Your journey to financial excellence continues. Keep up the amazing work!
                    </p>
                </div>

                <!-- Level Progress -->
                <div class="level-progress-container animate-slide-up animate-delay-1">
                    <div class="level-progress-card">
                        <div class="level-info">
                            <h2 class="level-title">Level <?php echo $profile['profile']['current_level']; ?></h2>
                            <p class="level-subtitle"><?php echo ucfirst($profile['profile']['level_name']); ?> Tier</p>
                        </div>
                        <div class="level-visual">
                            <div class="level-progress">
                                <svg width="120" height="120">
                                    <defs>
                                        <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="60" cy="60" r="54" class="level-progress-bg"></circle>
                                    <circle cx="60" cy="60" r="54" class="level-progress-fill"
                                            stroke-dasharray="<?php echo $levelProgress * 3.4; ?> 340"
                                            stroke-dashoffset="0"></circle>
                                </svg>
                                <div class="level-progress-text">
                                    <div class="level-progress-number"><?php echo $profile['profile']['current_level']; ?></div>
                                    <div class="level-progress-label"><?php echo $profile['next_level']['name'] ?? 'MAX'; ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="level-stats">
                            <div class="level-stat">
                                <span class="level-stat-value"><?php echo number_format($pointsToNextLevel); ?></span>
                                <span class="level-stat-label">points to next level</span>
                            </div>
                            <div class="level-stat">
                                <span class="level-stat-value">#<?php echo $userRank; ?></span>
                                <span class="level-stat-label">current rank</span>
                            </div>
                            <div class="level-stat">
                                <span class="level-stat-value"><?php echo $rankPercentile; ?>%</span>
                                <span class="level-stat-label">percentile</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card animate-slide-up animate-delay-2">
                        <div class="stat-icon" style="background: var(--primary-gradient);">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($profile['profile']['total_points']); ?></div>
                        <div class="stat-label">Total Points</div>
                    </div>

                    <div class="stat-card animate-slide-up animate-delay-2">
                        <div class="stat-icon" style="background: var(--secondary-gradient);">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="stat-value"><?php echo count($profile['badges']); ?></div>
                        <div class="stat-label">Badges Earned</div>
                    </div>

                    <div class="stat-card animate-slide-up animate-delay-3">
                        <div class="stat-icon" style="background: var(--success-gradient);">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div class="stat-value"><?php echo $profile['profile']['streak_days']; ?></div>
                        <div class="stat-label">Day Streak</div>
                    </div>

                    <div class="stat-card animate-slide-up animate-delay-3">
                        <div class="stat-icon" style="background: var(--warning-gradient);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-value">#<?php echo $userRank; ?></div>
                        <div class="stat-label">Your Rank</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Progress Section -->
    <section class="progress-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Your Progress</h2>
                <p class="section-subtitle">Track your journey to financial excellence</p>
            </div>

            <div class="progress-grid">
                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-title">Level Progress</span>
                        <span class="progress-value"><?php echo $levelProgress; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $levelProgress; ?>%;"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-title">Rank Progress</span>
                        <span class="progress-value">Top <?php echo $rankPercentile; ?>%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo $rankPercentile; ?>%; background: var(--success-gradient);"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <div class="progress-header">
                        <span class="progress-title">Achievement Progress</span>
                        <span class="progress-value"><?php echo count($achievements); ?>/10</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: <?php echo (count($achievements) / 10) * 100; ?>%; background: var(--warning-gradient);"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Achievements Section -->
    <section class="achievement-showcase">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Recent Achievements</h2>
                <p class="section-subtitle">Your latest accomplishments and milestones</p>
            </div>

            <?php if (empty($profile['badges'])): ?>
                <div class="empty-state">
                    <i class="fas fa-lock"></i>
                    <h3>No achievements yet</h3>
                    <p>Complete activities to unlock your first achievement!</p>
                </div>
            <?php else: ?>
                <div class="achievement-grid">
                    <?php foreach (array_slice($profile['badges'], 0, 8) as $badge): ?>
                        <div class="achievement-item unlocked">
                            <div class="achievement-icon"><?php echo $badge['icon']; ?></div>
                            <div class="achievement-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="achievement-points">+<?php echo $badge['points']; ?> pts</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Challenges Section -->
    <section class="challenges-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Active Challenges</h2>
                <p class="section-subtitle">Join challenges and compete with others</p>
            </div>

            <?php if (empty($userChallenges)): ?>
                <div class="empty-state">
                    <i class="fas fa-flag-checkered"></i>
                    <h3>No active challenges</h3>
                    <p>Check back soon for new challenges!</p>
                </div>
            <?php else: ?>
                <div class="challenge-list">
                    <?php foreach (array_slice($userChallenges, 0, 3) as $challenge): ?>
                        <div class="challenge-item">
                            <div class="challenge-header">
                                <div>
                                    <h3 class="challenge-title"><?php echo htmlspecialchars($challenge['title']); ?></h3>
                                    <p class="challenge-description"><?php echo htmlspecialchars($challenge['description']); ?></p>
                                </div>
                                <span class="challenge-status <?php echo $challenge['participation_status']; ?>">
                                    <?php echo ucfirst($challenge['participation_status']); ?>
                                </span>
                            </div>

                            <?php if ($challenge['participation_status'] === 'joined'): ?>
                                <div class="challenge-progress">
                                    <div class="progress-header">
                                        <span>Progress</span>
                                        <span><?php echo $challenge['user_progress']; ?> / <?php echo $challenge['target_value']; ?></span>
                                    </div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar-fill" style="width: <?php echo min(100, ($challenge['user_progress'] / $challenge['target_value']) * 100); ?>%;"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="joinChallenge(<?php echo $challenge['id']; ?>)">
                                    Join Challenge
                                </button>
                            <?php endif; ?>

                            <div class="challenge-rewards">
                                <span class="challenge-points">
                                    <i class="fas fa-coins"></i>
                                    <?php echo $challenge['points_reward']; ?> points
                                </span>
                                <span class="challenge-deadline">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M d', strtotime($challenge['end_date'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Rewards Preview -->
    <section class="rewards-preview">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Rewards Store</h2>
                <p class="section-subtitle">Redeem your points for amazing rewards</p>
            </div>

            <div class="rewards-grid">
                <?php foreach (array_slice($rewards, 0, 4) as $reward): ?>
                    <div class="reward-item <?php echo $reward['availability_status']; ?>">
                        <div class="reward-icon">
                            <?php
                            $iconMap = [
                                'discount' => 'fa-percentage',
                                'voucher' => 'fa-ticket',
                                'feature' => 'fa-star',
                                'service' => 'fa-concierge-bell',
                                'physical' => 'fa-gift'
                            ];
                            $icon = $iconMap[$reward['reward_type']] ?? 'fa-gift';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="reward-name"><?php echo htmlspecialchars($reward['name']); ?></div>
                        <div class="reward-cost">
                            <i class="fas fa-coins"></i>
                            <?php echo number_format($reward['points_cost']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-6">
                <a href="rewards.php" class="btn btn-primary btn-lg">
                    View All Rewards
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Leaderboard Section -->
    <section class="leaderboard-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Leaderboard</h2>
                <p class="section-subtitle">See how you rank among other users</p>
            </div>

            <div class="leaderboard-filters">
                <button class="filter-btn active" data-period="monthly">Monthly</button>
                <button class="filter-btn" data-period="weekly">Weekly</button>
                <button class="filter-btn" data-period="daily">Daily</button>
            </div>

            <div class="leaderboard-list">
                <?php foreach (array_slice($leaderboard, 0, 5) as $index => $user): ?>
                    <div class="leaderboard-item <?php echo $user['id'] == $userId ? 'current-user' : ''; ?>">
                        <div class="leaderboard-rank <?php echo $index < 3 ? 'top-3' : ''; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="leaderboard-user-info">
                            <div class="leaderboard-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="leaderboard-level"><?php echo ucfirst($user['level']['level_name']); ?></div>
                        </div>
                        <div class="leaderboard-points">
                            <?php echo number_format($user['total_points']); ?>
                            <div class="leaderboard-points-label">points</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-6">
                <a href="leaderboard.php" class="btn btn-secondary">
                    View Full Leaderboard
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- JavaScript -->
    <script src="../assets/js/gamification-dashboard.js"></script>
    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.animate-slide-up').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                observer.observe(el);
            });

            // Initialize real-time updates
            initializeRealTimeUpdates();
        });

        // Join challenge function
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
                    showToast('Success', 'Challenge joined successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error', data.message || 'Failed to join challenge', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error', 'An error occurred. Please try again.', 'error');
            });
        }

        // Show toast notification
        function showToast(title, message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-header">
                    <strong>${title}</strong>
                    <button type="button" class="toast-close" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;

            const container = document.getElementById('toastContainer');
            container.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // Initialize real-time updates
        function initializeRealTimeUpdates() {
            // WebSocket connection for real-time updates
            if (typeof WebSocket !== 'undefined') {
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const ws = new WebSocket(`${protocol}//${window.location.host}/ws/gamification`);

                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleRealTimeUpdate(data);
                };

                ws.onclose = function() {
                    // Reconnect after 5 seconds
                    setTimeout(initializeRealTimeUpdates, 5000);
                };
            }
        }

        // Handle real-time updates
        function handleRealTimeUpdate(data) {
            switch(data.type) {
                case 'points_awarded':
                    updatePointsDisplay(data.newTotal);
                    showToast('Points Earned!', `You earned ${data.points} points for ${data.reason}`, 'success');
                    break;
                case 'badge_earned':
                    showToast('Achievement Unlocked!', `You earned the "${data.badge.name}" badge!`, 'success');
                    break;
                case 'level_up':
                    showToast('Level Up!', `Congratulations! You've reached level ${data.newLevel}!`, 'success');
                    break;
                case 'challenge_completed':
                    showToast('Challenge Completed!', `You've completed "${data.challenge.title}"!`, 'success');
                    break;
            }
        }

        // Update points display
        function updatePointsDisplay(newTotal) {
            const pointsElements = document.querySelectorAll('#userPoints, .stat-value');
            pointsElements.forEach(el => {
                if (el.textContent.includes(',')) {
                    const current = parseInt(el.textContent.replace(/,/g, ''));
                    animateValue(el, current, newTotal, 1000);
                }
            });
        }

        // Animate number changes
        function animateValue(element, start, end, duration) {
            const range = end - start;
            const startTime = performance.now();

            function updateValue(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const value = Math.floor(start + range * progress);
                element.textContent = value.toLocaleString();

                if (progress < 1) {
                    requestAnimationFrame(updateValue);
                }
            }

            requestAnimationFrame(updateValue);
        }

        // Leaderboard filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const period = this.dataset.period;
                updateLeaderboard(period);
            });
        });

        // Update leaderboard
        function updateLeaderboard(period) {
            fetch(`../api/gamification.php?action=leaderboard&period=${period}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderLeaderboard(data.data);
                    }
                })
                .catch(error => {
                    console.error('Error updating leaderboard:', error);
                });
        }

        // Render leaderboard
        function renderLeaderboard(users) {
            const leaderboardList = document.querySelector('.leaderboard-list');
            const currentUserId = <?php echo $userId; ?>;

            leaderboardList.innerHTML = users.map((user, index) => `
                <div class="leaderboard-item ${user.id == currentUserId ? 'current-user' : ''}">
                    <div class="leaderboard-rank ${index < 3 ? 'top-3' : ''}">
                        ${index + 1}
                    </div>
                    <div class="leaderboard-user-info">
                        <div class="leaderboard-name">${user.name}</div>
                        <div class="leaderboard-level">${user.level.level_name}</div>
                    </div>
                    <div class="leaderboard-points">
                        ${parseInt(user.total_points).toLocaleString()}
                        <div class="leaderboard-points-label">points</div>
                    </div>
                </div>
            `).join('');
        }
    </script>
</body>
</html>