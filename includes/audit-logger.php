<?php
/**
 * Audit Logger
 * Logs all security-related activities for compliance
 */

class AuditLogger {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Log authentication attempt
     */
    public function logAuthAttempt($user_id, $email, $ip_address, $success, $reason = '') {
        try {
            $query = "INSERT INTO audit_logs (user_id, email, action, ip_address, success, reason, created_at) 
                      VALUES (?, ?, 'LOGIN_ATTEMPT', ?, ?, ?, NOW())";
            
            return $this->db->execute($query, [
                $user_id ?: null, 
                $email, 
                $ip_address, 
                $success ? 1 : 0, 
                $reason
            ]);
        } catch (Exception $e) {
            // Log the error but don't break the application
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log password change
     */
    public function logPasswordChange($user_id, $ip_address) {
        try {
            $query = "INSERT INTO audit_logs (user_id, action, ip_address, created_at) 
                      VALUES (?, 'PASSWORD_CHANGE', ?, NOW())";
            
            return $this->db->execute($query, [$user_id, $ip_address]);
        } catch (Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log permission change
     */
    public function logPermissionChange($user_id, $changed_user_id, $permission, $action, $ip_address) {
        try {
            $query = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                      VALUES (?, 'PERMISSION_CHANGE', ?, ?, NOW())";
            
            $details = "User $changed_user_id permission $permission $action";
            return $this->db->execute($query, [$user_id, $details, $ip_address]);
        } catch (Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log transaction
     */
    public function logTransaction($user_id, $transaction_type, $amount, $status, $ip_address) {
        try {
            $query = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) 
                      VALUES (?, 'TRANSACTION', ?, ?, NOW())";
            
            $details = "$transaction_type: $amount - $status";
            return $this->db->execute($query, [$user_id, $details, $ip_address]);
        } catch (Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log system event
     */
    public function logSystemEvent($event, $details, $ip_address = null) {
        try {
            $query = "INSERT INTO audit_logs (action, details, ip_address, created_at) 
                      VALUES (?, ?, ?, NOW())";
            
            return $this->db->execute($query, [$event, $details, $ip_address]);
        } catch (Exception $e) {
            error_log('Audit log failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit logs for user
     */
    public function getUserAuditLogs($user_id, $limit = 50) {
        try {
            $query = "SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
            
            return $this->db->fetchAll($query, [$user_id, $limit]);
        } catch (Exception $e) {
            error_log('Audit log query failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all audit logs with pagination
     */
    public function getAuditLogs($page = 1, $per_page = 50) {
        try {
            $offset = ($page - 1) * $per_page;
            $query = "SELECT al.*, u.first_name, u.last_name, u.email 
                      FROM audit_logs al 
                      LEFT JOIN users u ON al.user_id = u.id 
                      ORDER BY al.created_at DESC 
                      LIMIT ? OFFSET ?";
            
            return $this->db->fetchAll($query, [$per_page, $offset]);
        } catch (Exception $e) {
            error_log('Audit log query failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit logs by action type
     */
    public function getAuditLogsByAction($action, $limit = 50) {
        try {
            $query = "SELECT * FROM audit_logs WHERE action = ? ORDER BY created_at DESC LIMIT ?";
            
            return $this->db->fetchAll($query, [$action, $limit]);
        } catch (Exception $e) {
            error_log('Audit log query failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get failed login attempts count
     */
    public function getFailedLoginCount($hours = 24) {
        try {
            $query = "SELECT COUNT(*) as count FROM audit_logs 
                      WHERE action = 'LOGIN_ATTEMPT' AND success = 0 
                      AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";
            
            $result = $this->db->fetchOne($query, [$hours]);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log('Audit log query failed: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Clean up old audit logs (keep logs for 1 year)
     */
    public function cleanupOldLogs() {
        try {
            $query = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            return $this->db->execute($query);
        } catch (Exception $e) {
            error_log('Audit log cleanup failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if audit logs table exists
     */
    public function tableExists(): bool {
        try {
            $query = "SELECT 1 FROM audit_logs LIMIT 1";
            $this->db->fetchOne($query);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>