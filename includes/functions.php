<?php
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function setFlash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

function formatCurrency($amount) {
    return 'KES ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y H:i', strtotime($datetime));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        $db = new Database();
        $db->connect();
        $db->query('SELECT * FROM users WHERE id = ?');
        $db->bind('i', $_SESSION['user_id']);
        return $db->single();
    }
    return null;
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/auth/login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        die('Access Denied');
    }
}

function getUserById($id) {
    $db = new Database();
    $db->connect();
    $db->query('SELECT * FROM users WHERE id = ?');
    $db->bind('i', $id);
    return $db->single();
}

function getOrderById($id) {
    $db = new Database();
    $db->connect();
    $db->query('SELECT * FROM orders WHERE id = ?');
    $db->bind('i', $id);
    return $db->single();
}

function getLoanById($id) {
    $db = new Database();
    $db->connect();
    $db->query('SELECT * FROM loans WHERE id = ?');
    $db->bind('i', $id);
    return $db->single();
}

function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function calculateCreditScore($retailer_id) {
    $db = new Database();
    $db->connect();
    
    // Get total orders
    $db->query('SELECT COUNT(*) as count FROM orders WHERE retailer_id = ?');
    $db->bind('i', $retailer_id);
    $orders = $db->single();
    
    // Get completed loans
    $db->query('SELECT COUNT(*) as count FROM loans WHERE retailer_id = ? AND status = "repaid"');
    $db->bind('i', $retailer_id);
    $completed_loans = $db->single();
    
    // Get defaulted loans
    $db->query('SELECT COUNT(*) as count FROM loans WHERE retailer_id = ? AND status = "defaulted"');
    $db->bind('i', $retailer_id);
    $defaulted_loans = $db->single();
    
    $score = 500; // Base score
    $score += ($orders['count'] * 10); // Add points for orders
    $score += ($completed_loans['count'] * 50); // Add points for completed loans
    $score -= ($defaulted_loans['count'] * 100); // Deduct points for defaults
    
    return max(0, min(1000, $score)); // Cap between 0-1000
}

function addNotification($user_id, $title, $message, $type = 'system') {
    $db = new Database();
    $db->connect();
    $db->query('INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)');
    $db->bind('i', $user_id);
    $db->bind('s', $title);
    $db->bind('s', $message);
    $db->bind('s', $type);
    return $db->execute();
}

function getUnreadNotifications($user_id) {
    $db = new Database();
    $db->connect();
    $db->query('SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY created_at DESC LIMIT 5');
    $db->bind('i', $user_id);
    $result = $db->resultSet();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>
