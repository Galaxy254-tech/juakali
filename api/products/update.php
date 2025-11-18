<?php
/**
 * Update Product API Endpoint
 * PUT /api/products/update.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/validation.php';

try {
    // Check authentication
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    // Only suppliers can update products
    if ($_SESSION['role'] !== 'supplier' && $_SESSION['role'] !== 'admin') {
        throw new Exception('Permission denied', 403);
    }

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input', 400);
    }

    // Validate required fields
    $product_id = $input['product_id'] ?? null;
    $name = $input['name'] ?? null;
    $price = $input['price'] ?? null;
    $quantity = $input['quantity_available'] ?? null;

    if (!$product_id) {
        throw new Exception('Product ID is required', 400);
    }

    // Validate data
    if (!validateRequired($name)) {
        throw new Exception('Product name is required', 400);
    }
    
    if (!validatePrice($price)) {
        throw new Exception('Invalid price', 400);
    }
    
    if ($quantity !== null && !is_numeric($quantity)) {
        throw new Exception('Invalid quantity', 400);
    }

    $db = Database::getInstance();
    
    // Check product exists and belongs to supplier
    $db->query("SELECT supplier_id FROM products WHERE id = ?");
    $db->bind(':id', $product_id);
    $product = $db->single();

    if (!$product) {
        throw new Exception('Product not found', 404);
    }

    if ($_SESSION['role'] === 'supplier' && $product['supplier_id'] != $_SESSION['user_id']) {
        throw new Exception('You can only edit your own products', 403);
    }

    // Update product
    $db->query("
        UPDATE products 
        SET name = ?, 
            description = ?, 
            price = ?,
            quantity_available = ?,
            category = ?,
            status = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $db->bind(':name', $name);
    $db->bind(':description', $input['description'] ?? null);
    $db->bind(':price', $price);
    $db->bind(':quantity', $quantity);
    $db->bind(':category', $input['category'] ?? null);
    $db->bind(':status', $input['status'] ?? 'active');
    $db->bind(':id', $product_id);

    if ($db->execute()) {
        // Log action
        logAudit('update', 'products', $product_id, ['price' => $price, 'name' => $name]);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => ['product_id' => $product_id]
        ]);
    } else {
        throw new Exception('Failed to update product', 500);
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
