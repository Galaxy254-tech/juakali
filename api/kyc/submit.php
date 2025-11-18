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

if (!$data || !isset($data['user_id'], $data['document_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('INSERT INTO kyc_documents (user_id, document_type, document_url, status, created_at) 
           VALUES (?, ?, ?, "pending", NOW())');
$db->bind('iss', $data['user_id'], $data['document_type'], $data['document_url'] ?? '');

if ($db->execute()) {
    http_response_code(201);
    echo json_encode(['message' => 'KYC document submitted', 'kyc_id' => $db->lastInsertId()]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit KYC']);
}
?>
