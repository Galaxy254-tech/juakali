<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$db = Database::getInstance();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setting_key = $_POST['setting_key'] ?? null;
    $setting_value = $_POST['setting_value'] ?? null;
    
    if ($setting_key && $setting_value) {
        $db->query("SELECT id FROM system_settings WHERE setting_key = ?");
        $db->bind(':key', $setting_key);
        $existing = $db->single();
        
        if ($existing) {
            $db->query("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
        } else {
            $db->query("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        }
        $db->bind(':value', $setting_value);
        $db->bind(':key', $setting_key);
        $db->execute();
        
        $success = "Setting saved successfully!";
    }
}

// Get all settings
$db->query("SELECT * FROM system_settings");
$settings = $db->resultSet();
$settings_array = [];
foreach ($settings as $s) {
    $settings_array[$s['setting_key']] = $s['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 bg-light p-4">
                <h5 class="mb-4"><i class="fas fa-cog"></i> Admin Menu</h5>
                <ul class="list-unstyled">
                    <li><a href="/dashboard/admin/" class="text-decoration-none">Dashboard</a></li>
                    <li><a href="/dashboard/admin/settings.php" class="text-decoration-none text-success font-weight-bold">Settings</a></li>
                    <li><a href="/dashboard/admin/users-management.php" class="text-decoration-none">Users</a></li>
                    <li><a href="/dashboard/admin/kyc-management.php" class="text-decoration-none">KYC</a></li>
                    <li><a href="/dashboard/admin/penalties.php" class="text-decoration-none">Penalties</a></li>
                    <li><a href="/dashboard/admin/reports.php" class="text-decoration-none">Reports</a></li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 p-4">
                <h2 class="mb-4"><i class="fas fa-sliders-h"></i> System Settings</h2>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Configuration Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" name="setting_key" value="site_name" hidden>
                                <input type="text" class="form-control" name="setting_value" value="<?php echo htmlspecialchars($settings_array['site_name'] ?? 'JuaKali Lend'); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Support Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($settings_array['support_email'] ?? 'support@juakali.com'); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Support Phone</label>
                                <input type="tel" class="form-control" value="<?php echo htmlspecialchars($settings_array['support_phone'] ?? '+254712345678'); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Default Currency</label>
                                <select class="form-control">
                                    <option>KES - Kenyan Shilling</option>
                                    <option>USD - US Dollar</option>
                                    <option>EUR - Euro</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Maintenance Section -->
                <div class="card mt-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Maintenance</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary" onclick="clearCache()">
                            <i class="fas fa-trash"></i> Clear Cache
                        </button>
                        <button class="btn btn-primary" onclick="generateBackup()">
                            <i class="fas fa-download"></i> Backup Database
                        </button>
                        <button class="btn btn-primary" onclick="resetSystem()">
                            <i class="fas fa-refresh"></i> Reset System
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clearCache() {
            alert('Cache cleared successfully!');
        }
        function generateBackup() {
            alert('Backup initiated...');
        }
        function resetSystem() {
            if (confirm('Are you sure? This cannot be undone.')) {
                alert('System reset initiated');
            }
        }
    </script>
</body>
</html>
