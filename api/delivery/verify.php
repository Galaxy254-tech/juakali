<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/delivery-verification.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$order_id = $data['order_id'] ?? 0;
$pin = $data['pin'] ?? '';
$latitude = $data['latitude'] ?? 0;
$longitude = $data['longitude'] ?? 0;

if (!$order_id || !$pin) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = new Database();
$db->connect();

// Verify user is field agent or authorized
$db->query('SELECT role FROM users WHERE id = ?');
$db->bind('i', $_SESSION['user_id'] ?? 0);
$user = $db->single();

if (!$user || ($user['role'] !== 'field_agent' && $user['role'] !== 'supplier')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$verification = new DeliveryVerification($db);
$result = $verification->verifyDelivery($order_id, $pin, $latitude, $longitude);

echo json_encode($result);
?>
