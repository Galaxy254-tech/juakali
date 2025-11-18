<?php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('SELECT id, email, role, first_name, last_name, company_name, phone, kyc_verified, status, created_at FROM users WHERE id = ?');
$db->bind('i', $user_id);
$user = $db->single();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

http_response_code(200);
echo json_encode($user);
?>
