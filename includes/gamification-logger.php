<?php
/**
 * Comprehensive Logging and Error Handling System for Gamification
 * Provides structured logging, error tracking, and performance monitoring
 */

class GamificationLogger {
    private $logFile;
    private $errorLogFile;
    private $performanceLogFile;
    private $debugMode;
    private $db;

    public function __construct($debugMode = false) {
        $this->debugMode = $debugMode;
        $this->logFile = __DIR__ . '/../logs/gamification.log';
        $this->errorLogFile = __DIR__ . '/../logs/gamification-errors.log';
        $this->performanceLogFile = __DIR__ . '/../logs/gamification-performance.log';

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Initialize database connection for persistent logging
        require_once 'database.php';
        $this->db = Database::getInstance();
    }

    /**
     * Log general gamification events
     */
    public function log($level, $message, $context = [], $userId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'request_id' => $this->generateRequestId()
        ];

        // Write to file
        $logLine = json_encode($logEntry) . PHP_EOL;
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Write to database if available
        if ($this->db && $this->db->isConnected()) {
            try {
                $this->db->execute("
                    INSERT INTO gamification_logs (
                        level, message, context, user_id, ip_address,
                        request_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [
                    $logEntry['level'],
                    $logEntry['message'],
                    json_encode($context),
                    $userId,
                    $logEntry['ip'],
                    $logEntry['request_id'],
                    $timestamp
                ]);
            } catch (Exception $e) {
                // Fallback to file logging only
                error_log("Failed to write to database log: " . $e->getMessage());
            }
        }

        // Debug output
        if ($this->debugMode) {
            echo "[{$timestamp}] [{$level}] {$message} " . json_encode($context) . PHP_EOL;
        }
    }

