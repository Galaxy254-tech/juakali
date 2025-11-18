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

$kyc_id = $_GET['id'] ?? null;

if (!$kyc_id) {
    http_response_code(400);
    echo json_encode(['error' => 'KYC ID required']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('SELECT user_id FROM kyc_documents WHERE id = ?');
$db->bind('i', $kyc_id);
$kyc = $db->single();

if (!$kyc) {
    http_response_code(404);
    echo json_encode(['error' => 'KYC not found']);
    exit;
}

$db->query('UPDATE kyc_documents SET status = "approved" WHERE id = ?');
$db->bind('i', $kyc_id);
$db->execute();

$db->query('UPDATE users SET kyc_verified = TRUE WHERE id = ?');
$db->bind('i', $kyc['user_id']);
$db->execute();

http_response_code(200);
echo json_encode(['message' => 'KYC approved']);
?>
