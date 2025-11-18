<?php
/**
 * Cancel/Delete Order API Endpoint
 * DELETE /api/orders/delete.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;

    if (!$order_id) {
        throw new Exception('Order ID is required', 400);
    }

    $db = Database::getInstance();
    
    $db->query("SELECT retailer_id, status FROM orders WHERE id = ?");
    $db->bind(':id', $order_id);
    $order = $db->single();

    if (!$order) {
        throw new Exception('Order not found', 404);
    }

    if ($_SESSION['role'] === 'retailer' && $order['retailer_id'] != $_SESSION['user_id']) {
        throw new Exception('Permission denied', 403);
    }

    if (!in_array($order['status'], ['pending', 'confirmed'])) {
        throw new Exception('Cannot cancel orders in this status', 400);
    }

    $db->query("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $db->bind(':id', $order_id);

    if ($db->execute()) {
        logAudit('cancel', 'orders', $order_id, []);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    } else {
        throw new Exception('Failed to cancel order', 500);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'DELETE_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
