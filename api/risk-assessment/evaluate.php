<?php
/**
 * Risk Assessment API Endpoint
 * Comprehensive risk evaluation for users and transactions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../integrations/credit-scoring.php';

try {
    // Initialize database connection
    $db = Database::getInstance();

    // Initialize credit scoring engine
    $creditEngine = new CreditScoringEngine($db);

    $user_id = null;
    $transaction_data = [];

    // Handle different request methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get existing risk assessment for a user
            $user_id = $_GET['user_id'] ?? null;
            if (!$user_id) {
                throw new Exception('User ID is required for GET requests');
            }
            break;

        case 'POST':
            // Create new risk assessment
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }

            $user_id = $input['user_id'] ?? null;
            $transaction_data = $input['transaction_data'] ?? [];

            if (!$user_id) {
                throw new Exception('User ID is required');
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }

    // Verify user exists
    $db->query("SELECT id, role, status, email, phone FROM users WHERE id = ?");
    $db->bind(':id', $user_id);
    $user = $db->single();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Perform comprehensive risk assessment
    $credit_report = $creditEngine->getCreditReport($user_id);

    // Additional risk factors
    $additional_risks = assessAdditionalRiskFactors($db, $user_id, $transaction_data);

    // Calculate overall risk score
    $overall_risk_score = calculateOverallRiskScore($credit_report, $additional_risks);

    // Generate risk mitigation strategies
    $mitigation_strategies = generateMitigationStrategies($overall_risk_score, $credit_report, $additional_risks);

    // Compile comprehensive risk assessment
    $risk_assessment = [
        'user_id' => $user_id,
        'user_info' => [
            'role' => $user['role'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'status' => $user['status']
        ],
        'overall_risk_score' => $overall_risk_score,
        'risk_level' => determineRiskLevel($overall_risk_score),
        'credit_analysis' => $credit_report,
        'additional_risks' => $additional_risks,
        'mitigation_strategies' => $mitigation_strategies,
        'assessment_date' => date('Y-m-d H:i:s'),
        'next_review_date' => $credit_report['next_review_date'],
        'recommendations' => generateRiskRecommendations($overall_risk_score, $credit_report)
    ];

    // Save risk assessment to database
    saveRiskAssessment($db, $risk_assessment);

    // Log the assessment
    $db->query("
        INSERT INTO audit_logs (
            user_id, action, entity_type, entity_id,
            new_values, ip_address, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $db->bind(':user_id', $user_id);
    $db->bind(':action', 'risk_assessment_completed');
    $db->bind(':entity_type', 'risk_assessments');
    $db->bind(':entity_id', $user_id);
    $db->bind(':new_values', json_encode([
        'risk_score' => $overall_risk_score,
        'risk_level' => $risk_assessment['risk_level'],
        'credit_score' => $credit_report['credit_score']['score']
    ]));
    $db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $db->execute();

    // Return response
    echo json_encode([
        'success' => true,
        'data' => $risk_assessment,
        'message' => 'Risk assessment completed successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'RISK_ASSESSMENT_ERROR'
    ]);
}

/**
 * Assess additional risk factors beyond credit scoring
 */
