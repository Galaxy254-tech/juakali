<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/currency-manager.php';

header('Content-Type: application/json');

$from_currency = sanitize($_GET['from'] ?? 'KES');
$to_currency = sanitize($_GET['to'] ?? 'USD');
$amount = floatval($_GET['amount'] ?? 1);

$db = new Database();
$db->connect();
$currency_manager = new CurrencyManager($db);

$rate = $currency_manager->getExchangeRate($from_currency, $to_currency);
$converted = $currency_manager->convertAmount($amount, $from_currency, $to_currency);

echo json_encode([
    'success' => true,
    'from_currency' => $from_currency,
    'to_currency' => $to_currency,
    'rate' => $rate,
    'amount_original' => $amount,
    'amount_converted' => $converted
]);
?>
