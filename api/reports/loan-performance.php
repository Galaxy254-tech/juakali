<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $db = Database::getInstance();

    $db->query("
        SELECT 
            status,
            COUNT(*) as count,
            AVG(loan_amount) as avg_amount,
            SUM(loan_amount) as total_amount
        FROM loans
        GROUP BY status
    ");
    $stats = $db->resultSet();

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $stats]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
