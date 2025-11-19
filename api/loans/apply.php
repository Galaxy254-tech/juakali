<?php
/**
 * Loan Application API Endpoint
 * Process new loan applications with credit scoring and risk assessment
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
require_once '../../integrations/credit-scoring.php';

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
    $creditScoring = new CreditScoringEngine($db);

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    $requiredFields = ['loan_amount', 'loan_purpose', 'repayment_period'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $userId = $_SESSION['user_id'];
    $loanAmount = (float)$input['loan_amount'];
    $loanPurpose = trim($input['loan_purpose']);
    $repaymentPeriod = (int)$input['repayment_period'];

    // Validate loan amount
    if ($loanAmount < 1000 || $loanAmount > 500000) {
        throw new Exception('Loan amount must be between KES 1,000 and KES 500,000');
    }

    // Validate repayment period
    if ($repaymentPeriod < 7 || $repaymentPeriod > 365) {
        throw new Exception('Repayment period must be between 7 and 365 days');
    }

    // Check if user has existing active loans
    $activeLoans = $db->fetchOne("
        SELECT COUNT(*) as count, COALESCE(SUM(loan_amount), 0) as total_amount
        FROM loans
        WHERE borrower_id = ? AND status IN ('active', 'pending')
    ", [$userId]);

    if ($activeLoans['count'] >= 3) {
        throw new Exception('You have reached the maximum number of active loans');
    }

    if ($activeLoans['total_amount'] + $loanAmount > 200000) {
        throw new Exception('Total loan amount cannot exceed KES 200,000');
    }

    // Check for recent rejected applications
    $recentRejection = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM loans
        WHERE borrower_id = ? AND status = 'rejected'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ", [$userId]);

    if ($recentRejection['count'] >= 2) {
        throw new Exception('Please wait at least 7 days before applying again after recent rejection');
    }

    // Perform credit scoring
    $creditScoreResult = $creditScoring->calculateCreditScore($userId);

    if (!$creditScoreResult['success']) {
        throw new Exception('Credit assessment failed: ' . ($creditScoreResult['error'] ?? 'Unknown error'));
    }

    $creditScore = $creditScoreResult['score'];
    $riskCategory = $creditScoreResult['risk_category'];

    // Auto-reject based on credit score
    if ($creditScore < 400) {
        // Create rejected loan application
        $loanId = $db->execute("
            INSERT INTO loans (
                borrower_id, loan_amount, loan_purpose, repayment_period,
                status, interest_rate, credit_score, risk_category,
                rejection_reason, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'rejected', ?, ?, ?, 'Low credit score', NOW(), NOW())
        ", [
            $userId, $loanAmount, $loanPurpose, $repaymentPeriod,
            25.0, $creditScore, $riskCategory
        ]);

        echo json_encode([
            'success' => false,
            'message' => 'Loan application rejected due to low credit score',
            'error_code' => 'CREDIT_SCORE_TOO_LOW',
            'credit_score' => $creditScore,
            'risk_category' => $riskCategory
        ]);
        exit;
    }

    // Calculate interest rate based on risk category
    $interestRates = [
        'very_low' => 12.0,
        'low' => 15.0,
        'medium' => 18.0,
        'high' => 22.0,
        'very_high' => 25.0
    ];

    $interestRate = $interestRates[$riskCategory] ?? 25.0;

    // Calculate total repayment amount
    $totalInterest = ($loanAmount * $interestRate / 100) * ($repaymentPeriod / 365);
    $totalRepayment = $loanAmount + $totalInterest;

    // Create loan application
    $loanId = $db->execute("
        INSERT INTO loans (
            borrower_id, loan_amount, loan_purpose, repayment_period,
            interest_rate, total_repayment, credit_score, risk_category,
            status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ", [
        $userId, $loanAmount, $loanPurpose, $repaymentPeriod,
        $interestRate, $totalRepayment, $creditScore, $riskCategory
    ]);

    if (!$loanId) {
        throw new Exception('Failed to create loan application');
    }

    // Create repayment schedule
    $repaymentFrequency = 'weekly'; // Can be made configurable
    $paymentCount = ceil($repaymentPeriod / 7); // Weekly payments
    $paymentAmount = $totalRepayment / $paymentCount;

    $scheduleCreated = createRepaymentSchedule($db, $loanId, $paymentAmount, $paymentCount, $repaymentFrequency);

    if (!$scheduleCreated) {
        // Rollback loan creation
        $db->execute("DELETE FROM loans WHERE id = ?", [$loanId]);
        throw new Exception('Failed to create repayment schedule');
    }

    // Auto-approve if credit score is high enough
    $autoApprove = $creditScore >= 650 && $loanAmount <= 50000;

    if ($autoApprove) {
        $db->execute("
            UPDATE loans
            SET status = 'active', approved_at = NOW(), approved_by = NULL
            WHERE id = ?
        ", [$loanId]);

        // Log auto-approval
        $db->execute("
            INSERT INTO loan_approval_logs (
                loan_id, approved_by, approval_type, credit_score,
                risk_category, auto_approved, created_at
            ) VALUES (?, NULL, 'automatic', ?, ?, 1, NOW())
        ", [$loanId, $creditScore, $riskCategory]);
    }

    // Get loan details
    $loanDetails = $db->fetchOne("
        SELECT
            l.*,
            u.name as borrower_name,
            u.email as borrower_email,
            u.phone as borrower_phone
        FROM loans l
        JOIN users u ON l.borrower_id = u.id
        WHERE l.id = ?
    ", [$loanId]);

    // Send notification
    if ($autoApprove) {
        // Send loan approval notification
        require_once '../../includes/whatsapp-api.php';
        $whatsapp = new WhatsAppAPI($db);
        $whatsapp->sendLoanApprovalNotification(
            $userId,
            $loanId,
            $loanAmount,
            "Repayment period: $repaymentPeriod days, Interest rate: $interestRate%"
        );
    }

    echo json_encode([
        'success' => true,
        'message' => $autoApprove ? 'Loan approved automatically' : 'Loan application submitted successfully',
        'loan' => [
            'id' => $loanDetails['id'],
            'loan_amount' => $loanDetails['loan_amount'],
            'loan_purpose' => $loanDetails['loan_purpose'],
            'repayment_period' => $loanDetails['repayment_period'],
            'interest_rate' => $loanDetails['interest_rate'],
            'total_repayment' => $loanDetails['total_repayment'],
            'status' => $loanDetails['status'],
            'credit_score' => $loanDetails['credit_score'],
            'risk_category' => $loanDetails['risk_category'],
            'created_at' => $loanDetails['created_at'],
            'approved_at' => $loanDetails['approved_at']
        ],
        'credit_assessment' => [
            'credit_score' => $creditScore,
            'risk_category' => $riskCategory,
            'factors' => $creditScoreResult['features'] ?? []
        ],
        'next_steps' => $autoApprove
            ? 'Your loan has been approved and funds will be disbursed within 24 hours.'
            : 'Your application is under review. You will receive a notification within 24 hours.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'LOAN_APPLICATION_ERROR'
    ]);
}

function createRepaymentSchedule($db, $loanId, $paymentAmount, $paymentCount, $frequency) {
    try {
        $startDate = date('Y-m-d', strtotime('+7 days')); // First payment in 7 days

        for ($i = 0; $i < $paymentCount; $i++) {
            $dueDate = date('Y-m-d', strtotime($startDate . " +$i weeks"));

            $db->execute("
                INSERT INTO repayment_schedule (
                    loan_id, installment_number, due_date, amount_due,
                    status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ", [$loanId, $i + 1, $dueDate, $paymentAmount]);
        }

        return true;
    } catch (Exception $e) {
        error_log('Failed to create repayment schedule: ' . $e->getMessage());
        return false;
    }
}
?>