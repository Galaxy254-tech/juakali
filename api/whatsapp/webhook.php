<?php
/**
 * WhatsApp Webhook and API Endpoint
 * Handles incoming messages and outgoing notifications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/whatsapp-api.php';

try {
    $db = Database::getInstance();
    $whatsapp = new WhatsAppAPI($db);

    // Handle webhook verification (GET request)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleWebhookVerification($whatsapp);
    }

    // Handle incoming messages (POST request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handleIncomingMessages($whatsapp);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'WhatsApp API error: ' . $e->getMessage(),
        'error_code' => 'WHATSAPP_API_ERROR'
    ]);
}

function handleWebhookVerification($whatsapp) {
    $hubMode = $_GET['hub_mode'] ?? '';
    $hubVerifyToken = $_GET['hub_verify_token'] ?? '';
    $hubChallenge = $_GET['hub_challenge'] ?? '';

    $response = $whatsapp->verifyWebhook($hubMode, $hubVerifyToken, $hubChallenge);

    if ($response) {
        http_response_code(200);
        echo $response;
    } else {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Webhook verification failed',
            'error_code' => 'WEBHOOK_VERIFICATION_FAILED'
        ]);
    }
}

function handleIncomingMessages($whatsapp) {
    // Get the raw POST data
    $input = file_get_contents('php://input');
    $webhookData = json_decode($input, true);

    if (!$webhookData) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input',
            'error_code' => 'INVALID_JSON'
        ]);
        return;
    }

    // Log the webhook data for debugging
    error_log('WhatsApp webhook received: ' . $input);

    // Process the webhook
    $result = $whatsapp->processWebhook($webhookData);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Webhook processed successfully'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to process webhook',
            'error_code' => 'WEBHOOK_PROCESSING_FAILED'
        ]);
    }
}
?>