    /**
     * Log errors with stack trace
     */
    public function logError($message, $exception = null, $context = [], $userId = null) {
        $errorContext = $context;

        if ($exception) {
            $errorContext['exception'] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        $this->log('ERROR', $message, $errorContext, $userId);

        // Write to separate error file
        $timestamp = date('Y-m-d H:i:s');
        $errorLine = "[{$timestamp}] ERROR: {$message}" . PHP_EOL;
        if ($exception) {
            $errorLine .= "Exception: " . $exception->getMessage() . PHP_EOL;
            $errorLine .= "File: " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
            $errorLine .= "Trace: " . $exception->getTraceAsString() . PHP_EOL;
        }
        $errorLine .= "Context: " . json_encode($context) . PHP_EOL . PHP_EOL;

        file_put_contents($this->errorLogFile, $errorLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log performance metrics
     */
    public function logPerformance($operation, $duration, $metadata = [], $userId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $performanceEntry = [
            'timestamp' => $timestamp,
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'metadata' => $metadata,
            'user_id' => $userId
        ];

        // Write to performance log
        $logLine = json_encode($performanceEntry) . PHP_EOL;
        file_put_contents($this->performanceLogFile, $logLine, FILE_APPEND | LOCK_EX);

        // Log slow operations as warnings
        if ($duration > 1.0) { // Operations taking more than 1 second
            $this->log('WARNING', "Slow operation detected: {$operation}", [
                'duration_seconds' => $duration,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'metadata' => $metadata
            ], $userId);
        }
    }

    /**
     * Log user actions for audit trail
     */
    public function logUserAction($userId, $action, $details = []) {
        $this->log('INFO', "User action: {$action}", array_merge([
            'user_id' => $userId,
            'action' => $action,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli'
        ], $details), $userId);

        // Store in database for audit trail
        if ($this->db && $this->db->isConnected()) {
            try {
                $this->db->execute("
                    INSERT INTO gamification_audit_log (
                        user_id, action, details, user_agent, ip_address,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ", [
                    $userId,
                    $action,
                    json_encode($details),
                    $_SERVER['HTTP_USER_AGENT'] ?? 'cli',
                    $_SERVER['REMOTE_ADDR'] ?? 'cli'
                ]);
            } catch (Exception $e) {
                $this->logError("Failed to write audit log", $e, ['user_id' => $userId, 'action' => $action]);
            }
        }
    }

    /**
     * Log API requests and responses
     */
    public function logApiRequest($method, $endpoint, $requestData, $response, $duration, $userId = null) {
        $this->log('INFO', "API Request: {$method} {$endpoint}", [
            'method' => $method,
            'endpoint' => $endpoint,
            'request_data' => $this->sanitizeData($requestData),
            'response_status' => http_response_code(),
            'response_size' => strlen(json_encode($response)),
            'duration_ms' => round($duration * 1000, 2),
            'user_id' => $userId
        ], $userId);
    }

    /**
     * Log security events
     */
    public function logSecurity($event, $severity, $details = [], $userId = null) {
        $this->log($severity, "Security Event: {$event}", array_merge([
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'cli'
        ], $details), $userId);

        // High severity security events should be immediately alerted
        if (in_array($severity, ['CRITICAL', 'ERROR'])) {
            $this->sendSecurityAlert($event, $details, $userId);
        }
    }

    /**
     * Log business metrics
     */
    public function logBusinessMetric($metric, $value, $dimensions = []) {
        $this->log('INFO', "Business Metric: {$metric}", [
            'metric' => $metric,
            'value' => $value,
            'dimensions' => $dimensions,
            'date' => date('Y-m-d')
        ]);

        // Store in metrics table for analytics
        if ($this->db && $this->db->isConnected()) {
            try {
                $this->db->execute("
                    INSERT INTO gamification_metrics (
                        metric_name, metric_value, dimensions, recorded_at
                    ) VALUES (?, ?, ?, NOW())
                ", [
                    $metric,
                    $value,
                    json_encode($dimensions)
                ]);
            } catch (Exception $e) {
                $this->logError("Failed to store business metric", $e, ['metric' => $metric, 'value' => $value]);
            }
        }
    }

    /**
     * Measure and log operation performance
     */
    public function measure($operation, $callback, $context = [], $userId = null) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            $result = $callback();
            $success = true;
        } catch (Exception $e) {
            $result = null;
            $success = false;
            $this->logError("Operation failed: {$operation}", $e, $context, $userId);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        $this->logPerformance($operation, $duration, array_merge([
            'success' => $success,
            'memory_used_bytes' => $memoryUsed,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ], $context), $userId);

        if (!$success) {
            throw $e;
        }

        return $result;
    }

    /**
     * Get recent logs for analysis
     */
    public function getRecentLogs($level = null, $limit = 100, $userId = null) {
        try {
            $whereClause = "1=1";
            $params = [];

            if ($level) {
                $whereClause .= " AND level = ?";
                $params[] = strtoupper($level);
            }

            if ($userId) {
                $whereClause .= " AND user_id = ?";
                $params[] = $userId;
            }

            $logs = $this->db->fetchAll("
                SELECT * FROM gamification_logs
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT ?
            ", array_merge($params, [$limit]));

            // Decode JSON context for each log
            foreach ($logs as &$log) {
                $log['context'] = json_decode($log['context'], true) ?: [];
            }

            return $logs;
        } catch (Exception $e) {
            $this->logError("Failed to retrieve recent logs", $e);
            return [];
        }
    }

    /**
     * Get performance metrics summary
     */
    public function getPerformanceMetrics($hours = 24) {
        try {
            return $this->db->fetchAll("
                SELECT
                    operation,
                    COUNT(*) as count,
                    AVG(duration_ms) as avg_duration_ms,
                    MIN(duration_ms) as min_duration_ms,
                    MAX(duration_ms) as max_duration_ms,
                    AVG(memory_usage) as avg_memory_usage
                FROM gamification_performance_log
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY operation
                ORDER BY avg_duration_ms DESC
            ", [$hours]);
        } catch (Exception $e) {
            $this->logError("Failed to retrieve performance metrics", $e);
            return [];
        }
    }

    /**
     * Generate unique request ID for tracking
     */
    private function generateRequestId() {
        return uniqid('req_', true);
    }

    /**
     * Sanitize sensitive data for logging
     */
    private function sanitizeData($data) {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveFields = ['password', 'token', 'api_key', 'secret', 'credit_card'];
        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '***REDACTED***';
            }
        }

        return $sanitized;
    }

    /**
     * Send security alert for critical events
     */
    private function sendSecurityAlert($event, $details, $userId = null) {
        $alertMessage = "Security Alert: {$event}";
        $alertDetails = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_id' => $userId
        ], $details);

        // Log to system error log for immediate attention
        error_log("GAMIFICATION SECURITY ALERT: " . json_encode($alertDetails));

