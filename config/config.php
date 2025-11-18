<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'juakali_lend');
define('APP_NAME', 'JuaKali Lend');
define('APP_URL', 'http://localhost/juakali-lend');
define('APP_ENV', 'production');
define('SESSION_TIMEOUT', 3600);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('MAIL_FROM', 'noreply@juakali-lend.com');
define('MAIL_FROM_NAME', 'JuaKali Lend');
define('ITEMS_PER_PAGE', 10);
date_default_timezone_set('Africa/Nairobi');
?>