<?php
/**
 * Comprehensive API Testing Suite for Gamification System
 * Tests all endpoints with various scenarios and edge cases
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/gamification-system.php';
require_once __DIR__ . '/../includes/gamification-logger.php';

class GamificationAPITest {
    private $db;
    private $logger;
    private $testUserId;
    private $testResults = [];
    private $apiBaseUrl = 'http://localhost/juakali/api/gamification.php';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new GamificationLogger(true);

        // Create test user
        $this->setupTestUser();
    }

    /**
     * Run all API tests
     */
    public function runAllTests() {
        echo "Starting Gamification API Tests...\n\n";

        $this->testUserAuthentication();
        $this->testGetUserProfile();
        $this->testAwardPoints();
        $this->testGetLeaderboard();
        $this->testGetChallenges();
        $this->testJoinChallenge();
        $this->testGetRewards();
        $this->testGetAchievements();
        $this->testGetUserStats();
        $this->testErrorHandling();
        $this->testPerformance();

        $this->printTestSummary();
        return $this->testResults;
    }

    /**
     * Setup test user for testing
     */
    private function setupTestUser() {
        try {
            // Check if test user exists
            $existingUser = $this->db->fetchOne("
                SELECT id FROM users WHERE email = 'test@gamification.com'
            ");

            if ($existingUser) {
                $this->testUserId = $existingUser['id'];
            } else {
                // Create test user
                $this->db->execute("
                    INSERT INTO users (
                        name, email, phone, role, status, created_at
                    ) VALUES (?, ?, ?, 'borrower', 'active', NOW())
                ", [
                    'Test User',
                    'test@gamification.com',
                    '+254700000000'
                ]);

                $this->testUserId = $this->db->getLastInsertId();
            }

            // Initialize gamification profile
            $this->db->execute("
                INSERT INTO user_profiles (
                    user_id, total_points, current_level, streak_days, created_at, updated_at
                ) VALUES (?, 100, 1, 5, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                total_points = VALUES(total_points), updated_at = NOW()
            ", [$this->testUserId]);

            echo "✓ Test user setup completed (ID: {$this->testUserId})\n";

        } catch (Exception $e) {
            echo "✗ Failed to setup test user: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Test user authentication
     */
    private function testUserAuthentication() {
        echo "Testing User Authentication...\n";

        // Test missing authentication
        $result = $this->makeAPIRequest('GET', 'profile', null, false);
        $this->assertTest('missing_auth_returns_401', $result['status_code'] === 401, 'Should return 401 without auth');

        // Test valid authentication (simulated session)
        $_SESSION['user_id'] = $this->testUserId;
        $_SESSION['user_name'] = 'Test User';
        $_SESSION['user_role'] = 'borrower';

        $result = $this->makeAPIRequest('GET', 'profile');
        $this->assertTest('valid_auth_returns_200', $result['status_code'] === 200, 'Should return 200 with valid auth');

        echo "✓ User authentication tests completed\n\n";
    }

    /**
     * Test get user profile endpoint
     */
    private function testGetUserProfile() {
        echo "Testing Get User Profile...\n";

        $result = $this->makeAPIRequest('GET', 'profile');
        $this->assertTest('profile_returns_200', $result['status_code'] === 200, 'Profile should return 200');
        $this->assertTest('profile_has_data', !empty($result['data']['data']['profile']), 'Profile should contain data');
        $this->assertTest('profile_has_points', isset($result['data']['data']['profile']['total_points']), 'Profile should have points');

        echo "✓ Get user profile tests completed\n\n";
    }

    /**
     * Test award points endpoint
     */
    private function testAwardPoints() {
        echo "Testing Award Points...\n";

        // Test valid points award
        $data = [
            'action_type' => 'daily_login',
            'metadata' => ['test' => true]
        ];

        $result = $this->makeAPIRequest('POST', 'award_points', $data);
        $this->assertTest('award_points_returns_200', $result['status_code'] === 200, 'Award points should return 200');
        $this->assertTest('award_points_has_data', isset($result['data']['data']['points_awarded']), 'Should return points awarded');

        // Test invalid action type
        $data = ['action_type' => 'invalid_action'];
        $result = $this->makeAPIRequest('POST', 'award_points', $data);
        $this->assertTest('invalid_action_returns_error', !$result['data']['success'], 'Invalid action should return error');

        // Test missing fields
        $data = [];
        $result = $this->makeAPIRequest('POST', 'award_points', $data);
        $this->assertTest('missing_fields_returns_400', $result['status_code'] === 400, 'Missing fields should return 400');

        echo "✓ Award points tests completed\n\n";
    }

    /**
     * Test get leaderboard endpoint
     */
    private function testGetLeaderboard() {
        echo "Testing Get Leaderboard...\n";

        // Test default leaderboard
        $result = $this->makeAPIRequest('GET', 'leaderboard');
        $this->assertTest('leaderboard_returns_200', $result['status_code'] === 200, 'Leaderboard should return 200');
        $this->assertTest('leaderboard_is_array', is_array($result['data']['data']), 'Leaderboard should be an array');

        // Test with parameters
        $result = $this->makeAPIRequest('GET', 'leaderboard?period=weekly&limit=10');
        $this->assertTest('leaderboard_with_params_returns_200', $result['status_code'] === 200, 'Leaderboard with params should return 200');

        // Test limit validation
        $result = $this->makeAPIRequest('GET', 'leaderboard?limit=200');
        $this->assertTest('leaderboard_limit_capped', count($result['data']['data']) <= 100, 'Leaderboard limit should be capped at 100');

        echo "✓ Get leaderboard tests completed\n\n";
    }

    /**
     * Test get challenges endpoint
     */
    private function testGetChallenges() {
        echo "Testing Get Challenges...\n";

        // First create a test challenge
        $this->createTestChallenge();

        $result = $this->makeAPIRequest('GET', 'challenges');
        $this->assertTest('challenges_returns_200', $result['status_code'] === 200, 'Challenges should return 200');
        $this->assertTest('challenges_is_array', is_array($result['data']['data']), 'Challenges should be an array');

        echo "✓ Get challenges tests completed\n\n";
    }

    /**
     * Test join challenge endpoint
     */
    private function testJoinChallenge() {
        echo "Testing Join Challenge...\n";

        // Create test challenge and get its ID
        $challengeId = $this->createTestChallenge();

        // Test joining challenge
        $data = ['challenge_id' => $challengeId];
        $result = $this->makeAPIRequest('POST', 'join_challenge', $data);
        $this->assertTest('join_challenge_returns_200', $result['status_code'] === 200, 'Join challenge should return 200');
        $this->assertTest('join_challenge_succeeds', $result['data']['success'], 'Join challenge should succeed');

        // Test duplicate join
        $result = $this->makeAPIRequest('POST', 'join_challenge', $data);
        $this->assertTest('duplicate_join_fails', !$result['data']['success'], 'Duplicate join should fail');

        // Test invalid challenge ID
        $data = ['challenge_id' => 99999];
        $result = $this->makeAPIRequest('POST', 'join_challenge', $data);
        $this->assertTest('invalid_challenge_fails', !$result['data']['success'], 'Invalid challenge should fail');

        echo "✓ Join challenge tests completed\n\n";
    }

    /**
     * Test get rewards endpoint
     */
    private function testGetRewards() {
        echo "Testing Get Rewards...\n";

        // Create test reward
        $this->createTestReward();

        $result = $this->makeAPIRequest('GET', 'available_rewards');
        $this->assertTest('rewards_returns_200', $result['status_code'] === 200, 'Rewards should return 200');
        $this->assertTest('rewards_is_array', is_array($result['data']['data']), 'Rewards should be an array');

        echo "✓ Get rewards tests completed\n\n";
    }

    /**
     * Test get achievements endpoint
     */
    private function testGetAchievements() {
        echo "Testing Get Achievements...\n";

        $result = $this->makeAPIRequest('GET', 'achievements');
        $this->assertTest('achievements_returns_200', $result['status_code'] === 200, 'Achievements should return 200');
        $this->assertTest('achievements_is_array', is_array($result['data']['data']), 'Achievements should be an array');

        echo "✓ Get achievements tests completed\n\n";
    }

    /**
     * Test get user stats endpoint
     */
    private function testGetUserStats() {
        echo "Testing Get User Stats...\n";

        $result = $this->makeAPIRequest('GET', 'stats');
        $this->assertTest('stats_returns_200', $result['status_code'] === 200, 'Stats should return 200');
        $this->assertTest('stats_has_data', !empty($result['data']['data']), 'Stats should contain data');

        echo "✓ Get user stats tests completed\n\n";
    }

    /**
     * Test error handling
     */
    private function testErrorHandling() {
        echo "Testing Error Handling...\n";

        // Test invalid endpoint
        $result = $this->makeAPIRequest('GET', 'invalid_endpoint');
        $this->assertTest('invalid_endpoint_returns_400', $result['status_code'] === 400, 'Invalid endpoint should return 400');

        // Test invalid method
        $result = $this->makeAPIRequest('DELETE', 'profile');
        $this->assertTest('invalid_method_returns_405', $result['status_code'] === 405, 'Invalid method should return 405');

        // Test malformed JSON
        $result = $this->makeAPIRequest('POST', 'award_points', 'invalid json', false);
        $this->assertTest('malformed_json_handled', $result['status_code'] === 200, 'Malformed JSON should be handled gracefully');

        echo "✓ Error handling tests completed\n\n";
    }

    /**
     * Test performance
     */
    private function testPerformance() {
        echo "Testing Performance...\n";

        // Test response times
        $startTime = microtime(true);
        $result = $this->makeAPIRequest('GET', 'profile');
        $duration = microtime(true) - $startTime;

        $this->assertTest('profile_response_time', $duration < 1.0, "Profile should respond in under 1 second (took {$duration}s)");

        // Test concurrent requests simulation
        $startTime = microtime(true);
        for ($i = 0; $i < 5; $i++) {
            $this->makeAPIRequest('GET', 'profile');
        }
        $duration = microtime(true) - $startTime;

        $this->assertTest('concurrent_requests', $duration < 3.0, "5 concurrent requests should complete in under 3 seconds (took {$duration}s)");

        echo "✓ Performance tests completed\n\n";
    }

    /**
     * Make API request
     */
    private function makeAPIRequest($method, $endpoint, $data = null, $jsonEncode = true) {
        $url = $this->apiBaseUrl . '?action=' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        // Set cookies for session
        $cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                $payload = $jsonEncode ? json_encode($data) : $data;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload)
                ]);
            }
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseBody = substr($response, $headerSize);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Clean up cookie file
        unlink($cookieFile);

        if ($error) {
            return [
                'status_code' => 500,
                'data' => ['success' => false, 'message' => $error],
                'error' => $error
            ];
        }

        return [
            'status_code' => $statusCode,
            'data' => json_decode($responseBody, true) ?: ['success' => false, 'message' => 'Invalid response'],
            'response_body' => $responseBody
        ];
    }

    /**
     * Create test challenge
     */
    private function createTestChallenge() {
        $this->db->execute("
            INSERT INTO challenges (
                title, description, challenge_type, target_value,
                points_reward, start_date, end_date, status, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active', NOW())
            ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id)
        ", [
            'Test Challenge',
            'A challenge for testing purposes',
            'test_action',
            5,
            100
        ]);

        return $this->db->getLastInsertId();
    }

    /**
     * Create test reward
     */
    private function createTestReward() {
        $this->db->execute("
            INSERT INTO rewards (
                name, description, reward_type, points_cost,
                validity_days, max_per_user, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id)
        ", [
            'Test Reward',
            'A reward for testing purposes',
            'discount',
            50,
            30,
            5
        ]);

        return $this->db->getLastInsertId();
    }

    /**
     * Assert test result
     */
    private function assertTest($testName, $condition, $message) {
        $this->testResults[$testName] = [
            'passed' => $condition,
            'message' => $message
        ];

        $status = $condition ? '✓' : '✗';
        echo "  {$status} {$message}\n";

        if (!$condition) {
            $this->logger->log('ERROR', "Test failed: {$testName}", ['message' => $message]);
        }
    }

    /**
     * Print test summary
     */
    private function printTestSummary() {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($result) {
            return $result['passed'];
        }));
        $failed = $total - $passed;

        echo "\n" . str_repeat("=", 50) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat("=", 50) . "\n";
        echo "Total Tests: {$total}\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        echo "Success Rate: " . round(($passed / $total) * 100, 2) . "%\n";

        if ($failed > 0) {
            echo "\nFAILED TESTS:\n";
            foreach ($this->testResults as $testName => $result) {
                if (!$result['passed']) {
                    echo "  ✗ {$testName}: {$result['message']}\n";
                }
            }
        }

        echo str_repeat("=", 50) . "\n";
    }

    /**
     * Cleanup test data
     */
    public function cleanup() {
        try {
            // Remove test user data
            $this->db->execute("DELETE FROM user_points WHERE user_id = ?", [$this->testUserId]);
            $this->db->execute("DELETE FROM user_badges WHERE user_id = ?", [$this->testUserId]);
            $this->db->execute("DELETE FROM user_profiles WHERE user_id = ?", [$this->testUserId]);
            $this->db->execute("DELETE FROM user_activity WHERE user_id = ?", [$this->testUserId]);
            $this->db->execute("DELETE FROM users WHERE id = ?", [$this->testUserId]);

            // Remove test challenges and rewards
            $this->db->execute("DELETE FROM challenge_participants WHERE user_id = ?", [$this->testUserId]);
            $this->db->execute("DELETE FROM challenges WHERE title = 'Test Challenge'");
            $this->db->execute("DELETE FROM rewards WHERE name = 'Test Reward'");

            echo "✓ Test cleanup completed\n";

        } catch (Exception $e) {
            echo "✗ Cleanup failed: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (php_sapi_name() === 'cli') {
    // Setup session for CLI
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $tester = new GamificationAPITest();

    try {
        $results = $tester->runAllTests();

        // Exit with appropriate code
        $failed = count(array_filter($results, function($result) {
            return !$result['passed'];
        }));

        exit($failed > 0 ? 1 : 0);

    } catch (Exception $e) {
        echo "Test execution failed: " . $e->getMessage() . "\n";
        exit(1);
    } finally {
        $tester->cleanup();
    }
}
?>