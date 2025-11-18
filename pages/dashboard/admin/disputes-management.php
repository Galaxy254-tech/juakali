<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

$db->query("
    SELECT d.*, i.first_name as initiator_first, i.last_name as initiator_last, r.first_name as respondent_first, r.last_name as respondent_last
    FROM disputes d
    JOIN users i ON d.initiator_id = i.id
    JOIN users r ON d.respondent_id = r.id
    WHERE d.status IN ('open', 'in_review')
    ORDER BY d.created_at DESC
");
$disputes = $db->resultSet();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disputes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-gavel"></i> Dispute Resolution</h2>

        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Active Disputes (<?php echo count($disputes); ?>)</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Initiator</th>
                            <th>Respondent</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disputes as $dispute): ?>
                        <tr>
                            <td>#<?php echo $dispute['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($dispute['initiator_first'] . ' ' . $dispute['initiator_last']); ?></td>
                            <td><?php echo htmlspecialchars($dispute['respondent_first'] . ' ' . $dispute['respondent_last']); ?></td>
                            <td><?php echo htmlspecialchars($dispute['subject']); ?></td>
                            <td><span class="badge bg-warning">In Review</span></td>
                            <td><?php echo date('M d', strtotime($dispute['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewDispute(<?php echo $dispute['id']; ?>)">Review</button>
                                <button class="btn btn-sm btn-success" onclick="resolveDispute(<?php echo $dispute['id']; ?>)">Resolve</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function viewDispute(id) {
            alert('Opening dispute #' + id);
        }
        function resolveDispute(id) {
            let resolution = prompt('Enter resolution:');
            if (resolution) {
                alert('Dispute resolved');
            }
        }
    </script>
</body>
</html>
