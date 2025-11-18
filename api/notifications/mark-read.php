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

$notification_id = $_GET['id'] ?? null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Notification ID required']);
    exit;
}

$db = new Database();
$db->connect();

$db->query('UPDATE notifications SET is_read = TRUE WHERE id = ?');
$db->bind('i', $notification_id);

if ($db->execute()) {
    http_response_code(200);
    echo json_encode(['message' => 'Notification marked as read']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update notification']);
}
?>
