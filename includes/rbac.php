<?php
/**
 * Role-Based Access Control
 * Manages permissions and access control for different user roles
 */

class RBAC {
    private $db;
    private $permissions = [
        'retailer' => [
            'browse:products',
            'apply:credit',
            'view:own_orders',
            'view:own_repayments',
            'update:own_profile',
            'view:own_notifications',
            'manage:cart'
        ],
        'supplier' => [
            'manage:products',
            'process:orders',
            'update:inventory',
            'view:sales_analytics',
            'manage:deliveries',
            'view:own_profile',
            'view:own_notifications'
        ],
        'lender' => [
            'view:loan_applications',
            'approve:loans',
            'fund:orders',
            'monitor:portfolio',
            'withdraw:profits',
            'view:risk_assessment',
            'view:own_profile',
            'view:own_notifications'
        ],
        'admin' => [
            'manage:users',
            'manage:products',
            'manage:loans',
            'manage:disputes',
            'system:configuration',
            'view:financial_reports',
            'view:audit_logs',
            'manage:notifications'
        ]
    ];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($user_id, $permission) {
        $query = "SELECT role FROM users WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        $role = $row['role'];
        
        return in_array($permission, $this->permissions[$role] ?? []);
    }
    
    /**
     * Get user permissions
     */
    public function getUserPermissions($user_id) {
        $query = "SELECT role FROM users WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [];
        }
        
        $row = $result->fetch_assoc();
        return $this->permissions[$row['role']] ?? [];
    }
    
    /**
     * Check time-based access
     */
    public function isTimeBasedAccessAllowed($role) {
        $current_hour = (int)date('H');
        
        $time_restrictions = [
            'supplier' => ['start' => 6, 'end' => 22], // 6 AM - 10 PM
            'admin' => ['start' => 8, 'end' => 18]     // 8 AM - 6 PM
        ];
        
        if (!isset($time_restrictions[$role])) {
            return true;
        }
        
        $restriction = $time_restrictions[$role];
        return $current_hour >= $restriction['start'] && $current_hour < $restriction['end'];
    }
    
    /**
     * Check location-based access
     */
    public function isLocationBasedAccessAllowed($user_id, $user_ip) {
        $query = "SELECT country FROM users WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        $user_country = $row['country'];
        
        // Get country from IP (integrate with GeoIP service)
        $ip_country = $this->getCountryFromIP($user_ip);
        
        // Allow access if same country or within allowed regions
        return $user_country === $ip_country;
    }
    
    /**
     * Get country from IP address
     */
    private function getCountryFromIP($ip) {
        // Integrate with GeoIP service (MaxMind, IP2Location, etc.)
        // This is a placeholder
        return 'KE'; // Default to Kenya
    }
}
?>
