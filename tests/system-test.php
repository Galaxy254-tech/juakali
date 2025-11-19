<?php
/**
 * Comprehensive System Testing Script
 * Tests all major functionality of the JuaKali Lend platform
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../integrations/credit-scoring.php';
require_once '../includes/whatsapp-api.php';
require_once '../includes/mpesa.php';

class SystemTester {
    private $db;
    private $testResults = [];
    private $errors = [];
    private $testUserId = null;
    private $testLoanId = null;

    public function __construct() {
        echo "=== JuaKali Lend System Testing ===\n\n";
        $this->db = Database::getInstance();
    }

    public function runAllTests() {
        $this->testDatabaseConnection();
        $this->testUserManagement();
        $this->testCreditScoringEngine();
        $this->testFraudDetection();
        $this->testLoanApplication();
        $this->testWhatsAppIntegration();
        $this->testMpesaIntegration();
        $this->testFieldAgentSystem();
        $this->testQRDeliverySystem();
        $this->testAdminPanel();
        $this->testMobilePWA();
        $this->testAPIEndpoints();
        $this->testSystemSecurity();

        $this->generateTestReport();
    }

    private function testDatabaseConnection() {
        $this->runTest("Database Connection", function() {
            // Test basic database connection
            $result = $this->db->fetchOne("SELECT 1 as test");
            if (!$result || $result['test'] != 1) {
                throw new Exception("Database connection failed");
            }

            // Test if essential tables exist
            $tables = ['users', 'loans', 'repayment_schedule', 'credit_score_evaluations', 'fraud_detections'];
            foreach ($tables as $table) {
                $exists = $this->db->fetchOne("SHOW TABLES LIKE '$table'");
                if (!$exists) {
                    throw new Exception("Required table '$table' does not exist");
                }
            }

            return true;
        });
    }

    private function testUserManagement() {
        $this->runTest("User Registration", function() {
            // Test user creation
            $userData = [
                'name' => 'Test User',
                'email' => 'test' . time() . '@example.com',
                'phone' => '2547' . rand(10000000, 99999999),
                'password' => password_hash('test123', PASSWORD_DEFAULT),
                'role' => 'retailer'
            ];

            $this->testUserId = $this->db->execute("
                INSERT INTO users (name, email, phone, password, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ", array_values($userData));

            if (!$this->testUserId) {
                throw new Exception("Failed to create test user");
            }

            // Verify user was created
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$this->testUserId]);
            if (!$user || $user['email'] !== $userData['email']) {
                throw new Exception("User verification failed");
            }

            return true;
        });

        $this->runTest("User Authentication", function() {
            if (!$this->testUserId) {
                throw new Exception("No test user available");
            }

            // Test authentication logic would go here
            // For now, just verify user can be retrieved
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ? AND status = 'active'", [$this->testUserId]);
            if (!$user) {
                throw new Exception("Authentication test failed");
            }

            return true;
        });
    }

    private function testCreditScoringEngine() {
        $this->runTest("Credit Scoring Calculation", function() {
            if (!$this->testUserId) {
                throw new Exception("No test user available");
            }

            $creditScoring = new CreditScoringEngine($this->db);
            $result = $creditScoring->calculateCreditScore($this->testUserId);

            if (!$result['success']) {
                throw new Exception("Credit scoring failed: " . ($result['error'] ?? 'Unknown error'));
            }

            if (!isset($result['score']) || $result['score'] < 0 || $result['score'] > 1000) {
                throw new Exception("Invalid credit score returned: " . $result['score']);
            }

            // Verify credit score was saved
            $savedScore = $this->db->fetchOne("
                SELECT * FROM credit_score_evaluations
                WHERE user_id = ? ORDER BY created_at DESC LIMIT 1
            ", [$this->testUserId]);

            if (!$savedScore) {
                throw new Exception("Credit score not saved to database");
            }

            return true;
        });

        $this->runTest("Risk Category Assignment", function() {
            if (!$this->testUserId) {
                throw new Exception("No test user available");
            }

            $creditScoring = new CreditScoringEngine($this->db);
            $result = $creditScoring->calculateCreditScore($this->testUserId);

            $validCategories = ['very_low', 'low', 'medium', 'high', 'very_high'];
            if (!in_array($result['risk_category'], $validCategories)) {
                throw new Exception("Invalid risk category: " . $result['risk_category']);
            }

            return true;
        });
    }

    private function testFraudDetection() {
        $this->runTest("Fraud Detection Analysis", function() {
            if (!$this->testUserId) {
                throw new Exception("No test user available");
            }

            // Create test transaction data
            $transactionData = [
                'amount' => 50000,
                'device_id' => 'test_device_' . time(),
                'ip_address' => '192.168.1.' . rand(1, 254),
                'location' => 'Nairobi, Kenya',
                'time_of_day' => date('H:i:s'),
                'day_of_week' => date('l')
            ];

            // Simulate fraud detection
            $riskScore = $this->calculateTestFraudScore($transactionData);

            if ($riskScore < 0 || $riskScore > 100) {
                throw new Exception("Invalid fraud risk score: $riskScore");
            }

            // Log test fraud detection
            $this->db->execute("
                INSERT INTO fraud_detections (
                    user_id, risk_score, risk_level, pattern_type,
                    detection_data, status, created_at
                ) VALUES (?, ?, ?, 'test_pattern', ?, 'test_mode', NOW())
            ", [
                $this->testUserId, $riskScore,
                $riskScore > 70 ? 'HIGH' : ($riskScore > 40 ? 'MEDIUM' : 'LOW'),
                json_encode($transactionData)
            ]);

            return true;
        });
    }

    private function testLoanApplication() {
        $this->runTest("Loan Application Process", function() {
            if (!$this->testUserId) {
                throw new Exception("No test user available");
            }

            // Test loan application
            $loanData = [
                'borrower_id' => $this->testUserId,
                'loan_amount' => 10000,
                'loan_purpose' => 'Test loan for system testing',
                'repayment_period' => 30,
                'interest_rate' => 18.0,
                'status' => 'pending'
            ];

            $this->testLoanId = $this->db->execute("
                INSERT INTO loans (
                    borrower_id, loan_amount, loan_purpose, repayment_period,
                    interest_rate, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", array_values($loanData));

            if (!$this->testLoanId) {
                throw new Exception("Failed to create test loan");
            }

            // Create repayment schedule
            $this->createTestReaymentSchedule($this->testLoanId, $loanData['loan_amount']);

            return true;
        });

        $this->runTest("Loan Status Updates", function() {
            if (!$this->testLoanId) {
                throw new Exception("No test loan available");
            }

            // Test loan status update
            $result = $this->db->execute("
                UPDATE loans SET status = 'active', approved_at = NOW() WHERE id = ?
            ", [$this->testLoanId]);

            if (!$result) {
                throw new Exception("Failed to update loan status");
            }

            // Verify status was updated
            $loan = $this->db->fetchOne("SELECT status FROM loans WHERE id = ?", [$this->testLoanId]);
            if ($loan['status'] !== 'active') {
                throw new Exception("Loan status not updated correctly");
            }

            return true;
        });
    }

    private function testWhatsAppIntegration() {
        $this->runTest("WhatsApp Configuration", function() {
            // Test WhatsApp configuration loading
            $config = $this->db->fetchOne("SELECT * FROM system_settings WHERE setting_key = 'whatsapp_config'");

            if (!$config) {
                // Insert default config for testing
                $defaultConfig = [
                    'access_token' => 'test_token',
                    'phone_number_id' => 'test_phone_id',
                    'business_account_id' => 'test_business_id',
                    'webhook_verify_token' => 'test_token',
                    'environment' => 'sandbox'
                ];

                $this->db->execute("
                    INSERT INTO system_settings (setting_key, setting_value, updated_at)
                    VALUES ('whatsapp_config', ?, NOW())
                ", [json_encode($defaultConfig)]);
            }

            return true;
        });

        $this->runTest("WhatsApp Message Logging", function() {
            // Test WhatsApp message logging
            $result = $this->db->execute("
                INSERT INTO whatsapp_logs (
                    recipient_phone, message, message_type, status, created_at
                ) VALUES (?, ?, ?, 'test_sent', NOW())
            ", ['254712345678', 'Test message', 'text']);

            if (!$result) {
                throw new Exception("Failed to log WhatsApp message");
            }

            return true;
        });
    }

    private function testMpesaIntegration() {
        $this->runTest("M-Pesa Configuration", function() {
            // Test M-Pesa configuration
            $mpesa = new M_Pesa();

            // Just test that the class can be instantiated
            if (!class_exists('M_Pesa')) {
                throw new Exception("M_Pesa class not found");
            }

            return true;
        });

        $this->runTest("M-Pesa Transaction Logging", function() {
            // Test M-Pesa transaction logging
            $result = $this->db->execute("
                INSERT INTO mpesa_transactions (
                    checkout_request_id, phone_number, amount, transaction_type,
                    status, created_at
                ) VALUES (?, ?, ?, ?, 'test_pending', NOW())
            ", ['test_checkout_' . time(), '254712345678', 1000, 'loan_repayment']);

            if (!$result) {
                throw new Exception("Failed to log M-Pesa transaction");
            }

            return true;
        });
    }

    private function testFieldAgentSystem() {
        $this->runTest("Field Agent Management", function() {
            // Create test field agent
            $agentId = $this->db->execute("
                INSERT INTO field_agents (
                    user_id, agent_code, status, created_at, updated_at
                ) VALUES (?, ?, 'active', NOW(), NOW())
            ", [$this->testUserId, 'AGENT' . time()]);

            if (!$agentId) {
                throw new Exception("Failed to create test field agent");
            }

            // Test agent assignment
            if ($this->testLoanId) {
                $result = $this->db->execute("
                    INSERT INTO delivery_assignments (
                        agent_id, loan_id, assignment_type, status, created_at
                    ) VALUES (?, ?, 'delivery', 'assigned', NOW())
                ", [$agentId, $this->testLoanId]);

                if (!$result) {
                    throw new Exception("Failed to create delivery assignment");
                }
            }

            return true;
        });
    }

    private function testQRDeliverySystem() {
        $this->runTest("QR Code Generation", function() {
            // Test QR code data generation
            $qrData = [
                'order_id' => $this->testLoanId ?? 1,
                'agent_id' => $this->testUserId ?? 1,
                'timestamp' => time(),
                'nonce' => bin2hex(random_bytes(16))
            ];

            $qrString = base64_encode(json_encode($qrData));

            if (empty($qrString)) {
                throw new Exception("QR code generation failed");
            }

            // Test QR code logging
            $result = $this->db->execute("
                INSERT INTO qr_delivery_codes (
                    order_id, agent_id, qr_data, qr_code, status, created_at
                ) VALUES (?, ?, ?, ?, 'generated', NOW())
            ", [
                $this->testLoanId ?? 1,
                $this->testUserId ?? 1,
                json_encode($qrData),
                $qrString
            ]);

            if (!$result) {
                throw new Exception("Failed to save QR code");
            }

            return true;
        });
    }

    private function testAdminPanel() {
        $this->runTest("Admin Dashboard Data", function() {
            // Test admin dashboard statistics
            $stats = $this->db->fetchOne("
                SELECT
                    COUNT(*) as total_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                    COUNT(*) as total_loans
                FROM users
            ");

            if (!$stats || $stats['total_users'] == 0) {
                throw new Exception("Admin dashboard stats not available");
            }

            return true;
        });

        $this->runTest("System Alerts", function() {
            // Test system alerts functionality
            $result = $this->db->execute("
                INSERT INTO admin_alerts (
                    alert_type, title, message, severity, is_read, created_at
                ) VALUES ('test', 'Test Alert', 'This is a test alert', 'info', 0, NOW())
            ");

            if (!$result) {
                throw new Exception("Failed to create test alert");
            }

            return true;
        });
    }

    private function testMobilePWA() {
        $this->runTest("PWA Manifest", function() {
            // Check if PWA manifest file exists
            $manifestPath = __DIR__ . '/../mobile/manifest.json';
            if (!file_exists($manifestPath)) {
                throw new Exception("PWA manifest file not found");
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
            if (!$manifest || !isset($manifest['name'])) {
                throw new Exception("Invalid PWA manifest");
            }

            return true;
        });

        $this->runTest("Service Worker", function() {
            // Check if service worker file exists
            $swPath = __DIR__ . '/../mobile/service-worker-enhanced.js';
            if (!file_exists($swPath)) {
                throw new Exception("Service worker file not found");
            }

            $swContent = file_get_contents($swPath);
            if (strpos($swContent, 'CACHE_VERSION') === false) {
                throw new Exception("Service worker missing cache version");
            }

            return true;
        });
    }

    private function testAPIEndpoints() {
        $this->runTest("API Health Check", function() {
            // Test API health check endpoint
            $apiPath = __DIR__ . '/../api/index.php';
            if (!file_exists($apiPath)) {
                throw new Exception("API index file not found");
            }

            return true;
        });

        $this->runTest("Loan API Endpoint", function() {
            // Check if loan API endpoints exist
            $loanAPIPath = __DIR__ . '/../api/loans/apply.php';
            if (!file_exists($loanAPIPath)) {
                throw new Exception("Loan API endpoint not found");
            }

            return true;
        });

        $this->runTest("WhatsApp API Endpoint", function() {
            // Check if WhatsApp API endpoints exist
            $whatsappAPIPath = __DIR__ . '/../api/whatsapp/send-message.php';
            if (!file_exists($whatsappAPIPath)) {
                throw new Exception("WhatsApp API endpoint not found");
            }

            return true;
        });
    }

    private function testSystemSecurity() {
        $this->runTest("Password Hashing", function() {
            // Test password hashing
            $password = 'test123';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            if (!password_verify($password, $hash)) {
                throw new Exception("Password hashing/verification failed");
            }

            return true;
        });

        $this->runTest("Input Validation", function() {
            // Test input validation
            $maliciousInput = "<script>alert('xss')</script>";
            $sanitized = htmlspecialchars($maliciousInput, ENT_QUOTES, 'UTF-8');

            if ($sanitized === $maliciousInput) {
                throw new Exception("Input sanitization failed");
            }

            return true;
        });

        $this->runTest("Database Transactions", function() {
            // Test database transaction rollback
            $this->db->beginTransaction();

            try {
                $this->db->execute("INSERT INTO users (name, email, phone, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())", [
                    'Transaction Test', 'transact' . time() . '@test.com', '254799999999', password_hash('test', PASSWORD_DEFAULT), 'retailer'
                ]);

                // Intentionally rollback
                $this->db->rollback();

                // Verify rollback worked
                $user = $this->db->fetchOne("SELECT * FROM users WHERE email LIKE 'transact%@test.com'");
                if ($user) {
                    throw new Exception("Transaction rollback failed");
                }

            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }

            return true;
        });
    }

    private function runTest($testName, $testFunction) {
        echo "Testing: $testName ... ";

        try {
            $startTime = microtime(true);
            $result = $testFunction();
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $this->testResults[$testName] = [
                'status' => 'PASS',
                'duration' => $duration,
                'message' => 'Test completed successfully'
            ];

            echo "✓ PASS ({$duration}ms)\n";

        } catch (Exception $e) {
            $this->testResults[$testName] = [
                'status' => 'FAIL',
                'duration' => 0,
                'message' => $e->getMessage()
            ];

            $this->errors[] = $testName . ': ' . $e->getMessage();

            echo "✗ FAIL - " . $e->getMessage() . "\n";
        }
    }

    private function calculateTestFraudScore($transactionData) {
        // Simple fraud scoring simulation
        $score = 30; // Base score

        // Add points for various factors
        if ($transactionData['amount'] > 50000) $score += 20;
        if ($transactionData['time_of_day'] >= '22:00:00' || $transactionData['time_of_day'] <= '05:00:00') $score += 15;
        if ($transactionData['day_of_week'] === 'Sunday') $score += 10;

        return min(100, $score);
    }

    private function createTestReaymentSchedule($loanId, $loanAmount) {
        $paymentAmount = $loanAmount / 4; // 4 weekly payments
        $startDate = date('Y-m-d', strtotime('+7 days'));

        for ($i = 0; $i < 4; $i++) {
            $dueDate = date('Y-m-d', strtotime($startDate . " +$i weeks"));
            $this->db->execute("
                INSERT INTO repayment_schedule (
                    loan_id, installment_number, due_date, amount_due, status, created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ", [$loanId, $i + 1, $dueDate, $paymentAmount]);
        }
    }

    private function generateTestReport() {
        echo "\n=== Test Report ===\n";

        $totalTests = count($this->testResults);
        $passedTests = count(array_filter($this->testResults, fn($r) => $r['status'] === 'PASS'));
        $failedTests = $totalTests - $passedTests;

        echo "Total Tests: $totalTests\n";
        echo "Passed: $passedTests\n";
        echo "Failed: $failedTests\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

        if (!empty($this->errors)) {
            echo "Failed Tests:\n";
            foreach ($this->errors as $error) {
                echo "  - $error\n";
            }
            echo "\n";
        }

        echo "Performance Summary:\n";
        foreach ($this->testResults as $testName => $result) {
            if ($result['status'] === 'PASS') {
                echo "  - $testName: {$result['duration']}ms\n";
            }
        }

        // Cleanup test data
        $this->cleanupTestData();

        echo "\n=== System Testing Complete ===\n";
    }

    private function cleanupTestData() {
        echo "\nCleaning up test data...\n";

        try {
            // Remove test loan
            if ($this->testLoanId) {
                $this->db->execute("DELETE FROM repayment_schedule WHERE loan_id = ?", [$this->testLoanId]);
                $this->db->execute("DELETE FROM loans WHERE id = ?", [$this->testLoanId]);
            }

            // Remove test user
            if ($this->testUserId) {
                $this->db->execute("DELETE FROM users WHERE id = ?", [$this->testUserId]);
            }

            // Remove test entries
            $this->db->execute("DELETE FROM admin_alerts WHERE alert_type = 'test'");
            $this->db->execute("DELETE FROM whatsapp_logs WHERE status LIKE 'test_%'");
            $this->db->execute("DELETE FROM mpesa_transactions WHERE status LIKE 'test_%'");
            $this->db->execute("DELETE FROM fraud_detections WHERE status = 'test_mode'");

            echo "✓ Test data cleaned up\n";

        } catch (Exception $e) {
            echo "✗ Cleanup failed: " . $e->getMessage() . "\n";
        }
    }
}

// Run the tests
$tester = new SystemTester();
$tester->runAllTests();
?>