        // In a real implementation, this would send emails, SMS, or notifications
        // to security administrators
        if (function_exists('mail')) {
            $to = 'security@juakali-lend.com';
            $subject = 'Gamification Security Alert';
            $message = json_encode($alertDetails, JSON_PRETTY_PRINT);
            $headers = 'From: alerts@juakali-lend.com' . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();

            @mail($to, $subject, $message, $headers);
        }
    }

    /**
     * Clean up old logs to prevent disk space issues
     */
    public function cleanupOldLogs($daysToKeep = 30) {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

            // Clean file-based logs
            $this->rotateLogFile($this->logFile, $daysToKeep);
            $this->rotateLogFile($this->errorLogFile, $daysToKeep);
            $this->rotateLogFile($this->performanceLogFile, $daysToKeep);

            // Clean database logs
            if ($this->db && $this->db->isConnected()) {
                $this->db->execute("DELETE FROM gamification_logs WHERE created_at < ?", [$cutoffDate]);
                $this->db->execute("DELETE FROM gamification_performance_log WHERE created_at < ?", [$cutoffDate]);
                $this->db->execute("DELETE FROM gamification_audit_log WHERE created_at < ?", [$cutoffDate]);
            }

            $this->log('INFO', "Log cleanup completed", ['days_to_keep' => $daysToKeep]);

        } catch (Exception $e) {
            $this->logError("Log cleanup failed", $e);
        }
    }

    /**
     * Rotate log files to manage disk space
     */
    private function rotateLogFile($logFile, $daysToKeep) {
        if (!file_exists($logFile)) {
            return;
        }

        // Archive old log file
        $archiveFile = $logFile . '.' . date('Y-m-d') . '.gz';
        if (!file_exists($archiveFile)) {
            $handle = fopen($logFile, 'r');
            $archiveHandle = gzopen($archiveFile, 'w9');

            if ($handle && $archiveHandle) {
                while (!feof($handle)) {
                    gzwrite($archiveHandle, fread($handle, 1024));
                }
                fclose($handle);
                gzclose($archiveHandle);

                // Truncate current log file
                file_put_contents($logFile, '');
            }
        }

        // Delete very old archive files
        $archivePattern = dirname($logFile) . '/' . basename($logFile) . '.*.gz';
        $archiveFiles = glob($archivePattern);
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);

        foreach ($archiveFiles as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth() {
        $health = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Check log files
        $health['checks']['log_files_accessible'] = is_writable(dirname($this->logFile));

        // Check database connection
        $health['checks']['database_connected'] = $this->db && $this->db->isConnected();

        // Check recent errors
        $recentErrors = $this->getRecentLogs('ERROR', 10);
        $health['checks']['recent_errors'] = count($recentErrors);

        // Check performance
        $slowOperations = $this->getPerformanceMetrics(1);
        $health['checks']['slow_operations'] = count(array_filter($slowOperations, function($op) {
            return $op['avg_duration_ms'] > 1000;
        }));

        // Determine overall status
        if (!$health['checks']['database_connected'] || !$health['checks']['log_files_accessible']) {
            $health['status'] = 'critical';
        } elseif ($health['checks']['recent_errors'] > 5 || $health['checks']['slow_operations'] > 3) {
            $health['status'] = 'warning';
        }

        return $health;
    }
}

// Create additional tables for logging if they don't exist
function createLoggingTables() {
    $db = Database::getInstance();

    $tables = [
        "CREATE TABLE IF NOT EXISTS gamification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(10) NOT NULL,
            message TEXT NOT NULL,
            context JSON,
            user_id INT,
            ip_address VARCHAR(45),
            request_id VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_level (level),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at)
        )",

        "CREATE TABLE IF NOT EXISTS gamification_performance_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            operation VARCHAR(100) NOT NULL,
            duration_ms DECIMAL(10,2) NOT NULL,
            memory_usage BIGINT,
            peak_memory BIGINT,
            metadata JSON,
            user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_operation (operation),
            INDEX idx_created_at (created_at),
            INDEX idx_duration_ms (duration_ms)
        )",

        "CREATE TABLE IF NOT EXISTS gamification_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details JSON,
            user_agent TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        )",

        "CREATE TABLE IF NOT EXISTS gamification_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_name VARCHAR(100) NOT NULL,
            metric_value DECIMAL(15,2) NOT NULL,
            dimensions JSON,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_metric_name (metric_name),
            INDEX idx_recorded_at (recorded_at)
        )"
    ];

    foreach ($tables as $sql) {
        try {
            $db->execute($sql);
        } catch (Exception $e) {
            error_log("Failed to create logging table: " . $e->getMessage());
        }
    }
}

// Initialize logging tables
createLoggingTables();
?>