<?php
/**
 * PesaPal Payment Checkout Page
 * Beautiful, secure payment interface with green gradient theme
 */

declare(strict_types=1);

session_start();

require_once '../../config/config.php';
require_once '../../includes/database.php';
require_once '../../integrations/pesapal-gateway.php';

use Integrations\PesaPalGateway;

// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'retailer') {
    http_response_code(403);
    die('Access Denied');
}

// Validate loan ID from GET/POST
$loanId = (int)($_GET['loan_id'] ?? $_POST['loan_id'] ?? 0);
if ($loanId <= 0) {
    http_response_code(400);
    die('Invalid loan ID');
}

$db = \Includes\Database::getInstance()->getConnection();

// Fetch loan details
$stmt = $db->prepare("
    SELECT l.id, l.loan_amount, l.due_date, u.first_name, u.last_name, u.email, u.phone, 
           o.order_number, rs.amount_due
    FROM loans l
    JOIN users u ON l.retailer_id = u.id
    LEFT JOIN orders o ON l.order_id = o.id
    LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
    WHERE l.id = ? AND l.retailer_id = ?
");
$stmt->execute([$loanId, $_SESSION['user_id']]);
$loan = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$loan) {
    http_response_code(404);
    die('Loan not found');
}

// Process payment request
$iframeUrl = null;
$referenceCode = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pesapal = new PesaPalGateway($db, [
            'consumer_key' => $_ENV['PESAPAL_CONSUMER_KEY'] ?? '',
            'consumer_secret' => $_ENV['PESAPAL_CONSUMER_SECRET'] ?? '',
            'test_mode' => ($_ENV['APP_ENV'] ?? 'development') === 'development'
        ]);
        
        $result = $pesapal->processPayment([
            'amount' => $loan['amount_due'] ?? $loan['loan_amount'],
            'description' => 'Loan Repayment - Order #' . $loan['order_number'],
            'invoice_id' => $loanId,
            'first_name' => $loan['first_name'],
            'last_name' => $loan['last_name'],
            'email' => $loan['email'],
            'phone_number' => $loan['phone'],
            'currency' => 'KES'
        ]);
        
        if ($result['success']) {
            $iframeUrl = $result['iframe_url'];
            $referenceCode = $result['reference_code'];
        } else {
            $error = $result['error'] ?? 'Payment processing failed';
        }
    } catch (\Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PesaPal Payment - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #10b981;
            --primary-dark: #059669;
            --primary-light: #34d399;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            padding: 20px;
        }
        
        .payment-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            animation: slideInUp 0.6s ease-out;
        }
        
        @keyframes slideInUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .payment-header h1 {
            color: var(--gray-900);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .payment-header p {
            color: var(--gray-700);
            font-size: 14px;
            margin: 0;
        }
        
        .loan-details {
            background: var(--gray-50);
            border-left: 4px solid var(--primary-green);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--gray-900);
            font-weight: 700;
            color: var(--primary-green);
        }
        
        .error-alert {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .pesapal-iframe-container {
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .pesapal-iframe-container iframe {
            width: 100%;
            height: 700px;
            border: none;
            display: block;
        }
        
        .payment-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .payment-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);
        }
        
        .payment-button:active {
            transform: translateY(0);
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: var(--primary-green);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .back-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1>🔒 Secure Payment</h1>
            <p>Complete your loan repayment via PesaPal</p>
        </div>
        
        <div class="loan-details">
            <div class="detail-row">
                <span class="detail-label">Order Number</span>
                <span class="detail-value"><?= htmlspecialchars($loan['order_number'] ?? 'N/A') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount to Pay</span>
                <span class="detail-value">KES <?= number_format($loan['amount_due'] ?? $loan['loan_amount'], 2) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Due Date</span>
                <span class="detail-value"><?= date('M d, Y', strtotime($loan['due_date'])) ?></span>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error-alert">
                <strong>⚠ Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($iframeUrl): ?>
            <div class="pesapal-iframe-container">
                <iframe src="<?= htmlspecialchars($iframeUrl) ?>" style="width: 100%; height: 700px; border: none;"></iframe>
            </div>
            <p style="text-align: center; color: var(--gray-700); font-size: 12px; margin-top: 15px;">
                Reference: <?= htmlspecialchars($referenceCode) ?>
            </p>
        <?php else: ?>
            <form method="POST" style="margin-bottom: 20px;">
                <button type="submit" class="payment-button">
                    <span class="loading-spinner" style="display: none;"></span>
                    Proceed to Payment
                </button>
                <script>
                    document.querySelector('.payment-button').addEventListener('click', function() {
                        this.querySelector('.loading-spinner').style.display = 'inline-block';
                        this.disabled = true;
                    });
                </script>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="../../dashboard/retailer/repayments.php">← Back to Repayments</a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
