<?php
/**
 * Gamification Dashboard for JuaKali Lend
 * User interface for points, badges, challenges, and rewards
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
$rewardsHistory = $gamification->getUserRewardsHistory($userId, 20);

// Get user rank
$userRank = 1;
foreach ($leaderboard as $index => $user) {
    if ($user['id'] == $userId) {
        $userRank = $index + 1;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification Dashboard - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/animations.css" rel="stylesheet">
    <style>
        .level-badge {
            background: linear-gradient(135deg, var(--level-color) 0%, var(--level-color-dark) 100%);
        }

        .badge-card {
            transition: all 0.3s ease;
            transform-style: preserve-3d;
        }

        .badge-card:hover {
            transform: translateY(-5px) rotateY(5deg);
        }

        .progress-ring {
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        .streak-fire {
            animation: fire 1.5s ease-in-out infinite;
        }

        @keyframes fire {
            0%, 100% { transform: scale(1) rotate(0deg); }
            25% { transform: scale(1.1) rotate(-5deg); }
            75% { transform: scale(1.1) rotate(5deg); }
        }

        .points-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .7; }
        }

        .challenge-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .achievement-unlock {
            animation: slideInBounce 0.6s ease-out;
        }

        @keyframes slideInBounce {
            0% { transform: translateY(-100px); opacity: 0; }
            60% { transform: translateY(10px); opacity: 1; }
            80% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }

        .bronze { --level-color: #CD7F32; --level-color-dark: #8B5A2B; }
        .silver { --level-color: #C0C0C0; --level-color-dark: #808080; }
        .gold { --level-color: #FFD700; --level-color-dark: #DAA520; }
        .platinum { --level-color: #E5E4E2; --level-color-dark: #BCC6CC; }
        .diamond { --level-color: #B9F2FF; --level-color-dark: #87CEEB; }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-indigo-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="../dashboard.php" class="flex items-center space-x-3">
                        <i class="fas fa-arrow-left text-gray-600 hover:text-indigo-600"></i>
                        <span class="font-bold text-xl text-indigo-600">JuaKali Lend</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-coins text-yellow-500"></i>
                        <span class="font-semibold" id="quickPoints"><?php echo number_format($profile['profile']['total_points']); ?></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-fire text-orange-500 streak-fire"></i>
                        <span class="font-semibold"><?php echo $profile['profile']['streak_days']; ?> days</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Welcome Section with Level Progress -->
        <div class="bg-white rounded-2xl shadow-xl p-8 mb-8 achievement-unlock">
            <div class="flex flex-col lg:flex-row items-center justify-between">
                <div class="mb-6 lg:mb-0">
                    <h1 class="text-4xl font-bold text-gray-800 mb-2">
                        Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 🎉
                    </h1>
                    <p class="text-gray-600">Your journey to financial excellence continues</p>
                </div>

                <!-- Level Progress -->
                <div class="flex flex-col items-center">
                    <div class="relative">
                        <svg class="progress-ring" width="120" height="120">
                            <circle cx="60" cy="60" r="54" stroke="#e5e7eb" stroke-width="8" fill="none"/>
                            <circle cx="60" cy="60" r="54" stroke="url(#gradient)" stroke-width="8" fill="none"
                                    stroke-dasharray="<?php echo $profile['level_progress'] * 3.4; ?> 340"
                                    stroke-linecap="round"/>
                            <defs>
                                <linearGradient id="gradient">
                                    <stop offset="0%" stop-color="#8b5cf6"/>
                                    <stop offset="100%" stop-color="#3b82f6"/>
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold text-gray-800"><?php echo $profile['profile']['current_level']; ?></span>
                            <span class="text-xs text-gray-500"><?php echo $profile['next_level']['name'] ?? 'MAX'; ?></span>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <div class="level-badge text-white px-4 py-2 rounded-full text-sm font-semibold">
                            <?php echo ucfirst($profile['profile']['level_name']); ?> Level
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <?php echo number_format($profile['points_to_next_level']); ?> points to next level
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-xl p-6 text-white transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-yellow-100">Total Points</p>
                        <p class="text-3xl font-bold points-animation"><?php echo number_format($profile['profile']['total_points']); ?></p>
                    </div>
                    <i class="fas fa-coins text-4xl text-yellow-200"></i>
                </div>
            </div>

            <div class="bg-gradient-to-r from-purple-400 to-indigo-500 rounded-xl p-6 text-white transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100">Badges Earned</p>
                        <p class="text-3xl font-bold"><?php echo count($profile['badges']); ?></p>
                    </div>
                    <i class="fas fa-medal text-4xl text-purple-200"></i>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-400 to-blue-500 rounded-xl p-6 text-white transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100">Current Rank</p>
                        <p class="text-3xl font-bold">#<?php echo $userRank; ?></p>
                    </div>
                    <i class="fas fa-trophy text-4xl text-green-200"></i>
                </div>
            </div>

            <div class="bg-gradient-to-r from-red-400 to-pink-500 rounded-xl p-6 text-white transform hover:scale-105 transition-transform">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-100">Streak Days</p>
                        <p class="text-3xl font-bold"><?php echo $profile['profile']['streak_days']; ?> 🔥</p>
                    </div>
                    <i class="fas fa-fire text-4xl text-red-200 streak-fire"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Badges -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Your Badges</h2>
                        <button onclick="showAllBadges()" class="text-indigo-600 hover:text-indigo-800 font-semibold">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>

                    <?php if (empty($profile['badges'])): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-lock text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No badges earned yet. Complete activities to unlock your first badge!</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach (array_slice($profile['badges'], 0, 6) as $badge): ?>
                                <div class="badge-card bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg p-4 text-center cursor-pointer hover:shadow-lg">
                                    <div class="text-4xl mb-2"><?php echo $badge['icon']; ?></div>
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($badge['name']); ?></h3>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($badge['description']); ?></p>
                                    <div class="mt-2 text-xs text-indigo-600 font-semibold">
                                        +<?php echo $badge['points']; ?> pts
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Challenges -->
                <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-800">Active Challenges</h2>
                        <button onclick="refreshChallenges()" class="text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>

                    <?php if (empty($userChallenges)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-flag-checkered text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No active challenges right now. Check back soon!</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($userChallenges as $challenge): ?>
                                <div class="challenge-card rounded-lg p-4 text-white">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-bold text-lg"><?php echo htmlspecialchars($challenge['title']); ?></h3>
                                        <span class="bg-white bg-opacity-20 px-2 py-1 rounded text-xs">
                                            <?php echo $challenge['participation_status']; ?>
                                        </span>
                                    </div>
                                    <p class="text-sm opacity-90 mb-3"><?php echo htmlspecialchars($challenge['description']); ?></p>

                                    <?php if ($challenge['participation_status'] === 'joined'): ?>
                                        <div class="mb-2">
                                            <div class="flex justify-between text-sm mb-1">
                                                <span>Progress</span>
                                                <span><?php echo $challenge['user_progress']; ?> / <?php echo $challenge['target_value']; ?></span>
                                            </div>
                                            <div class="w-full bg-white bg-opacity-20 rounded-full h-2">
                                                <div class="bg-white rounded-full h-2 transition-all duration-500"
                                                     style="width: <?php echo min(100, ($challenge['user_progress'] / $challenge['target_value']) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <button onclick="joinChallenge(<?php echo $challenge['id']; ?>)"
                                                class="bg-white bg-opacity-20 hover:bg-opacity-30 px-4 py-2 rounded-lg font-semibold transition-colors">
                                            Join Challenge
                                        </button>
                                    <?php endif; ?>

                                    <div class="flex items-center justify-between mt-3 text-sm">
                                        <span><i class="fas fa-coins"></i> <?php echo $challenge['points_reward']; ?> pts</span>
                                        <span><i class="fas fa-clock"></i> <?php echo date('M d', strtotime($challenge['end_date'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Leaderboard -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Leaderboard</h2>
                    <div class="space-y-3">
                        <?php foreach (array_slice($leaderboard, 0, 5) as $index => $user): ?>
                            <div class="flex items-center justify-between p-2 rounded-lg <?php echo $user['id'] == $userId ? 'bg-indigo-50 border-2 border-indigo-200' : 'hover:bg-gray-50'; ?>">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold text-sm">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-sm"><?php echo htmlspecialchars($user['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo ucfirst($user['level']['level_name']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-sm"><?php echo number_format($user['total_points']); ?></p>
                                    <p class="text-xs text-gray-500">pts</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button onclick="showFullLeaderboard()" class="w-full mt-4 text-center text-indigo-600 hover:text-indigo-800 font-semibold text-sm">
                        View Full Leaderboard <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Recent Activity</h2>
                    <div class="space-y-3">
                        <?php foreach (array_slice($profile['recent_activity'], 0, 5) as $activity): ?>
                            <div class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-plus-circle text-green-500"></i>
                                    <div>
                                        <p class="text-sm font-medium"><?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></p>
                                    </div>
                                </div>
                                <span class="text-green-600 font-bold text-sm">+<?php echo $activity['points_earned']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Available Rewards -->
                <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Rewards Store</h2>
                    <div class="space-y-3">
                        <div class="bg-white rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-sm">5% Discount</h4>
                                    <p class="text-xs text-gray-500">On next loan interest</p>
                                </div>
                                <span class="text-orange-600 font-bold">500 pts</span>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-sm">Fee Waiver</h4>
                                    <p class="text-xs text-gray-500">Waive processing fees</p>
                                </div>
                                <span class="text-orange-600 font-bold">1000 pts</span>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg p-3 hover:shadow-md transition-shadow cursor-pointer">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-sm">Priority Support</h4>
                                    <p class="text-xs text-gray-500">1 month priority access</p>
                                </div>
                                <span class="text-orange-600 font-bold">750 pts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievement Notifications -->
        <div id="achievementNotifications" class="fixed bottom-4 right-4 space-y-2 z-50"></div>
    </div>

    <!-- Modals -->
    <div id="allBadgesModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-4xl w-full max-h-[80vh] overflow-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">All Badges</h2>
                    <button onclick="closeAllBadgesModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($profile['badges'] as $badge): ?>
                        <div class="text-center p-4 bg-gradient-to-br from-indigo-50 to-purple-50 rounded-lg">
                            <div class="text-5xl mb-3"><?php echo $badge['icon']; ?></div>
                            <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($badge['name']); ?></h3>
                            <p class="text-xs text-gray-600 mt-2"><?php echo htmlspecialchars($badge['description']); ?></p>
                            <div class="mt-3 text-sm text-indigo-600 font-semibold">
                                +<?php echo $badge['points']; ?> points
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                Earned <?php echo date('M j, Y', strtotime($badge['earned_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="leaderboardModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-2xl w-full max-h-[80vh] overflow-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Full Leaderboard</h2>
                    <button onclick="closeLeaderboardModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="space-y-2">
                    <?php foreach ($leaderboard as $index => $user): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg <?php echo $user['id'] == $userId ? 'bg-indigo-50 border-2 border-indigo-200' : 'hover:bg-gray-50'; ?>">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div>
                                    <p class="font-semibold"><?php echo htmlspecialchars($user['name']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo ucfirst($user['level']['level_name']); ?> • <?php echo $user['badges_count']; ?> badges</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold"><?php echo number_format($user['total_points']); ?></p>
                                <p class="text-sm text-gray-500">points</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

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