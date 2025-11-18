<?php
session_start();

// Simple configuration
define('APP_NAME', 'JuaKali Lend');
define('APP_URL', 'http://localhost:8081');

// Simple functions
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'login'; // login, mfa, otp

// FIX: Use Singleton pattern to get Database instance
$db = Database::getInstance();
$rate_limiter = new RateLimiter($db);
$audit_logger = new AuditLogger($db);
$mfa_handler = new MFAHandler($db);
$jwt_handler = new JWTHandler();
$client_ip = DeviceFingerprint::getClientIP();
$device_fingerprint = DeviceFingerprint::generate();

// Check rate limiting
if ($rate_limiter->isRateLimited($client_ip)) {
    $error = 'Too many login attempts. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'login') {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email format';
        } else {
            // FIX: Use the Database class methods correctly
            $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

            if ($user && password_verify($password, $user['password'])) {
                $audit_logger->logAuthAttempt($user['id'], $email, $client_ip, 1, 'Successful login');
                $rate_limiter->resetRateLimit($client_ip);
                
                if ($user['mfa_enabled']) {
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['temp_email'] = $email;
                    $_SESSION['temp_device_fingerprint'] = $device_fingerprint;
                    
                    // Send OTP
                    $mfa_handler->generateAndSendOTP($user['id'], $user['phone']);
                    redirect(APP_URL . '/auth/login.php?step=mfa');
                } else {
                    $access_token = $jwt_handler->createToken($user['id'], $user['role'], [], $device_fingerprint);
                    $refresh_token = $jwt_handler->createRefreshToken($user['id'], $device_fingerprint);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['access_token'] = $access_token;
                    $_SESSION['refresh_token'] = $refresh_token;
                    $_SESSION['device_fingerprint'] = $device_fingerprint;
                    
                    // Redirect based on role
                    $redirects = [
                        'retailer' => '/dashboard/retailer/',
                        'supplier' => '/dashboard/supplier/',
                        'lender' => '/dashboard/lender/',
                        'admin' => '/dashboard/admin/'
                    ];
                    
                    redirect(APP_URL . ($redirects[$user['role']] ?? '/'));
                }
            } else {
                $rate_limiter->recordFailedAttempt($client_ip);
                $audit_logger->logAuthAttempt(null, $email, $client_ip, 0, 'Invalid credentials');
                $error = 'Invalid email or password';
            }
        }
    } elseif ($step === 'mfa') {
        $otp = sanitize($_POST['otp'] ?? '');
        $user_id = $_SESSION['temp_user_id'] ?? null;
        
        if (!$user_id) {
            $error = 'Session expired. Please login again.';
        } elseif (empty($otp)) {
            $error = 'OTP is required';
        } elseif ($mfa_handler->verifyOTP($user_id, $otp)) {
            // FIX: Use Database method to get user
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
            
            if ($user) {
                $device_fingerprint = $_SESSION['temp_device_fingerprint'];
                
                $access_token = $jwt_handler->createToken($user['id'], $user['role'], [], $device_fingerprint);
                $refresh_token = $jwt_handler->createRefreshToken($user['id'], $device_fingerprint);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['access_token'] = $access_token;
                $_SESSION['refresh_token'] = $refresh_token;
                $_SESSION['device_fingerprint'] = $device_fingerprint;
                
                // Clean up temp session
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_email']);
                unset($_SESSION['temp_device_fingerprint']);
                
                $redirects = [
                    'retailer' => '/dashboard/retailer/',
                    'supplier' => '/dashboard/supplier/',
                    'lender' => '/dashboard/lender/',
                    'admin' => '/dashboard/admin/'
                ];
                
                redirect(APP_URL . ($redirects[$user['role']] ?? '/'));
            } else {
                $error = 'User not found. Please login again.';
            }
        } else {
            $error = 'Invalid OTP. Please try again.';
        }
    }
}

$remaining_attempts = $rate_limiter->getRemainingAttempts($client_ip);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/auth.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-leaf"></i> JuaKali Lend</h1>
                <p>Goods & Products Lending Platform</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 'login'): ?>
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                        
                        <button type="submit" class="btn-login mb-3">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>
                    
                    <?php if ($remaining_attempts < 5): ?>
                        <div class="alert alert-warning">
                            <small><i class="fas fa-exclamation-triangle"></i> <?php echo $remaining_attempts; ?> attempts remaining</small>
                        </div>
                    <?php endif; ?>
                <?php elseif ($step === 'mfa'): ?>
                    <form method="POST">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Enter the OTP sent to your phone
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="otp" class="form-label">One-Time Password</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" placeholder="000000" maxlength="6" required>
                        </div>
                        
                        <button type="submit" class="btn-login mb-3">
                            <i class="fas fa-check"></i> Verify OTP
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="login-footer">
                Don't have an account? <a href="register.php">Register here</a> | 
                <a href="forgot-password.php">Forgot password?</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>