<?php
/**
 * Seed Demo Data for Testing
 */
session_start();

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

// Create demo products
$demo_products = [
    ['name' => 'Rice (50kg)', 'price' => 2500, 'category' => 'Grains'],
    ['name' => 'Maize Flour (2kg)', 'price' => 200, 'category' => 'Flour'],
    ['name' => 'Sugar (1kg)', 'price' => 150, 'category' => 'Sweeteners'],
    ['name' => 'Cooking Oil (2L)', 'price' => 800, 'category' => 'Oils']
];

foreach ($demo_products as $product) {
    $db->query("
        INSERT INTO products (supplier_id, name, price, category, quantity_available, status)
        VALUES (1, ?, ?, ?, 100, 'active')
    ");
    $db->bind(':name', $product['name']);
    $db->bind(':price', $product['price']);
    $db->bind(':category', $product['category']);
    $db->execute();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Demo Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Demo data seeded successfully! <?php echo count($demo_products); ?> products added.
        </div>
        <a href="/" class="btn btn-primary">View Platform</a>
    </div>
</body>
</html>
