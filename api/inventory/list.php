<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'supplier') throw new Exception('Permission denied', 403);
    
    $db = Database::getInstance();
    $db->query("
        SELECT ia.*, p.name as product_name, p.quantity_available
        FROM inventory_alerts ia
        JOIN products p ON ia.product_id = p.id
        WHERE ia.supplier_id = ?
        ORDER BY ia.created_at DESC
    ");
    $db->bind(':supplier', $_SESSION['user_id']);
    $alerts = $db->resultSet();

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $alerts]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
