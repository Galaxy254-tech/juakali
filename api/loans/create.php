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

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['retailer_id'], $data['lender_id'], $data['loan_amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('INSERT INTO loans (retailer_id, lender_id, loan_amount, interest_rate, loan_term_days, status, created_at) 
           VALUES (?, ?, ?, ?, ?, "pending", NOW())');
$db->bind('iidii', $data['retailer_id'], $data['lender_id'], $data['loan_amount'], 
          $data['interest_rate'] ?? 10, $data['loan_term_days'] ?? 30);

if ($db->execute()) {
    http_response_code(201);
    echo json_encode(['message' => 'Loan application created', 'loan_id' => $db->lastInsertId()]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create loan']);
}
?>
