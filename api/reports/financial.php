<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');

    $db = Database::getInstance();
    
    // Total disbursed
    $db->query("SELECT SUM(loan_amount) as total FROM loans WHERE status = 'disbursed' AND DATE(created_at) BETWEEN ? AND ?");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $disbursed = $db->single()['total'] ?? 0;

    // Total repaid
    $db->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND DATE(payment_date) BETWEEN ? AND ?");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $repaid = $db->single()['total'] ?? 0;

    // Total interest
    $db->query("SELECT SUM((loan_amount * interest_rate / 100)) as total FROM loans WHERE DATE(created_at) BETWEEN ? AND ?");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $interest = $db->single()['total'] ?? 0;

    // Default rate
    $db->query("SELECT COUNT(*) as total FROM loans WHERE status = 'defaulted' AND DATE(created_at) BETWEEN ? AND ?");
    $db->bind(':start', $start_date);
    $db->bind(':end', $end_date);
    $defaults = $db->single()['total'] ?? 0;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'period' => ['start' => $start_date, 'end' => $end_date],
            'total_disbursed' => $disbursed,
            'total_repaid' => $repaid,
            'total_interest' => $interest,
            'defaults' => $defaults,
            'repayment_rate' => $disbursed > 0 ? ($repaid / $disbursed * 100) : 0
        ]
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
