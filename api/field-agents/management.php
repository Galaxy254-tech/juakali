<?php
/**
 * Field Agent Management API
 * Comprehensive field agent operations management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/sms-notifications.php';

try {
    $db = Database::getInstance();
    $action = $_GET['action'] ?? 'list';

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($db, $action);
            break;
        case 'POST':
            handlePostRequest($db, $action);
            break;
        case 'PUT':
            handlePutRequest($db, $action);
            break;
        case 'DELETE':
            handleDeleteRequest($db, $action);
            break;
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'FIELD_AGENT_ERROR'
    ]);
}

function handleGetRequest($db, $action) {
    switch ($action) {
        case 'list':
            // Get all field agents with their stats
            $agents = $db->fetchAll("
                SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone,
                    u.status,
                    u.created_at,
                    u.last_login,
                    (SELECT COUNT(*) FROM delivery_tracking dt
                     JOIN orders o ON dt.order_id = o.id
                     WHERE o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                     AND dt.status = 'delivered') as deliveries_this_month,
                    (SELECT SUM(CASE WHEN dt.status = 'delivered' THEN 100 ELSE 0 END)
                     FROM delivery_tracking dt
                     JOIN orders o ON dt.order_id = o.id
                     WHERE o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_earnings,
                    (SELECT AVG(CASE WHEN dt.status = 'delivered' THEN 1 ELSE 0 END) * 100
                     FROM delivery_tracking dt
                     JOIN orders o ON dt.order_id = o.id
                     WHERE o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as success_rate,
                    (SELECT COUNT(*) FROM delivery_tracking dt
                     JOIN orders o ON dt.order_id = o.id
                     WHERE dt.status = 'pending' AND o.delivery_date >= CURDATE()) as pending_deliveries
                FROM users u
                WHERE u.role = 'field_agent'
                ORDER BY u.status DESC, monthly_earnings DESC
            ");

            echo json_encode([
                'success' => true,
                'data' => $agents,
                'message' => 'Field agents retrieved successfully'
            ]);
            break;

        case 'profile':
            $agent_id = $_GET['agent_id'] ?? $_SESSION['user_id'] ?? null;
            if (!$agent_id) {
                throw new Exception('Agent ID is required');
            }

            // Get detailed agent profile
            $agent = $db->fetchOne("
                SELECT
                    u.*,
                    up.address,
                    up.city,
                    up.country,
                    up.bio,
                    cs.score as credit_score
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                LEFT JOIN credit_scores cs ON u.id = cs.retailer_id
                WHERE u.id = ? AND u.role = 'field_agent'
            ", [$agent_id]);

            if (!$agent) {
                throw new Exception('Agent not found');
            }

            // Get agent performance metrics
            $performance = $db->fetchOne("
                SELECT
                    COUNT(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as deliveries_month,
                    SUM(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 100 ELSE 0 END) as earnings_month,
                    COUNT(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as deliveries_week,
                    SUM(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 100 ELSE 0 END) as earnings_week,
                    COUNT(CASE WHEN dt.status = 'delivered' AND DATE(o.created_at) = CURDATE() THEN 1 END) as deliveries_today,
                    SUM(CASE WHEN dt.status = 'delivered' AND DATE(o.created_at) = CURDATE() THEN 100 ELSE 0 END) as earnings_today,
                    AVG(CASE WHEN dt.status = 'delivered' THEN 1 ELSE 0 END) * 100 as success_rate,
                    AVG(CASE WHEN dt.status = 'delivered' THEN TIMESTAMPDIFF(HOUR, o.delivery_date, dt.actual_delivery_date) END) as avg_delivery_hours
                FROM delivery_tracking dt
                JOIN orders o ON dt.order_id = o.id
                WHERE dt.assigned_agent_id = ?
            ", [$agent_id]);

            // Get assigned retailers
            $retailers = $db->fetchAll("
                SELECT DISTINCT
                    r.id,
                    r.first_name,
                    r.last_name,
                    r.company_name,
                    r.phone,
                    r.status,
                    cs.score as credit_score,
                    (SELECT COUNT(*) FROM orders WHERE retailer_id = r.id AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_orders
                FROM orders o
                JOIN users r ON o.retailer_id = r.id
                JOIN delivery_tracking dt ON o.id = dt.order_id
                LEFT JOIN credit_scores cs ON r.id = cs.retailer_id
                WHERE dt.assigned_agent_id = ?
                ORDER BY recent_orders DESC
                LIMIT 20
            ", [$agent_id]);

            // Get recent deliveries
            $recent_deliveries = $db->fetchAll("
                SELECT
                    o.order_number,
                    r.company_name as retailer_name,
                    s.company_name as supplier_name,
                    o.total_amount,
                    dt.status,
                    dt.tracking_number,
                    o.delivery_date,
                    dt.actual_delivery_date
                FROM delivery_tracking dt
                JOIN orders o ON dt.order_id = o.id
                JOIN users r ON o.retailer_id = r.id
                JOIN users s ON o.supplier_id = s.id
                WHERE dt.assigned_agent_id = ?
                ORDER BY o.created_at DESC
                LIMIT 10
            ", [$agent_id]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'agent' => $agent,
                    'performance' => $performance,
                    'assigned_retailers' => $retailers,
                    'recent_deliveries' => $recent_deliveries
                ],
                'message' => 'Agent profile retrieved successfully'
            ]);
            break;

        case 'deliveries':
            $agent_id = $_GET['agent_id'] ?? $_SESSION['user_id'] ?? null;
            $status_filter = $_GET['status'] ?? 'all';
            $date_filter = $_GET['date'] ?? null;

            if (!$agent_id) {
                throw new Exception('Agent ID is required');
            }

            $where_clause = "WHERE dt.assigned_agent_id = ?";
            $params = [$agent_id];

            if ($status_filter !== 'all') {
                $where_clause .= " AND dt.status = ?";
                $params[] = $status_filter;
            }

            if ($date_filter) {
                $where_clause .= " AND DATE(o.delivery_date) = ?";
                $params[] = $date_filter;
            }

            $deliveries = $db->fetchAll("
                SELECT
                    o.id,
                    o.order_number,
                    r.company_name as retailer_name,
                    r.phone as retailer_phone,
                    r.address as retailer_address,
                    s.company_name as supplier_name,
                    o.total_amount,
                    dt.status,
                    dt.tracking_number,
                    dt.current_location,
                    o.delivery_date,
                    dt.actual_delivery_date,
                    dt.delivery_notes,
                    dt.verification_code,
                    o.created_at as order_date
                FROM delivery_tracking dt
                JOIN orders o ON dt.order_id = o.id
                JOIN users r ON o.retailer_id = r.id
                JOIN users s ON o.supplier_id = s.id
                $where_clause
                ORDER BY o.delivery_date ASC, dt.status ASC
            ", $params);

            echo json_encode([
                'success' => true,
                'data' => $deliveries,
                'message' => 'Agent deliveries retrieved successfully'
            ]);
            break;

        case 'earnings':
            $agent_id = $_GET['agent_id'] ?? $_SESSION['user_id'] ?? null;
            $period = $_GET['period'] ?? 'month'; // week, month, quarter, year

            if (!$agent_id) {
                throw new Exception('Agent ID is required');
            }

            $interval_map = [
                'week' => 'INTERVAL 1 WEEK',
                'month' => 'INTERVAL 1 MONTH',
                'quarter' => 'INTERVAL 3 MONTH',
                'year' => 'INTERVAL 1 YEAR'
            ];
            $interval = $interval_map[$period] ?? 'INTERVAL 1 MONTH';

            $earnings_data = $db->fetchAll("
                SELECT
                    DATE_FORMAT(o.created_at, '" . ($period === 'year' ? '%Y' :
                              ($period === 'quarter' ? '%Y-%m' :
                              ($period === 'month' ? '%Y-%m' : '%Y-%m-%d'))) as period,
                    COUNT(CASE WHEN dt.status = 'delivered' THEN 1 END) as deliveries_completed,
                    SUM(CASE WHEN dt.status = 'delivered' THEN 100 ELSE 0 END) as total_earnings,
                    AVG(CASE WHEN dt.status = 'delivered' THEN 1 ELSE 0 END) * 100 as success_rate
                FROM delivery_tracking dt
                JOIN orders o ON dt.order_id = o.id
                WHERE dt.assigned_agent_id = ?
                AND o.created_at > DATE_SUB(NOW(), $interval)
                GROUP BY period
                ORDER BY period DESC
            ", [$agent_id]);

            $summary = $db->fetchOne("
                SELECT
                    COUNT(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), $interval) THEN 1 END) as total_deliveries,
                    SUM(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), $interval) THEN 100 ELSE 0 END) as total_earnings,
                    AVG(CASE WHEN dt.status = 'delivered' AND o.created_at > DATE_SUB(NOW(), $interval) THEN 1 ELSE 0 END) * 100 as avg_success_rate,
                    COUNT(CASE WHEN dt.status = 'pending' AND o.delivery_date >= CURDATE() THEN 1 END) as pending_deliveries
                FROM delivery_tracking dt
                JOIN orders o ON dt.order_id = o.id
                WHERE dt.assigned_agent_id = ?
            ", [$agent_id]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'earnings_by_period' => $earnings_data,
                    'summary' => $summary,
                    'period' => $period
                ],
                'message' => 'Agent earnings retrieved successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
}

function handlePostRequest($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    switch ($action) {
        case 'assign_delivery':
            // Assign delivery to field agent
            $required_fields = ['order_id', 'agent_id'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $order_id = $input['order_id'];
            $agent_id = $input['agent_id'];
            $priority = $input['priority'] ?? 'normal';

            // Verify order exists and is in correct status
            $order = $db->fetchOne("SELECT * FROM orders WHERE id = ? AND status IN ('confirmed', 'shipped')", [$order_id]);
            if (!$order) {
                throw new Exception('Order not found or not in assignable status');
            }

            // Generate verification code
            $verification_code = strtoupper(substr(md5(uniqid($order_id, true)), 0, 6));

            // Create or update delivery tracking
            $db->query("
                INSERT INTO delivery_tracking (
                    order_id, assigned_agent_id, status, tracking_number,
                    verification_code, priority, estimated_delivery, created_at, updated_at
                ) VALUES (?, ?, 'assigned', ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 2 DAY), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    assigned_agent_id = ?,
                    status = 'assigned',
                    verification_code = ?,
                    priority = ?,
                    updated_at = NOW()
            ");
            $db->bind(1, $order_id);
            $db->bind(2, $agent_id);
            $db->bind(3, 'JK' . str_pad($order_id, 8, '0', STR_PAD_LEFT));
            $db->bind(4, $verification_code);
            $db->bind(5, $priority);
            $db->bind(6, $agent_id);
            $db->bind(7, $verification_code);
            $db->bind(8, $priority);
            $db->execute();

            // Notify agent via SMS
            $agent = $db->fetchOne("SELECT phone, first_name FROM users WHERE id = ?", [$agent_id]);
            if ($agent) {
                $sms = new SMSNotification();
                $message = "Hi {$agent['first_name']}, you have a new delivery assigned. Order #{$order_id}. Verification code: {$verification_code}.";
                $sms->sendSMS($agent['phone'], $message, 'high');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Delivery assigned successfully',
                'verification_code' => $verification_code
            ]);
            break;

        case 'verify_delivery':
            // Verify delivery completion
            $required_fields = ['order_id', 'verification_code', 'agent_id'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $order_id = $input['order_id'];
            $verification_code = strtoupper($input['verification_code']);
            $agent_id = $input['agent_id'];
            $delivery_notes = $input['delivery_notes'] ?? '';
            $recipient_name = $input['recipient_name'] ?? '';
            $recipient_phone = $input['recipient_phone'] ?? '';
            $actual_location = $input['actual_location'] ?? '';
            $photos = $input['photos'] ?? [];

            // Verify the delivery assignment
            $delivery = $db->fetchOne("
                SELECT dt.*, o.total_amount, o.retailer_id, o.supplier_id
                FROM delivery_tracking dt
                JOIN orders o ON dt.order_id = o.id
                WHERE dt.order_id = ? AND dt.assigned_agent_id = ? AND dt.verification_code = ?
            ", [$order_id, $agent_id, $verification_code]);

            if (!$delivery) {
                throw new Exception('Invalid verification code or delivery assignment');
            }

            if ($delivery['status'] === 'delivered') {
                throw new Exception('Delivery already verified');
            }

            // Update delivery tracking
            $db->query("
                UPDATE delivery_tracking
                SET status = 'delivered',
                    actual_delivery_date = NOW(),
                    delivery_notes = ?,
                    recipient_name = ?,
                    recipient_phone = ?,
                    actual_location = ?,
                    photos = ?,
                    updated_at = NOW()
                WHERE order_id = ?
            ");
            $db->bind(1, $delivery_notes);
            $db->bind(2, $recipient_name);
            $db->bind(3, $recipient_phone);
            $db->bind(4, $actual_location);
            $db->bind(5, json_encode($photos));
            $db->bind(6, $order_id);
            $db->execute();

            // Update order status
            $db->query("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?", [$order_id]);

            // Trigger payment processing for the order
            $db->query("
                INSERT INTO payments (loan_id, amount, payment_method, status, payment_date, created_at)
                SELECT l.id, o.total_amount, 'delivery_confirmation', 'completed', NOW(), NOW()
                FROM orders o
                JOIN loans l ON o.id = l.order_id
                WHERE o.id = ?
            ", [$order_id]);

            // Award agent earnings
            $earnings = 100; // Base delivery fee
            $db->query("
                INSERT INTO agent_earnings (agent_id, order_id, amount, type, status, created_at)
                VALUES (?, ?, ?, 'delivery_fee', 'earned', NOW())
            ", [$agent_id, $order_id, $earnings]);

            // Send confirmation SMS to retailer
            $retailer = $db->fetchOne("SELECT first_name, phone FROM users WHERE id = ?", [$delivery['retailer_id']]);
            if ($retailer) {
                $sms = new SMSNotification();
                $message = "Hi {$retailer['first_name']}, your order #{$order_id} has been delivered successfully. Thank you for using JuaKali Lend!";
                $sms->sendSMS($retailer['phone'], $message, 'high');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Delivery verified successfully',
                'earnings_awarded' => $earnings
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
}

function handlePutRequest($db, $action) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    switch ($action) {
        case 'update_location':
            // Update agent location and delivery status
            $required_fields = ['order_id', 'agent_id', 'location'];
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $order_id = $input['order_id'];
            $agent_id = $input['agent_id'];
            $location = $input['location'];
            $status = $input['status'] ?? 'in_transit';
            $notes = $input['notes'] ?? '';

            // Update delivery tracking
            $db->query("
                UPDATE delivery_tracking
                SET status = ?,
                    current_location = ?,
                    delivery_notes = ?,
                    updated_at = NOW()
                WHERE order_id = ? AND assigned_agent_id = ?
            ");
            $db->bind(1, $status);
            $db->bind(2, $location);
            $db->bind(3, $notes);
            $db->bind(4, $order_id);
            $db->bind(5, $agent_id);
            $db->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
}

function handleDeleteRequest($db, $action) {
    switch ($action) {
        case 'unassign_delivery':
            $order_id = $_GET['order_id'] ?? null;
            $agent_id = $_GET['agent_id'] ?? null;

            if (!$order_id || !$agent_id) {
                throw new Exception('Order ID and Agent ID are required');
            }

            // Remove delivery assignment
            $db->query("
                UPDATE delivery_tracking
                SET assigned_agent_id = NULL,
                    status = 'pending',
                    verification_code = NULL,
                    updated_at = NOW()
                WHERE order_id = ? AND assigned_agent_id = ?
            ", [$order_id, $agent_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Delivery unassigned successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }
}
?>