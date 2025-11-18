<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

try {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') throw new Exception('Permission denied', 403);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    $db = Database::getInstance();

    if ($method === 'GET') {
        $db->query("SELECT * FROM system_settings");
        $settings = $db->resultSet();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $result]);
        
    } elseif ($method === 'PUT') {
        $key = $input['key'] ?? null;
        $value = $input['value'] ?? null;

        if (!$key) throw new Exception('Setting key required', 400);

        $db->query("SELECT id FROM system_settings WHERE setting_key = ?");
        $db->bind(':key', $key);
        $existing = $db->single();

        if ($existing) {
            $db->query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        } else {
            $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        }
        
        $db->bind(':value', $value);
        $db->bind(':key', $key);

        if ($db->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Setting updated']);
        } else {
            throw new Exception('Failed to update setting', 500);
        }
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
