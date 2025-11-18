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

if (!$data || !isset($data['name'], $data['price'], $data['supplier_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('INSERT INTO products (name, description, price, quantity_available, supplier_id, category, created_at) 
           VALUES (?, ?, ?, ?, ?, ?, NOW())');
$db->bind('ssdiss', $data['name'], $data['description'] ?? '', $data['price'], 
          $data['quantity_available'] ?? 0, $data['supplier_id'], $data['category'] ?? '');

if ($db->execute()) {
    http_response_code(201);
    echo json_encode(['message' => 'Product created', 'product_id' => $db->lastInsertId()]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create product']);
}
?>
