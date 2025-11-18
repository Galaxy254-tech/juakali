<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $penalty_id = $input['penalty_id'] ?? null;
    $reason = $input['reason'] ?? 'Admin waived';

    if (!$penalty_id) throw new Exception('Penalty ID required', 400);

    $db = Database::getInstance();
    $db->query("
        UPDATE penalty_records 
        SET waived = TRUE, waived_reason = ?, waived_by = ?, waived_at = NOW()
        WHERE id = ?
    ");
    $db->bind(':reason', $reason);
    $db->bind(':admin', $_SESSION['user_id']);
    $db->bind(':id', $penalty_id);

    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Penalty waived']);
    } else {
        throw new Exception('Failed to waive penalty', 500);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
