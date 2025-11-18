<?php
/**
 * Update Order Status API Endpoint
 * PUT /api/orders/update.php
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
    $status = $input['status'] ?? null;

    if (!$order_id || !$status) {
        throw new Exception('Order ID and status are required', 400);
    }

    $allowed_statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Invalid status', 400);
    }

    $db = Database::getInstance();
    
    $db->query("SELECT retailer_id, supplier_id FROM orders WHERE id = ?");
    $db->bind(':id', $order_id);
    $order = $db->single();

    if (!$order) {
        throw new Exception('Order not found', 404);
    }

    // Check permissions
    if ($_SESSION['role'] === 'supplier' && $order['supplier_id'] != $_SESSION['user_id']) {
        throw new Exception('Permission denied', 403);
    }

    if ($_SESSION['role'] === 'retailer' && $order['retailer_id'] != $_SESSION['user_id']) {
        throw new Exception('Permission denied', 403);
    }

    $db->query("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $db->bind(':status', $status);
    $db->bind(':id', $order_id);

    if ($db->execute()) {
        logAudit('update', 'orders', $order_id, ['status' => $status]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => ['order_id' => $order_id, 'status' => $status]
        ]);
    } else {
        throw new Exception('Failed to update order', 500);
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
