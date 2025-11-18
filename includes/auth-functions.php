<?php
class AuthFunctions {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function register($data) {
        $validation = new Validation();
        $rules = [
            'full_name' => 'required|min:3|max:100',
            'email' => 'required|email|unique:users',
            'phone' => 'required|phone|unique:users',
            'password' => 'required|min:8',
            'role' => 'required'
        ];

        if (!$validation->validate($data, $rules)) {
            return ['success' => false, 'errors' => $validation->getErrors()];
        }

        $hashedPassword = Security::hashPassword($data['password']);
        $verificationToken = Security::generateToken();

        $this->db->query("INSERT INTO users (full_name, email, phone, password, role, verification_token, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())")
                 ->bind($data['full_name'])
                 ->bind($data['email'])
                 ->bind($data['phone'])
                 ->bind($hashedPassword)
                 ->bind($data['role'])
                 ->bind($verificationToken)
                 ->execute();

        $userId = $this->db->lastInsertId();
        Security::logActivity($userId, 'user_registered', 'New user registration');

        return ['success' => true, 'user_id' => $userId, 'token' => $verificationToken];
    }

    public function login($email, $password) {
        $user = $this->db->query("SELECT * FROM users WHERE email = ?")
                         ->bind($email)
                         ->single();

        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is not active'];
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();

        Security::logActivity($user['id'], 'user_login', 'User logged in');

        return ['success' => true, 'user' => $user];
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            Security::logActivity($_SESSION['user_id'], 'user_logout', 'User logged out');
        }
        session_destroy();
        return true;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        return $this->db->query("SELECT * FROM users WHERE id = ?")
                        ->bind($_SESSION['user_id'])
                        ->single();
    }

    public function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }

    public function requireRole($role) {
        if (!$this->hasRole($role)) {
            header('Location: ' . APP_URL . '/auth/login.php');
            exit;
        }
    }

    public function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function sendOTP($phone, $otp) {
        // Integration with SMS gateway
        // This is a placeholder for actual SMS sending
        return true;
    }

    public function verifyOTP($phone, $otp) {
        $record = $this->db->query("SELECT * FROM otp_records WHERE phone = ? AND otp = ? AND expires_at > NOW()")
                           ->bind($phone)
                           ->bind($otp)
                           ->single();
        return $record ? true : false;
    }

    public function resetPassword($email, $newPassword) {
        $validation = new Validation();
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password too short'];
        }

        $hashedPassword = Security::hashPassword($newPassword);
        $this->db->query("UPDATE users SET password = ? WHERE email = ?")
                 ->bind($hashedPassword)
                 ->bind($email)
                 ->execute();

        return ['success' => true, 'message' => 'Password reset successfully'];
    }
}
?>
