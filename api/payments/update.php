<?php
/**
 * Update Payment Status API Endpoint
 * PUT /api/payments/update.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $payment_id = $input['payment_id'] ?? null;
    $status = $input['status'] ?? null;

    if (!$payment_id || !$status) {
        throw new Exception('Payment ID and status are required', 400);
    }

    $allowed_statuses = ['pending', 'completed', 'failed'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Invalid status', 400);
    }

    $db = Database::getInstance();
    
    $db->query("SELECT id FROM payments WHERE id = ?");
    $db->bind(':id', $payment_id);
    if (!$db->single()) {
        throw new Exception('Payment not found', 404);
    }

    $db->query("
        UPDATE payments 
        SET status = ?, payment_date = NOW()
        WHERE id = ?
    ");
    $db->bind(':status', $status);
    $db->bind(':id', $payment_id);

    if ($db->execute()) {
        logAudit('update', 'payments', $payment_id, ['status' => $status]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update payment', 500);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'UPDATE_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
