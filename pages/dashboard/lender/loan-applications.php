<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'lender') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

$db->query("
    SELECT l.*, u.company_name, cs.score
    FROM loans l
    JOIN users u ON l.retailer_id = u.id
    JOIN credit_scores cs ON l.retailer_id = cs.retailer_id
    WHERE l.status = 'pending'
    ORDER BY l.created_at DESC
");
$applications = $db->resultSet();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Applications - Lender</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-file-invoice"></i> Loan Applications (<?php echo count($applications); ?>)</h2>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Retailer</th>
                            <th>Loan Amount</th>
                            <th>Interest Rate</th>
                            <th>Credit Score</th>
                            <th>Applied</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                            <td>KES <?php echo number_format($app['loan_amount'], 0); ?></td>
                            <td><?php echo $app['interest_rate']; ?>%</td>
                            <td><span class="badge bg-info"><?php echo $app['score']; ?></span></td>
                            <td><?php echo date('M d', strtotime($app['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="approveLoan(<?php echo $app['id']; ?>)">Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="rejectLoan(<?php echo $app['id']; ?>)">Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function approveLoan(id) {
            fetch('/api/loans/approve.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({loan_id: id})
            }).then(r => r.json()).then(d => {
                alert(d.message);
                location.reload();
            });
        }

        function rejectLoan(id) {
            if (confirm('Reject this loan application?')) {
                alert('Loan rejected');
                location.reload();
            }
        }
    </script>
</body>
</html>
