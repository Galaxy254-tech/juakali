<?php
/**
 * JuaKali Lend - Secure Installation Wizard
 * Complete system installer with enhanced security and validation
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Start session with secure settings
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// Regenerate session ID to prevent fixation
if (empty($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Error reporting - be careful in production
if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if already installed and prevent re-installation
$config_file = __DIR__ . '/../config/config.php';
$lock_file = __DIR__ . '/../config/install.lock';
$installed = (file_exists($config_file) && file_exists($lock_file));

if ($installed && !isset($_GET['force'])) {
    header('HTTP/1.0 403 Forbidden');
    die('System already installed. Use ?force=1 to reinstall (not recommended).');
}

// Handle installation steps with validation
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(5, $step)); // Ensure step is between 1-5

$error = '';
$success = '';

// Process form submissions with security checks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token validation failed. Please try again.";
    } else {
        if ($step === 2) {
            // Database configuration with validation
            $db_host = trim($_POST['db_host'] ?? 'localhost');
            $db_user = trim($_POST['db_user'] ?? 'root');
            $db_pass = trim($_POST['db_pass'] ?? '');
            $db_name = trim($_POST['db_name'] ?? 'juakali_lend');
            $app_url = trim($_POST['app_url'] ?? 'http://localhost/juakali-lend');

            // Input validation
            if (empty($db_host) || empty($db_user) || empty($db_name) || empty($app_url)) {
                $error = "All fields are required!";
            } elseif (!filter_var($app_url, FILTER_VALIDATE_URL)) {
                $error = "Please enter a valid application URL!";
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $db_name)) {
                $error = "Database name can only contain letters, numbers and underscores!";
            } else {
                // Test database connection
                try {
                    $conn = new mysqli($db_host, $db_user, $db_pass);
                    
                    if ($conn->connect_error) {
                        $error = "Database connection failed: " . htmlspecialchars($conn->connect_error);
                    } else {
                        // Create database if not exists
                        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
                            $error = "Could not create database: " . htmlspecialchars($conn->error);
                        } else {
                            $conn->select_db($db_name);
                            
                            // Generate secure encryption key
                            $encryption_key = bin2hex(random_bytes(32));
                            
                            // Save configuration with proper escaping
                            $config_content = "<?php\n";
                            $config_content .= "// JuaKali Lend Configuration - Auto-generated\n";
                            $config_content .= "// Generated on: " . date('Y-m-d H:i:s') . "\n\n";
                            $config_content .= "define('DB_HOST', " . var_export($db_host, true) . ");\n";
                            $config_content .= "define('DB_USER', " . var_export($db_user, true) . ");\n";
                            $config_content .= "define('DB_PASS', " . var_export($db_pass, true) . ");\n";
                            $config_content .= "define('DB_NAME', " . var_export($db_name, true) . ");\n";
                            $config_content .= "define('APP_NAME', 'JuaKali Lend');\n";
                            $config_content .= "define('APP_URL', " . var_export($app_url, true) . ");\n";
                            $config_content .= "define('APP_ENV', 'production');\n";
                            $config_content .= "define('ENCRYPTION_KEY', " . var_export($encryption_key, true) . ");\n";
                            $config_content .= "define('SESSION_TIMEOUT', 3600);\n";
                            $config_content .= "define('PASSWORD_MIN_LENGTH', 8);\n";
                            $config_content .= "define('MAX_LOGIN_ATTEMPTS', 5);\n";
                            $config_content .= "define('LOCKOUT_TIME', 900);\n";
                            $config_content .= "define('UPLOAD_DIR', __DIR__ . '/../uploads/');\n";
                            $config_content .= "define('MAX_FILE_SIZE', 5242880);\n";
                            $config_content .= "define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);\n";
                            $config_content .= "define('MAIL_FROM', 'noreply@juakali-lend.com');\n";
                            $config_content .= "define('MAIL_FROM_NAME', 'JuaKali Lend');\n";
                            $config_content .= "define('ITEMS_PER_PAGE', 10);\n";
                            $config_content .= "define('INSTALL_DATE', " . var_export(date('Y-m-d H:i:s'), true) . ");\n";
                            $config_content .= "\n// Security Settings\n";
                            $config_content .= "ini_set('session.cookie_httponly', 1);\n";
                            $config_content .= "ini_set('session.cookie_secure', 1);\n";
                            $config_content .= "ini_set('session.use_strict_mode', 1);\n";
                            $config_content .= "date_default_timezone_set('Africa/Nairobi');\n";
                            $config_content .= "\n// Prevent direct access\n";
                            $config_content .= "if (!defined('ABSPATH')) {\n";
                            $config_content .= "    die('Direct access not permitted');\n";
                            $config_content .= "}\n";
                            $config_content .= "?>";
                            
                            // Ensure config directory exists
                            $config_dir = dirname($config_file);
                            if (!is_dir($config_dir)) {
                                mkdir($config_dir, 0755, true);
                            }
                            
                            if (file_put_contents($config_file, $config_content, LOCK_EX)) {
                                // Set secure permissions
                                chmod($config_file, 0640);
                                $_SESSION['db_config'] = compact('db_host', 'db_user', 'db_pass', 'db_name');
                                $_SESSION['db_configured'] = true;
                                $success = "Database configured successfully!";
                            } else {
                                $error = "Could not save configuration file. Please check file permissions for the config directory.";
                            }
                        }
                        $conn->close();
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($step === 3) {
            // Database initialization
            if (!isset($_SESSION['db_configured'])) {
                $error = "Please complete database configuration first!";
            } else {
                try {
                    $db_config = $_SESSION['db_config'];
                    $conn = new mysqli($db_config['db_host'], $db_config['db_user'], $db_config['db_pass'], $db_config['db_name']);
                    
                    if ($conn->connect_error) {
                        $error = "Database connection failed: " . htmlspecialchars($conn->connect_error);
                    } else {
                        // Import schema
                        $schema_file = __DIR__ . '/../database/schema.sql';
                        if (file_exists($schema_file)) {
                            $sql = file_get_contents($schema_file);
                            
                            // Remove comments and split queries
                            $sql = preg_replace('/--.*$/m', '', $sql);
                            $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                            
                            $queries = array_filter(
                                array_map('trim', preg_split('/;[\r\n]+/', $sql)),
                                function($q) {
                                    return !empty($q) && !preg_match('/^\/\*/', $q);
                                }
                            );
                            
                            $conn->begin_transaction();
                            
                            try {
                                foreach ($queries as $query) {
                                    if (!empty($query)) {
                                        if (!$conn->query($query)) {
                                            throw new Exception("Query failed: " . $conn->error . " | Query: " . substr($query, 0, 100));
                                        }
                                    }
                                }
                                
                                $conn->commit();
                                $_SESSION['db_initialized'] = true;
                                $success = "Database initialized successfully! Created all tables and indexes.";
                                
                            } catch (Exception $e) {
                                $conn->rollback();
                                $error = "Database initialization failed: " . $e->getMessage();
                            }
                        } else {
                            $error = "Schema file not found at: " . htmlspecialchars($schema_file);
                        }
                        $conn->close();
                    }
                } catch (Exception $e) {
                    $error = "Error: " . htmlspecialchars($e->getMessage());
                }
            }
        } elseif ($step === 4) {
            // Create admin user with enhanced validation
            if (!isset($_SESSION['db_initialized'])) {
                $error = "Please initialize the database first!";
            } else {
                require_once __DIR__ . '/../config/config.php';
                require_once __DIR__ . '/../includes/database.php';
                
                $email = trim($_POST['admin_email'] ?? '');
                $password = trim($_POST['admin_password'] ?? '');
                $password_confirm = trim($_POST['admin_password_confirm'] ?? '');
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                
                // Enhanced validation
                if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
                    $error = "All fields are required!";
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid email address!";
                } elseif ($password !== $password_confirm) {
                    $error = "Passwords do not match!";
                } elseif (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long!";
                } elseif (!preg_match('/[A-Z]/', $password)) {
                    $error = "Password must contain at least one uppercase letter!";
                } elseif (!preg_match('/[a-z]/', $password)) {
                    $error = "Password must contain at least one lowercase letter!";
                } elseif (!preg_match('/[0-9]/', $password)) {
                    $error = "Password must contain at least one number!";
                } else {
                    try {
                        $db = Database::getInstance();
                        
                        // Verify database connection
                        if (!$db->getConnection()) {
                            throw new Exception("Database connection not available");
                        }
                        
                        // Check if admin already exists
                        $existingAdmin = $db->fetchOne("SELECT id FROM users WHERE email = ? AND role = 'admin'", [$email]);
                        if ($existingAdmin) {
                            throw new Exception("Admin user with this email already exists!");
                        }
                        
                        // Create strong password hash
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        
                        // Use transaction for safety
                        $db->beginTransaction();
                        
                        try {
                            // Insert admin user
                            $result = $db->execute("
                                INSERT INTO users (email, password, first_name, last_name, role, status, kyc_verified, created_at, updated_at)
                                VALUES (?, ?, ?, ?, 'admin', 'active', TRUE, NOW(), NOW())
                            ", [$email, $hashed_password, $first_name, $last_name]);
                            
                            if ($result > 0) {
                                $lastInsertId = $db->lastInsertId();
                                
                                // Verify the insert worked
                                $verifyUser = $db->fetchOne("SELECT id, email, first_name, last_name FROM users WHERE id = ?", [$lastInsertId]);
                                if (!$verifyUser) {
                                    throw new Exception("User creation verification failed - no user found after insert");
                                }
                                
                                $db->commit();
                                
                                // Log the admin creation
                                error_log("JuaKali Lend: Admin user created - ID: $lastInsertId, Email: $email");
                                
                                $_SESSION['admin_created'] = true;
                                $_SESSION['admin_user'] = $verifyUser;
                                $success = "Admin user created successfully! Welcome " . htmlspecialchars($first_name) . " " . htmlspecialchars($last_name) . ".";
                                
                            } else {
                                throw new Exception("No rows affected - insert operation failed");
                            }
                            
                        } catch (Exception $e) {
                            $db->rollBack();
                            throw $e;
                        }
                        
                    } catch (Exception $e) {
                        $error = "Error creating admin user: " . $e->getMessage();
                        error_log("Admin creation error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Check and create required directories with secure permissions
$required_dirs = [
    __DIR__ . '/../uploads/' => 0755,
    __DIR__ . '/../logs/' => 0750,
    __DIR__ . '/../cache/' => 0750,
    __DIR__ . '/../config/' => 0750,
    __DIR__ . '/../database/' => 0755
];

foreach ($required_dirs as $dir => $perms) {
    if (!is_dir($dir)) {
        @mkdir($dir, $perms, true);
        // Add .htaccess for Apache security
        if (strpos($dir, 'uploads') !== false || strpos($dir, 'config') !== false) {
            file_put_contents($dir . '.htaccess', "Order deny,allow\nDeny from all");
        }
    }
}

// Create installation lock file when complete
if ($step === 5 && isset($_SESSION['admin_created'])) {
    file_put_contents($lock_file, "Installation completed: " . date('Y-m-d H:i:s') . "\nDO NOT DELETE THIS FILE");
    chmod($lock_file, 0440);
}

// Regenerate CSRF token after each POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="JuaKali Lend Installation Wizard">
    <meta name="robots" content="noindex,nofollow">
    <title>JuaKali Lend - Secure Installation Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUa6e4K1zE6ZmC5E6F4e8k8M8p8M8p8M8p8M8p8M8p8M8p8M8p8M8p8M8p8M" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #10b981;
            --primary-dark: #0d9668;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34d399 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 1rem;
        }
        
        .installer-container {
            width: 100%;
            max-width: 650px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .installer-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34d399 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .security-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .installer-body {
            padding: 2rem;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            background: #e5e7eb;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            color: #6b7280;
            border: 3px solid white;
            transition: all 0.3s ease;
        }
        
        .progress-step.active .step-circle {
            background: var(--primary-color);
            color: white;
            transform: scale(1.1);
        }
        
        .progress-step.completed .step-circle {
            background: var(--primary-color);
            color: white;
        }
        
        .step-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .progress-step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .step-connector {
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: 1;
        }
        
        .progress-step:last-child .step-connector {
            display: none;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .form-label .required {
            color: var(--danger-color);
            margin-left: 0.25rem;
        }
        
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.1);
        }
        
        .form-control.is-invalid {
            border-color: var(--danger-color);
        }
        
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: var(--danger-color); width: 25%; }
        .strength-fair { background: var(--warning-color); width: 50%; }
        .strength-good { background: #84cc16; width: 75%; }
        .strength-strong { background: var(--primary-color); width: 100%; }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34d399 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
            background: linear-gradient(135deg, var(--primary-dark) 0%, #10b981 100%);
        }
        
        .installation-status {
            background: #f0fdf4;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
        }
        
        .status-item.completed {
            color: var(--primary-color);
        }
        
        .status-item.pending {
            color: #6b7280;
        }
        
        .status-icon {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .security-alert {
            background: #fef3cd;
            border-left: 4px solid var(--warning-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .step-content.active {
            animation: fadeIn 0.3s ease-out;
        }
        
        .help-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <!-- Header -->
        <div class="installer-header">
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i> Secure Setup
            </div>
            <h1><i class="fas fa-leaf"></i> JuaKali Lend</h1>
            <p>Secure Installation Wizard</p>
        </div>

        <!-- Body -->
        <div class="installer-body">
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="progress-step <?php echo $step >= 1 ? 'completed' : ''; ?> <?php echo $step === 1 ? 'active' : ''; ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Welcome</div>
                    <div class="step-connector"></div>
                </div>
                <div class="progress-step <?php echo $step >= 2 ? 'completed' : ''; ?> <?php echo $step === 2 ? 'active' : ''; ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Database</div>
                    <div class="step-connector"></div>
                </div>
                <div class="progress-step <?php echo $step >= 3 ? 'completed' : ''; ?> <?php echo $step === 3 ? 'active' : ''; ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">Initialize</div>
                    <div class="step-connector"></div>
                </div>
                <div class="progress-step <?php echo $step >= 4 ? 'completed' : ''; ?> <?php echo $step === 4 ? 'active' : ''; ?>">
                    <div class="step-circle">4</div>
                    <div class="step-label">Admin</div>
                    <div class="step-connector"></div>
                </div>
                <div class="progress-step <?php echo $step === 5 ? 'active' : ''; ?>">
                    <div class="step-circle">5</div>
                    <div class="step-label">Complete</div>
                </div>
            </div>

            <!-- Step 1: Welcome -->
            <div class="step-content <?php echo $step === 1 ? 'active' : ''; ?>">
                <h2 class="mb-4">Welcome to JuaKali Lend</h2>
                <p class="text-muted mb-3">
                    This secure wizard will guide you through the setup process. All data is validated and transmitted securely.
                </p>
                
                <div class="security-alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> Security Notice:</strong><br>
                    • This installer will create sensitive configuration files<br>
                    • Ensure your server meets all security requirements<br>
                    • Remove the install directory after setup completion
                </div>
                
                <div class="installation-status">
                    <div class="status-item <?php echo file_exists($config_file) ? 'completed' : 'pending'; ?>">
                        <div class="status-icon">
                            <i class="fas fa-<?php echo file_exists($config_file) ? 'check-circle' : 'circle'; ?>"></i>
                        </div>
                        <div>Configuration File</div>
                    </div>
                    <div class="status-item <?php echo file_exists(__DIR__ . '/../database/schema.sql') ? 'completed' : 'pending'; ?>">
                        <div class="status-icon">
                            <i class="fas fa-<?php echo file_exists(__DIR__ . '/../database/schema.sql') ? 'check-circle' : 'circle'; ?>"></i>
                        </div>
                        <div>Database Schema</div>
                    </div>
                    <div class="status-item <?php echo is_writable(__DIR__ . '/../config/') ? 'completed' : 'pending'; ?>">
                        <div class="status-icon">
                            <i class="fas fa-<?php echo is_writable(__DIR__ . '/../config/') ? 'check-circle' : 'circle'; ?>"></i>
                        </div>
                        <div>File Permissions</div>
                    </div>
                </div>
                
                <p class="text-muted mb-4">
                    <strong>Before you begin:</strong><br>
                    • MySQL/MariaDB 5.7+ with PDO support<br>
                    • PHP 8.1+ with required extensions<br>
                    • Secure database credentials<br>
                    • Proper file permissions (755 for dirs, 644 for files)
                </p>
                
                <a href="?step=2" class="btn btn-primary w-100">
                    <i class="fas fa-shield-alt"></i> Begin Secure Installation
                </a>
            </div>

            <!-- Step 2: Database Configuration -->
            <div class="step-content <?php echo $step === 2 ? 'active' : ''; ?>">
                <h2 class="mb-4">Database Configuration</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <a href="?step=3" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-arrow-right"></i> Initialize Database
                    </a>
                <?php else: ?>
                    <form method="POST" id="dbConfigForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label class="form-label">
                                Database Host <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="db_host" value="localhost" required 
                                   pattern="[a-zA-Z0-9.-]+" title="Valid hostname or IP address">
                            <div class="help-text">Server hostname or IP address (localhost, 127.0.0.1, etc.)</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Database User <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="db_user" value="root" required
                                   pattern="[a-zA-Z0-9_]+" title="Alphanumeric characters and underscores only">
                            <div class="help-text">MySQL username with database creation privileges</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Database Password</label>
                            <input type="password" class="form-control" name="db_pass" autocomplete="new-password">
                            <div class="help-text">Leave empty if no password is set</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Database Name <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="db_name" value="juakali_lend" required
                                   pattern="[a-zA-Z0-9_]+" title="Alphanumeric characters and underscores only">
                            <div class="help-text">Database will be created with secure charset if it doesn't exist</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Application URL <span class="required">*</span>
                            </label>
                            <input type="url" class="form-control" name="app_url" value="http://localhost/juakali-lend" required>
                            <div class="help-text">Full base URL including http:// or https://</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-database"></i> Test Connection & Secure Configuration
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Step 3: Database Initialization -->
            <div class="step-content <?php echo $step === 3 ? 'active' : ''; ?>">
                <h2 class="mb-4">Initialize Database</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <a href="?step=4" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-arrow-right"></i> Create Admin User
                    </a>
                <?php elseif (!$error && isset($_SESSION['db_configured'])): ?>
                    <div class="security-alert">
                        <strong><i class="fas fa-database"></i> Database Ready:</strong><br>
                        • Connection verified successfully<br>
                        • Ready to create tables and indexes<br>
                        • All operations will be transactional
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-cogs"></i> Initialize Database Schema
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Please complete the database configuration in the previous step first.
                    </div>
                    <a href="?step=2" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left"></i> Back to Database Configuration
                    </a>
                <?php endif; ?>
            </div>

            <!-- Step 4: Create Admin User -->
            <div class="step-content <?php echo $step === 4 ? 'active' : ''; ?>">
                <h2 class="mb-4">Create Admin User</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                    <a href="?step=5" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-arrow-right"></i> Complete Setup
                    </a>
                <?php else: ?>
                    <form method="POST" id="adminForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <label class="form-label">
                                First Name <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="first_name" required
                                   pattern="[a-zA-Z\s]+" title="Letters and spaces only">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Last Name <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" name="last_name" required
                                   pattern="[a-zA-Z\s]+" title="Letters and spaces only">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Email Address <span class="required">*</span>
                            </label>
                            <input type="email" class="form-control" name="admin_email" required
                                   autocomplete="email">
                            <div class="help-text">This will be your login username</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Password <span class="required">*</span>
                            </label>
                            <input type="password" class="form-control" name="admin_password" required
                                   autocomplete="new-password" id="adminPassword"
                                   minlength="8" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$"
                                   title="Must contain at least one uppercase, one lowercase, and one number">
                            <div class="password-strength">
                                <div class="password-strength-bar" id="passwordStrength"></div>
                            </div>
                            <div class="help-text">
                                Minimum 8 characters with uppercase, lowercase, and number
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Confirm Password <span class="required">*</span>
                            </label>
                            <input type="password" class="form-control" name="admin_password_confirm" required
                                   autocomplete="new-password" id="adminPasswordConfirm">
                            <div class="help-text" id="passwordMatch"></div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" id="submitAdmin">
                            <i class="fas fa-user-shield"></i> Create Secure Admin Account
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Step 5: Completion -->
            <div class="step-content <?php echo $step === 5 ? 'active' : ''; ?>">
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--primary-color);"></i>
                </div>
                <h2 class="text-center mb-4">Secure Installation Complete!</h2>
                
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> JuaKali Lend has been successfully installed with enhanced security!
                </div>
                
                <?php if (isset($_SESSION['admin_user'])): ?>
                <div class="installation-status">
                    <div class="status-item completed">
                        <div class="status-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>Admin User: <?php echo htmlspecialchars($_SESSION['admin_user']['email']); ?></div>
                    </div>
                    <div class="status-item completed">
                        <div class="status-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>Database: <?php echo htmlspecialchars($_SESSION['db_config']['db_name'] ?? 'Unknown'); ?></div>
                    </div>
                    <div class="status-item completed">
                        <div class="status-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>Installation Date: <?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="security-alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> Critical Security Steps:</strong><br>
                    1. <strong>DELETE</strong> the /install directory immediately<br>
                    2. Change your database password if it's weak<br>
                    3. Configure SSL/TLS for your domain<br>
                    4. Set up regular backups<br>
                    5. Monitor your application logs
                </div>
                
                <a href="../auth/login.php" class="btn btn-primary w-100 mb-2">
                    <i class="fas fa-sign-in-alt"></i> Secure Login
                </a>
                <a href="../" class="btn btn-secondary w-100">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('adminPassword')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            strengthBar.className = 'password-strength-bar ';
            if (strength <= 25) strengthBar.className += 'strength-weak';
            else if (strength <= 50) strengthBar.className += 'strength-fair';
            else if (strength <= 75) strengthBar.className += 'strength-good';
            else strengthBar.className += 'strength-strong';
        });

        // Password confirmation check
        document.getElementById('adminPasswordConfirm')?.addEventListener('input', function(e) {
            const password = document.getElementById('adminPassword').value;
            const confirm = e.target.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm === '') {
                matchText.innerHTML = '';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                matchText.style.color = 'var(--primary-color)';
            } else {
                matchText.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                matchText.style.color = 'var(--danger-color)';
            }
        });

        // Form validation
        document.getElementById('adminForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('adminPassword').value;
            const confirm = document.getElementById('adminPasswordConfirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter, one lowercase letter, and one number!');
                return false;
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-danger')) {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>