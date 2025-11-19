<?php
/**
 * WhatsApp Message Sending API Endpoint
 * Handles sending various types of WhatsApp messages
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
require_once '../../includes/auth.php';
require_once '../../includes/whatsapp-api.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'error_code' => 'AUTHENTICATION_REQUIRED'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    $whatsapp = new WhatsAppAPI($db);

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $action = $input['action'] ?? '';

    switch ($action) {
        case 'send_text':
            handleSendTextMessage($whatsapp, $input);
            break;

        case 'send_payment_reminder':
            handleSendPaymentReminder($whatsapp, $input);
            break;

        case 'send_loan_approval':
            handleSendLoanApproval($whatsapp, $input);
            break;

        case 'send_fraud_alert':
            handleSendFraudAlert($whatsapp, $input);
            break;

        case 'send_delivery_notification':
            handleSendDeliveryNotification($whatsapp, $input);
            break;

        case 'send_otp':
            handleSendOTP($whatsapp, $input);
            break;

        case 'send_interactive':
            handleSendInteractiveMessage($whatsapp, $input);
            break;

        case 'send_media':
            handleSendMediaMessage($whatsapp, $input);
            break;

        case 'send_bulk':
            handleSendBulkMessages($whatsapp, $input);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'WHATSAPP_SEND_ERROR'
    ]);
}

function handleSendTextMessage($whatsapp, $input) {
    $requiredFields = ['recipient_phone', 'message'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $templateName = $input['template_name'] ?? null;
    $result = $whatsapp->sendTextMessage($input['recipient_phone'], $input['message'], $templateName);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Text message sent successfully',
            'data' => [
                'message_id' => $result['message_id']
            ]
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send text message');
    }
}

function handleSendPaymentReminder($whatsapp, $input) {
    $requiredFields = ['user_id', 'loan_id', 'amount', 'due_date'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $result = $whatsapp->sendPaymentReminder(
        $input['user_id'],
        $input['loan_id'],
        $input['amount'],
        $input['due_date']
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment reminder sent successfully'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send payment reminder');
    }
}

function handleSendLoanApproval($whatsapp, $input) {
    $requiredFields = ['user_id', 'loan_id', 'amount', 'terms'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $result = $whatsapp->sendLoanApprovalNotification(
        $input['user_id'],
        $input['loan_id'],
        $input['amount'],
        $input['terms']
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Loan approval notification sent successfully'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send loan approval notification');
    }
}

function handleSendFraudAlert($whatsapp, $input) {
    $requiredFields = ['user_id', 'risk_level', 'description'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Only admins can send fraud alerts
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required',
            'error_code' => 'ADMIN_ACCESS_REQUIRED'
        ]);
        exit;
    }

    $result = $whatsapp->sendFraudAlert(
        $input['user_id'],
        $input['risk_level'],
        $input['description']
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Fraud alert sent successfully'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send fraud alert');
    }
}

function handleSendDeliveryNotification($whatsapp, $input) {
    $requiredFields = ['user_id', 'order_id', 'delivery_status'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $result = $whatsapp->sendDeliveryNotification(
        $input['user_id'],
        $input['order_id'],
        $input['delivery_status'],
        $input['qr_code'] ?? null
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Delivery notification sent successfully'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send delivery notification');
    }
}

function handleSendOTP($whatsapp, $input) {
    $requiredFields = ['user_id', 'otp'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $result = $whatsapp->sendOTPVerification(
        $input['user_id'],
        $input['otp'],
        $input['purpose'] ?? 'verification'
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'OTP sent successfully'
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send OTP');
    }
}

function handleSendInteractiveMessage($whatsapp, $input) {
    $requiredFields = ['recipient_phone', 'header_text', 'body_text', 'buttons'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    if (!is_array($input['buttons']) || empty($input['buttons'])) {
        throw new Exception('Buttons must be a non-empty array');
    }

    $result = $whatsapp->sendInteractiveMessage(
        $input['recipient_phone'],
        $input['header_text'],
        $input['body_text'],
        $input['buttons']
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Interactive message sent successfully',
            'data' => [
                'message_id' => $result['message_id']
            ]
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send interactive message');
    }
}

function handleSendMediaMessage($whatsapp, $input) {
    $requiredFields = ['recipient_phone', 'media_url', 'media_type'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $validMediaTypes = ['image', 'document', 'audio', 'video'];
    if (!in_array($input['media_type'], $validMediaTypes)) {
        throw new Exception('Invalid media type. Must be one of: ' . implode(', ', $validMediaTypes));
    }

    $result = $whatsapp->sendMediaMessage(
        $input['recipient_phone'],
        $input['media_url'],
        $input['media_type'],
        $input['caption'] ?? ''
    );

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Media message sent successfully',
            'data' => [
                'message_id' => $result['message_id']
            ]
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Failed to send media message');
    }
}

function handleSendBulkMessages($whatsapp, $input) {
    $requiredFields = ['recipients', 'message'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    if (!is_array($input['recipients']) || empty($input['recipients'])) {
        throw new Exception('Recipients must be a non-empty array');
    }

    // Only admins can send bulk messages
    if ($_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Admin access required for bulk messages',
            'error_code' => 'ADMIN_ACCESS_REQUIRED'
        ]);
        exit;
    }

    $templateName = $input['template_name'] ?? null;
    $results = [];
    $successCount = 0;
    $failureCount = 0;

    foreach ($input['recipients'] as $recipient) {
        try {
            $result = $whatsapp->sendTextMessage($recipient, $input['message'], $templateName);
            $results[] = [
                'recipient' => $recipient,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }

            // Add small delay to avoid rate limiting
            usleep(100000); // 0.1 second

        } catch (Exception $e) {
            $results[] = [
                'recipient' => $recipient,
                'success' => false,
                'error' => $e->getMessage()
            ];
            $failureCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Bulk message processing completed. Success: $successCount, Failed: $failureCount",
        'data' => [
            'total_recipients' => count($input['recipients']),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ]
    ]);
}
?>