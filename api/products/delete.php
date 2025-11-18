<?php
/**
 * Delete Product API Endpoint
 * DELETE /api/products/delete.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    if ($_SESSION['role'] !== 'supplier' && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = $input['product_id'] ?? null;

    if (!$product_id) {
        throw new Exception('Product ID is required', 400);
    }

    $db = Database::getInstance();
    
    $db->query("SELECT supplier_id FROM products WHERE id = ?");
    $db->bind(':id', $product_id);
    $product = $db->single();

    if (!$product) {
        throw new Exception('Product not found', 404);
    }

    if ($_SESSION['role'] === 'supplier' && $product['supplier_id'] != $_SESSION['user_id']) {
        throw new Exception('Permission denied', 403);
    }

    // Soft delete
    $db->query("UPDATE products SET status = 'deleted', updated_at = NOW() WHERE id = ?");
    $db->bind(':id', $product_id);

    if ($db->execute()) {
        logAudit('delete', 'products', $product_id, []);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete product', 500);
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
