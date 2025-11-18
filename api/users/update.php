<?php
/**
 * Update User Profile API Endpoint
 * PUT /api/users/update.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/validation.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? $_SESSION['user_id'];

    // Users can only update their own profile unless admin
    if ($_SESSION['role'] !== 'admin' && $user_id != $_SESSION['user_id']) {
        throw new Exception('Permission denied', 403);
    }

    $db = Database::getInstance();
    
    // Verify user exists
    $db->query("SELECT id FROM users WHERE id = ?");
    $db->bind(':id', $user_id);
    if (!$db->single()) {
        throw new Exception('User not found', 404);
    }

    // Prepare update fields
    $updates = [];
    $bindings = [];

    if (isset($input['first_name'])) {
        $updates[] = "first_name = ?";
        $bindings['first_name'] = $input['first_name'];
    }

    if (isset($input['last_name'])) {
        $updates[] = "last_name = ?";
        $bindings['last_name'] = $input['last_name'];
    }

    if (isset($input['phone'])) {
        $updates[] = "phone = ?";
        $bindings['phone'] = $input['phone'];
    }

    if (isset($input['company_name'])) {
        $updates[] = "company_name = ?";
        $bindings['company_name'] = $input['company_name'];
    }

    if (isset($input['email'])) {
        // Check email not in use
        $db->query("SELECT id FROM users WHERE email = ? AND id != ?");
        $db->bind(':email', $input['email']);
        $db->bind(':id', $user_id);
        if ($db->single()) {
            throw new Exception('Email already in use', 400);
        }
        $updates[] = "email = ?";
        $bindings['email'] = $input['email'];
    }

    if (empty($updates)) {
        throw new Exception('No fields to update', 400);
    }

    $updates[] = "updated_at = NOW()";
    
    $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $db->query($query);

    foreach ($bindings as $key => $value) {
        $db->bind(':' . $key, $value);
    }
    $db->bind(':id', $user_id);

    if ($db->execute()) {
        logAudit('update', 'users', $user_id, $bindings);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User profile updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update user', 500);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'UPDATE_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
