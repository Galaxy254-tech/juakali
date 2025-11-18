<?php
/**
 * Get Single Loan API Endpoint
 * GET /api/loans/get.php?id=1
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/security.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $loan_id = $_GET['id'] ?? null;

    if (!$loan_id) {
        throw new Exception('Loan ID is required', 400);
    }

    $db = Database::getInstance();
    
    $db->query("
        SELECT l.*, 
               r.company_name as retailer_name,
               le.company_name as lender_name,
               o.order_number
        FROM loans l
        LEFT JOIN users r ON l.retailer_id = r.id
        LEFT JOIN users le ON l.lender_id = le.id
        LEFT JOIN orders o ON l.order_id = o.id
        WHERE l.id = ?
    ");
    $db->bind(':id', $loan_id);
    $loan = $db->single();

    if (!$loan) {
        throw new Exception('Loan not found', 404);
    }

    // Get repayment schedule
    $db->query("
        SELECT * FROM repayment_schedule
        WHERE loan_id = ?
        ORDER BY due_date ASC
    ");
    $db->bind(':id', $loan_id);
    $schedule = $db->resultSet();

    $loan['repayment_schedule'] = $schedule;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $loan
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'GET_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
