<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $offset = ($page - 1) * $limit;

    $db = Database::getInstance();
    
    $db->query("SELECT COUNT(*) as total FROM penalty_records WHERE waived = FALSE");
    $total = $db->single()['total'] ?? 0;

    $db->query("
        SELECT pr.*, l.loan_amount, u.company_name
        FROM penalty_records pr
        JOIN loans l ON pr.loan_id = l.id
        JOIN users u ON l.retailer_id = u.id
        WHERE pr.waived = FALSE
        ORDER BY pr.applied_at DESC
        LIMIT ? OFFSET ?
    ");
    $db->bind(':limit', $limit);
    $db->bind(':offset', $offset);
    $penalties = $db->resultSet();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $penalties,
        'pagination' => ['total' => $total, 'pages' => ceil($total / $limit), 'current_page' => $page]
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
