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

$retailer_id = $_GET['retailer_id'] ?? null;
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$offset = ($page - 1) * $limit;

$db = new Database();
$db->connect();

if ($retailer_id) {
    $db->query('SELECT * FROM orders WHERE retailer_id = ? LIMIT ? OFFSET ?');
    $db->bind('iii', $retailer_id, $limit, $offset);
} else {
    $db->query('SELECT * FROM orders LIMIT ? OFFSET ?');
    $db->bind('ii', $limit, $offset);
}

$orders = $db->resultSet();

http_response_code(200);
echo json_encode(['data' => $orders, 'page' => $page, 'limit' => $limit]);
?>
