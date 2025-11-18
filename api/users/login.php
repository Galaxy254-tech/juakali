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

if (!$data || !isset($data['email'], $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email or password']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('SELECT * FROM users WHERE email = ?');
$db->bind('s', $data['email']);
$user = $db->single();

if (!$user || !password_verify($data['password'], $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
    exit;
}

$token = bin2hex(random_bytes(32));
$db->query('INSERT INTO api_tokens (user_id, token, created_at) VALUES (?, ?, NOW())');
$db->bind('is', $user['id'], $token);
$db->execute();

http_response_code(200);
echo json_encode(['token' => $token, 'user_id' => $user['id'], 'role' => $user['role']]);
?>
