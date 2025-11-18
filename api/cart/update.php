<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) throw new Exception('Unauthorized', 401);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = $input['cart_id'] ?? null;
    $quantity = $input['quantity'] ?? null;

    if (!$cart_id || !$quantity || $quantity < 1) {
        throw new Exception('Invalid cart ID or quantity', 400);
    }

    $db = Database::getInstance();
    $db->query("UPDATE shopping_cart SET quantity = ?, subtotal = unit_price * ? WHERE id = ? AND retailer_id = ?");
    $db->bind(':qty1', $quantity);
    $db->bind(':qty2', $quantity);
    $db->bind(':id', $cart_id);
    $db->bind(':user', $_SESSION['user_id']);

    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Cart updated']);
    } else {
        throw new Exception('Failed to update cart', 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
