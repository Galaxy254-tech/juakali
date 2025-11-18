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

$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;
$offset = ($page - 1) * $limit;

$db = new Database();
$db->connect();

$db->query('SELECT p.*, u.company_name as supplier_name FROM products p 
           JOIN users u ON p.supplier_id = u.id 
           LIMIT ? OFFSET ?');
$db->bind('ii', $limit, $offset);
$products = $db->resultSet();

$db->query('SELECT COUNT(*) as count FROM products');
$total = $db->single()['count'];

http_response_code(200);
echo json_encode([
    'data' => $products,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);
?>
