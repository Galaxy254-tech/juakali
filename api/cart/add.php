<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retailer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'] ?? 0;
$quantity = $data['quantity'] ?? 1;
$user_id = $_SESSION['user_id'];

$db = new Database();
$db->connect();

// Validate product
$db->query('SELECT * FROM products WHERE id = ? AND status = "active"');
$db->bind('i', $product_id);
$product = $db->single();

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

if ($quantity > $product['quantity_available']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
    exit;
}

$unit_price = $product['price'];
$subtotal = $unit_price * $quantity;

// Check if already in cart
$db->query('SELECT id FROM shopping_cart WHERE retailer_id = ? AND product_id = ?');
$db->bind('i', $user_id);
$db->bind('i', $product_id);
$existing = $db->single();

if ($existing) {
    $db->query('UPDATE shopping_cart SET quantity = quantity + ?, subtotal = subtotal + ? WHERE retailer_id = ? AND product_id = ?');
    $db->bind('i', $quantity);
    $db->bind('d', $subtotal);
    $db->bind('i', $user_id);
    $db->bind('i', $product_id);
    $db->execute();
} else {
    $db->query('INSERT INTO shopping_cart (retailer_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
    $db->bind('i', $user_id);
    $db->bind('i', $product_id);
    $db->bind('i', $quantity);
    $db->bind('d', $unit_price);
    $db->bind('d', $subtotal);
    $db->execute();
}

echo json_encode(['success' => true, 'message' => 'Product added to cart']);
?>
