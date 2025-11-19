<?php
/**
 * M-Pesa Callback Handler
 * Processes M-Pesa payment callbacks and updates transaction statuses
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/mpesa.php';

try {
    $db = Database::getInstance();

    // Get the raw POST data
    $input = file_get_contents('php://input');
    $callbackData = json_decode($input, true);

    if (!$callbackData) {
        error_log('M-Pesa callback: Invalid JSON received');
        http_response_code(400);
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON']);
        exit;
    }

    // Log the callback for debugging
    error_log('M-Pesa callback received: ' . $input);

    $mpesa = new M_Pesa();
    $result = $mpesa->handleCallback($callbackData);

    if ($result['success']) {
        // Process the successful callback
        processSuccessfulCallback($db, $callbackData);

        // Respond to M-Pesa
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback processed successfully'
        ]);
    } else {
        error_log('M-Pesa callback processing failed: ' . ($result['error'] ?? 'Unknown error'));
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Callback processing failed'
        ]);
    }

} catch (Exception $e) {
    error_log('M-Pesa callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Internal server error'
    ]);
}

function processSuccessfulCallback($db, $callbackData) {
    $body = $callbackData['Body'] ?? [];
    $stkCallback = $body['stkCallback'] ?? [];

    $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
    $resultCode = $stkCallback['ResultCode'] ?? null;
    $resultDesc = $stkCallback['ResultDesc'] ?? null;
    $merchantRequestID = $stkCallback['MerchantRequestID'] ?? null;

    // Find the transaction
    $transaction = $db->fetchOne("
        SELECT * FROM mpesa_transactions
        WHERE checkout_request_id = ? AND status = 'pending'
    ", [$checkoutRequestID]);

    if (!$transaction) {
        error_log('M-Pesa callback: Transaction not found for CheckoutRequestID: ' . $checkoutRequestID);
        return;
    }

    // Update transaction status
    if ($resultCode === '0') {
        // Payment successful
        $callbackMetadata = $stkCallback['CallbackMetadata'] ?? [];
        $metadataItems = $callbackMetadata['Item'] ?? [];

        $amount = 0;
        $mpesaReceiptNumber = '';
        $transactionDate = '';
        $phoneNumber = '';

        foreach ($metadataItems as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $amount = (float)$item['Value'];
                    break;
                case 'MpesaReceiptNumber':
                    $mpesaReceiptNumber = $item['Value'];
                    break;
                case 'TransactionDate':
                    $transactionDate = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $phoneNumber = $item['Value'];
                    break;
            }
        }

        // Update transaction record
        $db->execute("
            UPDATE mpesa_transactions
            SET status = 'completed', transaction_id = ?, amount = ?,
            phone_number = ?, mpesa_receipt = ?, transaction_date = ?,
            completed_at = NOW(), response_data = ?, updated_at = NOW()
            WHERE id = ?
        ", [
            $mpesaReceiptNumber, $amount, $phoneNumber,
            $mpesaReceiptNumber, $transactionDate,
            json_encode($callbackData), $transaction['id']
        ]);

        // Process based on transaction type
        switch ($transaction['transaction_type']) {
            case 'loan_repayment':
                processLoanRepayment($db, $transaction, $amount, $mpesaReceiptNumber);
                break;
            case 'wallet_topup':
                processWalletTopup($db, $transaction, $amount, $mpesaReceiptNumber);
                break;
            case 'supplier_payment':
                processSupplierPayment($db, $transaction, $amount, $mpesaReceiptNumber);
                break;
        }

        // Send confirmation notification
        sendPaymentConfirmationNotification($db, $transaction, $amount, $mpesaReceiptNumber);

    } else {
        // Payment failed
        $db->execute("
            UPDATE mpesa_transactions
            SET status = 'failed', error_message = ?, response_data = ?,
            updated_at = NOW()
            WHERE id = ?
        ", [$resultDesc, json_encode($callbackData), $transaction['id']]);

        // Update related records based on transaction type
        switch ($transaction['transaction_type']) {
            case 'loan_repayment':
                $db->execute("
                    UPDATE loan_repayments
                    SET status = 'failed', error_message = ?, updated_at = NOW()
                    WHERE checkout_request_id = ?
                ", [$resultDesc, $checkoutRequestID]);
                break;
        }
    }
}

function processLoanRepayment($db, $transaction, $amount, $receiptNumber) {
    $loanId = $transaction['loan_id'];

    if (!$loanId) {
        return;
    }

    // Get pending repayment schedule
    $paymentSchedule = $db->fetchOne("
        SELECT * FROM repayment_schedule
        WHERE loan_id = ? AND status = 'pending'
        ORDER BY due_date ASC
        LIMIT 1
    ", [$loanId]);

    if (!$paymentSchedule) {
        return;
    }

    // Update repayment schedule
    $db->execute("
        UPDATE repayment_schedule
        SET status = 'completed', amount_paid = ?, paid_at = NOW(),
        payment_method = 'mpesa', mpesa_receipt = ?
        WHERE id = ?
    ", [$amount, $receiptNumber, $paymentSchedule['id']]);

    // Update loan repayment record
    $db->execute("
        UPDATE loan_repayments
        SET status = 'completed', amount_paid = ?, paid_at = NOW(),
        mpesa_receipt = ?, response_data = ?
        WHERE checkout_request_id = ?
    ", [$amount, $receiptNumber, json_encode($transaction), $transaction['checkout_request_id']]);

    // Check if loan is fully paid
    $remainingPayments = $db->fetchColumn("
        SELECT COUNT(*) FROM repayment_schedule
        WHERE loan_id = ? AND status = 'pending'
    ", [$loanId]);

    if ($remainingPayments == 0) {
        // Mark loan as completed
        $db->execute("
            UPDATE loans
            SET status = 'completed', completed_at = NOW()
            WHERE id = ?
        ", [$loanId]);

        // Create loan completion record
        $db->execute("
            INSERT INTO loan_completion_logs (loan_id, completion_type, completed_at)
            VALUES (?, 'full_repayment', NOW())
        ", [$loanId]);
    }

    // Update user's credit score based on payment behavior
    updateUserCreditScore($db, $transaction['user_id'] ?? $transaction['borrower_id']);
}

function processWalletTopup($db, $transaction, $amount, $receiptNumber) {
    $userId = $transaction['user_id'];

    if (!$userId) {
        return;
    }

    // Add to user wallet (if wallet system exists)
    $db->execute("
        INSERT INTO user_wallet_transactions (
            user_id, amount, transaction_type, reference,
            status, created_at
        ) VALUES (?, ?, 'credit', ?, 'completed', NOW())
    ", [$userId, $amount, $receiptNumber]);

    // Update user's credit score
    updateUserCreditScore($db, $userId);
}

function processSupplierPayment($db, $transaction, $amount, $receiptNumber) {
    // Update supplier payment status
    $db->execute("
        UPDATE supplier_payments
        SET status = 'completed', paid_at = NOW(),
        mpesa_receipt = ?, updated_at = NOW()
        WHERE checkout_request_id = ?
    ", [$receiptNumber, $transaction['checkout_request_id']]);
}

function updateUserCreditScore($db, $userId) {
    try {
        require_once '../../integrations/credit-scoring.php';
        $creditScoring = new CreditScoringEngine($db);

        // Update behavioral data for positive payment
        $creditScoring->updateBehavioralData($userId, [
            'payment_behavior' => 'on_time_payment',
            'payment_date' => date('Y-m-d'),
            'payment_amount' => 0
        ]);

    } catch (Exception $e) {
        error_log('Failed to update user credit score: ' . $e->getMessage());
    }
}

function sendPaymentConfirmationNotification($db, $transaction, $amount, $receiptNumber) {
    try {
        require_once '../../includes/whatsapp-api.php';
        $whatsapp = new WhatsAppAPI($db);

        $userId = $transaction['user_id'] ?? $transaction['borrower_id'];

        if ($userId && $transaction['transaction_type'] === 'loan_repayment') {
            $message = "Payment confirmation: We have received your loan repayment of KES " . number_format($amount, 0) . ". Receipt: $receiptNumber. Thank you for banking with JuaKali Lend.";
            $whatsapp->sendTextMessage($transaction['phone_number'], $message);
        }

    } catch (Exception $e) {
        error_log('Failed to send payment confirmation notification: ' . $e->getMessage());
    }
}
?>