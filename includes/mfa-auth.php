<?php
/**
 * Multi-Factor Authentication System for JuaKali Lend
 * Supports SMS, Email, WhatsApp, and TOTP-based 2FA
 */

class MFAAuth {
    private $db;
    private $totp;
    private $sessionTimeout = 3600; // 1 hour
    private $maxAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes

    public function __construct($database) {
        $this->db = $database;
        $this->totp = new TOTP();
    }

    /**
     * Send OTP verification code
     */
    public function sendOTP($userId, $purpose = 'login', $method = 'whatsapp') {
        try {
            $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Check rate limiting
            if ($this->isRateLimited($userId, $purpose)) {
                throw new Exception('Too many OTP requests. Please wait before trying again.');
            }

            // Generate 6-digit OTP
            $otp = $this->generateOTP();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Store OTP
            $this->db->execute("
                INSERT INTO otp_codes (user_id, code, purpose, method, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$userId, $otp, $purpose, $method, $expiresAt]);

            // Send OTP via selected method
            $sent = $this->sendOTPCode($user, $otp, $purpose, $method);

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'expires_at' => $expiresAt,
                    'method' => $method
                ];
            } else {
                throw new Exception('Failed to send OTP');
            }

        } catch (Exception $e) {
            error_log('OTP sending failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify OTP code
     */
    public function verifyOTP($userId, $code, $purpose = 'login') {
        try {
            $otpRecord = $this->db->fetchOne("
                SELECT * FROM otp_codes
                WHERE user_id = ? AND purpose = ? AND used = 0
                ORDER BY created_at DESC LIMIT 1
            ", [$userId, $purpose]);

            if (!$otpRecord) {
                throw new Exception('No valid OTP found');
            }

            // Check if expired
            if (strtotime($otpRecord['expires_at']) < time()) {
                throw new Exception('OTP has expired');
            }

            // Verify code
            if (!hash_equals($otpRecord['code'], $code)) {
                $this->incrementFailedAttempts($userId);
                throw new Exception('Invalid OTP code');
            }

            // Mark as used
            $this->db->execute("
                UPDATE otp_codes SET used = 1, used_at = NOW()
                WHERE id = ?
            ", [$otpRecord['id']]);

            // Reset failed attempts
            $this->resetFailedAttempts($userId);

            return [
                'success' => true,
                'message' => 'OTP verified successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Setup TOTP for user
     */
    public function setupTOTP($userId, $secret = null) {
        try {
            $user = $this->db->fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception('User not found');
            }

            $secret = $secret ?: $this->totp->generateSecret();
            $issuer = 'JuaKali Lend';
            $label = $user['email'];

            // Generate QR code URL
            $qrCodeUrl = $this->totp->getQRCodeUrl($issuer, $label, $secret);

            // Store TOTP secret (encrypted)
            $encryptedSecret = $this->encryptSecret($secret);
            $this->db->execute("
                INSERT INTO user_mfa (user_id, type, secret, is_enabled, created_at)
                VALUES (?, 'totp', ?, 0, NOW())
                ON DUPLICATE KEY UPDATE secret = ?, is_enabled = 0, updated_at = NOW()
            ", [$userId, $encryptedSecret, $encryptedSecret]);

            return [
                'success' => true,
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => $secret,
                'message' => 'Please scan the QR code with your authenticator app'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Enable TOTP after verification
     */
    public function enableTOTP($userId, $code) {
        try {
            $mfaRecord = $this->db->fetchOne("
                SELECT * FROM user_mfa WHERE user_id = ? AND type = 'totp'
            ", [$userId]);

            if (!$mfaRecord) {
                throw new Exception('TOTP setup not found');
            }

            $secret = $this->decryptSecret($mfaRecord['secret']);

            if (!$this->totp->verifyCode($secret, $code)) {
                throw new Exception('Invalid verification code');
            }

            // Enable TOTP
            $this->db->execute("
                UPDATE user_mfa SET is_enabled = 1, updated_at = NOW()
                WHERE user_id = ? AND type = 'totp'
            ", [$userId]);

            // Generate backup codes
            $backupCodes = $this->generateBackupCodes($userId);

            return [
                'success' => true,
                'message' => 'TOTP enabled successfully',
                'backup_codes' => $backupCodes
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify TOTP code
     */
    public function verifyTOTP($userId, $code) {
        try {
            $mfaRecord = $this->db->fetchOne("
                SELECT * FROM user_mfa
                WHERE user_id = ? AND type = 'totp' AND is_enabled = 1
            ", [$userId]);

            if (!$mfaRecord) {
                throw new Exception('TOTP not enabled for this user');
            }

            $secret = $this->decryptSecret($mfaRecord['secret']);

            if (!$this->totp->verifyCode($secret, $code)) {
                $this->incrementFailedAttempts($userId);
                throw new Exception('Invalid authentication code');
            }

            $this->resetFailedAttempts($userId);

            return [
                'success' => true,
                'message' => 'Authentication successful'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode($userId, $code) {
        try {
            $backupCode = $this->db->fetchOne("
                SELECT * FROM user_backup_codes
                WHERE user_id = ? AND code = ? AND used = 0
            ", [$userId, $code]);

            if (!$backupCode) {
                throw new Exception('Invalid backup code');
            }

            // Mark as used
            $this->db->execute("
                UPDATE user_backup_codes SET used = 1, used_at = NOW()
                WHERE id = ?
            ", [$backupCode['id']]);

            return [
                'success' => true,
                'message' => 'Backup code verified. Please generate new backup codes.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user needs MFA
     */
    public function requiresMFA($userId, $action = 'login') {
        $mfaSettings = $this->db->fetchAll("
            SELECT * FROM user_mfa_settings
            WHERE user_id = ? AND action = ? AND is_enabled = 1
        ", [$userId, $action]);

        return !empty($mfaSettings);
    }

    /**
     * Get available MFA methods for user
     */
    public function getAvailableMethods($userId) {
        $methods = [];

        // Check OTP methods
        $user = $this->db->fetchOne("SELECT phone, email FROM users WHERE id = ?", [$userId]);
        if ($user) {
            if ($user['phone']) {
                $methods[] = ['type' => 'otp', 'method' => 'sms', 'label' => 'SMS'];
                $methods[] = ['type' => 'otp', 'method' => 'whatsapp', 'label' => 'WhatsApp'];
            }
            if ($user['email']) {
                $methods[] = ['type' => 'otp', 'method' => 'email', 'label' => 'Email'];
            }
        }

        // Check TOTP
        $totpEnabled = $this->db->fetchOne("
            SELECT 1 FROM user_mfa
            WHERE user_id = ? AND type = 'totp' AND is_enabled = 1
        ", [$userId]);

        if ($totpEnabled) {
            $methods[] = ['type' => 'totp', 'label' => 'Authenticator App'];
        }

        // Check backup codes
        $backupCodes = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM user_backup_codes
            WHERE user_id = ? AND used = 0
        ", [$userId]);

        if ($backupCodes && $backupCodes['count'] > 0) {
            $methods[] = ['type' => 'backup', 'label' => 'Backup Code'];
        }

        return $methods;
    }

    /**
     * Biometric authentication setup
     */
    public function setupBiometric($userId, $biometricData, $deviceInfo) {
        try {
            // Hash biometric template
            $biometricHash = hash('sha256', json_encode($biometricData));

            // Store device biometric data
            $this->db->execute("
                INSERT INTO user_biometric_devices (
                    user_id, device_fingerprint, device_info, is_enabled, created_at
                ) VALUES (?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE
                device_fingerprint = VALUES(device_fingerprint),
                is_enabled = 1,
                updated_at = NOW()
            ", [$userId, $biometricHash, json_encode($deviceInfo)]);

            return [
                'success' => true,
                'message' => 'Biometric authentication enabled'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Adaptive authentication based on risk
     */
    public function getRequiredAuthLevel($userId, $context = []) {
        $baseLevel = 1; // Password only

        // Risk factors that increase auth level
        $riskScore = 0;

        // New device
        if (!empty($context['new_device'])) {
            $riskScore += 30;
        }

        // Unusual location
        if (!empty($context['unusual_location'])) {
            $riskScore += 25;
        }

        // High-value transaction
        if (!empty($context['high_value'])) {
            $riskScore += 20;
        }

        // Failed login attempts
        $failedAttempts = $this->getFailedAttempts($userId);
        $riskScore += min($failedAttempts * 10, 30);

        // Time-based factors
        $hour = (int)date('H');
        if ($hour < 6 || $hour > 22) {
            $riskScore += 15;
        }

        // Determine required auth level
        if ($riskScore >= 60) {
            return 3; // Password + OTP + TOTP
        } elseif ($riskScore >= 30) {
            return 2; // Password + OTP
        }

        return $baseLevel;
    }

    // Private helper methods
    private function generateOTP() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function sendOTPCode($user, $otp, $purpose, $method) {
        try {
            $message = $this->getOTPMessage($purpose, $otp);

            switch ($method) {
                case 'whatsapp':
                    require_once 'whatsapp-api.php';
                    $whatsapp = new WhatsAppAPI($this->db);
                    return $whatsapp->sendOTPVerification($user['id'], $otp, $purpose);

                case 'sms':
                    require_once 'sms-gateway.php';
                    $sms = new SMSGateway();
                    return $sms->send($user['phone'], $message);

                case 'email':
                    return $this->sendOTPEmail($user['email'], $message, $purpose);

                default:
                    return false;
            }
        } catch (Exception $e) {
            error_log('OTP sending failed: ' . $e->getMessage());
            return false;
        }
    }

    private function getOTPMessage($purpose, $otp) {
        $messages = [
            'login' => "Your JuaKali Lend login OTP is: $otp. Valid for 10 minutes. Do not share this code.",
            'transaction' => "Your JuaKali Lend transaction OTP is: $otp. Valid for 10 minutes.",
            'password_reset' => "Your JuaKali Lend password reset OTP is: $otp. Valid for 10 minutes.",
            'kyc_verification' => "Your JuaKali Lend KYC verification OTP is: $otp. Valid for 10 minutes."
        ];

        return $messages[$purpose] ?? "Your OTP is: $otp. Valid for 10 minutes.";
    }

    private function encryptSecret($secret) {
        $key = $_ENV['MFA_ENCRYPTION_KEY'] ?? 'default-key-change-in-production';
        return openssl_encrypt($secret, 'aes-256-cbc', $key, 0, substr(md5($key), 0, 16));
    }

    private function decryptSecret($encryptedSecret) {
        $key = $_ENV['MFA_ENCRYPTION_KEY'] ?? 'default-key-change-in-production';
        return openssl_decrypt($encryptedSecret, 'aes-256-cbc', $key, 0, substr(md5($key), 0, 16));
    }

    private function generateBackupCodes($userId, $count = 10) {
        $codes = [];

        // Delete old backup codes
        $this->db->execute("DELETE FROM user_backup_codes WHERE user_id = ?", [$userId]);

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(substr(md5(random_bytes(16)), 0, 8));
            $codes[] = $code;

            $this->db->execute("
                INSERT INTO user_backup_codes (user_id, code, created_at)
                VALUES (?, ?, NOW())
            ", [$userId, $code]);
        }

        return $codes;
    }

    private function isRateLimited($userId, $purpose) {
        $recentRequests = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM otp_codes
            WHERE user_id = ? AND purpose = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ", [$userId, $purpose]);

        return $recentRequests['count'] >= 3;
    }

    private function incrementFailedAttempts($userId) {
        $this->db->execute("
            INSERT INTO auth_attempts (user_id, attempts, last_attempt, created_at)
            VALUES (?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            last_attempt = NOW()
        ", [$userId]);
    }

    private function resetFailedAttempts($userId) {
        $this->db->execute("
            DELETE FROM auth_attempts WHERE user_id = ?
        ", [$userId]);
    }

    private function getFailedAttempts($userId) {
        $attempts = $this->db->fetchOne("
            SELECT attempts FROM auth_attempts WHERE user_id = ?
        ", [$userId]);

        return $attempts['attempts'] ?? 0;
    }

    private function sendOTPEmail($email, $message, $purpose) {
        // Implementation for sending OTP via email
        $subject = 'JuaKali Lend - OTP Verification';
        $headers = "From: noreply@juakali-lend.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        return mail($email, $subject, $message, $headers);
    }
}

/**
 * TOTP (Time-based One-Time Password) implementation
 */
class TOTP {
    public function generateSecret() {
        return $this->base32_encode(random_bytes(20));
    }

    public function getQRCodeUrl($issuer, $label, $secret) {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30
        ]);

        return "otpauth://totp/" . rawurlencode($label) . "?" . $params;
    }

    public function verifyCode($secret, $code, $window = 1) {
        $timestamp = floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $expectedCode = $this->generateCode($secret, $timestamp + $i);
            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    private function generateCode($secret, $timestamp) {
        $secret = $this->base32_decode($secret);
        $binaryTimestamp = pack('N*', 0) . pack('N*', $timestamp);
        $hash = hash_hmac('sha1', $binaryTimestamp, $secret, true);
        $offset = ord($hash[19]) & 0xF;
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % pow(10, 6);

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    private function base32_encode($data) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = '';
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $encoded .= $chars[($value >> ($bits - 5)) & 0x1F];
                $bits -= 5;
            }
        }

        if ($bits > 0) {
            $encoded .= $chars[($value << (5 - $bits)) & 0x1F];
        }

        return $encoded;
    }

    private function base32_decode($data) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decoded = '';
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            $pos = strpos($chars, $char);
            if ($pos === false) continue;

            $value = ($value << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $decoded .= chr(($value >> ($bits - 8)) & 0xFF);
                $bits -= 8;
            }
        }

        return $decoded;
    }
}
?>