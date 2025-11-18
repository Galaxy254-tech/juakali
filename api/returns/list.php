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

$status = $_GET['status'] ?? null;
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$offset = ($page - 1) * $limit;

$db = new Database();
$db->connect();

if ($status) {
    $db->query('SELECT * FROM return_requests WHERE status = ? LIMIT ? OFFSET ?');
    $db->bind('sii', $status, $limit, $offset);
} else {
    $db->query('SELECT * FROM return_requests LIMIT ? OFFSET ?');
    $db->bind('ii', $limit, $offset);
}

$returns = $db->resultSet();

http_response_code(200);
echo json_encode(['data' => $returns, 'page' => $page, 'limit' => $limit]);
?>
