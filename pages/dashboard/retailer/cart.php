<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireRole('retailer');

$user_id = $_SESSION['user_id'];
$db = new Database();
$db->connect();

$error = '';
$success = '';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_to_cart') {
        $product_id = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        // Get product details
        $db->query('SELECT * FROM products WHERE id = ? AND status = "active"');
        $db->bind('i', $product_id);
        $product = $db->single();
        
        if (!$product) {
            $error = 'Product not found or inactive';
        } else if ($quantity < 1) {
            $error = 'Invalid quantity';
        } else if ($quantity > $product['quantity_available']) {
            $error = 'Insufficient stock available';
        } else {
            $unit_price = $product['price'];
            $subtotal = $unit_price * $quantity;
            
            // Check if already in cart
            $db->query('SELECT id FROM shopping_cart WHERE retailer_id = ? AND product_id = ?');
            $db->bind('i', $user_id);
            $db->bind('i', $product_id);
            $existing = $db->single();
            
            if ($existing) {
                // Update quantity
                $db->query('UPDATE shopping_cart SET quantity = quantity + ?, subtotal = subtotal + ?, updated_at = NOW() 
                           WHERE retailer_id = ? AND product_id = ?');
                $db->bind('i', $quantity);
                $db->bind('d', $subtotal);
                $db->bind('i', $user_id);
                $db->bind('i', $product_id);
                $db->execute();
                $success = 'Product quantity updated in cart';
            } else {
                // Add new item
                $db->query('INSERT INTO shopping_cart (retailer_id, product_id, quantity, unit_price, subtotal) 
                           VALUES (?, ?, ?, ?, ?)');
                $db->bind('i', $user_id);
                $db->bind('i', $product_id);
                $db->bind('i', $quantity);
                $db->bind('d', $unit_price);
                $db->bind('d', $subtotal);
                $db->execute();
                $success = 'Product added to cart';
            }
        }
    } elseif ($action === 'update_quantity') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($quantity < 1) {
            // Remove item
            $db->query('DELETE FROM shopping_cart WHERE id = ? AND retailer_id = ?');
            $db->bind('i', $cart_id);
            $db->bind('i', $user_id);
            $db->execute();
            $success = 'Item removed from cart';
        } else {
            // Get product for stock check
            $db->query('SELECT p.quantity_available, sc.unit_price FROM shopping_cart sc 
                       JOIN products p ON sc.product_id = p.id 
                       WHERE sc.id = ? AND sc.retailer_id = ?');
            $db->bind('i', $cart_id);
            $db->bind('i', $user_id);
            $item = $db->single();
            
            if ($quantity > $item['quantity_available']) {
                $error = 'Insufficient stock available';
            } else {
                $new_subtotal = $item['unit_price'] * $quantity;
                $db->query('UPDATE shopping_cart SET quantity = ?, subtotal = ?, updated_at = NOW() 
                           WHERE id = ? AND retailer_id = ?');
                $db->bind('i', $quantity);
                $db->bind('d', $new_subtotal);
                $db->bind('i', $cart_id);
                $db->bind('i', $user_id);
                $db->execute();
                $success = 'Cart updated';
            }
        }
    } elseif ($action === 'remove_item') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $db->query('DELETE FROM shopping_cart WHERE id = ? AND retailer_id = ?');
        $db->bind('i', $cart_id);
        $db->bind('i', $user_id);
        $db->execute();
        $success = 'Item removed from cart';
    } elseif ($action === 'clear_cart') {
        $db->query('DELETE FROM shopping_cart WHERE retailer_id = ?');
        $db->bind('i', $user_id);
        $db->execute();
        $success = 'Cart cleared';
    } elseif ($action === 'save_for_later') {
        $cart_id = intval($_POST['cart_id'] ?? 0);
        $db->query('INSERT INTO cart_saved_for_later (cart_id) VALUES (?)');
        $db->bind('i', $cart_id);
        $db->execute();
        $success = 'Item saved for later';
    } elseif ($action === 'checkout') {
        // Create order from cart
        $db->query('SELECT sc.*, u.id as supplier_id FROM shopping_cart sc 
                   JOIN products p ON sc.product_id = p.id 
                   JOIN users u ON p.supplier_id = u.id 
                   WHERE sc.retailer_id = ? GROUP BY u.id');
        $db->bind('i', $user_id);
        $suppliers = $db->resultSet();
        
        if (!$suppliers) {
            $error = 'Cart is empty';
        } else {
            foreach ($suppliers as $supplier_group) {
                // Get supplier ID
                $supplier_id = $supplier_group['supplier_id'];
                
                // Calculate total
                $db->query('SELECT SUM(subtotal) as total FROM shopping_cart sc 
                           JOIN products p ON sc.product_id = p.id 
                           WHERE sc.retailer_id = ? AND p.supplier_id = ?');
                $db->bind('i', $user_id);
                $db->bind('i', $supplier_id);
                $total = $db->single()['total'];
                
                // Create order
                $order_number = 'ORD-' . $user_id . '-' . time();
                $db->query('INSERT INTO orders (retailer_id, supplier_id, order_number, total_amount, status) 
                           VALUES (?, ?, ?, ?, "pending")');
                $db->bind('i', $user_id);
                $db->bind('i', $supplier_id);
                $db->bind('s', $order_number);
                $db->bind('d', $total);
                $db->execute();
                $order_id = $db->lastInsertId();
                
                // Add order items
                $db->query('SELECT sc.* FROM shopping_cart sc 
                           JOIN products p ON sc.product_id = p.id 
                           WHERE sc.retailer_id = ? AND p.supplier_id = ?');
                $db->bind('i', $user_id);
                $db->bind('i', $supplier_id);
                $cart_items = $db->resultSet();
                
                foreach ($cart_items as $item) {
                    $db->query('INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) 
                               VALUES (?, ?, ?, ?, ?)');
                    $db->bind('i', $order_id);
                    $db->bind('i', $item['product_id']);
                    $db->bind('i', $item['quantity']);
                    $db->bind('d', $item['unit_price']);
                    $db->bind('d', $item['subtotal']);
                    $db->execute();
                }
            }
            
            // Clear cart
            $db->query('DELETE FROM shopping_cart WHERE retailer_id = ?');
            $db->bind('i', $user_id);
            $db->execute();
            
            $success = 'Order created successfully! Redirecting...';
            header('Location: orders.php', true, 303);
            exit;
        }
    }
}

// Get cart items
$db->query('SELECT sc.*, p.name, p.category, u.company_name FROM shopping_cart sc 
           JOIN products p ON sc.product_id = p.id 
           JOIN users u ON p.supplier_id = u.id 
           WHERE sc.retailer_id = ? 
           ORDER BY sc.updated_at DESC');
$db->bind('i', $user_id);
$cart_items = $db->resultSet();

// Calculate totals
$db->query('SELECT COUNT(*) as item_count, SUM(subtotal) as total_amount FROM shopping_cart WHERE retailer_id = ?');
$db->bind('i', $user_id);
$cart_summary = $db->single();

// Get user credit info
$db->query('SELECT * FROM credit_scores WHERE retailer_id = ?');
$db->bind('i', $user_id);
$credit = $db->single();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - JuaKali Lend</title>
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
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Browse Products</a></li>
                <li><a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                <li><a href="orders.php"><i class="fas fa-box"></i> Orders</a></li>
                <li><a href="repayments.php"><i class="fas fa-credit-card"></i> Repayments</a></li>
                <li><a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
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

            <div class="row">
                <div class="col-lg-8">
                    <div class="card-section">
                        <h3 class="section-title"><i class="fas fa-list"></i> Cart Items (<?php echo $cart_summary['item_count'] ?? 0; ?>)</h3>
                        
                        <?php if ($cart_items): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Supplier</th>
                                            <th>Unit Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['company_name']); ?></td>
                                                <td>KES <?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_quantity">
                                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                        <div class="input-group" style="width: 100px;">
                                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" class="btn btn-sm btn-outline-secondary">-</button>
                                                            <input type="text" class="form-control text-center" value="<?php echo $item['quantity']; ?>" readonly>
                                                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="btn btn-sm btn-outline-secondary">+</button>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td><strong>KES <?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="remove_item">
                                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top: 1rem;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="clear_cart">
                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash-alt"></i> Clear Cart
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Your cart is empty. <a href="products.php">Continue shopping</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Cart Summary -->
                    <div class="card-section">
                        <h3 class="section-title"><i class="fas fa-receipt"></i> Order Summary</h3>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>Subtotal:</span>
                                <strong>KES <?php echo number_format($cart_summary['total_amount'] ?? 0, 2); ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>Tax (16%):</span>
                                <strong>KES <?php echo number_format(($cart_summary['total_amount'] ?? 0) * 0.16, 2); ?></strong>
                            </div>
                            <hr>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 1.25rem;">
                                <span><strong>Total:</strong></span>
                                <strong style="color: #10b981;">KES <?php echo number_format(($cart_summary['total_amount'] ?? 0) * 1.16, 2); ?></strong>
                            </div>
                        </div>

                        <?php if ($cart_items): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="checkout">
                                <button type="submit" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                                </button>
                            </form>
                            <a href="products.php" class="btn btn-secondary w-100">
                                <i class="fas fa-shopping-bag"></i> Continue Shopping
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Credit Info -->
                    <div class="card-section">
                        <h3 class="section-title"><i class="fas fa-wallet"></i> Credit Info</h3>
                        <div style="margin-bottom: 0.5rem;">
                            <small class="text-muted">Available Credit</small>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">
                                KES <?php echo number_format(($credit['credit_limit'] ?? 0) - ($credit['total_borrowed'] ?? 0), 2); ?>
                            </div>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <small class="text-muted">Credit Limit</small>
                            <div style="font-size: 0.875rem;">
                                KES <?php echo number_format($credit['credit_limit'] ?? 0, 2); ?>
                            </div>
                        </div>
                        <?php if (($cart_summary['total_amount'] ?? 0) * 1.16 > ($credit['credit_limit'] ?? 0)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> Order exceeds your credit limit
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
