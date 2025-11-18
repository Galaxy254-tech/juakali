<?php
/**
 * Get Single Order API Endpoint
 * GET /api/orders/get.php?id=1
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/security.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $order_id = $_GET['id'] ?? null;

    if (!$order_id) {
        throw new Exception('Order ID is required', 400);
    }

    $db = Database::getInstance();
    
    $db->query("
        SELECT o.*, 
               r.company_name as retailer_name,
               s.company_name as supplier_name
        FROM orders o
        LEFT JOIN users r ON o.retailer_id = r.id
        LEFT JOIN users s ON o.supplier_id = s.id
        WHERE o.id = ?
    ");
    $db->bind(':id', $order_id);
    $order = $db->single();

    if (!$order) {
        throw new Exception('Order not found', 404);
    }

    // Get order items
    $db->query("
        SELECT oi.*, p.name as product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $db->bind(':id', $order_id);
    $items = $db->resultSet();

    $order['items'] = $items;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $order
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
