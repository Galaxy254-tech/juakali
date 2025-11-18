<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) throw new Exception('Unauthorized', 401);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = $input['cart_id'] ?? null;

    if (!$cart_id) throw new Exception('Cart ID required', 400);

    $db = Database::getInstance();
    $db->query("DELETE FROM shopping_cart WHERE id = ? AND retailer_id = ?");
    $db->bind(':id', $cart_id);
    $db->bind(':user', $_SESSION['user_id']);

    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Item removed']);
    } else {
        throw new Exception('Failed to remove item', 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
