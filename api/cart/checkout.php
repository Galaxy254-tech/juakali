<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'retailer') throw new Exception('Permission denied', 403);
    
    $db = Database::getInstance();
    
    // Get cart items
    $db->query("SELECT * FROM shopping_cart WHERE retailer_id = ?");
    $db->bind(':user', $_SESSION['user_id']);
    $cart_items = $db->resultSet();

    if (empty($cart_items)) {
        throw new Exception('Cart is empty', 400);
    }

    // Calculate total and group by supplier
    $orders_by_supplier = [];
    $total_amount = 0;

    foreach ($cart_items as $item) {
        $db->query("SELECT supplier_id FROM products WHERE id = ?");
        $db->bind(':id', $item['product_id']);
        $product = $db->single();

        if ($product) {
            $supplier_id = $product['supplier_id'];
            if (!isset($orders_by_supplier[$supplier_id])) {
                $orders_by_supplier[$supplier_id] = [];
            }
            $orders_by_supplier[$supplier_id][] = $item;
            $total_amount += $item['subtotal'];
        }
    }

    // Create orders for each supplier
    $order_ids = [];
    foreach ($orders_by_supplier as $supplier_id => $items) {
        $order_number = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
        $order_total = array_sum(array_column($items, 'subtotal'));

        $db->query("
            INSERT INTO orders (retailer_id, supplier_id, order_number, total_amount, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $db->bind(':retailer', $_SESSION['user_id']);
        $db->bind(':supplier', $supplier_id);
        $db->bind(':number', $order_number);
        $db->bind(':total', $order_total);
        
        if ($db->execute()) {
            $order_id = $db->lastInsertId();
            $order_ids[] = $order_id;

            // Add items to order
            foreach ($items as $item) {
                $db->query("
                    INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $db->bind(':order', $order_id);
                $db->bind(':product', $item['product_id']);
                $db->bind(':qty', $item['quantity']);
                $db->bind(':price', $item['unit_price']);
                $db->bind(':subtotal', $item['subtotal']);
                $db->execute();
            }
        }
    }

    // Clear cart
    $db->query("DELETE FROM shopping_cart WHERE retailer_id = ?");
    $db->bind(':user', $_SESSION['user_id']);
    $db->execute();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Orders created successfully',
        'data' => ['order_ids' => $order_ids, 'total' => $total_amount]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
