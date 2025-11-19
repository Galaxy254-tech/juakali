<?php
/**
 * Fraud Monitoring Dashboard
 * Real-time fraud detection and risk management interface
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../integrations/credit-scoring.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$creditScoring = new CreditScoringEngine($db);

// Get fraud monitoring data
$fraudCases = getFraudCases($db);
$riskTrends = getRiskTrends($db);
$fraudPatterns = getFraudPatterns($db);
$investigations = getActiveInvestigations($db);
$alerts = getHighRiskAlerts($db);

function getFraudCases($db) {
    return $db->fetchAll("
        SELECT
            fd.*,
            u.name as user_name,
            u.phone as user_phone,
            u.email as user_email,
            CASE
                WHEN fd.risk_level = 'HIGH' THEN 'danger'
                WHEN fd.risk_level = 'MEDIUM' THEN 'warning'
                ELSE 'info'
            END as risk_badge
        FROM fraud_detections fd
        LEFT JOIN users u ON fd.user_id = u.id
        WHERE fd.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY fd.risk_score DESC, fd.created_at DESC
        LIMIT 50
    ");
}

function getRiskTrends($db) {
    $trends = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayData = $db->fetchOne("
            SELECT
                COUNT(*) as total_cases,
                COUNT(CASE WHEN risk_level = 'HIGH' THEN 1 END) as high_risk,
                COUNT(CASE WHEN risk_level = 'MEDIUM' THEN 1 END) as medium_risk,
                COUNT(CASE WHEN risk_level = 'LOW' THEN 1 END) as low_risk,
                AVG(risk_score) as avg_risk_score
            FROM fraud_detections
            WHERE DATE(created_at) = ?
        ", [$date]);

        $trends[] = [
            'date' => date('M j', strtotime($date)),
            'total_cases' => $dayData['total_cases'],
            'high_risk' => $dayData['high_risk'],
            'medium_risk' => $dayData['medium_risk'],
            'low_risk' => $dayData['low_risk'],
            'avg_risk_score' => round($dayData['avg_risk_score'] ?? 0, 2)
        ];
    }
    return $trends;
}

function getFraudPatterns($db) {
    return $db->fetchAll("
        SELECT
            pattern_type,
            COUNT(*) as occurrence_count,
            AVG(risk_score) as avg_risk_score,
            MAX(created_at) as last_detected
        FROM fraud_detections
        WHERE pattern_type IS NOT NULL
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY pattern_type
        ORDER BY occurrence_count DESC
        LIMIT 10
    ");
}

function getActiveInvestigations($db) {
    return $db->fetchAll("
        SELECT
            fi.*,
            u.name as investigator_name,
            fd.risk_level,
            fd.risk_score
        FROM fraud_investigations fi
        LEFT JOIN users u ON fi.investigator_id = u.id
        LEFT JOIN fraud_detections fd ON fi.fraud_detection_id = fd.id
        WHERE fi.status IN ('OPEN', 'UNDER_REVIEW')
        ORDER BY fi.priority DESC, fi.created_at DESC
    ");
}

function getHighRiskAlerts($db) {
    return $db->fetchAll("
        SELECT
            *,
            CASE
                WHEN alert_type = 'IMMEDIATE_ACTION' THEN 'danger'
                WHEN alert_type = 'HIGH_PRIORITY' THEN 'warning'
                ELSE 'info'
            END as alert_badge
        FROM fraud_alerts
        WHERE is_resolved = 0
        AND severity IN ('HIGH', 'CRITICAL')
        ORDER BY severity DESC, created_at DESC
        LIMIT 10
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fraud Monitoring - JuaKali Lend Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .fraud-dashboard {
            background: #f8f9fa;
        }
        .risk-high {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }
        .risk-medium {
            border-left: 4px solid #ffc107;
            background: #fffdf5;
        }
        .risk-low {
            border-left: 4px solid #28a745;
            background: #f5fff5;
        }
        .fraud-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .fraud-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .pattern-item {
            padding: 15px;
            border-radius: 8px;
            background: white;
            margin-bottom: 10px;
            border-left: 3px solid #007bff;
        }
        .investigation-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
        }
        .severity-critical {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .severity-high {
            background: linear-gradient(135deg, #fd7e14 0%, #e85d04 100%);
            color: white;
        }
        .live-indicator {
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .risk-meter {
            height: 8px;
            border-radius: 4px;
            background: linear-gradient(to right, #28a745 0%, #ffc107 50%, #dc3545 100%);
            position: relative;
        }
        .risk-marker {
            position: absolute;
            top: -2px;
            width: 12px;
            height: 12px;
            background: white;
            border: 2px solid #007bff;
            border-radius: 50%;
        }
        .fraud-timeline {
            position: relative;
            padding-left: 30px;
        }
        .fraud-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
        }
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="fraud-dashboard">
    <!-- Header -->
    <div class="bg-primary text-white p-4 mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1><i class="fas fa-shield-alt"></i> Fraud Monitoring</h1>
                    <p class="mb-0">Real-time fraud detection and risk analysis</p>
                </div>
                <div class="col-md-6 text-end">
                    <span class="live-indicator"></span>
                    <span class="ms-2">Live Monitoring</span>
                    <span class="ms-4">Last Updated: <?php echo date('H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Critical Alerts -->
        <?php if (!empty($alerts)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5><i class="fas fa-exclamation-triangle"></i> Critical Fraud Alerts</h5>
                        <div class="row">
                            <?php foreach ($alerts as $alert): ?>
                                <div class="col-md-6 mb-2">
                                    <strong><?php echo htmlspecialchars($alert['title']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($alert['description']); ?></small>
                                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="resolveAlert(<?php echo $alert['id']; ?>)">
                                        Resolve
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card fraud-card severity-critical">
                    <div class="card-body text-center">
                        <div class="h1 mb-2"><?php echo count(array_filter($fraudCases, fn($case) => $case['risk_level'] === 'HIGH')); ?></div>
                        <div>High Risk Cases</div>
                        <small>Requiring immediate attention</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card fraud-card severity-high">
                    <div class="card-body text-center">
                        <div class="h1 mb-2"><?php echo count($activeInvestigations); ?></div>
                        <div>Active Investigations</div>
                        <small>Currently under review</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card fraud-card">
                    <div class="card-body text-center">
                        <div class="h1 mb-2"><?php echo count($fraudCases); ?></div>
                        <div>Total Cases (7 Days)</div>
                        <small>+<?php echo round((count($fraudCases) / 7) * 100, 1); ?>% from last week</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card fraud-card bg-success text-white">
                    <div class="card-body text-center">
                        <div class="h1 mb-2"><?php echo round(array_column($fraudCases, 'risk_score') ? array_sum(array_column($fraudCases, 'risk_score')) / count(array_column($fraudCases, 'risk_score')) : 0, 1); ?></div>
                        <div>Avg Risk Score</div>
                        <small>System-wide average</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-3">
                    <select class="form-select" id="riskFilter">
                        <option value="">All Risk Levels</option>
                        <option value="HIGH">High Risk</option>
                        <option value="MEDIUM">Medium Risk</option>
                        <option value="LOW">Low Risk</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="PENDING">Pending Review</option>
                        <option value="UNDER_REVIEW">Under Review</option>
                        <option value="RESOLVED">Resolved</option>
                        <option value="FALSE_POSITIVE">False Positive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by user, phone, email...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Fraud Cases Table -->
            <div class="col-md-8">
                <div class="card fraud-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list"></i> Recent Fraud Cases</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportCases()">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="refreshCases()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="fraudCasesTable">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Risk Level</th>
                                        <th>Score</th>
                                        <th>Pattern</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fraudCases as $case): ?>
                                        <tr class="risk-<?php echo strtolower($case['risk_level']); ?>">
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($case['user_name'] ?? 'Unknown'); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($case['user_phone'] ?? 'N/A'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $case['risk_badge']; ?>">
                                                    <?php echo $case['risk_level']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span><?php echo $case['risk_score']; ?></span>
                                                    <div class="risk-meter ms-2 flex-grow-1">
                                                        <div class="risk-marker" style="left: <?php echo ($case['risk_score'] / 100) * 100; ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($case['pattern_type'] ?? 'Unknown'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $case['status']; ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewCaseDetails(<?php echo $case['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="startInvestigation(<?php echo $case['id']; ?>)">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fraud Patterns & Investigations -->
            <div class="col-md-4">
                <!-- Common Fraud Patterns -->
                <div class="card fraud-card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-fingerprint"></i> Common Fraud Patterns</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($fraudPatterns as $pattern): ?>
                            <div class="pattern-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($pattern['pattern_type']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo $pattern['occurrence_count']; ?> occurrences
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning"><?php echo round($pattern['avg_risk_score'], 1); ?></span><br>
                                        <small class="text-muted">
                                            <?php echo date('M j', strtotime($pattern['last_detected'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Active Investigations -->
                <div class="card fraud-card">
                    <div class="card-header">
                        <h6><i class="fas fa-user-shield"></i> Active Investigations</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activeInvestigations)): ?>
                            <?php foreach ($activeInvestigations as $investigation): ?>
                                <div class="investigation-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong>Case #<?php echo $investigation['id']; ?></strong>
                                        <span class="badge bg-<?php echo $investigation['risk_level'] === 'HIGH' ? 'danger' : 'warning'; ?>">
                                            <?php echo $investigation['risk_level']; ?>
                                        </span>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($investigation['description']); ?></p>
                                    <small class="text-muted">
                                        Investigator: <?php echo htmlspecialchars($investigation['investigator_name'] ?? 'Unassigned'); ?><br>
                                        Created: <?php echo date('M j, H:i', strtotime($investigation['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No active investigations</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Trends Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card fraud-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Risk Trends (30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <div style="height: 400px;">
                            <canvas id="riskTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Case Details Modal -->
    <div class="modal fade" id="caseDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Fraud Case Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="caseDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Risk Trends Chart
        const riskTrendsCtx = document.getElementById('riskTrendsChart').getContext('2d');
        const riskTrendsChart = new Chart(riskTrendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($riskTrends, 'date')); ?>,
                datasets: [{
                    label: 'High Risk',
                    data: <?php echo json_encode(array_column($riskTrends, 'high_risk')); ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Medium Risk',
                    data: <?php echo json_encode(array_column($riskTrends, 'medium_risk')); ?>,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Low Risk',
                    data: <?php echo json_encode(array_column($riskTrends, 'low_risk')); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Auto-refresh data every 60 seconds
        setInterval(() => {
            location.reload();
        }, 60000);

        function viewCaseDetails(caseId) {
            // Load case details via AJAX
            fetch(`../api/fraud-detection/get-case-details.php?id=${caseId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('caseDetailsContent').innerHTML = `
                        <div class="fraud-timeline">
                            <!-- Timeline content will be populated here -->
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('caseDetailsModal')).show();
                });
        }

        function startInvestigation(caseId) {
            if (confirm('Start investigation for this fraud case?')) {
                fetch('../api/fraud-detection/start-investigation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ fraud_detection_id: caseId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Investigation started successfully');
                        location.reload();
                    } else {
                        alert('Failed to start investigation: ' + data.message);
                    }
                });
            }
        }

        function resolveAlert(alertId) {
            fetch('../api/fraud-detection/resolve-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ alert_id: alertId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function applyFilters() {
            const riskLevel = document.getElementById('riskFilter').value;
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;

            // Apply filters to the table
            const rows = document.querySelectorAll('#fraudCasesTable tbody tr');
            rows.forEach(row => {
                const rowRiskLevel = row.querySelector('td:nth-child(2) span').textContent;
                const rowStatus = row.querySelector('td:nth-child(5) span').textContent;
                const rowText = row.textContent.toLowerCase();

                let show = true;
                if (riskLevel && rowRiskLevel !== riskLevel) show = false;
                if (status && rowStatus !== status) show = false;
                if (search && !rowText.includes(search.toLowerCase())) show = false;

                row.style.display = show ? '' : 'none';
            });
        }

        function refreshCases() {
            location.reload();
        }

        function exportCases() {
            window.open('../api/fraud-detection/export-cases.php', '_blank');
        }
    </script>
</body>
</html>