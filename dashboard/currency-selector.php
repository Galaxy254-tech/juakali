<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/currency-manager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency = sanitize($_POST['currency'] ?? 'KES');
    
    $db = new Database();
    $db->connect();
    $currency_manager = new CurrencyManager($db);
    
    if ($currency_manager->setUserCurrency($currency)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'currency' => $currency]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid currency']);
    }
    exit;
}
?>
