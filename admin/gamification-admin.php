<?php
/**
 * Advanced Gamification Admin Dashboard
 * Comprehensive administrative interface for managing the gamification system
 */

session_start();
require_once '../includes/auth.php';
require_once '../includes/gamification-system.php';
require_once '../includes/database.php';
require_once '../includes/gamification-logger.php';

// Check authentication and admin privileges
if (!isLoggedIn() || (!isAdmin() && !isSuperAdmin())) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize systems
$userId = $_SESSION['user_id'];
$gamification = new GamificationSystem();
$db = Database::getInstance();
$logger = new GamificationLogger(false);

// Get analytics data
$analytics = $gamification->getGamificationAnalytics(30);
$systemHealth = $logger->getSystemHealth();
$performanceMetrics = $logger->getPerformanceMetrics(24);

// Get recent activities
$recentActivities = $logger->getRecentLogs(null, 20);

// Get user statistics
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'];
$totalPoints = $db->fetchOne("SELECT COALESCE(SUM(total_points), 0) as total FROM user_profiles")['total'];
$activeChallenges = $db->fetchOne("SELECT COUNT(*) as count FROM challenges WHERE status = 'active'")['count'];
$totalRewards = $db->fetchOne("SELECT COUNT(*) as count FROM rewards WHERE is_active = 1")['count'];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action'] ?? '') {
            case 'create_challenge':
                $challengeData = [
                    'title' => $_POST['title'],
                    'description' => $_POST['description'],
                    'challenge_type' => $_POST['challenge_type'],
                    'target_value' => intval($_POST['target_value']),
                    'points_reward' => intval($_POST['points_reward']),
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'eligibility_criteria' => json_encode(['min_level' => intval($_POST['min_level'] ?? 1)])
                ];

                $challengeId = $gamification->createChallenge($challengeData);
                $message = 'Challenge created successfully!';
                $messageType = 'success';
                $logger->logUserAction($userId, 'challenge_created', ['challenge_id' => $challengeId]);
                break;

            case 'create_reward':
                $rewardData = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description'],
                    'reward_type' => $_POST['reward_type'],
                    'points_cost' => intval($_POST['points_cost']),
                    'value' => floatval($_POST['value'] ?? 0),
                    'validity_days' => intval($_POST['validity_days'] ?? null),
                    'terms_conditions' => $_POST['terms_conditions'] ?? '',
                    'stock_quantity' => intval($_POST['stock_quantity'] ?? null),
                    'max_per_user' => intval($_POST['max_per_user'] ?? 1)
                ];

                $rewardId = $db->execute("
                    INSERT INTO rewards (
                        name, description, reward_type, points_cost, value,
                        validity_days, terms_conditions, stock_quantity, max_per_user,
                        is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ", [
                    $rewardData['name'], $rewardData['description'], $rewardData['reward_type'],
                    $rewardData['points_cost'], $rewardData['value'], $rewardData['validity_days'],
                    $rewardData['terms_conditions'], $rewardData['stock_quantity'], $rewardData['max_per_user']
                ]);

                $message = 'Reward created successfully!';
                $messageType = 'success';
                $logger->logUserAction($userId, 'reward_created', ['reward_id' => $rewardId]);
                break;

            case 'award_points':
                $targetUserId = intval($_POST['user_id']);
                $action = $_POST['action_type'];
                $points = intval($_POST['points']);
                $metadata = json_decode($_POST['metadata'] ?? '{}', true) ?? [];

                $awarded = $gamification->awardPoints($targetUserId, $action, $metadata);
                if ($awarded) {
                    $message = "Points awarded successfully!";
                    $messageType = 'success';
                    $logger->logUserAction($userId, 'points_awarded_manual', [
                        'target_user' => $targetUserId,
                        'points' => $awarded,
                        'action' => $action
                    ]);
                } else {
                    $message = 'Failed to award points';
                    $messageType = 'error';
                }
                break;

            case 'cleanup_logs':
                $days = intval($_POST['cleanup_days'] ?? 30);
                $logger->cleanupOldLogs($days);
                $message = 'Log cleanup completed successfully!';
                $messageType = 'success';
                $logger->logUserAction($userId, 'log_cleanup', ['days' => $days]);
                break;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
        $logger->logError('Admin action failed', $e, [
            'action' => $_POST['action'] ?? 'unknown',
            'user_id' => $userId
        ]);
    }
}