function assessAdditionalRiskFactors($db, $user_id, $transaction_data) {
    $risks = [];

    // 1. Account age risk
    $db->query("SELECT DATEDIFF(NOW(), created_at) as days_active FROM users WHERE id = ?");
    $db->bind(':id', $user_id);
    $user_age = $db->single();

    $account_age_risk = 0;
    if ($user_age['days_active'] < 30) {
        $account_age_risk = 30;
    } elseif ($user_age['days_active'] < 90) {
        $account_age_risk = 15;
    } elseif ($user_age['days_active'] < 365) {
        $account_age_risk = 5;
    }

    $risks['account_age'] = [
        'risk_score' => $account_age_risk,
        'days_active' => $user_age['days_active'],
        'assessment' => $account_age_risk > 20 ? 'high' : ($account_age_risk > 10 ? 'medium' : 'low')
    ];

    // 2. Verification status risk
    $db->query("SELECT kyc_verified, business_registration FROM users WHERE id = ?");
    $db->bind(':id', $user_id);
    $verification = $db->single();

    $verification_risk = 0;
    if (!$verification['kyc_verified']) {
        $verification_risk += 25;
    }
    if (!$verification['business_registration']) {
        $verification_risk += 15;
    }

    $risks['verification'] = [
        'risk_score' => $verification_risk,
        'kyc_verified' => $verification['kyc_verified'],
        'business_registered' => $verification['business_registration'],
        'assessment' => $verification_risk > 20 ? 'high' : ($verification_risk > 10 ? 'medium' : 'low')
    ];

    // 3. Payment history risk
    $db->query("
        SELECT COUNT(*) as total_payments,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
               SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        WHERE l.retailer_id = ?
    ");
    $db->bind(':retailer_id', $user_id);
    $payment_history = $db->single();

    $payment_risk = 0;
    if ($payment_history['total_payments'] > 0) {
        $failure_rate = $payment_history['failed_payments'] / $payment_history['total_payments'];
        $payment_risk = $failure_rate * 50;
    } elseif ($payment_history['total_payments'] == 0) {
        $payment_risk = 10; // Slight risk for no payment history
    }

    $risks['payment_history'] = [
        'risk_score' => $payment_risk,
        'total_payments' => $payment_history['total_payments'],
        'failure_rate' => $payment_history['total_payments'] > 0 ?
            ($payment_history['failed_payments'] / $payment_history['total_payments']) * 100 : 0,
        'assessment' => $payment_risk > 25 ? 'high' : ($payment_risk > 10 ? 'medium' : 'low')
    ];

    // 4. Dispute history risk
    $db->query("
        SELECT COUNT(*) as total_disputes,
               SUM(CASE WHEN status = 'resolved' AND resolution LIKE '%favor%' THEN 1 ELSE 0 END) as unfavorable_resolutions
        FROM disputes
        WHERE initiator_id = ? OR respondent_id = ?
    ");
    $db->bind(':initiator_id', $user_id);
    $db->bind(':respondent_id', $user_id);
    $dispute_history = $db->single();

    $dispute_risk = 0;
    if ($dispute_history['total_disputes'] > 0) {
        $unfavorable_rate = $dispute_history['unfavorable_resolutions'] / $dispute_history['total_disputes'];
        $dispute_risk = $dispute_history['total_disputes'] * 5 + ($unfavorable_rate * 30);
    }

    $risks['dispute_history'] = [
        'risk_score' => $dispute_risk,
        'total_disputes' => $dispute_history['total_disputes'],
        'unfavorable_resolutions' => $dispute_history['unfavorable_resolutions'],
        'assessment' => $dispute_risk > 20 ? 'high' : ($dispute_risk > 10 ? 'medium' : 'low')
    ];

    // 5. Recent activity risk
    $db->query("
        SELECT COUNT(*) as recent_logins,
               COUNT(DISTINCT ip_address) as unique_ips
        FROM sessions
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $db->bind(':user_id', $user_id);
    $recent_activity = $db->single();

    $activity_risk = 0;
    if ($recent_activity['unique_ips'] > 3) {
        $activity_risk += 15;
    }
    if ($recent_activity['recent_logins'] > 50) {
        $activity_risk += 10;
    }

    $risks['recent_activity'] = [
        'risk_score' => $activity_risk,
        'recent_logins' => $recent_activity['recent_logins'],
        'unique_ips' => $recent_activity['unique_ips'],
        'assessment' => $activity_risk > 15 ? 'high' : ($activity_risk > 5 ? 'medium' : 'low')
    ];

    return $risks;
}

/**
 * Calculate overall risk score from all factors
 */
function calculateOverallRiskScore($credit_report, $additional_risks) {
    // Credit score risk (inverse of credit score)
    $credit_score = $credit_report['credit_score']['score'];
    $credit_risk = max(0, (850 - $credit_score) / 850 * 100);

    // Fraud risk
    $fraud_risk = $credit_report['fraud_analysis']['fraud_score'];

    // Additional risks
    $additional_risk_total = array_sum(array_column($additional_risks, 'risk_score'));

    // Weighted average
    $overall_score = (
        $credit_risk * 0.4 +           // 40% weight to credit
        $fraud_risk * 0.3 +            // 30% weight to fraud
        $additional_risk_total * 0.3   // 30% weight to other factors
    );

    return min(100, max(0, $overall_score));
}

/**
 * Determine risk level from score
 */
function determineRiskLevel($score) {
    if ($score >= 80) return 'critical';
    if ($score >= 60) return 'high';
    if ($score >= 40) return 'medium';
    if ($score >= 20) return 'low';
    return 'minimal';
}

/**
 * Generate risk mitigation strategies
 */
function generateMitigationStrategies($overall_score, $credit_report, $additional_risks) {
    $strategies = [];

    if ($overall_score >= 60) {
        $strategies[] = [
            'type' => 'enhanced_monitoring',
            'description' => 'Implement enhanced monitoring for all transactions',
            'priority' => 'high',
            'automated' => false
        ];
    }

    if ($credit_report['fraud_analysis']['is_suspicious']) {
        $strategies[] = [
            'type' => 'manual_review',
            'description' => 'Require manual review for all high-value transactions',
            'priority' => 'critical',
            'automated' => false
        ];
    }

    if ($additional_risks['verification']['risk_score'] > 20) {
        $strategies[] = [
            'type' => 'verification_required',
            'description' => 'Require additional identity verification',
            'priority' => 'high',
            'automated' => true
        ];
    }

    if ($additional_risks['payment_history']['risk_score'] > 25) {
        $strategies[] = [
            'type' => 'payment_restrictions',
            'description' => 'Implement stricter payment terms and conditions',
            'priority' => 'medium',
            'automated' => true
        ];
    }

    if ($credit_report['credit_score']['score'] < 500) {
        $strategies[] = [
            'type' => 'credit_limit_reduction',
            'description' => 'Reduce credit limits and require collateral',
            'priority' => 'high',
            'automated' => true
        ];
    }

    return $strategies;
}

/**
 * Generate risk-based recommendations
 */
function generateRiskRecommendations($overall_score, $credit_report) {
    $recommendations = [];

    if ($overall_score >= 80) {
        $recommendations[] = [
            'action' => 'immediate_suspension',
            'reason' => 'Critical risk level detected',
            'automated' => true
        ];
    } elseif ($overall_score >= 60) {
        $recommendations[] = [
            'action' => 'temporary_hold',
            'reason' => 'High risk level requires manual review',
            'automated' => false
        ];
    } elseif ($overall_score >= 40) {
        $recommendations[] = [
            'action' => 'enhanced_monitoring',
            'reason' => 'Medium risk level requires additional oversight',
            'automated' => true
        ];
    }

    if ($credit_report['credit_score']['confidence'] < 0.6) {
        $recommendations[] = [
            'action' => 'collect_more_data',
            'reason' => 'Insufficient data for accurate risk assessment',
            'automated' => false
        ];
    }

    return $recommendations;
}

/**
 * Save risk assessment to database
 */
function saveRiskAssessment($db, $assessment) {
    $db->query("
        INSERT INTO risk_assessments (
            retailer_id, risk_score, risk_level, fraud_indicators,
            assessment_date, updated_at
        ) VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            risk_score = ?,
            risk_level = ?,
            fraud_indicators = ?,
            assessment_date = NOW(),
            updated_at = NOW()
    ");

    $db->bind(':retailer_id', $assessment['user_id']);
    $db->bind(':risk_score', $assessment['overall_risk_score']);
    $db->bind(':risk_level', $assessment['risk_level']);
    $db->bind(':fraud_indicators', json_encode($assessment));
    $db->bind(':risk_score', $assessment['overall_risk_score']);
    $db->bind(':risk_level', $assessment['risk_level']);
    $db->bind(':fraud_indicators', json_encode($assessment));
    $db->execute();
}
?>