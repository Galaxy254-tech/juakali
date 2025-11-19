<?php
/**
 * Gamification Integration API
 * Integrates gamification system with loan processes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/loyalty-gamification.php';
require_once '../includes/auth.php';

// Start session for authentication
session_start();

// Initialize database and gamification system
$db = new Database();
$gamification = new LoyaltyGamificationSystem($db);

// Get authenticated user
function getAuthenticatedUser() {
    // Check session first
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }

    // Check API key
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        // Validate token and return user ID
        // This would implement JWT validation or similar
        return null; // Placeholder
    }

    return null;
}

$userId = getAuthenticatedUser();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get request action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_profile':
            handleGetProfile($userId, $gamification);
            break;

        case 'award_payment_points':
            handleAwardPaymentPoints($userId, $gamification, $db);
            break;

        case 'award_loan_points':
            handleAwardLoanPoints($userId, $gamification, $db);
            break;

        case 'award_referral_points':
            handleAwardReferralPoints($userId, $gamification, $db);
            break;

        case 'award_kyc_points':
            handleAwardKYCPoints($userId, $gamification);
            break;

        case 'redeem_reward':
            handleRedeemReward($userId, $gamification);
            break;

        case 'get_leaderboard':
            handleGetLeaderboard($gamification);
            break;

        case 'get_analytics':
            handleGetAnalytics($userId, $gamification);
            break;

        case 'update_streak':
            handleUpdateStreak($userId, $gamification);
            break;

        case 'check_achievements':
            handleCheckAchievements($userId, $gamification);
            break;

        case 'get_rewards_history':
            handleGetRewardsHistory($userId, $gamification);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
}

/**
 * Get user gamification profile
 */
function handleGetProfile($userId, $gamification) {
    $profile = $gamification->getUserGamificationProfile($userId);
    $stats = $gamification->getUserGamificationStats($userId);
    $rank = $gamification->getUserRank($userId, 'points');

    if (!$profile) {
        $profile = $gamification->initializeUserGamification($userId);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'profile' => $profile,
            'stats' => $stats,
            'rank' => $rank,
            'achievements' => json_decode($profile['achievements_unlocked'] ?: '[]', true),
            'badges' => json_decode($profile['badges_earned'] ?: '[]', true),
            'available_rewards' => $gamification->getAvailableRewards()
        ]
    ]);
}

/**
 * Award points for loan payments
 */
function handleAwardPaymentPoints($userId, $gamification, $db) {
    $loanId = $_POST['loan_id'] ?? null;
    $paymentAmount = floatval($_POST['payment_amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d H:i:s');
    $dueDate = $_POST['due_date'] ?? null;

    if (!$loanId || $paymentAmount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }

    // Check if loan exists and belongs to user
    $loan = $db->fetchOne("
        SELECT * FROM loans
        WHERE id = ? AND user_id = ? AND status = 'active'
    ", [$loanId, $userId]);

    if (!$loan) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Loan not found']);
        return;
    }

    // Check if points already awarded for this payment
    $existingAward = $db->fetchOne("
        SELECT 1 FROM points_transactions
        WHERE user_id = ? AND reason LIKE ?
        ORDER BY transaction_date DESC LIMIT 1
    ", [$userId, "payment_loan_{$loanId}%"]);

    if ($existingAward) {
        echo json_encode(['success' => false, 'message' => 'Points already awarded for this payment']);
        return;
    }

    // Calculate if payment is early or on-time
    $daysEarly = 0;
    $isEarly = false;
    $isOnTime = true;

    if ($dueDate) {
        $paymentDateTime = new DateTime($paymentDate);
        $dueDateTime = new DateTime($dueDate);

        if ($paymentDateTime < $dueDateTime) {
            $isEarly = true;
            $daysEarly = $dueDateTime->diff($paymentDateTime)->days;
        } elseif ($paymentDateTime > $dueDateTime) {
            $isOnTime = false;
        }
    }

    // Award points based on payment timing
    $result = [];
    if ($isEarly) {
        $result = $gamification->awardEarlyPaymentPoints($userId, $loanId, $paymentAmount, $daysEarly);
    } elseif ($isOnTime) {
        $result = $gamification->awardOnTimePaymentPoints($userId, $loanId, $paymentAmount);
    }

    // Update payment streak
    $gamification->updateUserStreak($userId, 'payment');

    // Check for new achievements and badges
    $gamification->checkAchievements($userId);
    $gamification->checkBadges($userId);

    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => $isEarly ? 'Early payment bonus awarded!' : 'On-time payment points awarded!'
    ]);
}

/**
 * Award points for loan activities
 */
function handleAwardLoanPoints($userId, $gamification, $db) {
    $loanAction = $_POST['loan_action'] ?? null; // 'applied', 'approved', 'disbursed'
    $loanId = $_POST['loan_id'] ?? null;
    $loanAmount = floatval($_POST['loan_amount'] ?? 0);

    if (!$loanAction || !$loanId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        return;
    }

    $pointsAwarded = 0;
    $description = '';

    switch ($loanAction) {
        case 'applied':
            $pointsAwarded = 10;
            $description = 'Loan application submitted';
            break;

        case 'approved':
            $pointsAwarded = 15;
            $description = 'Loan approved';
            break;

        case 'disbursed':
            $pointsAwarded = 20;
            $description = 'Loan disbursed';
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid loan action']);
            return;
    }

    // Award points
    $result = $gamification->awardPoints(
        $userId,
        $pointsAwarded,
        "loan_{$loanAction}",
        $description
    );

    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => "Loan activity points awarded: +{$pointsAwarded}"
    ]);
}

