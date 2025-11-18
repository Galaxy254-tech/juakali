<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) throw new Exception('Unauthorized', 401);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = $input['product_id'] ?? null;

    if (!$product_id) throw new Exception('Product ID required', 400);

    $db = Database::getInstance();
    
    // Check if already in wishlist
    $db->query("SELECT id FROM wishlist WHERE retailer_id = ? AND product_id = ?");
    $db->bind(':user', $_SESSION['user_id']);
    $db->bind(':product', $product_id);
    
    if ($db->single()) {
        throw new Exception('Already in wishlist', 400);
    }

    $db->query("INSERT INTO wishlist (retailer_id, product_id) VALUES (?, ?)");
    $db->bind(':user', $_SESSION['user_id']);
    $db->bind(':product', $product_id);

    if ($db->execute()) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
    } else {
        throw new Exception('Failed to add to wishlist', 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
