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

$product_id = $_GET['product_id'] ?? null;
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$offset = ($page - 1) * $limit;

$db = new Database();
$db->connect();

if ($product_id) {
    $db->query('SELECT * FROM product_reviews WHERE product_id = ? LIMIT ? OFFSET ?');
    $db->bind('iii', $product_id, $limit, $offset);
} else {
    $db->query('SELECT * FROM product_reviews LIMIT ? OFFSET ?');
    $db->bind('ii', $limit, $offset);
}

$reviews = $db->resultSet();

http_response_code(200);
echo json_encode(['data' => $reviews, 'page' => $page, 'limit' => $limit]);
?>
