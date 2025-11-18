<?php
session_start();
require_once '../config/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $company_name = sanitize($_POST['company_name'] ?? '');

    if (empty($email) || empty($password) || empty($role) || empty($first_name)) {
        $error = 'All required fields must be filled';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // FIX: Use Singleton pattern to get Database instance
        $db = Database::getInstance();
        
        // Check if email exists using Database class methods
        $existing_user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        
        if ($existing_user) {
            $error = 'Email already registered';
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert user using Database class methods
            $result = $db->execute(
                "INSERT INTO users (email, password, first_name, last_name, phone, role, company_name, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$email, $hashed_password, $first_name, $last_name, $phone, $role, $company_name]
            );
            
            if ($result > 0) {
                $user_id = $db->lastInsertId();
                
                // Create user profile
                $db->execute("INSERT INTO user_profiles (user_id, created_at) VALUES (?, NOW())", [$user_id]);
                
                // Create credit score for retailers
                if ($role === 'retailer') {
                    $db->execute(
                        "INSERT INTO credit_scores (retailer_id, score, created_at) VALUES (?, 500, NOW())", 
                        [$user_id]
                    );
                }
                
                // Log the registration
                error_log("User registered: ID $user_id, Email: $email, Role: $role");
                
                $success = 'Registration successful! Please login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - JuaKali Lend</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            padding: 1rem;
        }
        
        .register-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .register-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .register-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.1);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            border: none;
            border-radius: 8px;
            color: white;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1><i class="fas fa-leaf"></i> JuaKali Lend</h1>
                <p>Create Your Account</p>
            </div>
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <div class="mt-2">
                            <a href="login.php" class="btn btn-sm btn-outline-success">Proceed to Login</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!$success): ?>
                <form method="POST" id="registerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="company_name" class="form-label">Company Name</label>
                        <input type="text" class="form-control" id="company_name" name="company_name"
                               value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="role" class="form-label">Account Type *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Select your role</option>
                            <option value="retailer" <?php echo ($_POST['role'] ?? '') === 'retailer' ? 'selected' : ''; ?>>Retailer</option>
                            <option value="supplier" <?php echo ($_POST['role'] ?? '') === 'supplier' ? 'selected' : ''; ?>>Supplier</option>
                            <option value="lender" <?php echo ($_POST['role'] ?? '') === 'lender' ? 'selected' : ''; ?>>Lender</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="form-text text-muted">
                            Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters
                        </small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <small class="form-text text-muted" id="passwordMatch"></small>
                    </div>
                    
                    <button type="submit" class="btn-register mb-3">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="register-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation check
        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirm = e.target.value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirm === '') {
                matchText.innerHTML = '';
            } else if (password === confirm) {
                matchText.innerHTML = '<i class="fas fa-check text-success"></i> Passwords match';
            } else {
                matchText.innerHTML = '<i class="fas fa-times text-danger"></i> Passwords do not match';
            }
        });

        // Form validation
        document.getElementById('registerForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
                e.preventDefault();
                alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long!');
                return false;
            }
        });
    </script>
</body>
</html>