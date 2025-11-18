<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $db = Database::getInstance();
    
    // Find overdue repayments
    $db->query("
        SELECT rs.*, l.loan_amount
        FROM repayment_schedule rs
        JOIN loans l ON rs.loan_id = l.id
        WHERE rs.status = 'pending' AND rs.due_date < CURDATE()
    ");
    $overdue = $db->resultSet();

    foreach ($overdue as $repay) {
        $days_overdue = (strtotime(date('Y-m-d')) - strtotime($repay['due_date'])) / (24 * 60 * 60);
        
        // Calculate penalty
        $penalty_rate = 0.5; // 0.5% per day
        if ($days_overdue > 7) $penalty_rate = 1.0;
        if ($days_overdue > 30) $penalty_rate = 2.0;
        
        $penalty_amount = ($repay['amount_due'] * $penalty_rate / 100);
        $penalty_amount = min($penalty_amount, $repay['loan_amount'] * 0.25); // Max 25%
        $penalty_amount = max($penalty_amount, 50); // Min 50

        // Check if penalty already exists
        $db->query("SELECT id FROM penalty_records WHERE repayment_id = ? AND waived = FALSE");
        $db->bind(':id', $repay['id']);
        
        if (!$db->single()) {
            $db->query("
                INSERT INTO penalty_records (repayment_id, loan_id, penalty_amount, days_overdue, reason)
                VALUES (?, ?, ?, ?, 'Automatic penalty calculation')
            ");
            $db->bind(':repay', $repay['id']);
            $db->bind(':loan', $repay['loan_id']);
            $db->bind(':amount', $penalty_amount);
            $db->bind(':days', ceil($days_overdue));
            $db->execute();
        }
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Penalties calculated', 'records' => count($overdue)]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
