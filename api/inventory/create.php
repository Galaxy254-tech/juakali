<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'supplier') throw new Exception('Permission denied', 403);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $product_id = $input['product_id'] ?? null;
    $threshold = $input['threshold_quantity'] ?? 10;

    if (!$product_id) throw new Exception('Product ID required', 400);

    $db = Database::getInstance();
    $db->query("SELECT id FROM products WHERE id = ? AND supplier_id = ?");
    $db->bind(':id', $product_id);
    $db->bind(':supplier', $_SESSION['user_id']);
    
    if (!$db->single()) throw new Exception('Product not found', 404);

    $db->query("
        INSERT INTO inventory_alerts (product_id, supplier_id, alert_type, threshold_quantity, current_quantity, status)
        VALUES (?, ?, 'low_stock', ?, 0, 'active')
    ");
    $db->bind(':product', $product_id);
    $db->bind(':supplier', $_SESSION['user_id']);
    $db->bind(':threshold', $threshold);

    if ($db->execute()) {
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Inventory alert created']);
    } else {
        throw new Exception('Failed to create alert', 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
