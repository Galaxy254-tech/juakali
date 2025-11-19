<?php
/**
 * Security Enhancements for Gamification System
 * Provides input validation, rate limiting, fraud detection, and data sanitization
 */

class GamificationSecurity {
    private $db;
    private $logger;
    private $rateLimitWindow = 3600; // 1 hour
    private $maxRequestsPerHour = 1000;
    private $maxPointsPerHour = 10000;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new GamificationLogger(false);
    }

    /**
     * Validate and sanitize input data
     */
    public function validateInput($data, $rules = []) {
        $sanitized = [];
        $errors = [];

        foreach ($data as $key => $value) {
            try {
                $rule = $rules[$key] ?? [];

                // Basic sanitization
                if (is_string($value)) {
                    $value = trim($value);
                    $value = stripslashes($value);
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }

                // Apply validation rules
                $isValid = $this->applyValidationRule($key, $value, $rule);

                if ($isValid === true) {
                    $sanitized[$key] = $value;
                } else {
                    $errors[$key] = $isValid;
                }

            } catch (Exception $e) {
                $errors[$key] = "Invalid input format";
                $this->logger->log('WARNING', "Input validation failed", [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'valid' => empty($errors),
            'data' => $sanitized,
            'errors' => $errors
        ];
    }

    /**
     * Apply specific validation rule
     */
    private function applyValidationRule($key, $value, $rule) {
        // Required validation
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            return "This field is required";
        }

        // Skip further validation if value is empty and not required
        if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
            return true;
        }

        // Type validation
        if (isset($rule['type'])) {
            switch ($rule['type']) {
                case 'int':
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        return "Must be an integer";
                    }
                    $value = (int) $value;
                    break;

                case 'float':
                    if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                        return "Must be a number";
                    }
                    $value = (float) $value;
                    break;

                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        return "Must be a valid email address";
                    }
                    break;

                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        return "Must be a valid URL";
                    }
                    break;

                case 'string':
                    if (!is_string($value)) {
                        return "Must be a string";
                    }
                    break;

                case 'array':
                    if (!is_array($value)) {
                        return "Must be an array";
                    }
                    break;

                case 'json':
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return "Must be valid JSON";
                    }
                    break;
            }
        }

        // Length validation
        if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
            return "Must be at least {$rule['min_length']} characters";
        }

        if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
            return "Must be no more than {$rule['max_length']} characters";
        }

        // Range validation for numbers
        if (isset($rule['min']) && $value < $rule['min']) {
            return "Must be at least {$rule['min']}";
        }

        if (isset($rule['max']) && $value > $rule['max']) {
            return "Must be no more than {$rule['max']}";
        }

        // Pattern validation
        if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
            return "Invalid format";
        }

        // Enum validation
        if (isset($rule['enum']) && !in_array($value, $rule['enum'])) {
            return "Must be one of: " . implode(', ', $rule['enum']);
        }

        // Custom validation
        if (isset($rule['custom']) && is_callable($rule['custom'])) {
            $result = $rule['custom']($value);
            if ($result !== true) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Check rate limiting for API requests
     */
    public function checkRateLimit($userId, $ipAddress, $endpoint) {
        $cacheKey = "rate_limit_{$userId}_{$ipAddress}_{$endpoint}";
        $windowStart = date('Y-m-d H:i:s', time() - $this->rateLimitWindow);

        // Clean old records
        $this->db->execute("
            DELETE FROM api_rate_limit
            WHERE created_at < ?
        ", [$windowStart]);

        // Check current count
        $currentCount = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM api_rate_limit
            WHERE user_id = ? AND ip_address = ? AND endpoint = ?
            AND created_at >= ?
        ", [$userId, $ipAddress, $endpoint, $windowStart]);

        if ($currentCount['count'] >= $this->maxRequestsPerHour) {
            $this->logger->logSecurity('rate_limit_exceeded', 'WARNING', [
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'endpoint' => $endpoint,
                'count' => $currentCount['count']
            ], $userId);

            return false;
        }

        // Record this request
        $this->db->execute("
            INSERT INTO api_rate_limit (
                user_id, ip_address, endpoint, created_at
            ) VALUES (?, ?, ?, NOW())
        ", [$userId, $ipAddress, $endpoint]);

        return true;
    }

    /**
     * Check for suspicious activity patterns
     */
    public function detectSuspiciousActivity($userId, $action, $metadata = []) {
        $suspiciousPatterns = [
            'rapid_point_earning' => $this->checkRapidPointEarning($userId),
            'unusual_behavior' => $this->checkUnusualBehavior($userId),
            'multiple_accounts' => $this->checkMultipleAccounts($userId),
            'fraudulent_actions' => $this->checkFraudulentActions($userId, $action)
        ];

        foreach ($suspiciousPatterns as $pattern => $isSuspicious) {
            if ($isSuspicious) {
                $this->logger->logSecurity("suspicious_activity_{$pattern}", 'WARNING', [
                    'user_id' => $userId,
                    'action' => $action,
                    'metadata' => $metadata
                ], $userId);

                // For high-risk patterns, automatically block
                if (in_array($pattern, ['multiple_accounts', 'fraudulent_actions'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check for rapid point earning patterns
     */
    private function checkRapidPointEarning($userId) {
        $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);

        $pointsEarned = $this->db->fetchOne("
            SELECT COALESCE(SUM(final_points), 0) as total
            FROM user_points
            WHERE user_id = ? AND created_at >= ?
        ", [$userId, $oneHourAgo]);

        return $pointsEarned['total'] > $this->maxPointsPerHour;
    }

    /**
     * Check for unusual behavior patterns
     */
    private function checkUnusualBehavior($userId) {
        // Check for actions outside normal hours (2 AM - 6 AM)
        $currentHour = (int) date('H');
        if ($currentHour >= 2 && $currentHour <= 6) {
            $nightActions = $this->db->fetchOne("
                SELECT COUNT(*) as count FROM user_activity
                WHERE user_id = ? AND HOUR(activity_date) BETWEEN 2 AND 6
                AND DATE(activity_date) = CURDATE()
            ", [$userId]);

            // More than 5 actions during night hours is suspicious
            return $nightActions['count'] > 5;
        }

        return false;
    }

    /**
     * Check for multiple accounts from same IP
     */
    private function checkMultipleAccounts($userId) {
        $user = $this->db->fetchOne("
            SELECT registration_ip FROM users WHERE id = ?
        ", [$userId]);

        if (!$user['registration_ip']) {
            return false;
        }

        $accountsFromIP = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM users
            WHERE registration_ip = ? AND status = 'active'
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", [$user['registration_ip']]);

        // More than 3 accounts from same IP in 30 days is suspicious
        return $accountsFromIP['count'] > 3;
    }

    /**
     * Check for fraudulent actions
     */
    private function checkFraudulentActions($userId, $action) {
        // Check for impossible sequences
        switch ($action) {
            case 'challenge_completed':
                return $this->checkImpossibleChallengeCompletion($userId);

            case 'badge_earned':
                return $this->checkImpossibleBadgeEarning($userId);

            default:
                return false;
        }
    }

    /**
     * Check for impossible challenge completion times
     */
    private function checkImpossibleChallengeCompletion($userId) {
        $recentCompletions = $this->db->fetchAll("
            SELECT cp.*, c.target_value, c.challenge_type
            FROM challenge_participants cp
            JOIN challenges c ON cp.challenge_id = c.id
            WHERE cp.user_id = ? AND cp.completed = 1
            AND cp.completed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ", [$userId]);

        foreach ($recentCompletions as $completion) {
            $timeTaken = strtotime($completion['completed_at']) - strtotime($completion['joined_at']);

            // Completed in less than 1 minute is suspicious for most challenges
            if ($timeTaken < 60 && $completion['target_value'] > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for impossible badge earning times
     */
    private function checkImpossibleBadgeEarning($userId) {
        $recentBadges = $this->db->fetchAll("
            SELECT ub.*, b.badge_code
            FROM user_badges ub
            JOIN badges b ON ub.badge_code = b.badge_code
            WHERE ub.user_id = ? AND ub.earned_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ", [$userId]);

        // Earning more than 3 badges in 5 minutes is suspicious
        return count($recentBadges) > 3;
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        $isValid = hash_equals($_SESSION['csrf_token'], $token);

        if (!$isValid) {
            $this->logger->logSecurity('csrf_token_invalid', 'WARNING', [
                'provided_token' => $token,
                'session_token' => $_SESSION['csrf_token'],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli'
            ]);
        }

        return $isValid;
    }

    /**
     * Encrypt sensitive data
     */
    public function encrypt($data, $key = null) {
        $key = $key ?: $this->getEncryptionKey();
        $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt($encryptedData, $key = null) {
        $key = $key ?: $this->getEncryptionKey();
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * Get encryption key
     */
    private function getEncryptionKey() {
        // In production, this should be stored securely (environment variable, key management service)
        return hash('sha256', 'juakali-gamification-secure-key-2024');
    }

    /**
     * Sanitize metadata for logging
     */
    public function sanitizeMetadata($metadata) {
        if (!is_array($metadata)) {
            return $metadata;
        }

        $sensitiveFields = [
            'password', 'token', 'api_key', 'secret', 'credit_card',
            'ssn', 'bank_account', 'pin', 'cvv'
        ];

        foreach ($metadata as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $metadata[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->sanitizeMetadata($value);
            }
        }

        return $metadata;
    }

    /**
     * Validate API request integrity
     */
    public function validateRequestIntegrity($requestBody, $signature, $timestamp) {
        // Check if timestamp is within acceptable window (5 minutes)
        $currentTime = time();
        $requestTime = $timestamp;

        if (abs($currentTime - $requestTime) > 300) {
            $this->logger->logSecurity('request_timestamp_invalid', 'WARNING', [
                'current_time' => $currentTime,
                'request_time' => $requestTime,
                'difference' => abs($currentTime - $requestTime)
            ]);
            return false;
        }

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $requestBody . $timestamp, $this->getEncryptionKey());

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->logSecurity('request_signature_invalid', 'CRITICAL', [
                'provided_signature' => $signature,
                'expected_signature' => $expectedSignature,
                'request_body' => substr($requestBody, 0, 100) // First 100 chars for debugging
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get security headers for API responses
     */
    public function getSecurityHeaders() {
        return [
            'X-Content-Type-Options: nosniff',
            'X-Frame-Options: DENY',
            'X-XSS-Protection: 1; mode=block',
            'Strict-Transport-Security: max-age=31536000; includeSubDomains',
            'Content-Security-Policy: default-src \'self\'',
            'Referrer-Policy: strict-origin-when-cross-origin'
        ];
    }

    /**
     * Block suspicious user temporarily
     */
    public function blockUser($userId, $reason, $duration = 3600) {
        try {
            $this->db->execute("
                INSERT INTO security_blocks (
                    user_id, reason, blocked_until, created_at
                ) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
            ", [$userId, $reason, $duration]);

            $this->logger->logSecurity('user_blocked', 'CRITICAL', [
                'user_id' => $userId,
                'reason' => $reason,
                'duration' => $duration
            ], $userId);

            return true;

        } catch (Exception $e) {
            $this->logger->logError('Failed to block user', $e, ['user_id' => $userId]);
            return false;
        }
    }

    /**
     * Check if user is blocked
     */
    public function isUserBlocked($userId) {
        $block = $this->db->fetchOne("
            SELECT * FROM security_blocks
            WHERE user_id = ? AND blocked_until > NOW()
        ", [$userId]);

        if ($block) {
            $this->logger->logSecurity('blocked_user_access_attempt', 'WARNING', [
                'user_id' => $userId,
                'blocked_until' => $block['blocked_until'],
                'reason' => $block['reason']
            ], $userId);

            return true;
        }

        return false;
    }

    /**
     * Clean up old security data
     */
    public function cleanup() {
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));

        // Clean old rate limit records
        $this->db->execute("DELETE FROM api_rate_limit WHERE created_at < ?", [$cutoffDate]);

        // Clean old block records
        $this->db->execute("DELETE FROM security_blocks WHERE blocked_until < ?", [$cutoffDate]);

        $this->logger->log('INFO', 'Security cleanup completed', ['cutoff_date' => $cutoffDate]);
    }
}

// Create security tables if they don't exist
function createSecurityTables() {
    $db = Database::getInstance();

    $tables = [
        "CREATE TABLE IF NOT EXISTS api_rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            ip_address VARCHAR(45),
            endpoint VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_endpoint (user_id, endpoint),
            INDEX idx_created_at (created_at),
            INDEX idx_ip_address (ip_address)
        )",

        "CREATE TABLE IF NOT EXISTS security_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reason VARCHAR(200) NOT NULL,
            blocked_until DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_blocked_until (blocked_until)
        )"
    ];

    foreach ($tables as $sql) {
        try {
            $db->execute($sql);
        } catch (Exception $e) {
            error_log("Failed to create security table: " . $e->getMessage());
        }
    }
}

// Initialize security tables
createSecurityTables();
?>