<?php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['retailer_id'], $data['supplier_id'], $data['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

$order_number = 'ORD-' . time();
$total_amount = 0;

foreach ($data['items'] as $item) {
    $db->query('SELECT price FROM products WHERE id = ?');
    $db->bind('i', $item['product_id']);
    $product = $db->single();
    $total_amount += $product['price'] * $item['quantity'];
}

$db->query('INSERT INTO orders (order_number, retailer_id, supplier_id, total_amount, status, created_at) 
           VALUES (?, ?, ?, ?, "pending", NOW())');
$db->bind('siid', $order_number, $data['retailer_id'], $data['supplier_id'], $total_amount);

if ($db->execute()) {
    $order_id = $db->lastInsertId();
    
    foreach ($data['items'] as $item) {
        $db->query('INSERT INTO order_items (order_id, product_id, quantity, price) 
                   SELECT ?, id, ?, price FROM products WHERE id = ?');
        $db->bind('iii', $order_id, $item['quantity'], $item['product_id']);
        $db->execute();
    }
    
    http_response_code(201);
    echo json_encode(['message' => 'Order created', 'order_id' => $order_id, 'order_number' => $order_number]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order']);
}
?>
