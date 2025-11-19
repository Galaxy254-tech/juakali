<?php
/**
 * Real-time Monitoring and Alerts System
 * Intelligent alerting, system health monitoring, and performance metrics
 */

class MonitoringSystem {
    private $db;
    private $redis;
    private $notificationChannels;
    private $alertRules;
    private $metricsBuffer = [];
    private $bufferSize = 1000;

    public function __construct($database, $redis = null) {
        $this->db = $database;
        $this->redis = $redis;
        $this->initializeMonitoring();
        $this->loadAlertRules();
        $this->initializeNotificationChannels();
    }

    /**
     * Initialize monitoring system
     */
    private function initializeMonitoring() {
        // Set up error and exception handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);

        // Register shutdown function
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Load alert rules from configuration
     */
    private function loadAlertRules() {
        $this->alertRules = [
            'critical' => [
                'system_down' => [
                    'condition' => 'ping_check == 0',
                    'cooldown' => 300, // 5 minutes
                    'channels' => ['sms', 'email', 'whatsapp', 'slack'],
                    'threshold' => 1
                ],
                'high_error_rate' => [
                    'condition' => 'error_rate > 10',
                    'cooldown' => 600, // 10 minutes
                    'channels' => ['email', 'slack'],
                    'threshold' => 5
                ],
                'database_connection' => [
                    'condition' => 'db_connected == 0',
                    'cooldown' => 180, // 3 minutes
                    'channels' => ['sms', 'email', 'slack'],
                    'threshold' => 1
                ],
                'high_memory_usage' => [
                    'condition' => 'memory_usage > 90',
                    'cooldown' => 300, // 5 minutes
                    'channels' => ['email', 'slack'],
                    'threshold' => 3
                ],
                'disk_space_low' => [
                    'condition' => 'disk_space < 10',
                    'cooldown' => 600, // 10 minutes
                    'channels' => ['email', 'slack'],
                    'threshold' => 5
                ]
            ],
            'warning' => [
                'payment_delays' => [
                    'condition' => 'avg_payment_delay > 7',
                    'cooldown' => 900, // 15 minutes
                    'channels' => ['email'],
                    'threshold' => 3
                ],
                'loan_application_spike' => [
                    'condition' => 'hourly_applications > threshold',
                    'cooldown' => 300, // 5 minutes
                    'channels' => ['email'],
                    'threshold' => 2
                ],
                'fraud_detection_increase' => [
                    'condition' => 'hourly_fraud_cases > 5',
                    'cooldown' => 600, // 10 minutes
                    'channels' => ['email', 'slack'],
                    'threshold' => 1
                ],
                'slow_response_time' => [
                    'condition' => 'avg_response_time > 3000',
                    'cooldown' => 300, // 5 minutes
                    'channels' => ['email'],
                    'threshold' => 3
                ]
            ],
            'info' => [
                'new_user_registration' => [
                    'condition' => 'new_user_count > 0',
                    'cooldown' => 60, // 1 minute
                    'channels' => ['slack'],
                    'threshold' => 1
                ],
                'loan_completion' => [
                    'condition' => 'completed_loans > 0',
                    'cooldown' => 300, // 5 minutes
                    'channels' => ['email'],
                    'threshold' => 1
                ],
                'daily_summary' => [
                    'condition' => 'cron_job == true',
                    'cooldown' => 86400, // 24 hours
                    'channels' => ['email'],
                    'threshold' => 1
                ]
            ]
        ];
    }

    /**
     * Initialize notification channels
     */
    private function initializeNotificationChannels() {
        $this->notificationChannels = [
            'email' => new EmailNotificationChannel($this->db),
            'sms' => new SMSNotificationChannel($this->db),
            'whatsapp' => new WhatsAppNotificationChannel($this->db),
            'slack' => new SlackNotificationChannel(),
            'webhook' => new WebhookNotificationChannel($this->db)
        ];
    }

    /**
     * Collect system metrics
     */
    public function collectMetrics() {
        try {
            $timestamp = microtime(true);
            $metrics = [
                'timestamp' => $timestamp,
                'system' => $this->collectSystemMetrics(),
                'database' => $this->collectDatabaseMetrics(),
                'application' => $this->collectApplicationMetrics(),
                'business' => $this->collectBusinessMetrics(),
                'performance' => $this->collectPerformanceMetrics()
            ];

            // Buffer metrics for trend analysis
            $this->bufferMetrics($metrics);

            // Check alert conditions
            $this->checkAlertConditions($metrics);

            // Store in Redis for real-time dashboard
            if ($this->redis) {
                $this->redis->set('juakali:metrics:latest', json_encode($metrics), 60);
                $this->redis->expire('juakali:metrics:latest', 60);
            }

            return $metrics;

        } catch (Exception $e) {
            $this->handleMonitoringError('METRICS_COLLECTION', $e);
        }
    }

    /**
     * Collect system metrics
     */
    private function collectSystemMetrics() {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        // CPU usage (if available)
        $cpuUsage = $this->getCpuUsage();

        // Disk space
        $diskSpace = $this->getDiskSpace();

        // Network status
        $pingCheck = $this->performPingCheck();

        return [
            'memory_usage' => round(($memoryUsage / 1024 / 1024 / 1024), 2),
            'memory_peak' => round(($memoryPeak / 1024 / 1024 / 1024), 2),
            'memory_limit' => ini_get('memory_limit') / 1024 / 1024,
            'cpu_usage' => $cpuUsage,
            'disk_space' => [
                'total' => $diskSpace['total'],
                'used' => $diskSpace['used'],
                'free' => $diskSpace['free'],
                'usage_percentage' => $diskSpace['usage_percentage']
            ],
            'load_average' => sys_getloadavg(),
            'uptime' => $this->getUptime(),
            'timestamp' => date('Y-m-d H:i:s'),
            'ping_check' => $pingCheck
        ];
    }

    /**
     * Collect database metrics
     */
    private function collectDatabaseMetrics() {
        try {
            $startTime = microtime(true);

            // Test database connection
            $result = $this->db->fetchOne("SELECT 1 as test");

            $responseTime = (microtime(true) - $startTime) * 1000;

            // Get database statistics
            $stats = $this->db->fetchOne("
                SHOW STATUS LIKE 'Threads_connected'
            ");

            // Get slow queries
            $slowQueries = $this->db->fetchAll("
                SELECT query_time, sql_text
                FROM slow_query_log
                WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY query_time DESC
                LIMIT 5
            ");

            // Connection pool metrics
            $poolStats = $this->getDatabasePoolStats();

            return [
                'connected' => !empty($result),
                'response_time' => round($responseTime, 2),
                'connections' => $stats['Value'] ?? 0,
                'slow_queries' => count($slowQueries),
                'avg_query_time' => $this->getAverageQueryTime(),
                'pool_stats' => $poolStats,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Collect application metrics
     */
    private function collectApplicationMetrics() {
        $sessionCount = $this->getSessionCount();
        $activeUsers = $this->getActiveUsersCount();

        // Error tracking
        $errorCount = $this->getErrorCount();
        $warningCount = $this->getWarningCount();

        // Recent activity
        $recentActivity = $this->getRecentActivityCount();

        // Queue metrics
        $queueStats = $this->getQueueStats();

        return [
            'active_sessions' => $sessionCount,
            'active_users' => $activeUsers,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'recent_activity' => $recentActivity,
            'queue_stats' => $queueStats,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Collect business metrics
     */
    private function collectBusinessMetrics() {
        try {
            $metrics = $this->db->fetchOne("
                SELECT
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN u.id END) as daily_active_users,
                    COUNT(DISTINCT l.id) as total_loans,
                    COUNT(DISTINCT CASE WHEN l.status = 'active' THEN l.id END) as active_loans,
                    COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.id END) as completed_loans,
                    COUNT(DISTINCT CASE WHEN l.status = 'defaulted' THEN l.id END) as defaulted_loans,
                    COALESCE(SUM(l.loan_amount), 0) as total_portfolio,
                    COALESCE(SUM(CASE WHEN rs.status = 'completed' THEN rs.amount_paid ELSE 0 END), 0) as total_repaid,
                    COUNT(DISTINCT fd.id) as fraud_cases_today,
                    COUNT(DISTINCT ua.id) as user_actions_today,
                    COUNT(DISTINCT o.id) as orders_today
                FROM users u
                LEFT JOIN loans l ON u.id = l.borrower_id
                LEFT JOIN repayment_schedule rs ON l.id = rs.loan_id
                LEFT JOIN fraud_detections fd ON u.id = fd.user_id AND DATE(fd.created_at) = CURDATE()
                LEFT JOIN user_analytics ua ON u.id = ua.user_id AND DATE(ua.created_at) = CURDATE()
                LEFT JOIN orders o ON o.user_id = u.id AND DATE(o.created_at) = CURDATE()
            ");

            // Hourly trends
            $hourlyTrends = $this->getHourlyTrends();

            return array_merge($metrics, ['hourly_trends' => $hourlyTrends]);

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Collect performance metrics
     */
    private function collectPerformanceMetrics() {
        $apiMetrics = $this->getAPIMetrics();
        $pageLoadMetrics = $this->getPageLoadMetrics();
        $conversionMetrics = $this->getConversionMetrics();

        return [
            'api' => $apiMetrics,
            'page_load' => $pageLoadMetrics,
            'conversion' => $conversionMetrics,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Check alert conditions and trigger alerts
     */
    private function checkAlertConditions($metrics) {
        foreach ($this->alertRules as $severity => $rules) {
            foreach ($rules as $ruleName => $ruleConfig) {
                if ($this->evaluateCondition($ruleConfig['condition'], $metrics)) {
                    $this->triggerAlert($severity, $ruleName, $ruleConfig, $metrics);
                }
            }
        }
    }

    /**
     * Evaluate alert condition
     */
    private function evaluateCondition($condition, $metrics) {
        try {
            // Parse condition and replace placeholders
            $evaluatedCondition = $condition;

            // Replace common placeholders
            $replacements = [
                'ping_check' => $metrics['system']['ping_check'] ?? 0,
                'error_rate' => $metrics['performance']['error_rate'] ?? 0,
                'db_connected' => $metrics['database']['connected'] ? 1 : 0,
                'memory_usage' => $metrics['system']['memory_usage'] ?? 0,
                'disk_space' => $metrics['system']['disk_space']['usage_percentage'] ?? 0,
                'hourly_applications' => $metrics['business']['hourly_trends']['loan_applications'] ?? 0,
                'completed_loans' => $metrics['business']['completed_loans'] ?? 0,
                'avg_response_time' => $metrics['performance']['api']['avg_response_time'] ?? 0,
                'new_user_count' => $metrics['business']['hourly_trends']['new_users'] ?? 0,
                'total_users' => $metrics['business']['total_users'] ?? 0,
                'active_users' => $metrics['business']['daily_active_users'] ?? 0
            ];

            foreach ($replacements as $placeholder => $value) {
                $evaluatedCondition = str_replace($placeholder, $value, $evaluatedCondition);
            }

            // Simple evaluation for basic conditions
            return $this->evaluateSimpleCondition($evaluatedCondition);

        } catch (Exception $e) {
            $this->handleMonitoringError('ALERT_EVALUATION', $e);
            return false;
        }
    }

    /**
     * Evaluate simple conditions
     */
    private function evaluateSimpleCondition($condition) {
        // Basic comparison evaluation
        if (preg_match('/([<>=!]=)\s*([0-9.]+)\.?[0-9]*)/', $condition, $matches)) {
            $operator = $matches[1];
            $value = floatval($matches[2]);
            $threshold = isset($matches[3]) ? floatval($matches[3]) : 0;

            switch ($operator) {
                case '>':
                    return $value > $threshold;
                case '>=':
                    return $value >= $threshold;
                case '<':
                    return $value < $threshold;
                case '<=':
                    return $value <= $threshold;
                case '=':
                case '==':
                    return abs($value - $threshold) < 0.001;
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Trigger alert
     */
    private function triggerAlert($severity, $ruleName, $ruleConfig, $metrics) {
        try {
            // Check cooldown period
            $cooldownKey = "alert:{$severity}:{$ruleName}";
            if ($this->isInCooldown($cooldownKey, $ruleConfig['cooldown'])) {
                return;
            }

            $alertData = [
                'id' => uniqid(),
                'severity' => $severity,
                'rule_name' => $ruleName,
                'title' => $this->generateAlertTitle($severity, $ruleName),
                'message' => $this->generateAlertMessage($severity, $ruleName, $metrics),
                'metrics' => $metrics,
                'triggered_at' => date('Y-m-d H:i:s'),
                'acknowledged' => false,
                'channels' => $ruleConfig['channels']
            ];

            // Save alert to database
            $this->saveAlert($alertData);

            // Send notifications through configured channels
            foreach ($ruleConfig['channels'] as $channel) {
                if (isset($this->notificationChannels[$channel])) {
                    $this->notificationChannels[$channel]->send($alertData);
                }
            }

            // Update cooldown
            $this->updateCooldown($cooldownKey, $ruleConfig['cooldown']);

        } catch (Exception $e) {
            $this->handleMonitoringError('ALERT_TRIGGERING', $e);
        }
    }

    /**
     * Generate alert title
     */
    private function generateAlertTitle($severity, $ruleName) {
        $titles = [
            'critical' => 'CRITICAL',
            'warning' => 'WARNING',
            'info' => 'INFO'
        ];

        $ruleNames = [
            'system_down' => 'System Down',
            'high_error_rate' => 'High Error Rate',
            'database_connection' => 'Database Connection Failed',
            'high_memory_usage' => 'High Memory Usage',
            'disk_space_low' => 'Low Disk Space',
            'payment_delays' => 'Payment Delays',
            'loan_application_spike' => 'Loan Application Spike',
            'fraud_detection_increase' => 'Fraud Detection Increase',
            'slow_response_time' => 'Slow Response Time',
            'new_user_registration' => 'New User Registration',
            'loan_completion' => 'Loan Completed',
            'daily_summary' => 'Daily Summary'
        ];

        return ($titles[$severity] ?? 'INFO') . ': ' . ($ruleNames[$ruleName] ?? $ruleName);
    }

    /**
     * Generate alert message
     */
    private function generateAlertMessage($severity, $ruleName, $metrics) {
        $messages = [
            'system_down' => 'System is down or unresponsive. Immediate attention required.',
            'high_error_rate' => 'Error rate is above threshold. Current: ' . round($metrics['performance']['error_rate'] ?? 0) . '%',
            'database_connection' => 'Database connection failed. Please check database server.',
            'high_memory_usage' => 'Memory usage is at ' . round($metrics['system']['memory_usage'] . 1) . '%. Consider scaling.',
            'disk_space_low' => 'Disk space is critically low. Only ' . round($metrics['system']['disk_space']['free'], 2) . 'GB remaining.',
            'payment_delays' => 'Average payment delay is ' . round($metrics['business']['avg_payment_delay'] ?? 0) . ' days.',
            'loan_application_spike' => 'Spike in loan applications detected. Current hourly: ' . ($metrics['business']['hourly_trends']['loan_applications'] ?? 0),
            'fraud_detection_increase' => 'Increase in fraud cases detected. Current hourly: ' . ($metrics['business']['hourly_trends']['fraud_cases'] ?? 0),
            'slow_response_time' => 'Average response time is ' . round($metrics['performance']['api']['avg_response_time']) . 'ms.',
            'new_user_registration' => 'New user registration detected.',
            'loan_completion' => 'Loan completed successfully.',
            'daily_summary' => 'Daily system performance summary.'
        ];

        return $messages[$ruleName] ?? 'System alert triggered.';
    }

    /**
     * Save alert to database
     */
    private function saveAlert($alertData) {
        try {
            $this->db->execute("
                INSERT INTO system_alerts (
                    alert_id, severity, rule_name, title, message,
                    metrics_data, triggered_at, acknowledged, channels,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $alertData['id'],
                $alertData['severity'],
                $alertData['rule_name'],
                $alertData['title'],
                $alertData['message'],
                json_encode($alertData['metrics']),
                $alertData['triggered_at'],
                $alertData['acknowledged'] ? 1 : 0,
                json_encode($alertData['channels']),
                $alertData['id']
            ]);

        } catch (Exception $e) {
            error_log('Failed to save alert: ' . $e->getMessage());
        }
    }

    /**
     * Check if alert is in cooldown period
     */
    private function isInCooldown($key, $cooldownSeconds) {
        if (!$this->redis) {
            return false;
        }

        $lastAlertTime = $this->redis->get($key);
        if ($lastAlertTime && (time() - $lastAlertTime) < $cooldownSeconds) {
            return true;
        }

        return false;
    }

    /**
     * Update cooldown period
     */
    private function updateCooldown($key, $cooldownSeconds) {
        if ($this->redis) {
            $this->redis->setex($key, time() + $cooldownSeconds, 1);
        }
    }

    /**
     * Get CPU usage
     */
    private function getCpuUsage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0]; // 1-minute average
        }
        return 0;
    }

    /**
     * Get disk space information
     */
    private function getDiskSpace() {
        $total = disk_total_space('/');
        $free = disk_free_space('/');

        return [
            'total' => round($total / 1024 / 1024 / 1024, 2),
            'used' => round(($total - $free) / 1024 / 1024 / 1024, 2),
            'free' => round($free / 1024 / 1024 / 1024, 2),
            'usage_percentage' => round((($total - $free) / $total) * 100, 2)
        ];
    }

    /**
     * Perform ping check
     */
    private function performPingCheck() {
        // Simple ping to Google DNS (8.8.8.8)
        $pingResult = shell_exec('ping -c 1 -W 5 8.8.8.8 2>&1', $output, $returnVar);
        return $returnVar === 0 ? 1 : 0;
    }

    /**
     * Get system uptime
     */
    private function getUptime() {
        if (function_exists('shell_exec')) {
            $uptime = shell_exec('uptime');
            if (preg_match('/up\s+(\d+)\s+days?,/', $uptime, $matches)) {
                return (int)$matches[1];
            }
        }
        return 0;
    }

    /**
     * Get session count
     */
    private function getSessionCount() {
        try {
            return $this->db->fetchColumn("SELECT COUNT(*) FROM sessions WHERE expiry > NOW()");
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get active users count
     */
    private function getActiveUsersCount() {
        try {
            return $this->db->fetchColumn("
                SELECT COUNT(*) FROM users
                WHERE last_login >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get error count
     */
    private function getErrorCount() {
        if (!$this->redis) {
            return 0;
        }

        return $this->redis->get('juakali:metrics:errors:count') ?: 0;
    }

    /**
     * Get warning count
     */
    private function getWarningCount() {
        if (!$this->redis) {
            return 0;
        }

        return $this->redis->get('juakali:metrics:warnings:count') ?: 0;
    }

    /**
     * Get recent activity count
     */
    private function getRecentActivityCount() {
        if (!$this->redis) {
            return 0;
        }

        return $this->redis->get('juakali:metrics:activity:count') ?: 0;
    }

    /**
     * Get queue statistics
     */
    private function getQueueStats() {
        try {
            return $this->db->fetchOne("
                SELECT
                    COUNT(*) as pending_jobs,
                    COUNT(CASE WHEN attempts > 0 THEN 1 END) as processing_jobs,
                    COUNT(CASE WHEN attempts >= 3 THEN 1 END) as failed_jobs,
                    AVG(attempts) as avg_attempts
                FROM job_queue
                WHERE status = 'pending'
            ");
        } catch (Exception $e) {
            return [
                'pending_jobs' => 0,
                'processing_jobs' => 0,
                'failed_jobs' => 0,
                'avg_attempts' => 0
            ];
        }
    }

    /**
     * Get hourly trends
     */
    private function getHourlyTrends() {
        $trends = [];
        for ($i = 23; $i >= 0; $i--) {
            $hour = date('Y-m-d H:00:00', strtotime("-$i hours"));
            $trends[$hour] = [
                'hour' => $hour,
                'loan_applications' => $this->getHourlyLoanApplications($hour),
                'new_users' => $this->getHourlyNewUsers($hour),
                'loan_completions' => $this->getHourlyLoanCompletions($hour),
                'fraud_cases' => $this->getHourlyFraudCases($hour)
            ];
        }
        return $trends;
    }

    /**
     * Get hourly loan applications
     */
    private function getHourlyLoanApplications($hour) {
        try {
            return $this->db->fetchColumn("
                SELECT COUNT(*) FROM loans
                WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 HOUR)
            ", [$hour, $hour]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get hourly new users
     */
    private function getHourlyNewUsers($hour) {
        try {
            return $this->db->fetchColumn("
                SELECT COUNT(*) FROM users
                WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 HOUR)
            ", [$hour, $hour]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get hourly loan completions
     */
    private function getHourlyLoanCompletions($hour) {
        try {
            return $this->db->fetchColumn("
                SELECT COUNT(*) FROM loans
                WHERE status = 'completed'
                AND completed_at >= ? AND completed_at < DATE_ADD(?, INTERVAL 1 HOUR)
            ", [$hour, $hour]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get hourly fraud cases
     */
    private function getHourlyFraudCases($hour) {
        try {
            return $this->db->fetchColumn("
                SELECT COUNT(*) FROM fraud_detections
                WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 HOUR)
            ", [$hour, $hour]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get API metrics
     */
    private function getAPIMetrics() {
        if (!$this->redis) {
            return $this->getAPIMetricsFromLogs();
        }

        $metrics = $this->redis->get('juakali:metrics:api:latest');
        if ($metrics) {
            return json_decode($metrics, true);
        }

        return $this->getAPIMetricsFromLogs();
    }

    /**
     * Get API metrics from logs
     */
    private function getAPIMetricsFromLogs() {
        try {
            return $this->db->fetchOne("
                SELECT
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status_code >= 400 THEN 1 END) as error_count,
                    COUNT(CASE WHEN status_code >= 500 THEN 1 END) as server_errors,
                    ROUND(AVG(response_time), 2) as avg_response_time,
                    MAX(response_time) as max_response_time,
                    COUNT(CASE WHEN response_time > 3000 THEN 1 END) as slow_requests
                FROM system_logs
                WHERE log_type = 'API_REQUEST'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
        } catch (Exception $e) {
            return [
                'total_requests' => 0,
                'error_count' => 0,
                'server_errors' => 0,
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'slow_requests' => 0,
                'error_rate' => 0
            ];
        }
    }

    /**
     * Get page load metrics
     */
    private function getPageLoadMetrics() {
        if (!$this->redis) {
            return $this->getPageLoadMetricsFromLogs();
        }

        $metrics = $this->redis->get('juakali:metrics:page_load:latest');
        if ($metrics) {
            return json_decode($metrics, true);
        }

        return $this->getPageLoadMetricsFromLogs();
    }

    /**
     * Get page load metrics from logs
     */
    private function getPageLoadMetricsFromLogs() {
        try {
            return $this->db->fetchOne("
                SELECT
                    COUNT(*) as total_requests,
                    ROUND(AVG(load_time), 2) as avg_load_time,
                    MAX(load_time) as max_load_time,
                    COUNT(CASE WHEN load_time > 3000 THEN 1 END) as slow_pages
                FROM page_load_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
        } catch (Exception $e) {
            return [
                'total_requests' => 0,
                'avg_load_time' => 0,
                'max_load_time' => 0,
                'slow_pages' => 0
            ];
        }
    }

    /**
     * Get conversion metrics
     */
    private function getConversionMetrics() {
        try {
            return $this->db->fetchOne("
                SELECT
                    COUNT(*) as total_visitors,
                    COUNT(CASE WHEN converted = 1 THEN 1 END) as conversions,
                    ROUND(COUNT(CASE WHEN converted = 1 THEN 1 END) * 100.0 / COUNT(*), 2) as conversion_rate,
                    COUNT(CASE WHEN converted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as recent_conversions
                FROM user_analytics
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
        } catch (Exception $e) {
            return [
                'total_visitors' => 0,
                'conversions' => 0,
                'conversion_rate' => 0,
                'recent_conversions' => 0
            ];
        }
    }

    /**
     * Get database pool statistics
     */
    private function getDatabasePoolStats() {
        try {
            $poolStats = $this->db->fetchOne("SHOW STATUS LIKE 'Threads_connected'");
            return [
                'active_connections' => $poolStats['Value'] ?? 0,
                'max_connections' => $this->db->fetchOne("SHOW VARIABLES LIKE 'max_connections'")['Value'] ?? 100,
                'connection_pool_size' => 0
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get average query time
     */
    {
        private function getAverageQueryTime() {
            try {
                return $this->db->fetchOne("
                    SELECT AVG(query_time) as avg_time
                    FROM slow_query_log
                    WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ")['avg_time'] ?? 0;
            } catch (Exception $e) {
                return 0;
            }
        }
    }

    /**
     * Buffer metrics for trend analysis
     */
    private function bufferMetrics($metrics) {
        $this->metricsBuffer[] = $metrics;

        // Keep only recent metrics
        if (count($this->metricsBuffer) > $this->bufferSize) {
            array_shift($this->metricsBuffer);
        }

        // Store in Redis for persistence
        if ($this->redis) {
            $this->redis->set('juakali:metrics:buffer', json_encode(array_slice($this->metricsBuffer, -100)), 3600);
            $this->redis->expire('juakali:metrics:buffer', 3600);
        }
    }

    /**
     * Handle PHP errors
     */
    public function handleError($errno, $errstr) {
        $this->logError('PHP_ERROR', "Error [{$errno}]: {$errstr}");

        // Increment error count
        if ($this->redis) {
            $this->redis->incr('juakali:metrics:errors:count');
            $this->redis->expire('juakali:metrics:errors:count', 86400);
        }

        // Check if error rate is critical
        $this->checkHighErrorRate();
    }

    /**
     * Handle exceptions
     */
    public function handleException($exception) {
        $this->logError('EXCEPTION', get_class($exception) . ': ' . $exception->getMessage());
    }

    /**
     * Handle PHP shutdown
     */
    public function handleShutdown() {
        $error = error_get_last();
        if ($error && $error['type'] === E_ERROR) {
            $this->handleError('SHUTDOWN', $error['message']);
        }
    }

    /**
     * Log monitoring error
     */
    private function logError($type, $message) {
        $logData = [
            'type' => $type,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];

        // Save to database
        try {
            $this->db->execute("
                INSERT INTO monitoring_logs (type, message, timestamp, request_uri, remote_addr, user_agent, stack_trace, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", array_values($logData));
        } catch (Exception $e) {
            error_log('Failed to log monitoring error: ' . $e->getMessage());
        }

        // Also log to error log
        error_log("JuaKali Monitoring [{$type}]: {$message}");
    }

    /**
     * Check high error rate and trigger alert if needed
     */
    private function checkHighErrorRate() {
        if (!$this->redis) {
            return;
        }

        $errorCount = $this->redis->get('juakali:metrics:errors:count');
        $totalRequests = $this->redis->get('juakali:metrics:api:total_requests') ?? 0;

        if ($totalRequests > 0) {
            $errorRate = ($errorCount / $totalRequests) * 100;
            if ($errorRate > 10) {
                $this->triggerAlert('critical', 'high_error_rate', [
                    'condition' => 'error_rate > 10',
                    'cooldown' => 600,
                    'channels' => ['email', 'slack'],
                    'threshold' => 5
                ], $this->collectMetrics());
            }
        }
    }

    /**
     * Get monitoring dashboard data
     */
    public function getDashboardData($timeRange = 24) {
        return [
            'system_status' => $this->getSystemStatus(),
            'recent_alerts' => $this->getRecentAlerts(),
            'performance_overview' => $this->getPerformanceOverview($timeRange),
            'resource_usage' => $this->getResourceUsage(),
            'error_trends' => $this->getErrorTrends(),
            'uptime_info' => $this->getUptimeInfo()
        ];
    }

    /**
     * Get system status
     */
    public function getSystemStatus() {
        $metrics = $this->collectMetrics();

        return [
            'overall_health' => $this->calculateOverallHealth($metrics),
            'database' => $metrics['database']['connected'],
            'memory' => [
                'usage' => $metrics['system']['memory_usage'],
                'limit' => $metrics['system']['memory_limit'],
                'status' => $metrics['system']['memory_usage'] > 80 ? 'critical' : ($metrics['system']['memory_usage'] > 60 ? 'warning' : 'good')
            ],
            'disk' => [
                'usage' => $metrics['system']['disk_space']['usage_percentage'],
                'available' => $metrics['system']['disk_space']['free'],
                'status' => $metrics['system']['disk_space']['usage_percentage'] > 90 ? 'critical' : ($metrics['system']['disk_space']['usage_percentage'] > 80 ? 'warning' : 'good')
            ],
            'api' => [
                'status' => $metrics['performance']['api']['error_rate'] > 5 ? 'warning' : 'good',
                'response_time' => $metrics['performance']['api']['avg_response_time']
            ],
            'last_check' => $metrics['timestamp']
        ];
    }

    /**
     * Calculate overall system health score
     */
    private function calculateOverallHealth($metrics) {
        $scores = [
            'database' => $metrics['database']['connected'] ? 25 : 0,
            'memory' => max(0, 25 - $metrics['system']['memory_usage'] * 5),
            'disk' => max(0, 25 - $metrics['system']['disk_space']['usage_percentage'] * 2.5),
            'api' => max(0, 25 - ($metrics['performance']['api']['error_rate'] * 5))
        ];

        return array_sum($scores);
    }

    /**
     * Get recent alerts
     */
    public function getRecentAlerts($limit = 10) {
        try {
            return $this->db->fetchAll("
                SELECT * FROM system_alerts
                WHERE acknowledged = 0
                ORDER BY created_at DESC
                LIMIT ?
            ", [$limit]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get performance overview
     */
    public function getPerformanceOverview($timeRange) {
        $metrics = $this->collectMetrics();

        return [
            'api_performance' => [
                'avg_response_time' => $metrics['performance']['api']['avg_response_time'],
                'error_rate' => $metrics['performance']['api']['error_rate'],
                'requests_per_minute' => $this->calculateRequestsPerMinute($timeRange),
                'slow_requests' => $metrics['performance']['api']['slow_requests']
            ],
            'business_performance' => [
                'conversion_rate' => $metrics['performance']['conversion']['conversion_rate'],
                'active_users' => $metrics['application']['active_users'],
                'daily_transactions' => $this->calculateDailyTransactions($timeRange),
                'successful_transactions' => $this->calculateSuccessfulTransactions($timeRange)
            ],
            'resource_usage' => [
                'memory_usage' => $metrics['system']['memory_usage'],
                'disk_usage' => $metrics['system']['disk_space']['usage_percentage'],
                'cpu_usage' => $metrics['system']['cpu_usage']
            ]
        ];
    }

    /**
     * Get resource usage
     */
    public function getResourceUsage() {
        $metrics = $this->collectMetrics();

        return [
            'memory' => [
                'used' => $metrics['system']['memory_usage'],
                'peak' => $metrics['system']['memory_peak'],
                'limit' => $metrics['system']['memory_limit'],
                'percentage' => round(($metrics['system']['memory_usage'] / $metrics['system']['memory_limit']) * 100, 2)
            ],
            'disk' => [
                'total' => $metrics['system']['disk_space']['total'],
                'used' => $metrics['system']['disk_space']['used'],
                'free' => $metrics['system']['disk_space']['free'],
                'usage_percentage' => $metrics['system']['disk_space']['usage_percentage']
            ],
            'cpu' => [
                'load_average' => $metrics['system']['load_average'],
                'uptime' => $metrics['system']['uptime']
            ]
        ];
    }

    /**
     * Get error trends
     */
    public function getErrorTrends() {
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $hour = date('Y-m-d H:00:00', strtotime("-$i hours"));
            $errorCount = $this->getErrorCount();
            $trends[] = [
                'timestamp' => $hour,
                'errors' => $errorCount,
                'level' => $errorCount > 10 ? 'high' : ($errorCount > 5 ? 'medium' : 'low')
            ];
        }
        return $trends;
    }

    /**
     * Get uptime information
     */
    public function getUptimeInfo() {
        return [
            'uptime_seconds' => $this->getUptime(),
            'uptime_days' => floor($this->getUptime() / 86400),
            'formatted' => $this->formatUptime($this->getUptime()),
            'start_time' => date('Y-m-d H:i:s', strtotime('-' . $this->getUptime() . ' seconds'))
        ];
    }

    /**
     * Format uptime in human readable format
     */
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days} day" . ($days > 1 ? 's' : '');
        if ($hours > 0) $parts[] = "{$hours} hour" . ($hours > 1 ? 's' : '');
        if ($minutes > 0) $parts[] = "{$minutes} minute" . ($minutes > 1 ? 's' : '');
        if ($seconds > 0) $parts[] = "{$seconds} second" . ($seconds > 1 ? 's' : '');

        return implode(', ', $parts);
    }

    /**
     * Calculate requests per minute
     */
    private function calculateRequestsPerMinute($timeRange) {
        try {
            $result = $this->db->fetchOne("
                SELECT COUNT(*) / 60 as requests_per_minute
                FROM system_logs
                WHERE log_type = 'API_REQUEST'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ", [$timeRange]);
            return round($result['requests_per_minute'], 2);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate daily transactions
     */
    private function calculateDailyTransactions($timeRange) {
        try {
            return $this->db->fetchOne("
                SELECT COUNT(*) / ? as daily_transactions
                FROM financial_transactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$timeRange]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate successful transactions
     */
    private function calculateSuccessfulTransactions($timeRange) {
        try {
            return $this->db->fetchOne("
                SELECT COUNT(*) / ? as successful_transactions
                FROM financial_transactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'completed'
            ", [$timeRange]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get monitoring summary
     */
    public function getMonitoringSummary() {
        $metrics = $this->collectMetrics();

        return [
            'timestamp' => $metrics['timestamp'],
            'system_health' => $this->calculateOverallHealth($metrics),
            'active_alerts' => count($this->getRecentAlerts()),
            'error_rate' => $metrics['performance']['api']['error_rate'],
            'avg_response_time' => $metrics['performance']['api']['avg_response_time'],
            'total_users' => $metrics['business']['total_users'],
            'active_loans' => $metrics['business']['active_loans'],
            'uptime' => $metrics['system']['uptime']
        ];
    }
}

/**
 * Abstract notification channel interface
 */
interface NotificationChannel {
    public function send($alertData);
}

/**
 * Email notification channel
 */
class EmailNotificationChannel implements NotificationChannel {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function send($alertData) {
        try {
            $recipients = $this->getAlertRecipients($alertData);

            foreach ($recipients as $recipient) {
                $sent = $this->sendEmail($recipient, $alertData);
                if (!$sent) {
                    error_log("Failed to send email alert to: {$recipient}");
                }
            }

            return true;

        } catch (Exception $e) {
            error_log('Email notification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getAlertRecipients($alertData) {
        // Get admin users for critical alerts
        if ($alertData['severity'] === 'critical') {
            return $this->db->fetchAllColumn("
                SELECT email FROM users WHERE role = 'admin' AND status = 'active'
            ");
        }

        // Get system administrators for warning and info alerts
        return $this->db->fetchAllColumn("
            SELECT email FROM users WHERE role IN ('admin', 'lender') AND status = 'active'
        ");
    }

    private function sendEmail($recipient, $alertData) {
        $subject = $alertData['title'];
        $message = $alertData['message'];

        $headers = [
            'From: 'JuaKali Lend <noreply@juakali-lend.com>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'X-Priority: '1'
        ];

        // Add priority headers for critical alerts
        if ($alertData['severity'] === 'critical') {
            $headers[] = 'X-Priority: 1';
            $headers[] = 'X-Alert: critical';
        } elseif ($alertData['severity'] === 'warning') {
            $headers[] = 'X-Priority: 2';
            $headers[] = 'X-Alert: warning';
        }

        return mail($recipient, $subject, $message, $headers);
    }
}

/**
 * SMS notification channel
 */
class SMSNotificationChannel implements NotificationChannel {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function send($alertData) {
        try {
            $numbers = $this->getAlertRecipients($alertData);

            foreach ($numbers as $number) {
                $sent = $this->sendSMS($number, $alertData);
                if (!$sent) {
                    error_log("Failed to send SMS alert to: {$number}");
                }
            }

            return true;

        } catch (Exception $e) {
            error_log('SMS notification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getAlertRecipients($alertData) {
        // Get admin users for critical alerts
        if ($alertData['severity'] === 'critical') {
            return $this->db->fetchAllColumn("
                SELECT phone FROM users WHERE role = 'admin' AND status = 'active' AND phone IS NOT NULL
            ");
        }

        return [];
    }

    private function sendSMS($phoneNumber, $alertData) {
        // Integration with SMS gateway would go here
        // For now, just log the attempt
        error_log("SMS Alert to {$phoneNumber}: {$alertData['title']} - {$alertData['message']}");
        return true;
    }
}

/**
 * WhatsApp notification channel
 */
class WhatsAppNotificationChannel implements NotificationChannel {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function send($alertData) {
        try {
            require_once 'whatsapp-api.php';
            $whatsapp = new WhatsAppAPI($this->db);

            // Get admin WhatsApp numbers
            $numbers = $this->getAlertRecipients($alertData);

            foreach ($numbers as $number) {
                $sent = $whatsapp->sendTextMessage($number, $alertData['message']);
                if (!$sent) {
                    error_log("Failed to send WhatsApp alert to: {$number}");
                }
            }

            return true;

        } catch (Exception $e) {
            error_log('WhatsApp notification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getAlertRecipients($alertData) {
        // Get admin WhatsApp numbers for critical alerts
        if ($alertData['severity'] === 'critical') {
            return $this->db->fetchAllColumn("
                SELECT phone FROM users WHERE role = 'admin' AND status = 'active' AND phone IS NOT NULL
            ");
        }

        return [];
    }
}

/**
 * Slack notification channel
 */
class SlackNotificationChannel implements NotificationChannel {
    private $webhookUrl;

    public function __construct() {
        $this->webhookUrl = $_ENV['SLACK_WEBHOOK_URL'] ?? '';
    }

    public function send($alertData) {
        if (empty($this->webhook_url)) {
            return false;
        }

        try {
            $payload = [
                'text' => $alertData['title'],
                'attachments' => [
                    [
                        'color' => $this->getSlackColor($alertData['severity']),
                        'fields' => [
                            [
                                'title' => 'Alert Details',
                                'value' => $alertData['message']
                            ],
                            [
                                'title' => 'Severity',
                                'value' => strtoupper($alertData['severity'])
                            ],
                            [
                                'title' => 'Triggered At',
                                'value' => $alertData['triggered_at']
                            ]
                        ]
                    ]
                ]
            ];

            $ch = curl_init($this->webhook_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return $httpCode === 200;

        } catch (Exception $e) {
            error_log('Slack notification failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getSlackColor($severity) {
        $colors = [
            'critical' => 'danger',
            'warning' => 'warning',
            'info' => 'good'
        ];

        return $colors[$severity] ?? 'info';
    }
}

/**
 * Webhook notification channel
 */
class WebhookNotificationChannel implements NotificationChannel {
    private $db;
    private $webhooks = [];

    public function __construct($database) {
        $this->db = $database;
        $this->loadWebhooks();
    }

    private function loadWebhooks() {
        $this->webhooks = $this->db->fetchAll("
            SELECT * FROM webhooks WHERE is_active = 1
        ");
    }

    public function send($alertData) {
        foreach ($this->webhooks as $webhook) {
            try {
                $sent = $this->sendWebhook($webhook, $alertData);
                if ($sent) {
                    $this->logWebhookResult($webhook, $alertData, 'success');
                }
            } catch (Exception $e) {
                $this->logWebhookResult($webhook, $alertData, 'error', $e->getMessage());
            }
        }
    }

    private function sendWebhook($webhook, $alertData) {
        $payload = [
            'alert' => $alertData,
            'timestamp' => date('Y-m-d H:i:s'),
            'system' => 'JuaKali Lend'
        ];

        $ch = curl_init($webhook['url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
            ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $httpCode === 200;
    }

    private function logWebhookResult($webhook, $alertData, $status, $error = null) {
        try {
            $this->db->execute("
                INSERT INTO webhook_logs (webhook_id, alert_data, response_status, error_message, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$webhook['id'], json_encode($alertData), $status, $error]);
        } catch (Exception $e) {
            error_log('Failed to log webhook result: ' . $e->getMessage());
        }
    }
}
?>