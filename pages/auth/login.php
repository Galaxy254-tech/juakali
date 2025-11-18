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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } else {
        // Mock authentication - in real system, this would validate against database
        if ($email === 'demo@juakali.com' && $password === 'demo123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'retailer';
            $_SESSION['first_name'] = 'John';
            $_SESSION['last_name'] = 'Doe';

            redirect('../index.php');
        } else {
            $error = 'Invalid email or password. Use demo@juakali.com / demo123 for demo';
        }
    }
}
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

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Demo Credentials:</strong><br>
                    Email: demo@juakali.com<br>
                    Password: demo123
                </div>

                <form method="POST">
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="Enter your email" required value="demo@juakali.com">
                    </div>

                    <div class="form-group mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required value="demo123">
                    </div>

                    <button type="submit" class="btn-login mb-3">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
            </div>
            <div class="login-footer">
                Don't have an account? <a href="register.php">Register here</a> |
                <a href="forgot-password.php">Forgot password?</a><br>
                <a href="../index.php">← Back to Home</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>