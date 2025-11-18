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

if (!$data || !isset($data['email'], $data['password'], $data['role'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('SELECT id FROM users WHERE email = ?');
$db->bind('s', $data['email']);
if ($db->single()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit;
}

$hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);

$db->query('INSERT INTO users (email, password, role, first_name, last_name, company_name, phone, created_at) 
           VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
$db->bind('sssssss', $data['email'], $hashed_password, $data['role'], 
          $data['first_name'] ?? '', $data['last_name'] ?? '', 
          $data['company_name'] ?? '', $data['phone'] ?? '');

if ($db->execute()) {
    http_response_code(201);
    echo json_encode(['message' => 'User registered successfully', 'user_id' => $db->lastInsertId()]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed']);
}
?>
