<?php
/**
 * USSD Service for JuaKali Lend
 * Enables access via feature phones for users without smartphones
 * Supports loan applications, repayments, balance checks, and account management
 */

header('Content-Type: text/plain; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Log USSD requests for debugging
error_log('USSD Request: ' . file_get_contents('php://input'));

class USSDService {
    private $db;
    private $sessionId;
    private $phoneNumber;
    private $text;
    private $serviceCode;
    private $ussdString;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->parseRequest();
    }

    /**
     * Parse incoming USSD request
     */
    private function parseRequest() {
        $this->sessionId = $_POST['sessionId'] ?? $_GET['sessionId'] ?? '';
        $this->serviceCode = $_POST['serviceCode'] ?? $_GET['serviceCode'] ?? '';
        $this->phoneNumber = $_POST['phoneNumber'] ?? $_GET['phoneNumber'] ?? '';
        $this->text = $_POST['text'] ?? $_GET['text'] ?? '';

        // Store USSD session
        $this->storeSession();
    }

    /**
     * Store USSD session data
     */
    private function storeSession() {
        $this->db->execute("
            INSERT INTO ussd_sessions (
                session_id, phone_number, service_code, input_text,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            input_text = VALUES(input_text), updated_at = NOW()
        ", [
            $this->sessionId,
            $this->phoneNumber,
            $this->serviceCode,
            $this->text
        ]);
    }

    /**
     * Main USSD handler
     */
    public function handleRequest() {
        try {
            // Get or create user session
            $session = $this->getUserSession();

            if (!$session) {
                return $this->startNewSession();
            }

            // Parse user input
            $input = $this->text;
            $inputs = explode('*', $input);
            $currentStep = count($inputs);

            // Route to appropriate handler
            switch ($session['ussd_state']) {
                case 'main_menu':
                    return $this->handleMainMenu($inputs, $currentStep);
                case 'loan_menu':
                    return $this->handleLoanMenu($inputs, $currentStep);
                case 'apply_loan':
                    return $this->handleLoanApplication($inputs, $currentStep);
                case 'repayment_menu':
                    return $this->handleRepaymentMenu($inputs, $currentStep);
                case 'check_balance':
                    return $this->handleBalanceCheck($inputs, $currentStep);
                case 'account_menu':
                    return $this->handleAccountMenu($inputs, $currentStep);
                case 'support_menu':
                    return $this->handleSupportMenu($inputs, $currentStep);
                default:
                    return $this->startNewSession();
            }

        } catch (Exception $e) {
            error_log('USSD Error: ' . $e->getMessage());
            return "END System error. Please try again later.\n";
        }
    }

    /**
     * Get or create user session
     */
    private function getUserSession() {
        $user = $this->db->fetchOne("
            SELECT * FROM users WHERE phone = ? AND status = 'active'
        ", [$this->phoneNumber]);

        if (!$user) {
            return null;
        }

        $session = $this->db->fetchOne("
            SELECT * FROM ussd_sessions
            WHERE session_id = ? AND phone_number = ?
            ORDER BY updated_at DESC LIMIT 1
        ", [$this->sessionId, $this->phoneNumber]);

        if ($session) {
            $session['user'] = $user;
            return $session;
        }

        // Create new session
        $this->db->execute("
            INSERT INTO ussd_sessions (
                session_id, phone_number, service_code, user_id,
                ussd_state, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'main_menu', NOW(), NOW())
        ", [$this->sessionId, $this->phoneNumber, $this->serviceCode, $user['id']]);

        return [
            'user_id' => $user['id'],
            'ussd_state' => 'main_menu',
            'user' => $user
        ];
    }

    /**
     * Start new session
     */
    private function startNewSession() {
        $user = $this->db->fetchOne("
            SELECT * FROM users WHERE phone = ? AND status = 'active'
        ", [$this->phoneNumber]);

        if (!$user) {
            return "CON Welcome to JuaKali Lend!\n";
        }

        return "CON Karibu " . htmlspecialchars($user['name']) . "!\n" .
               "JuaKali Lend Menu:\n" .
               "1. My Loans\n" .
               "2. Apply for Loan\n" .
               "3. Make Payment\n" .
               "4. Check Balance\n" .
               "5. My Account\n" .
               "6. Help & Support\n";
    }

    /**
     * Handle main menu
     */
    private function handleMainMenu($inputs, $currentStep) {
        if ($currentStep == 1) {
            $choice = $inputs[0] ?? '';

            $this->updateSessionState($choice);

            switch ($choice) {
                case '1':
                    return $this->showLoanMenu();
                case '2':
                    return $this->startLoanApplication();
                case '3':
                    return $this->showRepaymentMenu();
                case '4':
                    return $this->showBalanceMenu();
                case '5':
                    return $this->showAccountMenu();
                case '6':
                    return $this->showSupportMenu();
                default:
                    return "CON Invalid choice. Please try again:\n" .
                           "1. My Loans\n2. Apply Loan\n3. Pay\n4. Balance\n5. Account\n6. Help\n";
            }
        }

        return $this->startNewSession();
    }

    /**
     * Show loan menu
     */
    private function showLoanMenu() {
        $userId = $this->getCurrentUserId();
        $loans = $this->db->fetchAll("
            SELECT
                id, loan_number, loan_amount, status, created_at,
                (SELECT SUM(total_due) FROM repayment_schedule WHERE loan_id = loans.id AND status = 'pending') as pending_amount
            FROM loans
            WHERE borrower_id = ? AND status IN ('active', 'completed', 'pending')
            ORDER BY created_at DESC
            LIMIT 3
        ", [$userId]);

        if (empty($loans)) {
            return "CON You have no active loans.\n" .
                   "0. Back to Main Menu\n";
        }

        $menu = "CON Your Loans:\n";
        foreach ($loans as $index => $loan) {
            $status = $this->getLoanStatusIcon($loan['status']);
            $menu .= ($index + 1) . ". " . $status . " KES " . number_format($loan['loan_amount'], 0);
            if ($loan['pending_amount'] > 0) {
                $menu .= " (Due: KES " . number_format($loan['pending_amount'], 0) . ")";
            }
            $menu .= "\n";
        }
        $menu .= "0. Back to Main Menu\n";

        return $menu;
    }

    /**
     * Start loan application
     */
    private function startLoanApplication() {
        $this->updateSessionState('apply_loan');
        return "CON Apply for Loan:\n" .
               "Enter amount (KES 1000-100000):\n";
    }

    /**
     * Handle loan application process
     */
    private function handleLoanApplication($inputs, $currentStep) {
        $userId = $this->getCurrentUserId();

        switch ($currentStep) {
            case 1: // Amount
                $amount = (int)$inputs[0];
                if ($amount < 1000 || $amount > 100000) {
                    return "CON Invalid amount. Enter KES 1000-100000:\n";
                }
                return "CON Loan Amount: KES " . number_format($amount, 0) . "\n" .
                       "Enter loan purpose:\n";

            case 2: // Purpose
                $purpose = $inputs[1];
                if (strlen($purpose) < 3) {
                    return "CON Purpose too short. Enter details:\n";
                }
                return "CON Purpose: " . htmlspecialchars($purpose) . "\n" .
                       "Repayment period (days):\n" .
                       "1. 7 days\n2. 14 days\n3. 30 days\n4. 60 days\n";

            case 3: // Period
                $periodChoice = $inputs[2];
                $periods = [7, 14, 30, 60];
                if (!isset($periods[$periodChoice - 1])) {
                    return "CON Invalid choice. Select period:\n" .
                           "1. 7 days\n2. 14 days\n3. 30 days\n4. 60 days\n";
                }
                $period = $periods[$periodChoice - 1];

                // Get user input from previous steps
                $amount = (int)$inputs[0];
                $purpose = $inputs[1];

                return "CON Confirm Application:\n" .
                       "Amount: KES " . number_format($amount, 0) . "\n" .
                       "Purpose: " . htmlspecialchars($purpose) . "\n" .
                       "Period: {$period} days\n" .
                       "1. Confirm\n2. Cancel\n";
        }

        return "CON Application cancelled.\n0. Main Menu\n";
    }

    /**
     * Show repayment menu
     */
    private function showRepaymentMenu() {
        $userId = $this->getCurrentUserId();
        $overduePayments = $this->db->fetchAll("
            SELECT
                rs.*, l.loan_number,
                DATEDIFF(CURDATE(), rs.due_date) as days_overdue
            FROM repayment_schedule rs
            JOIN loans l ON rs.loan_id = l.id
            WHERE l.borrower_id = ? AND rs.status = 'pending'
            ORDER BY rs.due_date ASC
            LIMIT 3
        ", [$userId]);

        if (empty($overduePayments)) {
            return "CON No pending payments.\n" .
                   "0. Back to Main Menu\n";
        }

        $menu = "CON Pending Payments:\n";
        foreach ($overduePayments as $index => $payment) {
            $daysOverdue = $payment['days_overdue'];
            $overdueText = $daysOverdue > 0 ? " ({$daysOverdue} days late)" : " (Due soon)";
            $menu .= ($index + 1) . ". KES " . number_format($payment['total_due'], 0) . $overdueText . "\n";
        }
        $menu .= "0. Back to Main Menu\n";

        return $menu;
    }

    /**
     * Show balance menu
     */
    private function showBalanceMenu() {
        $userId = $this->getCurrentUserId();

        // Get loan summary
        $summary = $this->db->fetchOne("
            SELECT
                COUNT(*) as total_loans,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_loans,
                COALESCE(SUM(CASE WHEN status = 'active' THEN loan_amount ELSE 0 END), 0) as active_total,
                COALESCE(SUM(CASE WHEN rs.status = 'pending' THEN rs.total_due ELSE 0 END), 0) as pending_payments
            FROM loans l
            LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
            WHERE l.borrower_id = ?
        ", [$userId]);

        return "CON Your Account Summary:\n" .
               "Active Loans: {$summary['active_loans']}\n" .
               "Total Active: KES " . number_format($summary['active_total'], 0) . "\n" .
               "Pending Payments: KES " . number_format($summary['pending_payments'], 0) . "\n" .
               "0. Back to Main Menu\n";
    }

    /**
     * Show account menu
     */
    private function showAccountMenu() {
        $userId = $this->getCurrentUserId();
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

        return "CON My Account:\n" .
               "Name: " . htmlspecialchars($user['name']) . "\n" .
               "Phone: " . htmlspecialchars($user['phone']) . "\n" .
               "Email: " . htmlspecialchars($user['email']) . "\n" .
               "1. Change PIN\n" .
               "2. Update Profile\n" .
               "3. Account Statement\n" .
               "0. Back to Main Menu\n";
    }

    /**
     * Show support menu
     */
    private function showSupportMenu() {
        return "CON Help & Support:\n" .
               "1. Contact Customer Service\n" .
               "2. Report Issue\n" .
               "3. FAQs\n" .
               "4. Call Agent\n" .
               "0. Back to Main Menu\n";
    }

    /**
     * Update session state
     */
    private function updateSessionState($state) {
        $this->db->execute("
            UPDATE ussd_sessions
            SET ussd_state = ?, updated_at = NOW()
            WHERE session_id = ?
        ", [$state, $this->sessionId]);
    }

    /**
     * Get current user ID
     */
    private function getCurrentUserId() {
        $session = $this->db->fetchOne("
            SELECT user_id FROM ussd_sessions
            WHERE session_id = ? AND phone_number = ?
            ORDER BY updated_at DESC LIMIT 1
        ", [$this->sessionId, $this->phoneNumber]);

        return $session['user_id'] ?? null;
    }

    /**
     * Get loan status icon
     */
    private function getLoanStatusIcon($status) {
        $icons = [
            'active' => '🟢',
            'pending' => '🟡',
            'completed' => '✅',
            'defaulted' => '🔴'
        ];

        return $icons[$status] ?? '❓';
    }

    /**
     * Process loan application confirmation
     */
    public function processLoanConfirmation($amount, $purpose, $period, $choice) {
        if ($choice != '1') {
            return "END Application cancelled. Thank you!";
        }

        $userId = $this->getCurrentUserId();

        // Check credit score
        require_once '../integrations/credit-scoring.php';
        $creditScoring = new CreditScoringEngine($this->db);
        $creditResult = $creditScoring->calculateCreditScore($userId);

        if (!$creditResult['success'] || $creditResult['score'] < 400) {
            return "END Application declined. Insufficient credit score. Please contact customer service.";
        }

        // Create loan application
        $interestRate = $creditResult['risk_category'] === 'low' ? 15 : 18;
        $totalRepayment = $amount + ($amount * $interestRate / 100);

        $loanId = $this->db->execute("
            INSERT INTO loans (
                borrower_id, loan_amount, loan_purpose, repayment_period,
                interest_rate, total_repayment, credit_score, risk_category,
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ", [
            $userId, $amount, $purpose, $period,
            $interestRate, $totalRepayment,
            $creditResult['score'], $creditResult['risk_category']
        ]);

        if ($loanId) {
            return "END Application successful! Loan ID: {$loanId}. You will receive confirmation shortly. Thank you!";
        } else {
            return "END Application failed. Please try again later.";
        }
    }

    /**
     * Get USSD statistics
     */
    public function getUSSDStatistics($dateRange = 30) {
        return $this->db->fetchOne("
            SELECT
                COUNT(*) as total_sessions,
                COUNT(DISTINCT phone_number) as unique_users,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as recent_sessions,
                COUNT(CASE WHEN ussd_state = 'apply_loan' THEN 1 END) as loan_applications,
                AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_session_duration
            FROM ussd_sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$dateRange, $dateRange]);
    }
}

// Handle the request
try {
    $ussd = new USSDService();
    echo $ussd->handleRequest();
} catch (Exception $e) {
    error_log('USSD Service Error: ' . $e->getMessage());
    echo "END Service temporarily unavailable. Please try again later.\n";
}
?>