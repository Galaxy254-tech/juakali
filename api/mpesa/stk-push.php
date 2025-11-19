<?php
/**
 * M-Pesa STK Push API Endpoint
 * Initiate M-Pesa payments for loan repayments and other transactions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/mpesa.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'error_code' => 'AUTHENTICATION_REQUIRED'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'];

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $transactionType = $input['transaction_type'] ?? 'loan_repayment';

    switch ($transactionType) {
        case 'loan_repayment':
            handleLoanRepayment($db, $input, $userId);
            break;
        case 'wallet_topup':
            handleWalletTopup($db, $input, $userId);
            break;
        case 'goods_payment':
            handleGoodsPayment($db, $input, $userId);
            break;
        default:
            throw new Exception('Invalid transaction type');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'MPESA_STK_PUSH_ERROR'
    ]);
}

function handleLoanRepayment($db, $input, $userId) {
    // Validate required fields
    $requiredFields = ['loan_id', 'amount'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $loanId = (int)$input['loan_id'];
    $amount = (float)$input['amount'];
    $phoneNumber = $input['phone_number'] ?? null;

    // Validate amount
    if ($amount < 100 || $amount > 50000) {
        throw new Exception('Amount must be between KES 100 and KES 50,000');
    }

    // Verify loan ownership
    $loan = $db->fetchOne("
        SELECT * FROM loans
        WHERE id = ? AND borrower_id = ? AND status = 'active'
    ", [$loanId, $userId]);

    if (!$loan) {
        throw new Exception('Invalid or inactive loan');
    }

    // Get user's phone number if not provided
    if (!$phoneNumber) {
        $user = $db->fetchOne("SELECT phone FROM users WHERE id = ?", [$userId]);
        $phoneNumber = $user['phone'];
    }

    // Check for pending repayments
    $pendingRepayment = $db->fetchOne("
        SELECT * FROM repayment_schedule
        WHERE loan_id = ? AND status = 'pending'
        ORDER BY due_date ASC
        LIMIT 1
    ", [$loanId]);

    if (!$pendingRepayment) {
        throw new Exception('No pending repayments found');
    }

    // Check if amount exceeds due amount
    if ($amount > $pendingRepayment['amount_due']) {
        throw new Exception('Amount exceeds the due payment of KES ' . number_format($pendingRepayment['amount_due'], 0));
    }

    // Check for existing pending STK requests
    $existingRequest = $db->fetchOne("
        SELECT * FROM mpesa_transactions
        WHERE loan_id = ? AND phone_number = ? AND status = 'pending'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ", [$loanId, $phoneNumber]);

    if ($existingRequest) {
        throw new Exception('A payment request is already in progress. Please wait for the current request to complete.');
    }

    // Create M-Pesa instance
    $mpesa = new M_Pesa();

    // Generate account reference
    $accountReference = 'LOAN' . str_pad($loanId, 6, '0', STR_PAD_LEFT);
    $transactionDesc = "Loan repayment for loan #$loanId";

    // Initiate STK Push
    try {
        $result = $mpesa->sendPaymentRequest($phoneNumber, $amount, $loanId, $transactionDesc);

        if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            $checkoutRequestID = $result['CheckoutRequestID'];

            // Save transaction record
            $db->execute("
                INSERT INTO mpesa_transactions (
                    checkout_request_id, phone_number, amount, loan_id,
                    account_reference, transaction_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'loan_repayment', 'pending', NOW())
            ", [
                $checkoutRequestID, $phoneNumber, $amount, $loanId,
                $accountReference
            ]);

            // Create repayment record
            $repaymentId = $db->execute("
                INSERT INTO loan_repayments (
                    loan_id, amount, payment_method, phone_number,
                    checkout_request_id, status, created_at
                ) VALUES (?, ?, 'mpesa', ?, ?, 'pending', NOW())
            ", [$loanId, $amount, $phoneNumber, $checkoutRequestID]);

            echo json_encode([
                'success' => true,
                'message' => 'Payment request sent to your phone. Please enter your M-Pesa PIN to complete the payment.',
                'checkout_request_id' => $checkoutRequestID,
                'transaction_details' => [
                    'amount' => $amount,
                    'phone_number' => $phoneNumber,
                    'account_reference' => $accountReference,
                    'transaction_desc' => $transactionDesc
                ],
                'next_steps' => 'Check your phone for M-Pesa prompt and enter your PIN to complete payment'
            ]);

        } else {
            throw new Exception('Failed to initiate M-Pesa payment: ' . ($result['errorMessage'] ?? 'Unknown error'));
        }

    } catch (Exception $e) {
        throw new Exception('M-Pesa payment initiation failed: ' . $e->getMessage());
    }
}

function handleWalletTopup($db, $input, $userId) {
    // Validate required fields
    $requiredFields = ['amount'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $amount = (float)$input['amount'];
    $phoneNumber = $input['phone_number'] ?? null;

    // Validate amount
    if ($amount < 100 || $amount > 100000) {
        throw new Exception('Amount must be between KES 100 and KES 100,000');
    }

    // Get user's phone number if not provided
    if (!$phoneNumber) {
        $user = $db->fetchOne("SELECT phone FROM users WHERE id = ?", [$userId]);
        $phoneNumber = $user['phone'];
    }

    // Create M-Pesa instance
    $mpesa = new M_Pesa();

    // Generate account reference
    $accountReference = 'WALLET' . str_pad($userId, 6, '0', STR_PAD_LEFT);
    $transactionDesc = "Wallet topup for user #$userId";

    // Initiate STK Push
    try {
        $result = $mpesa->sendSTKPush($phoneNumber, $amount, $accountReference, $transactionDesc);

        if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            $checkoutRequestID = $result['CheckoutRequestID'];

            // Save transaction record
            $db->execute("
                INSERT INTO mpesa_transactions (
                    checkout_request_id, phone_number, amount, user_id,
                    account_reference, transaction_type, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'wallet_topup', 'pending', NOW())
            ", [
                $checkoutRequestID, $phoneNumber, $amount, $userId,
                $accountReference
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Wallet topup request sent to your phone',
                'checkout_request_id' => $checkoutRequestID,
                'transaction_details' => [
                    'amount' => $amount,
                    'phone_number' => $phoneNumber,
                    'account_reference' => $accountReference,
                    'transaction_desc' => $transactionDesc
                ]
            ]);

        } else {
            throw new Exception('Failed to initiate wallet topup: ' . ($result['errorMessage'] ?? 'Unknown error'));
        }

    } catch (Exception $e) {
        throw new Exception('Wallet topup initiation failed: ' . $e->getMessage());
    }
}

function handleGoodsPayment($db, $input, $userId) {
    // Validate required fields
    $requiredFields = ['order_id', 'amount', 'supplier_id'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $orderId = (int)$input['order_id'];
    $amount = (float)$input['amount'];
    $supplierId = (int)$input['supplier_id'];
    $phoneNumber = $input['phone_number'] ?? null;

    // Validate order ownership
    $order = $db->fetchOne("
        SELECT o.*, s.phone as supplier_phone
        FROM orders o
        JOIN suppliers s ON o.supplier_id = s.id
        WHERE o.id = ? AND o.retailer_id = ? AND o.status = 'pending_payment'
    ", [$orderId, $userId]);

    if (!$order) {
        throw new Exception('Invalid order or order not ready for payment');
    }

    // Check amount matches order total
    if (abs($amount - $order['total_amount']) > 1) {
        throw new Exception('Payment amount does not match order total');
    }

    // Use supplier's phone number if not provided
    if (!$phoneNumber) {
        $phoneNumber = $order['supplier_phone'];
    }

    // Create M-Pesa instance
    $mpesa = new M_Pesa();

    // Generate account reference
    $accountReference = 'ORDER' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
    $transactionDesc = "Payment for order #$orderId";

    // Initiate B2C payment to supplier
    try {
        $result = $mpesa->paySupplier($phoneNumber, $amount, $orderId, $transactionDesc);

        if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            // Update order status
            $db->execute("
                UPDATE orders
                SET status = 'paid', paid_at = NOW(), payment_method = 'mpesa'
                WHERE id = ?
            ", [$orderId]);

            echo json_encode([
                'success' => true,
                'message' => 'Payment to supplier initiated successfully',
                'transaction_details' => [
                    'amount' => $amount,
                    'supplier_phone' => $phoneNumber,
                    'order_id' => $orderId,
                    'conversation_id' => $result['ConversationID'] ?? null
                ]
            ]);

        } else {
            throw new Exception('Failed to initiate supplier payment: ' . ($result['errorMessage'] ?? 'Unknown error'));
        }

    } catch (Exception $e) {
        throw new Exception('Supplier payment initiation failed: ' . $e->getMessage());
    }
}
?>