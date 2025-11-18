<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'juakali_lend');

// Application Settings
define('APP_NAME', 'JuaKali Lend');
define('APP_URL', 'http://localhost/juakali-lend');
define('APP_ENV', 'development');

// Security Settings
define('JWT_SECRET', 'your-secret-key-change-in-production');
define('SESSION_TIMEOUT', 3600);
define('PASSWORD_MIN_LENGTH', 8);

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Pagination
define('ITEMS_PER_PAGE', 10);

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Africa/Nairobi');
?>
