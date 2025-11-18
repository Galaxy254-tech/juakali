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
    SELECT pr.*, p.name as product_name, u.company_name as retailer_name
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    JOIN users u ON pr.retailer_id = u.id
    WHERE p.supplier_id = ?
    ORDER BY pr.created_at DESC
");
$db->bind(':supplier', $_SESSION['user_id']);
$reviews = $db->resultSet();

// Get average rating
$db->query("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
    FROM product_reviews pr
    JOIN products p ON pr.product_id = p.id
    WHERE p.supplier_id = ?
");
$db->bind(':supplier', $_SESSION['user_id']);
$stats = $db->single();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - Supplier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid p-4">
        <h2 class="mb-4"><i class="fas fa-star"></i> Customer Reviews</h2>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Average Rating</h6>
                        <h3><i class="fas fa-star text-warning"></i> <?php echo round($stats['avg_rating'] ?? 0, 1); ?>/5</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Reviews</h6>
                        <h3><?php echo $stats['total_reviews'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews -->
        <div class="card">
            <div class="card-body">
                <?php foreach ($reviews as $review): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="row">
                        <div class="col-md-8">
                            <h6><?php echo htmlspecialchars($review['product_name']); ?></h6>
                            <small class="text-muted">By <?php echo htmlspecialchars($review['retailer_name']); ?></small>
                            <div>
                                <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                                <i class="fas fa-star text-warning"></i>
                                <?php endfor; ?>
                                <?php for ($i = $review['rating']; $i < 5; $i++): ?>
                                <i class="fas fa-star text-muted"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="mt-2"><?php echo htmlspecialchars($review['review_text']); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
