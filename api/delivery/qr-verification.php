<?php
/**
 * QR Code Delivery Verification API
 * Handles QR code generation and verification for deliveries
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
require_once '../../includes/qr-delivery.php';

try {
    $db = Database::getInstance();
    $qrSystem = new QRDeliverySystem($db);

    // Create tables if they don't exist
    createQRDeliveryTables($db);

    $action = $_GET['action'] ?? 'generate';

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            handlePostRequest($db, $qrSystem, $action);
            break;
        case 'GET':
            handleGetRequest($db, $qrSystem, $action);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'QR_DELIVERY_ERROR'
    ]);
}

function handlePostRequest($db, $qrSystem, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    switch ($action) {
        case 'generate':
            // Generate QR code for delivery
            $required_fields = ['order_id', 'agent_id'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $result = $qrSystem->generateDeliveryQR($input['order_id'], $input['agent_id']);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'data' => $result,
                    'message' => 'QR code generated successfully'
                ]);
            } else {
                throw new Exception($result['error']);
            }
            break;

        case 'verify':
            // Verify QR code and complete delivery
            $required_fields = ['qr_data', 'completion_data'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $result = $qrSystem->completeDeliveryWithQR($input['qr_data'], $input['completion_data']);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'data' => $result,
                    'message' => $result['message']
                ]);
            } else {
                throw new Exception($result['error']);
            }
            break;

        case 'validate':
            // Validate QR code without completing delivery
            if (empty($input['qr_data'])) {
                throw new Exception('QR data is required');
            }

            $result = $qrSystem->verifyDeliveryQR($input['qr_data']);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'data' => $result,
                    'message' => 'QR code validated successfully'
                ]);
            } else {
                throw new Exception($result['error']);
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
}

function handleGetRequest($db, $qrSystem, $action) {
    switch ($action) {
        case 'stats':
            // Get QR verification statistics
            $date_range = $_GET['date_range'] ?? 30; // days
            $stats = $qrSystem->getQRVerificationStats($date_range);

            echo json_encode([
                'success' => true,
                'data' => $stats,
                'message' => 'QR verification statistics retrieved successfully'
            ]);
            break;

        case 'cleanup':
            // Clean up expired QR codes (admin only)
            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) {
                throw new Exception('Authentication required');
            }

            // Verify admin role
            $user = $db->fetchOne("SELECT role FROM users WHERE id = ?", [$user_id]);
            if (!$user || $user['role'] !== 'admin') {
                throw new Exception('Admin access required');
            }

            $cleaned_count = $qrSystem->cleanupExpiredQRCodes();

            echo json_encode([
                'success' => true,
                'data' => ['cleaned_count' => $cleaned_count],
                'message' => "Cleaned up {$cleaned_count} expired QR codes"
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
}
?>