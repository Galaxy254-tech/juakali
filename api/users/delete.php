<?php
/**
 * Soft Delete User Account API Endpoint
 * DELETE /api/users/delete.php
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? $_SESSION['user_id'];

    // Users can only delete their own account unless admin
    if ($_SESSION['role'] !== 'admin' && $user_id != $_SESSION['user_id']) {
        throw new Exception('Permission denied', 403);
    }

    $db = Database::getInstance();
    
    $db->query("SELECT id FROM users WHERE id = ?");
    $db->bind(':id', $user_id);
    if (!$db->single()) {
        throw new Exception('User not found', 404);
    }

    // Soft delete
    $db->query("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
    $db->bind(':id', $user_id);

    if ($db->execute()) {
        logAudit('delete', 'users', $user_id, []);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User account deactivated'
        ]);
    } else {
        throw new Exception('Failed to delete user', 500);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'DELETE_FAILED',
        'message' => $e->getMessage()
    ]);
}
?>
