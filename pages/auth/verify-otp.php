<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/mfa.php';

$error = '';
$success = '';
$phone = $_SESSION['phone_for_verification'] ?? '';

if (empty($phone)) {
    redirect(APP_URL . '/auth/register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = sanitize($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        $error = 'OTP is required';
    } else {
        $db = new Database();
        $db->connect();
        $mfa_handler = new MFAHandler($db);
        
        $user_id = $_SESSION['temp_user_id'] ?? null;
        
        if ($user_id && $mfa_handler->verifyOTP($user_id, $otp)) {
            $_SESSION['phone_verified'] = true;
            $success = 'Phone verified successfully! Redirecting...';
            header('refresh:2;url=' . APP_URL . '/auth/register.php?step=kyc');
        } else {
            $error = 'Invalid OTP. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - JuaKali Lend</title>
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
                <p>Verify Phone Number</p>
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
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Enter the OTP sent to <?php echo $phone; ?>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group mb-3">
                            <label for="otp" class="form-label">One-Time Password</label>
                            <input type="text" class="form-control text-center" id="otp" name="otp" placeholder="000000" maxlength="6" required autofocus>
                        </div>
                        
                        <button type="submit" class="btn-login mb-3">
                            <i class="fas fa-check"></i> Verify OTP
                        </button>
                    </form>
                    
                    <div class="text-center">
                        <small class="text-muted">Didn't receive OTP? <a href="#" class="text-decoration-none">Resend</a></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
