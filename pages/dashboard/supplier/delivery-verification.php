<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../integrations/qr-generator.php';
require_once '../../includes/delivery-verification.php';

requireRole('supplier');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

$error = '';
$success = '';
$verification_result = null;

// Get pending deliveries for this supplier
$db->query('SELECT o.*, dt.status, dt.tracking_number, u.company_name FROM orders o
           JOIN delivery_tracking dt ON o.id = dt.order_id
           JOIN users u ON o.retailer_id = u.id
           WHERE o.supplier_id = ? AND dt.status IN ("pending", "in_transit", "out_for_delivery")
           ORDER BY o.delivery_date ASC');
$db->bind('i', $user_id);
$pending_deliveries = $db->resultSet();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if ($action === 'generate_qr') {
        $db->query('SELECT * FROM orders WHERE id = ? AND supplier_id = ?');
        $db->bind('i', $order_id);
        $db->bind('i', $user_id);
        $order = $db->single();
        
        if ($order) {
            $db->query('SELECT tracking_number FROM delivery_tracking WHERE order_id = ?');
            $db->bind('i', $order_id);
            $tracking = $db->single();
            
            $_SESSION['qr_order_id'] = $order_id;
            $_SESSION['qr_code'] = QRCodeGenerator::generateOrderQR($order_id, $tracking['tracking_number']);
            $success = 'QR code generated successfully!';
        }
    } elseif ($action === 'send_verification_pin') {
        $verification = new DeliveryVerification($db);
        $result = $verification->initiateDeliveryVerification($order_id, $user_id);
        
        if ($result['success']) {
            $success = $result['message'];
            $_SESSION['delivery_verification'] = $result;
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Verification - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <h5 style="margin-bottom: 1.5rem; font-weight: 700;"><i class="fas fa-bars"></i> Menu</h5>
            <ul class="sidebar-menu">
                <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="delivery-verification.php" class="active"><i class="fas fa-check-circle"></i> Verify Deliveries</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-clipboard-check"></i> Delivery Verification</h1>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- QR Code Display -->
            <?php if (isset($_SESSION['qr_code'])): ?>
                <div class="card-section">
                    <h3 class="section-title">Generated QR Code</h3>
                    <div style="text-align: center; padding: 2rem;">
                        <img src="<?php echo htmlspecialchars($_SESSION['qr_code']); ?>" alt="Delivery QR Code" style="max-width: 300px;">
                        <p style="margin-top: 1rem; color: #6b7280;">
                            Scan this QR code for verification
                        </p>
                        <?php unset($_SESSION['qr_code']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pending Deliveries -->
            <div class="card-section">
                <h3 class="section-title"><i class="fas fa-truck"></i> Pending Deliveries</h3>
                <?php if ($pending_deliveries): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Retailer</th>
                                    <th>Tracking #</th>
                                    <th>Delivery Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_deliveries as $delivery): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($delivery['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($delivery['tracking_number']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($delivery['delivery_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $delivery['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="generate_qr">
                                                <input type="hidden" name="order_id" value="<?php echo $delivery['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-qrcode"></i> QR Code
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="send_verification_pin">
                                                <input type="hidden" name="order_id" value="<?php echo $delivery['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="fas fa-key"></i> Send PIN
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No pending deliveries.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
