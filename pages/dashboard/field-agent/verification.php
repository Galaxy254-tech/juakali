<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('field_agent');

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;
$db = new Database();
$db->connect();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_id) {
    $pin = sanitize($_POST['pin'] ?? '');
    $delivery_notes = sanitize($_POST['delivery_notes'] ?? '');
    $latitude = sanitize($_POST['latitude'] ?? '');
    $longitude = sanitize($_POST['longitude'] ?? '');
    $proof_photo = $_FILES['proof_photo'] ?? null;

    if (empty($pin) || strlen($pin) !== 4) {
        $error = 'PIN must be 4 digits';
    } else {
        // Verify PIN with retailer (compare last 4 digits of phone or PIN sent to them)
        // Update delivery tracking
        $db->query('UPDATE delivery_tracking SET status = ?, current_location = ?, actual_delivery_date = NOW() WHERE order_id = ?');
        $db->bind('s', 'delivered');
        $db->bind('s', $latitude . ',' . $longitude);
        $db->bind('i', $order_id);
        $db->execute();

        if ($db->getAffectedRows()) {
            // Update order status
            $db->query('UPDATE orders SET status = ? WHERE id = ?');
            $db->bind('s', 'delivered');
            $db->bind('i', $order_id);
            $db->execute();

            $success = 'Delivery verified successfully!';
        } else {
            $error = 'Failed to verify delivery. Please try again.';
        }
    }
}

if ($order_id) {
    $db->query('SELECT o.*, u.company_name, dt.status, dt.tracking_number FROM orders o
               JOIN delivery_tracking dt ON o.id = dt.order_id
               JOIN users u ON o.supplier_id = u.id
               WHERE o.id = ?');
    $db->bind('i', $order_id);
    $order = $db->single();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Delivery - Field Agent - JuaKali Lend</title>
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
                <li><a href="retailers.php"><i class="fas fa-users"></i> Retailers</a></li>
                <li><a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a></li>
                <li><a href="verification.php" class="active"><i class="fas fa-check-circle"></i> Verify Deliveries</a></li>
                <li><a href="earnings.php"><i class="fas fa-money-bill"></i> My Earnings</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-check-circle"></i> Verify Delivery</h1>
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

            <?php if ($order): ?>
                <div class="card-section">
                    <h3 class="section-title">Delivery Details</h3>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number']); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Supplier:</strong> <?php echo htmlspecialchars($order['company_name']); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Amount:</strong> KES <?php echo number_format($order['total_amount'], 2); ?>
                        </div>
                    </div>
                </div>

                <div class="card-section">
                    <h3 class="section-title">Verify Delivery</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group mb-3">
                            <label for="pin" class="form-label">Delivery PIN (4 digits)</label>
                            <input type="text" class="form-control" id="pin" name="pin" placeholder="Enter 4-digit PIN" maxlength="4" required>
                            <small class="text-muted">Ask the retailer for the PIN sent to their phone</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="proof_photo" class="form-label">Proof Photo</label>
                            <input type="file" class="form-control" id="proof_photo" name="proof_photo" accept="image/*" required>
                            <small class="text-muted">Take a photo of the delivery as proof</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="text" class="form-control" id="latitude" name="latitude" placeholder="Auto-filled" readonly>
                        </div>

                        <div class="form-group mb-3">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="text" class="form-control" id="longitude" name="longitude" placeholder="Auto-filled" readonly>
                        </div>

                        <div class="form-group mb-3">
                            <label for="delivery_notes" class="form-label">Delivery Notes</label>
                            <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="3" placeholder="Any notes about the delivery..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Confirm Delivery
                        </button>
                        <a href="deliveries.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </form>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Please select a delivery to verify.
                    <a href="deliveries.php" class="btn btn-sm btn-primary ms-2">View Deliveries</a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill geolocation
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
                document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
            });
        }
    </script>
</body>
</html>
