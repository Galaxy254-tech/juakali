<?php
/**
 * Fraud Detection API Endpoint
 * Analyzes transactions and user behavior for fraudulent patterns
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../integrations/credit-scoring.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $required_fields = ['user_id'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $user_id = $input['user_id'];
    $order_amount = $input['order_amount'] ?? null;
    $context = $input['context'] ?? [];

    // Initialize database connection
    $db = Database::getInstance();

    // Verify user exists
    $db->query("SELECT id, role, status FROM users WHERE id = ?");
    $db->bind(':id', $user_id);
    $user = $db->single();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Initialize credit scoring engine
    $creditEngine = new CreditScoringEngine($db);

    // Perform comprehensive fraud analysis
    $fraud_analysis = $creditEngine->detectFraud($user_id, $order_amount, $context);

    // Log the analysis request
    $db->query("
        INSERT INTO audit_logs (
            user_id, action, entity_type, entity_id,
            new_values, ip_address, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $db->bind(':user_id', $user_id);
    $db->bind(':action', 'fraud_analysis_requested');
    $db->bind(':entity_type', 'fraud_detection');
    $db->bind(':entity_id', $user_id);
    $db->bind(':new_values', json_encode([
        'order_amount' => $order_amount,
        'context' => $context,
        'fraud_score' => $fraud_analysis['fraud_score'],
        'risk_level' => $fraud_analysis['risk_level']
    ]));
    $db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
    $db->execute();

    // Return response based on fraud risk level
    $http_code = 200;
    $response_data = [
        'success' => true,
        'data' => $fraud_analysis,
        'message' => 'Fraud analysis completed successfully'
    ];

    // Adjust response based on risk level
    switch ($fraud_analysis['risk_level']) {
        case 'critical':
            $http_code = 403;
            $response_data['message'] = 'High fraud risk detected - transaction blocked';
            break;
        case 'high':
            $http_code = 406;
            $response_data['message'] = 'Fraud risk detected - manual review required';
            break;
        case 'medium':
            $response_data['message'] = 'Moderate fraud risk - proceed with caution';
            break;
        default:
            $response_data['message'] = 'Low fraud risk - transaction approved';
    }

    http_response_code($http_code);
    echo json_encode($response_data);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'FRAUD_ANALYSIS_ERROR'
    ]);
}
?>