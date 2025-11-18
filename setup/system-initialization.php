<?php
/**
 * System Initialization after installation
 */
session_start();

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

// Initialize system settings
$default_settings = [
    'site_name' => 'JuaKali Lend',
    'support_email' => 'support@juakali.com',
    'support_phone' => '+254712345678',
    'default_currency' => 'KES',
    'default_language' => 'en'
];

foreach ($default_settings as $key => $value) {
    $db->query("SELECT id FROM system_settings WHERE setting_key = ?");
    $db->bind(':key', $key);
    if (!$db->single()) {
        $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $db->bind(':key', $key);
        $db->bind(':value', $value);
        $db->execute();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Initialization</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> System initialized successfully!
        </div>
        <a href="/dashboard/admin/" class="btn btn-primary">Go to Admin Dashboard</a>
    </div>
</body>
</html>
