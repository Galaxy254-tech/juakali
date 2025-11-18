<?php
/**
 * Get Payment Details API Endpoint
 * GET /api/payments/get.php?id=1
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/security.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $payment_id = $_GET['id'] ?? null;

    if (!$payment_id) {
        throw new Exception('Payment ID is required', 400);
    }

    $db = Database::getInstance();
    
    $db->query("
        SELECT p.*, l.loan_amount, l.retailer_id, l.lender_id
        FROM payments p
        LEFT JOIN loans l ON p.loan_id = l.id
        WHERE p.id = ?
    ");
    $db->bind(':id', $payment_id);
    $payment = $db->single();

    if (!$payment) {
        throw new Exception('Payment not found', 404);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $payment
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'GET_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
