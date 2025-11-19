<?php
/**
 * Fraud Detection Monitoring API
 * Provides real-time fraud monitoring data for admin dashboard
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Initialize database connection
    $db = Database::getInstance();

    // Get monitoring parameters
    $timeframe = $_GET['timeframe'] ?? '24h'; // 1h, 24h, 7d, 30d
    $severity_filter = $_GET['severity'] ?? 'all'; // all, high, medium, low

    // Convert timeframe to SQL interval
    $interval_map = [
        '1h' => 'INTERVAL 1 HOUR',
        '24h' => 'INTERVAL 24 HOUR',
        '7d' => 'INTERVAL 7 DAY',
        '30d' => 'INTERVAL 30 DAY'
    ];
    $interval = $interval_map[$timeframe] ?? 'INTERVAL 24 HOUR';

    // Build severity filter
    $severity_clause = '';
    $params = [];
    if ($severity_filter !== 'all') {
        $severity_clause = 'AND severity = ?';
        $params[] = $severity_filter;
    }

    // 1. Get fraud detection summary
    $fraud_summary = $db->fetchAll("
        SELECT
            severity,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'investigated' THEN 1 ELSE 0 END) as investigated,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM fraud_detection
        WHERE created_at > DATE_SUB(NOW(), $interval)
        $severity_clause
        GROUP BY severity
        ORDER BY
            CASE severity
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 5
            END
    ", $params);

    // 2. Get recent fraud events
    $recent_fraud = $db->fetchAll("
        SELECT
            fd.id,
            fd.user_id,
            u.first_name,
            u.last_name,
            u.email,
            fd.event_type,
            fd.description,
            fd.severity,
            fd.status,
            fd.created_at
        FROM fraud_detection fd
        JOIN users u ON fd.user_id = u.id
        WHERE fd.created_at > DATE_SUB(NOW(), $interval)
        $severity_clause
        ORDER BY fd.created_at DESC
        LIMIT 50
    ", $params);

    // 3. Get fraud trends over time
    $fraud_trends = $db->fetchAll("
        SELECT
            DATE_FORMAT(created_at, '" . ($timeframe === '1h' ? '%Y-%m-%d %H:00:00' :
                          ($timeframe === '24h' ? '%Y-%m-%d %H:00:00' :
                          ($timeframe === '7d' ? '%Y-%m-%d' : '%Y-%m-%u'))) as time_period,
            severity,
            COUNT(*) as count
        FROM fraud_detection
        WHERE created_at > DATE_SUB(NOW(), $interval)
        $severity_clause
        GROUP BY time_period, severity
        ORDER BY time_period DESC, severity
    ", $params);

    // 4. Get high-risk users
    $high_risk_users = $db->fetchAll("
        SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.role,
            COUNT(fd.id) as fraud_events,
            SUM(CASE WHEN fd.severity IN ('critical', 'high') THEN 1 ELSE 0 END) as high_severity_events,
            MAX(fd.created_at) as last_fraud_event,
            cs.score as credit_score,
            ra.risk_score as risk_assessment_score
        FROM users u
        LEFT JOIN fraud_detection fd ON u.id = fd.user_id
            AND fd.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN credit_scores cs ON u.id = cs.retailer_id
        LEFT JOIN risk_assessments ra ON u.id = ra.retailer_id
        WHERE u.status = 'active'
        GROUP BY u.id
        HAVING fraud_events > 0 OR (ra.risk_score > 60 OR cs.score < 500)
        ORDER BY high_severity_events DESC, fraud_events DESC, ra.risk_score DESC
        LIMIT 20
    ");

    // 5. Get fraud pattern analysis
    $fraud_patterns = $db->fetchAll("
        SELECT
            event_type,
            COUNT(*) as occurrence_count,
            SUM(CASE WHEN severity IN ('critical', 'high') THEN 1 ELSE 0 END) as high_severity_count,
            AVG(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) * 100 as resolution_rate
        FROM fraud_detection
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY event_type
        HAVING occurrence_count > 5
        ORDER BY high_severity_count DESC, occurrence_count DESC
    ");

    // 6. Get system performance metrics
    $system_metrics = $db->fetchOne("
        SELECT
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as fraud_checks_24h,
            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as fraud_checks_1h,
            AVG(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND JSON_EXTRACT(description, '$.fraud_score') > 0
                THEN JSON_EXTRACT(description, '$.fraud_score')
                ELSE NULL END) as avg_fraud_score_24h,
            COUNT(CASE WHEN status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as pending_investigations,
            COUNT(CASE WHEN status = 'resolved' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as resolved_today
        FROM fraud_detection
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    // 7. Get geographic hotspots
    $geo_hotspots = $db->fetchAll("
        SELECT
            s.ip_address,
            COUNT(DISTINCT s.user_id) as unique_users,
            COUNT(fd.id) as fraud_events,
            AVG(JSON_EXTRACT(fd.description, '$.fraud_score')) as avg_fraud_score
        FROM sessions s
        JOIN fraud_detection fd ON s.user_id = fd.user_id
            AND s.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND fd.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY s.ip_address
        HAVING fraud_events > 2 AND unique_users > 1
        ORDER BY fraud_events DESC, avg_fraud_score DESC
        LIMIT 10
    ");

    // 8. Get automated responses triggered
    $automated_responses = $db->fetchAll("
        SELECT
            al.action,
            COUNT(*) as count,
            COUNT(DISTINCT al.user_id) as affected_users,
            MAX(al.created_at) as last_triggered
        FROM audit_logs al
        WHERE al.action IN ('auto_suspension_fraud_risk', 'credit_limit_updated', 'fraud_analysis_requested')
            AND al.created_at > DATE_SUB(NOW(), $interval)
        GROUP BY al.action
        ORDER BY count DESC
    ");

    // 9. Calculate risk score distribution
    $risk_distribution = $db->fetchAll("
        SELECT
            CASE
                WHEN JSON_EXTRACT(description, '$.fraud_score') >= 80 THEN 'Critical (80-100)'
                WHEN JSON_EXTRACT(description, '$.fraud_score') >= 60 THEN 'High (60-79)'
                WHEN JSON_EXTRACT(description, '$.fraud_score') >= 40 THEN 'Medium (40-59)'
                WHEN JSON_EXTRACT(description, '$.fraud_score') >= 20 THEN 'Low (20-39)'
                ELSE 'Minimal (0-19)'
            END as risk_category,
            COUNT(*) as count
        FROM fraud_detection
        WHERE created_at > DATE_SUB(NOW(), $interval)
            AND JSON_EXTRACT(description, '$.fraud_score') IS NOT NULL
        GROUP BY risk_category
        ORDER BY
            CASE risk_category
                WHEN 'Critical (80-100)' THEN 1
                WHEN 'High (60-79)' THEN 2
                WHEN 'Medium (40-59)' THEN 3
                WHEN 'Low (20-39)' THEN 4
                WHEN 'Minimal (0-19)' THEN 5
            END
    ");

    // 10. Get real-time alerts
    $realtime_alerts = $db->fetchAll("
        SELECT
            fd.id,
            fd.user_id,
            u.first_name,
            u.last_name,
            u.email,
            fd.event_type,
            JSON_EXTRACT(fd.description, '$.fraud_score') as fraud_score,
            fd.severity,
            fd.created_at
        FROM fraud_detection fd
        JOIN users u ON fd.user_id = u.id
        WHERE fd.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND fd.status = 'pending'
            AND fd.severity IN ('critical', 'high')
        ORDER BY JSON_EXTRACT(fd.description, '$.fraud_score') DESC, fd.created_at DESC
        LIMIT 10
    ");

    // Compile monitoring dashboard data
    $dashboard_data = [
        'summary' => [
            'total_fraud_events' => array_sum(array_column($fraud_summary, 'count')),
            'pending_investigations' => array_sum(array_column($fraud_summary, 'pending')),
            'resolved_cases' => array_sum(array_column($fraud_summary, 'resolved')),
            'high_risk_users' => count($high_risk_users),
            'realtime_alerts' => count($realtime_alerts)
        ],
        'fraud_summary_by_severity' => $fraud_summary,
        'recent_fraud_events' => $recent_fraud,
        'fraud_trends' => $fraud_trends,
        'high_risk_users' => $high_risk_users,
        'fraud_patterns' => $fraud_patterns,
        'system_metrics' => $system_metrics,
        'geographic_hotspots' => $geo_hotspots,
        'automated_responses' => $automated_responses,
        'risk_distribution' => $risk_distribution,
        'realtime_alerts' => $realtime_alerts,
        'timeframe' => $timeframe,
        'generated_at' => date('Y-m-d H:i:s')
    ];

    // Cache this data for 5 minutes to improve performance
    $cache_key = "fraud_monitoring_" . $timeframe . "_" . $severity_filter;
    // Note: In production, you'd use Redis or Memcached for caching

    echo json_encode([
        'success' => true,
        'data' => $dashboard_data,
        'message' => 'Fraud monitoring data retrieved successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'FRAUD_MONITORING_ERROR'
    ]);
}
?>