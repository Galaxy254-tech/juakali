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

$db = new Database();
$db->connect();

$db->query('SELECT COUNT(*) as total_users FROM users');
$total_users = $db->single()['total_users'];

$db->query('SELECT COUNT(*) as total_orders FROM orders');
$total_orders = $db->single()['total_orders'];

$db->query('SELECT SUM(total_amount) as total_revenue FROM orders WHERE status = "delivered"');
$total_revenue = $db->single()['total_revenue'] ?? 0;

$db->query('SELECT COUNT(*) as total_loans FROM loans');
$total_loans = $db->single()['total_loans'];

$db->query('SELECT SUM(loan_amount) as total_disbursed FROM loans WHERE status IN ("disbursed", "repaid")');
$total_disbursed = $db->single()['total_disbursed'] ?? 0;

http_response_code(200);
echo json_encode([
    'total_users' => $total_users,
    'total_orders' => $total_orders,
    'total_revenue' => $total_revenue,
    'total_loans' => $total_loans,
    'total_disbursed' => $total_disbursed
]);
?>
