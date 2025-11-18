<?php
/**
 * List Loans API Endpoint
 * GET /api/loans/list.php?page=1&status=disbursed
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/security.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $status = $_GET['status'] ?? null;
    $offset = ($page - 1) * $limit;

    $db = Database::getInstance();
    
    $query = "SELECT l.*, r.company_name as retailer_name, le.company_name as lender_name
              FROM loans l
              LEFT JOIN users r ON l.retailer_id = r.id
              LEFT JOIN users le ON l.lender_id = le.id
              WHERE 1=1";
    
    $bindings = [];

    // Filter by status
    if ($status) {
        $query .= " AND l.status = ?";
        $bindings['status'] = $status;
    }

    // Filter by user role
    if ($_SESSION['role'] === 'retailer') {
        $query .= " AND l.retailer_id = ?";
        $bindings['retailer_id'] = $_SESSION['user_id'];
    } elseif ($_SESSION['role'] === 'lender') {
        $query .= " AND l.lender_id = ?";
        $bindings['lender_id'] = $_SESSION['user_id'];
    }

    // Get total
    $db->query("SELECT COUNT(*) as total FROM loans l WHERE 1=1" . (isset($bindings['status']) ? " AND l.status = ?" : "") . (isset($bindings['retailer_id']) ? " AND l.retailer_id = ?" : "") . (isset($bindings['lender_id']) ? " AND l.lender_id = ?" : ""));
    foreach ($bindings as $key => $value) {
        $db->bind(':' . $key, $value);
    }
    $total = $db->single()['total'] ?? 0;

    // Get paginated results
    $query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
    $db->query($query);
    
    foreach ($bindings as $key => $value) {
        $db->bind(':' . $key, $value);
    }
    $db->bind(':limit', $limit);
    $db->bind(':offset', $offset);
    
    $loans = $db->resultSet();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $loans,
        'pagination' => [
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'LIST_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
