<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'supplier') throw new Exception('Permission denied', 403);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $alert_id = $input['alert_id'] ?? null;

    if (!$alert_id) throw new Exception('Alert ID required', 400);

    $db = Database::getInstance();
    $db->query("UPDATE inventory_alerts SET status = 'resolved' WHERE id = ? AND supplier_id = ?");
    $db->bind(':id', $alert_id);
    $db->bind(':supplier', $_SESSION['user_id']);

    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Alert resolved']);
    } else {
        throw new Exception('Failed to resolve alert', 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
