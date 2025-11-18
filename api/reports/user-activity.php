<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $days = $_GET['days'] ?? 7;
    $db = Database::getInstance();

    $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $db->bind(':days', $days);
    $activity = $db->resultSet();

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $activity]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
