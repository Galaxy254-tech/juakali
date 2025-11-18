<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/database.php';

try {
    $db = Database::getInstance();
    $db->query("SELECT 1");
    $db_status = $db->single() ? 'operational' : 'error';

    $disk_space = disk_free_space('/');
    $disk_total = disk_total_space('/');
    $disk_usage = ($disk_total - $disk_space) / $disk_total * 100;

    $memory_limit = ini_get('memory_limit');
    $upload_limit = ini_get('upload_max_filesize');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'database' => $db_status,
            'memory_limit' => $memory_limit,
            'upload_limit' => $upload_limit,
            'disk_usage' => round($disk_usage, 2) . '%',
            'php_version' => phpversion(),
            'timestamp' => date('c')
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
