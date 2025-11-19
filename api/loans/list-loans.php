<?php
/**
 * Get Loans API Endpoint
 * Retrieve user's loan information with filtering and pagination
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/auth.php';

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

    // Get query parameters
    $status = $_GET['status'] ?? 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sortBy = $_GET['sort_by'] ?? 'created_at';
    $sortOrder = $_GET['sort_order'] ?? 'DESC';

    // Validate parameters
    $validStatuses = ['all', 'pending', 'active', 'completed', 'defaulted', 'rejected'];
    if (!in_array($status, $validStatuses)) {
        $status = 'all';
    }

    $validSortFields = ['created_at', 'loan_amount', 'status', 'next_payment_date'];
    if (!in_array($sortBy, $validSortFields)) {
        $sortBy = 'created_at';
    }

    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

    // Build WHERE clause
    $whereClause = "WHERE l.borrower_id = ?";
    $params = [$userId];

    if ($status !== 'all') {
        $whereClause .= " AND l.status = ?";
        $params[] = $status;
    }

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM loans l $whereClause";
    $totalResult = $db->fetchOne($countSql, $params);
    $totalLoans = $totalResult['total'];

    // Get loans with additional data
    $sql = "
        SELECT
            l.*,
            CASE
                WHEN l.status = 'active' THEN (
                    SELECT MIN(due_date)
                    FROM repayment_schedule
                    WHERE loan_id = l.id AND status = 'pending'
                )
                ELSE NULL
            END as next_payment_date,
            CASE
                WHEN l.status IN ('active', 'completed') THEN (
                    SELECT COALESCE(SUM(amount_paid), 0)
                    FROM repayment_schedule
                    WHERE loan_id = l.id AND status = 'completed'
                )
                ELSE 0
            END as total_repaid,
            CASE
                WHEN l.status IN ('active', 'completed') THEN (
                    SELECT COALESCE(SUM(amount_due), 0)
                    FROM repayment_schedule
                    WHERE loan_id = l.id
                )
                ELSE 0
            END as total_due,
            (
                SELECT COUNT(*)
                FROM repayment_schedule
                WHERE loan_id = l.id AND status = 'pending'
            ) as remaining_payments,
            (
                SELECT COUNT(*)
                FROM repayment_schedule
                WHERE loan_id = l.id AND status = 'completed'
            ) as completed_payments,
            cse.credit_score as credit_score_at_application,
            cse.risk_category as risk_category_at_application
        FROM loans l
        LEFT JOIN credit_score_evaluations cse ON cse.user_id = l.borrower_id
            AND cse.created_at <= l.created_at
        $whereClause
        ORDER BY l.$sortBy $sortOrder
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $loans = $db->fetchAll($sql, $params);

    // Enrich loan data
    foreach ($loans as &$loan) {
        // Calculate remaining balance
        $loan['remaining_balance'] = max(0, $loan['total_due'] - $loan['total_repaid']);

        // Calculate progress percentage
        $loan['repayment_progress'] = $loan['total_due'] > 0
            ? round(($loan['total_repaid'] / $loan['total_due']) * 100, 2)
            : 0;

        // Determine if overdue
        $loan['is_overdue'] = $loan['status'] === 'active' && $loan['next_payment_date']
            && $loan['next_payment_date'] < date('Y-m-d');

        // Calculate days until next payment
        if ($loan['next_payment_date']) {
            $nextPaymentDate = new DateTime($loan['next_payment_date']);
            $today = new DateTime();
            $loan['days_until_next_payment'] = $today->diff($nextPaymentDate)->days;
            if ($nextPaymentDate < $today) {
                $loan['days_until_next_payment'] = -$loan['days_until_next_payment'];
            }
        }

        // Format amounts
        $loan['loan_amount_formatted'] = number_format($loan['loan_amount'], 0);
        $loan['total_repaid_formatted'] = number_format($loan['total_repaid'], 0);
        $loan['remaining_balance_formatted'] = number_format($loan['remaining_balance'], 0);

        // Add status display
        $loan['status_display'] = ucwords(str_replace('_', ' ', $loan['status']));

        // Add risk category display
        if ($loan['risk_category_at_application']) {
            $loan['risk_category_display'] = ucwords(str_replace('_', ' ', $loan['risk_category_at_application']));
        }
    }

    // Calculate pagination info
    $totalPages = ceil($totalLoans / $limit);
    $hasNextPage = $offset + $limit < $totalLoans;
    $hasPrevPage = $offset > 0;

    echo json_encode([
        'success' => true,
        'loans' => $loans,
        'pagination' => [
            'total' => $totalLoans,
            'limit' => $limit,
            'offset' => $offset,
            'current_page' => floor($offset / $limit) + 1,
            'total_pages' => $totalPages,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'filters' => [
            'status' => $status,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ],
        'summary' => [
            'total_loans' => $totalLoans,
            'active_loans' => count(array_filter($loans, fn($loan) => $loan['status'] === 'active')),
            'completed_loans' => count(array_filter($loans, fn($loan) => $loan['status'] === 'completed')),
            'total_borrowed' => array_sum(array_column($loans, 'loan_amount')),
            'total_repaid' => array_sum(array_column($loans, 'total_repaid')),
            'outstanding_balance' => array_sum(array_column($loans, 'remaining_balance'))
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve loans: ' . $e->getMessage(),
        'error_code' => 'LOANS_RETRIEVAL_ERROR'
    ]);
}
?>