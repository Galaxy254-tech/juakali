<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

// If user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['role'] ?? 'retailer';

    // Redirect based on user role
    switch ($userRole) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'lender':
            header('Location: lender/dashboard.php');
            break;
        case 'supplier':
            header('Location: supplier/dashboard.php');
            break;
        case 'retailer':
        default:
            header('Location: pages/dashboard/retailer/');
            break;
    }
    exit();
}

$error = '';
$success = '';

// Initialize database
$db = new Database();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        try {
            // Check database for user
            $user = $db->fetchOne("
                SELECT id, first_name, last_name, email, password, role, phone, status,
                       last_login, profile_image, two_factor_enabled
                FROM users
                WHERE email = ? AND status = 'active'
            ", [$email]);

            if ($user && password_verify($password, $user['password'])) {
                // Login successful - create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['phone'] = $user['phone'];
                $_SESSION['profile_image'] = $user['profile_image'];
                $_SESSION['two_factor_enabled'] = $user['two_factor_enabled'];
                $_SESSION['login_time'] = time();

                // Update last login
                $db->update('users', [
                    'last_login' => date('Y-m-d H:i:s'),
                    'last_login_ip' => $_SERVER['REMOTE_ADDR']
                ], 'id = ?', [$user['id']]);

                // Set remember me cookie if checked
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60); // 30 days

                    // Store token in database
                    $db->insert('remember_tokens', [
                        'user_id' => $user['id'],
                        'token' => hash('sha256', $token),
                        'expires_at' => date('Y-m-d H:i:s', $expires),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    setcookie('remember_token', $token, $expires, '/', '', true, true);
                }

                // Check if 2FA is enabled
                if ($user['two_factor_enabled']) {
                    $_SESSION['pending_2fa'] = true;
                    header('Location: auth/2fa.php');
                    exit();
                }

                // Award login points if gamification is available
                try {
                    if (class_exists('LoyaltyGamificationSystem')) {
                        $gamification = new LoyaltyGamificationSystem($db);

                        // Check if this is first login today
                        $lastLogin = $db->fetchOne("
                            SELECT created_at FROM points_transactions
                            WHERE user_id = ? AND reason = 'daily_login'
                            ORDER BY created_at DESC LIMIT 1
                        ", [$user['id']]);

                        if (!$lastLogin || date('Y-m-d', strtotime($lastLogin['created_at'])) !== date('Y-m-d')) {
                            $gamification->awardPoints($user['id'], 10, 'daily_login', 'Daily login bonus');
                            $gamification->updateUserStreak($user['id'], 'login');
                        }
                    }
                } catch (Exception $e) {
                    // Gamification system not available, continue with login
                }

                // Redirect to appropriate dashboard based on role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'lender':
                        header('Location: lender/dashboard.php');
                        break;
                    case 'supplier':
                        header('Location: supplier/dashboard.php');
                        break;
                    case 'retailer':
                    default:
                        header('Location: pages/dashboard/retailer/');
                        break;
                }
                exit();

            } else {
                // Invalid credentials
                $error = 'Invalid email or password';

                // Log failed login attempt
                $db->insert('login_attempts', [
                    'email' => $email,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'attempt_time' => date('Y-m-d H:i:s'),
                    'status' => 'failed'
                ]);
            }

        } catch (Exception $e) {
            $error = 'Login system temporarily unavailable. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Handle remember me login
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $tokenHash = hash('sha256', $_COOKIE['remember_token']);

        $rememberRecord = $db->fetchOne("
            SELECT rt.*, u.id, u.first_name, u.last_name, u.email, u.role, u.phone,
                   u.profile_image, u.two_factor_enabled, u.status
            FROM remember_tokens rt
            JOIN users u ON rt.user_id = u.id
            WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = 'active'
        ", [$tokenHash]);

        if ($rememberRecord) {
            // Auto-login successful
            $_SESSION['user_id'] = $rememberRecord['id'];
            $_SESSION['email'] = $rememberRecord['email'];
            $_SESSION['first_name'] = $rememberRecord['first_name'];
            $_SESSION['last_name'] = $rememberRecord['last_name'];
            $_SESSION['full_name'] = $rememberRecord['first_name'] . ' ' . $rememberRecord['last_name'];
            $_SESSION['role'] = $rememberRecord['role'];
            $_SESSION['phone'] = $rememberRecord['phone'];
            $_SESSION['profile_image'] = $rememberRecord['profile_image'];
            $_SESSION['two_factor_enabled'] = $rememberRecord['two_factor_enabled'];
            $_SESSION['login_time'] = time();

            // Update last login
            $db->update('users', [
                'last_login' => date('Y-m-d H:i:s'),
                'last_login_ip' => $_SERVER['REMOTE_ADDR']
            ], 'id = ?', [$rememberRecord['id']]);

            // Redirect to dashboard
            switch ($rememberRecord['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'lender':
                    header('Location: lender/dashboard.php');
                    break;
                case 'supplier':
                    header('Location: supplier/dashboard.php');
                    break;
                case 'retailer':
                default:
                    header('Location: pages/dashboard/retailer/');
                    break;
            }
            exit();
        } else {
            // Invalid or expired token, remove cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    } catch (Exception $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}

$pageTitle = 'Login - JuaKali Lend';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #10b981;
            --primary-dark: #059669;
            --primary-light: #34d399;
            --secondary-color: #6366f1;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(16, 185, 129, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(99, 102, 241, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 40%, rgba(251, 191, 36, 0.2) 0%, transparent 50%);
            animation: bgMove 20s ease-in-out infinite;
        }

        @keyframes bgMove {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-20px, -20px) rotate(1deg); }
            66% { transform: translate(20px, -10px) rotate(-1deg); }
        }

        /* Floating Shapes */
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 120px;
            height: 120px;
            top: 70%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape-3 {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 30%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        /* Login Container */
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            display: flex;
            animation: slideIn 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Left Panel - Branding */
        .login-branding {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 60px 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-branding::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .brand-logo {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .brand-logo i {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .brand-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #fff, #f0fdf4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 40px;
        }

        .feature-list {
            list-style: none;
            position: relative;
            z-index: 1;
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }

        .feature-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .feature-text {
            flex: 1;
        }

        .feature-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .feature-desc {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Right Panel - Login Form */
        .login-form-container {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }

        .login-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }

        /* Form Styles */
        .form-floating-custom {
            position: relative;
            margin-bottom: 25px;
        }

        .form-control-custom {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-floating-custom label {
            position: absolute;
            top: 50%;
            left: 20px;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 1rem;
            pointer-events: none;
            transition: var(--transition);
            background: white;
            padding: 0 5px;
        }

        .form-control-custom:focus + label,
        .form-control-custom:not(:placeholder-shown) + label {
            top: -10px;
            font-size: 0.85rem;
            color: var(--primary-color);
        }

        .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            transition: var(--transition);
        }

        .form-control-custom:focus ~ .input-icon {
            color: var(--primary-color);
        }

        .password-toggle {
            cursor: pointer;
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            accent-color: var(--primary-color);
        }

        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .forgot-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Demo Alert */
        .demo-alert {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #fbbf24;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            position: relative;
        }

        .demo-alert::before {
            content: '🔓';
            position: absolute;
            top: -10px;
            left: 20px;
            background: #fbbf24;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .demo-title {
            font-weight: 700;
            color: #92400e;
            margin-bottom: 10px;
        }

        .demo-credentials {
            background: rgba(255, 255, 255, 0.8);
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin-top: 10px;
        }

        /* Error & Success Messages */
        .alert-custom {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #f87171;
            color: #991b1b;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #34d399;
            color: #065f46;
        }

        .alert-icon {
            margin-right: 15px;
            font-size: 1.2rem;
        }

        /* Divider */
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            background: white;
            padding: 0 20px;
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Social Login */
        .social-login {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .btn-social {
            flex: 1;
            padding: 12px;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: var(--border-radius);
            color: #374151;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-social:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .register-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Back to Home */
        .back-home {
            position: absolute;
            top: 30px;
            left: 30px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            transition: var(--transition);
            z-index: 10;
        }

        .back-home:hover {
            transform: translateX(-5px);
        }

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-login.loading .spinner {
            display: inline-block;
            margin-left: 10px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
                margin: 20px;
            }

            .login-branding {
                padding: 40px 30px;
                text-align: center;
            }

            .brand-title {
                font-size: 2rem;
            }

            .login-form-container {
                padding: 40px 30px;
            }

            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .social-login {
                flex-direction: column;
            }

            .back-home {
                position: relative;
                top: auto;
                left: auto;
                justify-content: center;
                margin-bottom: 20px;
                color: var(--dark-color);
            }

            body {
                padding: 10px;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .login-form-container {
                background: rgba(31, 41, 55, 0.95);
                color: white;
            }

            .form-control-custom {
                background: #374151;
                border-color: #4b5563;
                color: white;
            }

            .form-control-custom:focus + label {
                background: #374151;
            }

            .btn-social {
                background: #374151;
                border-color: #4b5563;
                color: white;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation"></div>

    <!-- Floating Shapes -->
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>

    <!-- Back to Home -->
    <a href="index.php" class="back-home">
        <i class="fas fa-arrow-left"></i>
        Back to Home
    </a>

    <!-- Login Container -->
    <div class="login-container">
        <!-- Left Panel - Branding -->
        <div class="login-branding">
            <div class="brand-logo">
                <i class="fas fa-leaf"></i>
                <h1 class="brand-title">JuaKali Lend</h1>
                <p class="brand-subtitle">Empowering Small Businesses</p>
            </div>

            <ul class="feature-list">
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Quick Approval</div>
                        <div class="feature-desc">Get instant credit decisions</div>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Secure Platform</div>
                        <div class="feature-desc">Bank-level security & encryption</div>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Build Credit</div>
                        <div class="feature-desc">Improve your credit score over time</div>
                    </div>
                </li>
                <li class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="feature-text">
                        <div class="feature-title">Mobile First</div>
                        <div class="feature-desc">Manage loans on any device</div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="login-form-container">
            <div class="login-header">
                <h2 class="login-title">Welcome Back</h2>
                <p class="login-subtitle">Sign in to access your account</p>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert-custom alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-custom alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Demo Alert -->
            <div class="demo-alert">
                <div class="demo-title">Demo Account Available</div>
                <p>Use the following credentials to explore the platform:</p>
                <div class="demo-credentials">
                    <strong>Email:</strong> demo@juakali.com<br>
                    <strong>Password:</strong> demo123
                </div>
            </div>

            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <!-- Email Field -->
                <div class="form-floating-custom">
                    <input type="email"
                           class="form-control-custom"
                           id="email"
                           name="email"
                           placeholder=" "
                           value="demo@juakali.com"
                           required>
                    <label for="email">Email Address</label>
                    <i class="fas fa-envelope input-icon"></i>
                </div>

                <!-- Password Field -->
                <div class="form-floating-custom">
                    <input type="password"
                           class="form-control-custom"
                           id="password"
                           name="password"
                           placeholder=" "
                           value="demo123"
                           required>
                    <label for="password">Password</label>
                    <i class="fas fa-eye input-icon password-toggle" id="passwordToggle"></i>
                </div>

                <!-- Form Options -->
                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me for 30 days</label>
                    </div>
                    <a href="pages/auth/forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                    <span class="spinner"></span>
                </button>
            </form>

            <!-- Divider -->
            <div class="divider">
                <span>OR</span>
            </div>

            <!-- Social Login (Future Feature) -->
            <div class="social-login">
                <a href="#" class="btn-social" onclick="alert('Google login coming soon!')">
                    <i class="fab fa-google"></i>
                    Google
                </a>
                <a href="#" class="btn-social" onclick="alert('Phone login coming soon!')">
                    <i class="fas fa-phone"></i>
                    Phone
                </a>
            </div>

            <!-- Register Link -->
            <div class="register-link">
                Don't have an account? <a href="pages/auth/register.php">Sign up for free</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordInput = document.getElementById('password');

        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submission loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function() {
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...<span class="spinner"></span>';
        });

        // Auto-fill demo credentials on page load
        window.addEventListener('load', function() {
            // Add smooth entrance animation
            document.querySelector('.login-container').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.login-container').style.opacity = '1';
            }, 100);
        });

        // Form validation
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        emailInput.addEventListener('blur', function() {
            if (!validateEmail(this.value)) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#10b981';
            }
        });

        passwordInput.addEventListener('input', function() {
            if (this.value.length < 6) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#10b981';
            }
        });

        // Add enter key support for form submission
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loginForm.dispatchEvent(new Event('submit'));
            }
        });

        // Add some interactive animations
        const featureItems = document.querySelectorAll('.feature-item');
        featureItems.forEach((item, index) => {
            item.style.animationDelay = `${index * 0.1}s`;
            item.style.animation = 'slideIn 0.6s ease-out forwards';
            item.style.opacity = '0';
            setTimeout(() => {
                item.style.opacity = '1';
            }, index * 100);
        });
    </script>
</body>
</html>