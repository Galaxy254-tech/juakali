<?php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$loan_id = $_GET['id'] ?? null;

if (!$loan_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Loan ID required']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('UPDATE loans SET status = "disbursed", disbursement_date = NOW(), updated_at = NOW() WHERE id = ?');
$db->bind('i', $loan_id);

if ($db->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Loan disbursed']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to disburse loan']);
}
?>
