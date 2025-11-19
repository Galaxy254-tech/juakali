<?php
/**
 * JuaKali Lend API Documentation and Health Check
 * Central API endpoint with documentation
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

try {
    $db = Database::getInstance();
    $endpoint = $_GET['endpoint'] ?? 'docs';

    switch ($endpoint) {
        case 'health':
            handleHealthCheck($db);
            break;
        case 'docs':
            handleAPIDocumentation();
            break;
        case 'version':
            handleVersionInfo();
            break;
        case 'metrics':
            handleSystemMetrics($db);
            break;
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint not found',
                'available_endpoints' => ['health', 'docs', 'version', 'metrics']
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'API error: ' . $e->getMessage(),
        'error_code' => 'API_ERROR'
    ]);
}

function handleHealthCheck($db) {
    try {
        // Check database connection
        $db->fetchOne("SELECT 1");

        // Check essential tables exist
        $tables = ['users', 'loans', 'repayment_schedule', 'system_settings'];
        $missingTables = [];

        foreach ($tables as $table) {
            $result = $db->fetchOne("SHOW TABLES LIKE '$table'");
            if (!$result) {
                $missingTables[] = $table;
            }
        }

        $healthy = empty($missingTables);

        echo json_encode([
            'success' => true,
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'version' => '2.0.0',
            'checks' => [
                'database' => [
                    'status' => $healthy ? 'pass' : 'fail',
                    'message' => $healthy ? 'Database connection successful' : 'Missing tables: ' . implode(', ', $missingTables)
                ],
                'memory' => [
                    'status' => memory_get_usage() < 128 * 1024 * 1024 ? 'pass' : 'warn',
                    'usage' => formatBytes(memory_get_usage()),
                    'limit' => '128MB'
                ],
                'disk_space' => [
                    'status' => disk_free_space('/') > 100 * 1024 * 1024 ? 'pass' : 'warn',
                    'free' => formatBytes(disk_free_space('/')),
                    'total' => formatBytes(disk_total_space('/'))
                ]
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'status' => 'unhealthy',
            'timestamp' => date('c'),
            'error' => $e->getMessage()
        ]);
    }
}

function handleAPIDocumentation() {
    $docs = [
        'title' => 'JuaKali Lend API',
        'version' => '2.0.0',
        'description' => 'Comprehensive API for JuaKali Lend microfinance platform',
        'base_url' => 'https://juakali-lend.com/api',
        'authentication' => [
            'type' => 'Bearer Token',
            'description' => 'Include Authorization header with Bearer token for authenticated endpoints'
        ],
        'endpoints' => [
            // Authentication
            [
                'group' => 'Authentication',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/users/login.php',
                        'description' => 'User login',
                        'parameters' => [
                            'email' => 'string|required',
                            'password' => 'string|required',
                            'remember_me' => 'boolean|optional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'user' => 'object',
                            'token' => 'string'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/users/register.php',
                        'description' => 'User registration',
                        'parameters' => [
                            'name' => 'string|required',
                            'email' => 'string|required',
                            'phone' => 'string|required',
                            'password' => 'string|required',
                            'role' => 'string|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'user' => 'object',
                            'message' => 'string'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/users/logout.php',
                        'description' => 'User logout',
                        'authentication' => true,
                        'response' => [
                            'success' => 'boolean',
                            'message' => 'string'
                        ]
                    ]
                ]
            ],
            // Loans
            [
                'group' => 'Loans',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/loans/list.php',
                        'description' => 'Get user loans',
                        'authentication' => true,
                        'parameters' => [
                            'status' => 'string|optional',
                            'limit' => 'integer|optional',
                            'offset' => 'integer|optional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'loans' => 'array',
                            'pagination' => 'object'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/loans/apply.php',
                        'description' => 'Apply for a loan',
                        'authentication' => true,
                        'parameters' => [
                            'loan_amount' => 'number|required',
                            'loan_purpose' => 'string|required',
                            'repayment_period' => 'integer|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'loan' => 'object',
                            'message' => 'string'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/loans/repayment.php',
                        'description' => 'Make loan repayment',
                        'authentication' => true,
                        'parameters' => [
                            'loan_id' => 'integer|required',
                            'amount' => 'number|required',
                            'payment_method' => 'string|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'transaction' => 'object',
                            'message' => 'string'
                        ]
                    ]
                ]
            ],
            // Credit Scoring
            [
                'group' => 'Credit Scoring',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/credit-scoring/evaluate.php',
                        'description' => 'Get credit score evaluation',
                        'authentication' => true,
                        'parameters' => [
                            'user_id' => 'integer|optional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'credit_score' => 'integer',
                            'risk_category' => 'string',
                            'factors' => 'array'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/credit-scoring/update-behavioral.php',
                        'description' => 'Update behavioral data',
                        'authentication' => true,
                        'parameters' => [
                            'user_id' => 'integer|required',
                            'behavioral_data' => 'object|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'message' => 'string'
                        ]
                    ]
                ]
            ],
            // Fraud Detection
            [
                'group' => 'Fraud Detection',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/fraud-detection/analyze.php',
                        'description' => 'Analyze transaction for fraud',
                        'authentication' => true,
                        'parameters' => [
                            'user_id' => 'integer|required',
                            'transaction_data' => 'object|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'risk_score' => 'integer',
                            'risk_level' => 'string',
                            'recommendations' => 'array'
                        ]
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/fraud-detection/reports.php',
                        'description' => 'Get fraud detection reports',
                        'authentication' => true,
                        'authorization' => 'admin',
                        'parameters' => [
                            'date_range' => 'string|optional',
                            'risk_level' => 'string|optional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'reports' => 'array'
                        ]
                    ]
                ]
            ],
            // Field Agents
            [
                'group' => 'Field Agents',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/field-agents/management.php',
                        'description' => 'Get field agent data',
                        'authentication' => true,
                        'parameters' => [
                            'agent_id' => 'integer|optional',
                            'action' => 'string|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'data' => 'object'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/field-agents/assign-delivery.php',
                        'description' => 'Assign delivery to agent',
                        'authentication' => true,
                        'authorization' => 'admin',
                        'parameters' => [
                            'agent_id' => 'integer|required',
                            'order_id' => 'integer|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'assignment' => 'object'
                        ]
                    ]
                ]
            ],
            // QR Delivery
            [
                'group' => 'QR Delivery',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/delivery/qr-verification.php',
                        'description' => 'Generate or verify QR codes',
                        'authentication' => true,
                        'parameters' => [
                            'action' => 'string|required',
                            'order_id' => 'integer|conditional',
                            'agent_id' => 'integer|conditional',
                            'qr_data' => 'string|conditional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'qr_code' => 'object|string',
                            'verification_result' => 'object|conditional'
                        ]
                    ]
                ]
            ],
            // WhatsApp
            [
                'group' => 'WhatsApp',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/whatsapp/send-message.php',
                        'description' => 'Send WhatsApp message',
                        'authentication' => true,
                        'parameters' => [
                            'action' => 'string|required',
                            'recipient_phone' => 'string|conditional',
                            'message' => 'string|conditional',
                            'user_id' => 'integer|conditional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'message_id' => 'string|conditional',
                            'message' => 'string'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/whatsapp/webhook.php',
                        'description' => 'WhatsApp webhook endpoint',
                        'authentication' => false,
                        'description' => 'Handles incoming WhatsApp messages and webhooks'
                    ]
                ]
            ],
            // M-Pesa
            [
                'group' => 'M-Pesa Payments',
                'endpoints' => [
                    [
                        'method' => 'POST',
                        'path' => '/mpesa/stk-push.php',
                        'description' => 'Initiate M-Pesa STK Push',
                        'authentication' => true,
                        'parameters' => [
                            'phone_number' => 'string|required',
                            'amount' => 'number|required',
                            'account_reference' => 'string|required',
                            'transaction_desc' => 'string|required'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'checkout_request_id' => 'string',
                            'response' => 'object'
                        ]
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/mpesa/callback.php',
                        'description' => 'M-Pesa callback handler',
                        'authentication' => false,
                        'description' => 'Handles M-Pesa payment callbacks'
                    ]
                ]
            ],
            // Analytics
            [
                'group' => 'Analytics',
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/analytics/dashboard.php',
                        'description' => 'Get dashboard analytics',
                        'authentication' => true,
                        'authorization' => 'admin',
                        'parameters' => [
                            'date_range' => 'string|optional',
                            'metrics' => 'string|optional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'analytics' => 'object'
                        ]
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/analytics/reports.php',
                        'description' => 'Generate reports',
                        'authentication' => true,
                        'authorization' => 'admin',
                        'parameters' => [
                            'report_type' => 'string|required',
                            'format' => 'string|optional',
                            'filters' => 'object|optional'
                        ],
                        'response' => [
                            'success' => 'boolean',
                            'report_url' => 'string|conditional',
                            'data' => 'object|conditional'
                        ]
                    ]
                ]
            ]
        ],
        'response_formats' => [
            'success_response' => [
                'success' => 'boolean',
                'data' => 'object|array',
                'message' => 'string|optional',
                'timestamp' => 'string'
            ],
            'error_response' => [
                'success' => 'boolean',
                'message' => 'string',
                'error_code' => 'string',
                'timestamp' => 'string'
            ]
        ],
        'rate_limits' => [
            'default' => '100 requests per hour',
            'authenticated' => '1000 requests per hour',
            'admin' => '5000 requests per hour'
        ],
        'support' => [
            'contact' => 'api-support@juakali-lend.com',
            'documentation' => 'https://docs.juakali-lend.com/api',
            'status_page' => 'https://status.juakali-lend.com'
        ]
    ];

    echo json_encode($docs, JSON_PRETTY_PRINT);
}

function handleVersionInfo() {
    echo json_encode([
        'success' => true,
        'version' => '2.0.0',
        'build' => '2024.11.19',
        'environment' => 'production',
        'api_version' => 'v2',
        'features' => [
            'Credit Scoring Engine',
            'Fraud Detection System',
            'Field Agent Management',
            'QR Code Delivery',
            'WhatsApp Integration',
            'M-Pesa Payments',
            'Mobile PWA',
            'Admin Dashboard',
            'Real-time Analytics'
        ],
        'dependencies' => [
            'PHP' => '8.4+',
            'MySQL' => '8.0+',
            'Bootstrap' => '5.3+',
            'Chart.js' => '4.0+'
        ],
        'endpoints_count' => 25,
        'last_updated' => date('c')
    ]);
}

function handleSystemMetrics($db) {
    try {
        // Get basic system metrics
        $metrics = [
            'timestamp' => date('c'),
            'uptime' => shell_exec('uptime -p 2>/dev/null') ?: 'Unknown',
            'memory' => [
                'usage' => formatBytes(memory_get_usage()),
                'peak' => formatBytes(memory_get_peak_usage())
            ],
            'database' => [
                'connections' => $db->fetchOne("SHOW STATUS LIKE 'Threads_connected'")['Value'] ?? 0,
                'queries_per_second' => round($db->fetchOne("SHOW STATUS LIKE 'Queries'")['Value'] / (time() - $db->getConnectionTime()), 2)
            ],
            'users' => [
                'total' => $db->fetchColumn("SELECT COUNT(*) FROM users"),
                'active_today' => $db->fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(last_login) = CURDATE()"),
                'new_this_month' => $db->fetchColumn("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
            ],
            'loans' => [
                'total' => $db->fetchColumn("SELECT COUNT(*) FROM loans"),
                'active' => $db->fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'active'"),
                'completed' => $db->fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'completed'"),
                'portfolio_value' => $db->fetchColumn("SELECT COALESCE(SUM(loan_amount), 0) FROM loans WHERE status IN ('active', 'completed')")
            ],
            'payments' => [
                'total_repaid' => $db->fetchColumn("SELECT COALESCE(SUM(amount_paid), 0) FROM repayment_schedule WHERE status = 'completed'"),
                'pending_amount' => $db->fetchColumn("SELECT COALESCE(SUM(amount_due), 0) FROM repayment_schedule WHERE status = 'pending'"),
                'overdue_amount' => $db->fetchColumn("SELECT COALESCE(SUM(amount_due), 0) FROM repayment_schedule WHERE status = 'pending' AND due_date < CURDATE()")
            ]
        ];

        echo json_encode([
            'success' => true,
            'metrics' => $metrics
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve system metrics: ' . $e->getMessage()
        ]);
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>