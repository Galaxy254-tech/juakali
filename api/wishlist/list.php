<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) throw new Exception('Unauthorized', 401);
    
    $db = Database::getInstance();
    
    $db->query("
        SELECT p.*, u.company_name as supplier_name
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        JOIN users u ON p.supplier_id = u.id
        WHERE w.retailer_id = ?
        ORDER BY w.added_at DESC
    ");
    $db->bind(':user', $_SESSION['user_id']);
    $items = $db->resultSet();

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $items]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
