<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/audit-logger.php';

$error = '';
$success = '';
$token = sanitize($_GET['token'] ?? '');

if (empty($token)) {
    $error = 'Invalid reset link';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'All fields are required';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        $db = new Database();
        $db->connect();
        
        $db->query('SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = FALSE');
        $db->bind('s', $token);
        $reset = $db->single();
        
        if ($reset) {
            $hashed_password = hashPassword($password);
            
            // Update password
            $db->query('UPDATE users SET password = ? WHERE id = ?');
            $db->bind('s', $hashed_password);
            $db->bind('i', $reset['user_id']);
            $db->execute();
            
            // Mark token as used
            $db->query('UPDATE password_resets SET used = TRUE WHERE token = ?');
            $db->bind('s', $token);
            $db->execute();
            
            // Log password change
            $audit_logger = new AuditLogger($db);
            $audit_logger->logPasswordChange($reset['user_id'], $_SERVER['REMOTE_ADDR']);
            
            $success = 'Password reset successfully. You can now login with your new password.';
        } else {
            $error = 'Invalid or expired reset link';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - JuaKali Lend</title>
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
                <p>Reset Password</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <div class="text-center">
                        <a href="login.php" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                    </div>
                <?php elseif (empty($error)): ?>
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required>
                            <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                        </div>
                        
                        <button type="submit" class="btn-login mb-3">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="login-footer">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
