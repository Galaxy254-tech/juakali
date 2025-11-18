<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'retailer') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

$db->query("
    SELECT p.*, u.company_name as supplier_name
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    JOIN users u ON p.supplier_id = u.id
    WHERE w.retailer_id = ?
    ORDER BY w.added_at DESC
");
$db->bind(':retailer', $_SESSION['user_id']);
$wishlist_items = $db->resultSet();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wishlist - Retailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-heart"></i> My Wishlist</h2>

        <div class="row g-4">
            <?php foreach ($wishlist_items as $item): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($item['supplier_name']); ?></p>
                        <h6 class="text-success">KES <?php echo number_format($item['price'], 2); ?></h6>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-primary" onclick="addToCart(<?php echo $item['id']; ?>)">Add to Cart</button>
                            <button class="btn btn-sm btn-danger" onclick="removeFromWishlist(<?php echo $item['id']; ?>)">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function addToCart(productId) {
            fetch('/api/cart/add.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({product_id: productId, quantity: 1})
            }).then(r => r.json()).then(d => {
                alert(d.message);
            });
        }

        function removeFromWishlist(productId) {
            fetch('/api/wishlist/remove.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({product_id: productId})
            }).then(r => r.json()).then(d => {
                alert(d.message);
                location.reload();
            });
        }
    </script>
</body>
</html>
