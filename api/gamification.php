<?php
/**
 * Gamification API Endpoints
 * Handles all gamification-related API requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/auth.php';
require_once '../includes/gamification-system.php';
require_once '../includes/database.php';

// Start session for authentication
session_start();

// API response helper
function apiResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Validate authentication
function validateAuth() {
    if (!isLoggedIn()) {
        apiResponse(false, null, 'Authentication required', 401);
    }
    return $_SESSION['user_id'];
}

// Parse JSON input
function getJSONInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

// Main API router
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            handleGetRequests($action);
            break;

        case 'POST':
            handlePostRequests($action);
            break;

        case 'PUT':
            handlePutRequests($action);
            break;

        default:
            apiResponse(false, null, 'Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log('Gamification API Error: ' . $e->getMessage());
    apiResponse(false, null, 'Internal server error', 500);
}

// Handle GET requests
function handleGetRequests($action) {
    $userId = validateAuth();
    $gamification = new GamificationSystem($userId);

    switch ($action) {
        case 'profile':
            $profile = $gamification->getUserGamificationProfile($userId);
            apiResponse(true, $profile);
            break;

        case 'leaderboard':
            $period = $_GET['period'] ?? 'monthly';
            $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
            $leaderboard = $gamification->getLeaderboard('points', $period, $limit);
            apiResponse(true, $leaderboard);
            break;

        case 'challenges':
            $challenges = $gamification->getUserChallenges($userId);
            apiResponse(true, $challenges);
            break;

        case 'rewards_history':
            $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
            $history = $gamification->getUserRewardsHistory($userId, $limit);
            apiResponse(true, $history);
            break;

        case 'stats':
            $stats = $gamification->getUserStats($userId);
            apiResponse(true, $stats);
            break;

        case 'achievements':
            $achievements = $gamification->getUserAchievements($userId);
            apiResponse(true, $achievements);
            break;

        case 'available_rewards':
            $rewards = $gamification->getAvailableRewards($userId);
            apiResponse(true, $rewards);
            break;

        case 'challenge_details':
            $challengeId = intval($_GET['challenge_id'] ?? 0);
            if (!$challengeId) {
                apiResponse(false, null, 'Challenge ID required', 400);
            }
            $details = $gamification->getChallengeDetails($challengeId, $userId);
            apiResponse(true, $details);
            break;

        default:
            apiResponse(false, null, 'Invalid action', 400);
    }
}

// Handle POST requests
function handlePostRequests($action) {
    $userId = validateAuth();
    $gamification = new GamificationSystem($userId);
    $data = getJSONInput();

    switch ($action) {
        case 'award_points':
            $requiredFields = ['action_type', 'points'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    apiResponse(false, null, "Missing required field: {$field}", 400);
                }
            }

            $pointsAwarded = $gamification->awardPoints(
                $userId,
                $data['action_type'],
                $data['metadata'] ?? []
            );

            if ($pointsAwarded) {
                // Trigger real-time notification
                triggerRealtimeUpdate($userId, [
                    'type' => 'points_awarded',
                    'points' => $pointsAwarded,
                    'reason' => $data['action_type'],
                    'newTotal' => $gamification->getUserGamificationProfile($userId)['profile']['total_points']
                ]);

                apiResponse(true, [
                    'points_awarded' => $pointsAwarded,
                    'action_type' => $data['action_type']
                ], 'Points awarded successfully');
            } else {
                apiResponse(false, null, 'Failed to award points', 500);
            }

        case 'join_challenge':
            $challengeId = intval($data['challenge_id'] ?? 0);
            if (!$challengeId) {
                apiResponse(false, null, 'Challenge ID required', 400);
            }

            $result = $gamification->joinChallenge($userId, $challengeId);
            if ($result) {
                apiResponse(true, ['challenge_id' => $challengeId], 'Challenge joined successfully');
            } else {
                apiResponse(false, null, 'Failed to join challenge', 500);
            }

        case 'redeem_reward':
            $rewardId = intval($data['reward_id'] ?? 0);
            if (!$rewardId) {
                apiResponse(false, null, 'Reward ID required', 400);
            }

            $result = $gamification->redeemReward($userId, $rewardId);
            if ($result['success']) {
                apiResponse(true, $result, 'Reward redeemed successfully');
            } else {
                apiResponse(false, null, $result['message'] ?? 'Failed to redeem reward', 400);
            }

        case 'create_challenge':
            // Admin only
            if (!isAdmin()) {
                apiResponse(false, null, 'Admin access required', 403);
            }

            $requiredFields = ['title', 'description', 'challenge_type', 'target_value', 'points_reward'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    apiResponse(false, null, "Missing required field: {$field}", 400);
                }
            }

            $challengeId = $gamification->createChallenge($data);
            if ($challengeId) {
                apiResponse(true, ['challenge_id' => $challengeId], 'Challenge created successfully');
            } else {
                apiResponse(false, null, 'Failed to create challenge', 500);
            }

        case 'trigger_achievement':
            // For testing and manual triggers
            $achievementType = $data['achievement_type'] ?? '';
            if (!$achievementType) {
                apiResponse(false, null, 'Achievement type required', 400);
            }

            $result = $gamification->triggerAchievement($userId, $achievementType, $data['metadata'] ?? []);
            apiResponse(true, $result, 'Achievement triggered');

        default:
            apiResponse(false, null, 'Invalid action', 400);
    }
}

// Handle PUT requests
function handlePutRequests($action) {
    $userId = validateAuth();
    $gamification = new GamificationSystem($userId);
    $data = getJSONInput();

    switch ($action) {
        case 'update_progress':
            $challengeId = intval($data['challenge_id'] ?? 0);
            $progress = intval($data['progress'] ?? 0);

            if (!$challengeId) {
                apiResponse(false, null, 'Challenge ID required', 400);
            }

            $result = $gamification->updateChallengeProgress($userId, $challengeId, $progress);
            apiResponse(true, $result, 'Progress updated');

        case 'claim_reward':
            $rewardId = intval($data['reward_id'] ?? 0);
            if (!$rewardId) {
                apiResponse(false, null, 'Reward ID required', 400);
            }

            $result = $gamification->claimReward($userId, $rewardId);
            if ($result['success']) {
                apiResponse(true, $result, 'Reward claimed successfully');
            } else {
                apiResponse(false, null, $result['message'] ?? 'Failed to claim reward', 400);
            }

        default:
            apiResponse(false, null, 'Invalid action', 400);
    }
}

// Real-time notification function
function triggerRealtimeUpdate($userId, $data) {
    // In a real implementation, this would use WebSocket or Server-Sent Events
    // For now, we'll store in a notification queue
    $db = Database::getInstance();

    $db->execute("
        INSERT INTO realtime_notifications (
            user_id, notification_type, data, created_at
        ) VALUES (?, ?, ?, NOW())
    ", [
        $userId,
        $data['type'],
        json_encode($data)
    ]);
}

// Admin check function
function isAdmin() {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin';
}

// WebSocket notification endpoint for real-time updates
if ($action === 'notifications') {
    $userId = validateAuth();
    $db = Database::getInstance();

    // Get pending notifications
    $notifications = $db->fetchAll("
        SELECT * FROM realtime_notifications
        WHERE user_id = ? AND delivered = 0
        ORDER BY created_at ASC
        LIMIT 10
    ", [$userId]);

    // Mark notifications as delivered
    if (!empty($notifications)) {
        $notificationIds = array_column($notifications, 'id');
        $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';

        $db->execute("
            UPDATE realtime_notifications
            SET delivered = 1, delivered_at = NOW()
            WHERE id IN ($placeholders)
        ", $notificationIds);
    }

    // Return notifications
    $notificationData = array_map(function($notification) {
        return json_decode($notification['data'], true);
    }, $notifications);

    apiResponse(true, $notificationData);
}

// Analytics endpoint (admin only)
if ($action === 'analytics') {
    if (!isAdmin()) {
        apiResponse(false, null, 'Admin access required', 403);
    }

    $dateRange = intval($_GET['date_range'] ?? 30);
    $gamification = new GamificationSystem();
    $analytics = $gamification->getGamificationAnalytics($dateRange);

    apiResponse(true, $analytics);
}

// Export data endpoint
if ($action === 'export') {
    $userId = validateAuth();
    $format = $_GET['format'] ?? 'json';

    $gamification = new GamificationSystem();

    switch ($format) {
        case 'csv':
            $csv = $gamification->exportUserDataCSV($userId);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="gamification_data.csv"');
            echo $csv;
            exit;

        case 'pdf':
            $pdf = $gamification->exportUserDataPDF($userId);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="gamification_report.pdf"');
            echo $pdf;
            exit;

        default:
            $data = $gamification->exportUserDataJSON($userId);
            apiResponse(true, $data);
    }
}

// Health check endpoint
if ($action === 'health') {
    $db = Database::getInstance();

    $health = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => $db->isConnected() ? 'connected' : 'disconnected',
        'version' => '1.0.0',
        'uptime' => time() - ($GLOBALS['start_time'] ?? time())
    ];

    apiResponse(true, $health);
}

?>
</html>
</body>
</html>