/**
 * Award points for successful referrals
 */
function handleAwardReferralPoints($userId, $gamification, $db) {
    $referredUserId = $_POST['referred_user_id'] ?? null;

    if (!$referredUserId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing referred user ID']);
        return;
    }

    // Check if referral exists and is valid
    $referral = $db->fetchOne("
        SELECT * FROM user_referrals
        WHERE referrer_id = ? AND referred_user_id = ? AND status = 'completed'
    ", [$userId, $referredUserId]);

    if (!$referral) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Referral not found or not completed']);
        return;
    }

    // Check if points already awarded
    $existingAward = $db->fetchOne("
        SELECT 1 FROM points_transactions
        WHERE user_id = ? AND reason = 'successful_referral'
        AND description LIKE ?
    ", [$userId, "%referred_user_{$referredUserId}%"]);

    if ($existingAward) {
        echo json_encode(['success' => false, 'message' => 'Referral points already awarded']);
        return;
    }

    $result = $gamification->awardReferralPoints($userId, $referredUserId);

    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => 'Referral bonus awarded!'
    ]);
}

/**
 * Award points for KYC completion
 */
function handleAwardKYCPoints($userId, $gamification) {
    // Check if user has completed KYC
    $kycStatus = $db->fetchOne("
        SELECT status FROM kyc_verifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ", [$userId]);

    if (!$kycStatus || $kycStatus['status'] !== 'verified') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'KYC not completed']);
        return;
    }

    // Check if points already awarded
    $existingAward = $db->fetchOne("
        SELECT 1 FROM points_transactions
        WHERE user_id = ? AND reason = 'kyc_completion'
    ", [$userId]);

    if ($existingAward) {
        echo json_encode(['success' => false, 'message' => 'KYC points already awarded']);
        return;
    }

    $result = $gamification->awardKYCCompletionPoints($userId);

    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => 'KYC completion bonus awarded!'
    ]);
}

/**
 * Redeem reward
 */
function handleRedeemReward($userId, $gamification) {
    $rewardId = $_POST['reward_id'] ?? null;

    if (!$rewardId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing reward ID']);
        return;
    }

    $result = $gamification->redeemPoints($userId, $rewardId);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'data' => $result,
            'message' => 'Reward redeemed successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
}

/**
 * Get leaderboard
 */
function handleGetLeaderboard($gamification) {
    $type = $_GET['type'] ?? 'points';
    $limit = intval($_GET['limit'] ?? 50);
    $period = $_GET['period'] ?? 'all_time';

    $leaderboard = $gamification->getLeaderboard($type, $limit, $period);

    echo json_encode([
        'success' => true,
        'data' => [
            'leaderboard' => $leaderboard,
            'type' => $type,
            'period' => $period,
            'total_users' => count($leaderboard)
        ]
    ]);
}

/**
 * Get gamification analytics
 */
function handleGetAnalytics($userId, $gamification) {
    $period = intval($_GET['period'] ?? 30);

    // Only allow admin users to get full analytics
    $userRole = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$userId])['role'] ?? 'user';

    if ($userRole !== 'admin') {
        // For regular users, return personal analytics only
        $profile = $gamification->getUserGamificationProfile($userId);
        $stats = $gamification->getUserGamificationStats($userId);

        echo json_encode([
            'success' => true,
            'data' => [
                'personal_stats' => $profile,
                'user_stats' => $stats,
                'rank' => $gamification->getUserRank($userId, 'points')
            ]
        ]);
        return;
    }

    $analytics = $gamification->getGamificationAnalytics($period);

    echo json_encode([
        'success' => true,
        'data' => $analytics
    ]);
}

/**
 * Update user streak
 */
function handleUpdateStreak($userId, $gamification) {
    $activityType = $_POST['activity_type'] ?? 'login';

    $newStreak = $gamification->updateUserStreak($userId, $activityType);

    echo json_encode([
        'success' => true,
        'data' => [
            'current_streak' => $newStreak,
            'activity_type' => $activityType
        ],
        'message' => 'Streak updated successfully!'
    ]);
}

/**
 * Check for new achievements and badges
 */
function handleCheckAchievements($userId, $gamification) {
    $newAchievements = $gamification->checkAchievements($userId);
    $newBadges = $gamification->checkBadges($userId);

    echo json_encode([
        'success' => true,
        'data' => [
            'new_achievements' => $newAchievements,
            'new_badges' => $newBadges,
            'total_new' => count($newAchievements) + count($newBadges)
        ],
        'message' => 'Achievements and badges checked!'
    ]);
}

/**
 * Get rewards redemption history
 */
function handleGetRewardsHistory($userId, $gamification) {
    $limit = intval($_GET['limit'] ?? 20);

    $history = $db->fetchAll("
        SELECT
            pr.*,
            DATE(pr.redemption_date) as redemption_date_formatted
        FROM points_redemptions pr
        WHERE pr.user_id = ?
        ORDER BY pr.redemption_date DESC
        LIMIT ?
    ", [$userId, $limit]);

    echo json_encode([
        'success' => true,
        'data' => [
            'history' => $history,
            'total_count' => count($history)
        ]
    ]);
}

?>