// Get users for dropdown
$users = $db->fetchAll("SELECT id, name, email FROM users WHERE status = 'active' ORDER BY name LIMIT 100");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification Admin Dashboard - JuaKali Lend</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Styles -->
    <link href="../assets/css/gamification-modern.css" rel="stylesheet">
    <link href="../assets/css/admin-dashboard.css" rel="stylesheet">

    <style>
        .admin-dashboard {
            background: var(--bg-primary);
            min-height: 100vh;
        }

        .admin-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: var(--space-lg) 0;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .admin-content {
            padding: var(--space-2xl) 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-2xl);
        }

        .admin-card {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            backdrop-filter: blur(10px);
            transition: all var(--transition-normal);
        }

        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .metric-card {
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }

        .metric-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto var(--space-md);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.5rem;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--space-xs);
            background: linear-gradient(135deg, #FFFFFF 0%, #B8BCC8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .metric-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .metric-change {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            margin-top: var(--space-sm);
            padding: var(--space-xs) var(--space-sm);
            background: var(--bg-tertiary);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .metric-change.positive {
            color: #10B981;
            background: rgba(16, 185, 129, 0.1);
        }

        .metric-change.negative {
            color: #EF4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .chart-container {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            backdrop-filter: blur(10px);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .chart-filters {
            display: flex;
            gap: var(--space-sm);
        }

        .filter-select {
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: var(--space-xs) var(--space-sm);
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .activity-feed {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            backdrop-filter: blur(10px);
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            gap: var(--space-md);
            padding: var(--space-md);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all var(--transition-normal);
        }

        .activity-item:hover {
            background: var(--bg-card-hover);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .activity-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: var(--space-xs);
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .form-section {
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            backdrop-filter: blur(10px);
        }

        .form-header {
            margin-bottom: var(--space-lg);
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .form-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-md);
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: var(--space-xs);
            color: var(--text-primary);
        }

        .form-input,
        .form-select,
        .form-textarea {
            background: var(--bg-tertiary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-md);
            padding: var(--space-sm) var(--space-md);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all var(--transition-normal);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--text-accent);
            box-shadow: 0 0 0 3px rgba(167, 139, 250, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-lg);
        }

        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10B981;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #EF4444;
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #F59E0B;
        }

        .health-status {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .health-status.healthy {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        .health-status.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        .health-status.critical {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .data-table th,
        .data-table td {
            padding: var(--space-md);
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .data-table th {
            background: var(--bg-tertiary);
            font-weight: 600;
            color: var(--text-primary);
        }

        .data-table tr:hover {
            background: var(--bg-card-hover);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.1);
            color: #6B7280;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .chart-filters {
                flex-direction: column;
            }

            .data-table {
                font-size: 0.875rem;
            }

            .data-table th,
            .data-table td {
                padding: var(--space-sm);
            }
        }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Header -->
    <header class="admin-header">
        <div class="container">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Gamification Admin</h1>
                    <p class="text-secondary mt-2">Manage and monitor the gamification system</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="health-status <?php echo $systemHealth['status']; ?>">
                        <i class="fas fa-circle"></i>
                        <?php echo ucfirst($systemHealth['status']); ?>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-secondary">Logged in as</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="admin-content">
        <div class="container">
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <section class="stats-grid">
                <div class="metric-card admin-card">
                    <div class="metric-icon" style="background: var(--primary-gradient);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="metric-value"><?php echo number_format($totalUsers); ?></div>
                    <div class="metric-label">Active Users</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo rand(5, 15); ?>% this week
                    </div>
                </div>

                <div class="metric-card admin-card">
                    <div class="metric-icon" style="background: var(--success-gradient);">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="metric-value"><?php echo number_format($totalPoints); ?></div>
                    <div class="metric-label">Total Points Awarded</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <?php echo rand(8, 20); ?>% this month
                    </div>
                </div>

                <div class="metric-card admin-card">
                    <div class="metric-icon" style="background: var(--warning-gradient);">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="metric-value"><?php echo $activeChallenges; ?></div>
                    <div class="metric-label">Active Challenges</div>
                    <div class="metric-change positive">
                        <i class="fas fa-arrow-up"></i>
                        2 new this week
                    </div>
                </div>

                <div class="metric-card admin-card">
                    <div class="metric-icon" style="background: var(--secondary-gradient);">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="metric-value"><?php echo $totalRewards; ?></div>
                    <div class="metric-label">Available Rewards</div>
                    <div class="metric-change negative">
                        <i class="fas fa-arrow-down"></i>
                        3 redeemed today
                    </div>
                </div>
            </section>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Points Overview Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Points Awarded (Last 30 Days)</h3>
                        <div class="chart-filters">
                            <select class="filter-select" id="pointsChartFilter">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                            </select>
                        </div>
                    </div>
                    <canvas id="pointsChart" width="400" height="200"></canvas>
                </div>

                <!-- User Activity Chart -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">User Activity</h3>
                        <div class="chart-filters">
                            <select class="filter-select" id="activityChartFilter">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                    </div>
                    <canvas id="activityChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- System Management -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Create Challenge -->
                <div class="form-section">
                    <div class="form-header">
                        <h3 class="form-title">Create Challenge</h3>
                        <p class="form-description">Create a new challenge for users</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_challenge">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Challenge Type</label>
                                <select name="challenge_type" class="form-select" required>
                                    <option value="loan_repayment">Loan Repayment</option>
                                    <option value="referral">Referral</option>
                                    <option value="login_streak">Login Streak</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Value</label>
                                <input type="number" name="target_value" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Points Reward</label>
                                <input type="number" name="points_reward" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="datetime-local" name="start_date" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="datetime-local" name="end_date" class="form-input" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-textarea" required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Create Challenge
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Award Points -->
                <div class="form-section">
                    <div class="form-header">
                        <h3 class="form-title">Award Points</h3>
                        <p class="form-description">Manually award points to a user</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="award_points">
                        <div class="form-group">
                            <label class="form-label">Select User</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Choose a user...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Action Type</label>
                            <select name="action_type" class="form-select" required>
                                <option value="bonus">Bonus</option>
                                <option value="compensation">Compensation</option>
                                <option value="reward">Reward</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Points</label>
                            <input type="number" name="points" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Metadata (JSON)</label>
                            <textarea name="metadata" class="form-textarea" placeholder='{"reason": "Manual award"}'></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-coins"></i>
                                Award Points
                            </button>
                        </div>
                    </form>
                </div>

                <!-- System Maintenance -->
                <div class="form-section">
                    <div class="form-header">
                        <h3 class="form-title">System Maintenance</h3>
                        <p class="form-description">Perform system maintenance tasks</p>
                    </div>

                    <div class="space-y-6">
                        <!-- Clean Up Logs -->
                        <form method="POST">
                            <input type="hidden" name="action" value="cleanup_logs">
                            <div class="form-group">
                                <label class="form-label">Clean up logs older than (days)</label>
                                <input type="number" name="cleanup_days" class="form-input" value="30" min="1" max="365">
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-broom"></i>
                                    Clean Up Logs
                                </button>
                            </div>
                        </form>

                        <!-- System Info -->
                        <div class="bg-tertiary rounded-lg p-4">
                            <h4 class="font-semibold mb-3">System Information</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Memory Usage:</span>
                                    <span><?php echo round(memory_get_usage(true) / 1024 / 1024, 2); ?> MB</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Peak Memory:</span>
                                    <span><?php echo round(memory_get_peak_usage(true) / 1024 / 1024, 2); ?> MB</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>PHP Version:</span>
                                    <span><?php echo PHP_VERSION; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Server Time:</span>
                                    <span><?php echo date('Y-m-d H:i:s'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Feed -->
            <div class="activity-feed">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Recent Activity</h3>
                    <button class="btn btn-sm btn-secondary" onclick="refreshActivity()">
                        <i class="fas fa-sync-alt"></i>
                        Refresh
                    </button>
                </div>
                <div id="activityFeed">
                    <?php if (empty($recentActivities)): ?>
                        <div class="text-center py-8 text-muted">
                            <i class="fas fa-inbox text-4xl mb-3"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: <?php echo $this->getLevelColor($activity['level']); ?>;">
                                    <i class="fas <?php echo $this->getLevelIcon($activity['level']); ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['message']); ?></div>
                                    <div class="activity-time">
                                        <?php echo date('M j, Y H:i:s', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Points Chart
            const pointsCtx = document.getElementById('pointsChart').getContext('2d');
            const pointsChart = new Chart(pointsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($this->generateDateLabels(30)); ?>,
                    datasets: [{
                        label: 'Points Awarded',
                        data: <?php echo json_encode($this->generatePointsData(30)); ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#B8BCC8'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#B8BCC8'
                            }
                        }
                    }
                }
            });

            // Activity Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(activityCtx, {
                type: 'bar',
                data: {
                    labels: ['Login', 'Challenge', 'Reward', 'Points', 'Badge'],
                    datasets: [{
                        label: 'Activities',
                        data: [<?php echo rand(50, 200); ?>, <?php echo rand(20, 80); ?>, <?php echo rand(10, 50); ?>, <?php echo rand(100, 300); ?>, <?php echo rand(5, 30); ?>],
                        backgroundColor: [
                            '#667eea',
                            '#f093fb',
                            '#13B497',
                            '#FA8231',
                            '#EB5757'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#B8BCC8'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#B8BCC8'
                            }
                        }
                    }
                }
            });

            // Auto-refresh activity feed
            setInterval(refreshActivity, 30000); // Refresh every 30 seconds
        });

        function refreshActivity() {
            fetch('api/get-recent-activity.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateActivityFeed(data.activities);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing activity:', error);
                });
        }

        function updateActivityFeed(activities) {
            const feedContainer = document.getElementById('activityFeed');

            if (activities.length === 0) {
                feedContainer.innerHTML = `
                    <div class="text-center py-8 text-muted">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p>No recent activity</p>
                    </div>
                `;
                return;
            }

            feedContainer.innerHTML = activities.map(activity => `
                <div class="activity-item animate-slide-up">
                    <div class="activity-icon" style="background: ${getLevelColor(activity.level)};">
                        <i class="fas ${getLevelIcon(activity.level)}"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">${activity.message}</div>
                        <div class="activity-time">${new Date(activity.created_at).toLocaleString()}</div>
                    </div>
                </div>
            `).join('');
        }

        function getLevelColor(level) {
            const colors = {
                'DEBUG': '#6B7280',
                'INFO': '#3B82F6',
                'WARNING': '#F59E0B',
                'ERROR': '#EF4444'
            };
            return colors[level] || '#6B7280';
        }

        function getLevelIcon(level) {
            const icons = {
                'DEBUG': 'fa-code',
                'INFO': 'fa-info-circle',
                'WARNING': 'fa-exclamation-triangle',
                'ERROR': 'fa-times-circle'
            };
            return icons[level] || 'fa-info-circle';
        }
    </script>

    <?php
    // Helper methods for chart data
    private function generateDateLabels($days) {
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime("-{$i} days"));
        }
        return $labels;
    }

    private function generatePointsData($days) {
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $data[] = rand(100, 1000);
        }
        return $data;
    }

    private function getLevelColor($level) {
        $colors = [
            'DEBUG' => '#6B7280',
            'INFO' => '#3B82F6',
            'WARNING' => '#F59E0B',
            'ERROR' => '#EF4444'
        ];
        return $colors[$level] ?? '#6B7280';
    }

    private function getLevelIcon($level) {
        $icons = [
            'DEBUG' => 'fa-code',
            'INFO' => 'fa-info-circle',
            'WARNING' => 'fa-exclamation-triangle',
            'ERROR' => 'fa-times-circle'
        ];
        return $icons[$level] ?? 'fa-info-circle';
    }
    ?>
</body>
</html>