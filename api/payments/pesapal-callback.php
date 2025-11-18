<?php
/**
 * PesaPal IPN Callback Handler
 * Receives and processes payment notifications from PesaPal
 */

declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../includes/database.php';
require_once '../../integrations/pesapal-gateway.php';

use Integrations\PesaPalGateway;

try {
    $db = \Includes\Database::getInstance()->getConnection();
    
    $pesapal = new PesaPalGateway($db, [
        'consumer_key' => $_ENV['PESAPAL_CONSUMER_KEY'] ?? '',
        'consumer_secret' => $_ENV['PESAPAL_CONSUMER_SECRET'] ?? '',
        'test_mode' => ($_ENV['APP_ENV'] ?? 'development') === 'development'
    ]);
    
    // Get POST data
    $postData = $_POST;
    
    // Handle IPN callback
    if ($pesapal->handleIPNCallback($postData)) {
        // Log successful callback
        error_log("PesaPal IPN processed successfully: " . json_encode($postData));
        
        // Return success response to PesaPal
        http_response_code(200);
        echo "pesapal_notification_type=" . urlencode($_POST['pesapal_notification_type'] ?? '') . 
             "&pesapal_transaction_tracking_id=" . urlencode($_POST['pesapal_transaction_tracking_id'] ?? '') .
             "&pesapal_merchant_reference=" . urlencode($_POST['pesapal_merchant_reference'] ?? '');
    } else {
        http_response_code(400);
        echo "Error processing payment";
    }
} catch (\Exception $e) {
    error_log("PesaPal Callback Error: " . $e->getMessage());
    http_response_code(500);
    echo "Server error";
}
