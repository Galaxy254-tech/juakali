<?php
/**
 * Get Single Product API Endpoint
 * GET /api/products/get.php?id=1
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/database.php';

try {
    $product_id = $_GET['id'] ?? null;

    if (!$product_id) {
        throw new Exception('Product ID is required', 400);
    }

    $db = Database::getInstance();
    
    $db->query("
        SELECT p.*, u.company_name as supplier_name
        FROM products p
        LEFT JOIN users u ON p.supplier_id = u.id
        WHERE p.id = ? AND p.status != 'deleted'
    ");
    $db->bind(':id', $product_id);
    $product = $db->single();

    if (!$product) {
        throw new Exception('Product not found', 404);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $product
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
