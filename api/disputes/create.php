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

if (!$data || !isset($data['initiator_id'], $data['respondent_id'], $data['subject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('INSERT INTO disputes (initiator_id, respondent_id, subject, description, status, created_at) 
           VALUES (?, ?, ?, ?, "open", NOW())');
$db->bind('iiss', $data['initiator_id'], $data['respondent_id'], $data['subject'], $data['description'] ?? '');

if ($db->execute()) {
    http_response_code(201);
    echo json_encode(['message' => 'Dispute created', 'dispute_id' => $db->lastInsertId()]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create dispute']);
}
?>
