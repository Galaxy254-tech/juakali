<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

// Get pending KYC documents
$db->query("
    SELECT kd.*, u.first_name, u.last_name, u.email
    FROM kyc_documents kd
    JOIN users u ON kd.user_id = u.id
    WHERE kd.status = 'pending'
    ORDER BY kd.created_at ASC
");
$pending_kyc = $db->resultSet();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-id-card"></i> KYC Verification</h2>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Pending Documents (<?php echo count($pending_kyc); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($pending_kyc)): ?>
                    <p class="text-muted">No pending KYC documents</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Document Type</th>
                                <th>Submitted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_kyc as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['email']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($doc['document_url']); ?>" class="btn btn-sm btn-info" target="_blank">View</a>
                                    <button class="btn btn-sm btn-success" onclick="approveKYC(<?php echo $doc['id']; ?>)">Approve</button>
                                    <button class="btn btn-sm btn-danger" onclick="rejectKYC(<?php echo $doc['id']; ?>)">Reject</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function approveKYC(id) {
            if (confirm('Approve this KYC document?')) {
                fetch('/api/kyc/approve.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({kyc_id: id})
                }).then(r => r.json()).then(d => {
                    alert(d.message);
                    location.reload();
                });
            }
        }

        function rejectKYC(id) {
            let reason = prompt('Rejection reason:');
            if (reason) {
                fetch('/api/kyc/reject.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({kyc_id: id, reason: reason})
                }).then(r => r.json()).then(d => {
                    alert(d.message);
                    location.reload();
                });
            }
        }
    </script>
</body>
</html>
