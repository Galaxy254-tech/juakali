<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'supplier') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

$db->query("
    SELECT ia.*, p.name as product_name
    FROM inventory_alerts ia
    JOIN products p ON ia.product_id = p.id
    WHERE ia.supplier_id = ?
");
$db->bind(':supplier', $_SESSION['user_id']);
$alerts = $db->resultSet();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Supplier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-warehouse"></i> Inventory Alerts</h2>

        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Alert Type</th>
                            <th>Threshold</th>
                            <th>Current Quantity</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($alert['product_name']); ?></td>
                            <td><span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?></span></td>
                            <td><?php echo $alert['threshold_quantity']; ?></td>
                            <td><?php echo $alert['current_quantity']; ?></td>
                            <td><span class="badge bg-<?php echo $alert['status'] === 'active' ? 'danger' : 'success'; ?>"><?php echo ucfirst($alert['status']); ?></span></td>
                            <td>
                                <?php if ($alert['status'] === 'active'): ?>
                                <button class="btn btn-sm btn-success" onclick="resolveAlert(<?php echo $alert['id']; ?>)">Resolve</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function resolveAlert(id) {
            fetch('/api/inventory/resolve.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({alert_id: id})
            }).then(r => r.json()).then(d => {
                alert(d.message);
                location.reload();
            });
        }
    </script>
</body>
</html>
