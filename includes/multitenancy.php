<?php
/**
 * Multitenancy Architecture for JuaKali Lend
 * Supports multiple lenders as independent nodes with data isolation
 * Each lender has their own branding, settings, and user management
 */

class MultitenancyManager {
    private $db;
    private $currentTenant;
    private $tenantId;
    private $tenantData;

    public function __construct($database) {
        $this->db = $database;
        $this->initializeTenant();
    }

    /**
     * Initialize current tenant based on subdomain or header
     */
    private function initializeTenant() {
        // Method 1: Subdomain-based identification
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $subdomain = $this->extractSubdomain($host);

        // Method 2: Header-based identification (for API)
        $tenantHeader = $_SERVER['HTTP_X_TENANT_ID'] ?? '';
        $tenantId = $tenantHeader ?: null;

        // Method 3: Session-based identification
        if (!$tenantId && isset($_SESSION['tenant_id'])) {
            $tenantId = $_SESSION['tenant_id'];
        }

        // Load tenant data
        if ($tenantId) {
            $this->loadTenantById($tenantId);
        } elseif ($subdomain) {
            $this->loadTenantBySubdomain($subdomain);
        } else {
            // Default to system tenant
            $this->loadSystemTenant();
        }
    }

    /**
     * Extract subdomain from host
     */
    private function extractSubdomain($host) {
        $domainParts = explode('.', $host);
        if (count($domainParts) > 2) {
            return $domainParts[0];
        }
        return null;
    }

    /**
     * Load tenant by ID
     */
    private function loadTenantById($tenantId) {
        $tenant = $this->db->fetchOne("
            SELECT * FROM tenants
            WHERE id = ? AND status = 'active'
        ", [$tenantId]);

        if ($tenant) {
            $this->setCurrentTenant($tenant);
        }
    }

    /**
     * Load tenant by subdomain
     */
    private function loadTenantBySubdomain($subdomain) {
        $tenant = $this->db->fetchOne("
            SELECT * FROM tenants
            WHERE subdomain = ? AND status = 'active'
        ", [$subdomain]);

        if ($tenant) {
            $this->setCurrentTenant($tenant);
        } else {
            // Check if this is a reserved system subdomain
            $this->loadSystemTenant();
        }
    }

    /**
     * Load system tenant
     */
    private function loadSystemTenant() {
        $tenant = $this->db->fetchOne("
            SELECT * FROM tenants
            WHERE tenant_type = 'system' AND status = 'active'
            LIMIT 1
        ");

        if ($tenant) {
            $this->setCurrentTenant($tenant);
        }
    }

    /**
     * Set current tenant
     */
    private function setCurrentTenant($tenant) {
        $this->tenantData = $tenant;
        $this->tenantId = $tenant['id'];
        $this->currentTenant = $tenant;

        // Store in session
        $_SESSION['tenant_id'] = $tenant['id'];

        // Apply tenant-specific configurations
        $this->applyTenantConfigurations();
    }

    /**
     * Apply tenant-specific configurations
     */
    private function applyTenantConfigurations() {
        // Set timezone
        if (!empty($this->tenantData['timezone'])) {
            date_default_timezone_set($this->tenantData['timezone']);
        }

        // Set currency
        define('TENANT_CURRENCY', $this->tenantData['currency'] ?? 'KES');
        define('TENANTANT_CURRENCY_SYMBOL', $this->tenantData['currency_symbol'] ?? 'KES');

        // Set branding
        define('TENANT_NAME', $this->tenantData['name']);
        define('TENANT_LOGO', $this->tenantData['logo_url'] ?? null);
        define('TENANT_PRIMARY_COLOR', $this->tenantData['primary_color'] ?? '#007bff');

        // Set contact info
        define('TENANT_SUPPORT_PHONE', $this->tenantData['support_phone'] ?? '');
        define('TENANT_SUPPORT_EMAIL', $this->tenantData['support_email'] ?? '');
    }

    /**
     * Create new tenant
     */
    public function createTenant($tenantData) {
        try {
            // Validate required fields
            $requiredFields = ['name', 'owner_id', 'business_name', 'subdomain'];
            foreach ($requiredFields as $field) {
                if (empty($tenantData[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Check if subdomain is available
            if ($this->isSubdomainTaken($tenantData['subdomain'])) {
                throw new Exception('Subdomain already taken');
            }

            // Generate unique tenant ID
            $tenantCode = $this->generateTenantCode($tenantData['business_name']);

            // Create tenant record
            $tenantId = $this->db->execute("
                INSERT INTO tenants (
                    tenant_code, name, business_name, subdomain, owner_id,
                    currency, currency_symbol, timezone, primary_color, logo_url,
                    support_phone, support_email, business_address, registration_number,
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ", [
                $tenantCode,
                $tenantData['name'],
                $tenantData['business_name'],
                $tenantData['subdomain'],
                $tenantData['owner_id'],
                $tenantData['currency'] ?? 'KES',
                $tenantData['currency_symbol'] ?? 'KES',
                $tenantData['timezone'] ?? 'Africa/Nairobi',
                $tenantData['primary_color'] ?? '#007bff',
                $tenantData['logo_url'] ?? null,
                $tenantData['support_phone'] ?? null,
                $tenantData['support_email'] ?? null,
                $tenantData['business_address'] ?? null,
                $tenantData['registration_number'] ?? null
            ]);

            if (!$tenantId) {
                throw new Exception('Failed to create tenant');
            }

            // Initialize tenant settings
            $this->initializeTenantSettings($tenantId);

            // Create default user roles for tenant
            $this->createDefaultRoles($tenantId);

            // Assign owner as admin
            $this->assignOwnerAsAdmin($tenantId, $tenantData['owner_id']);

            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'tenant_code' => $tenantCode,
                'subdomain' => $tenantData['subdomain'],
                'message' => 'Tenant created successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Initialize tenant settings
     */
    private function initializeTenantSettings($tenantId) {
        $defaultSettings = [
            'interest_rates' => json_encode([
                'retailer' => ['min' => 15, 'max' => 25, 'default' => 18],
                'supplier' => ['min' => 10, 'max' => 20, 'default' => 15]
            ]),
            'loan_limits' => json_encode([
                'min_amount' => 1000,
                'max_amount' => 100000,
                'max_active_loans' => 3
            ]),
            'penalty_settings' => json_encode([
                'daily_rate' => 0.05,
                'grace_period_days' => 1,
                'max_penalty_days' => 30
            ]),
            'notification_settings' => json_encode([
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'email_enabled' => true
            ]),
            'payment_methods' => json_encode([
                'mpesa' => true,
                'bank_transfer' => true,
                'cash' => false
            ])
        ];

        foreach ($defaultSettings as $key => $value) {
            $this->db->execute("
                INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$tenantId, $key, $value]);
        }
    }

    /**
     * Create default roles for tenant
     */
    private function createDefaultRoles($tenantId) {
        $defaultRoles = [
            ['name' => 'admin', 'display_name' => 'Administrator', 'permissions' => json_encode(['*'])],
            ['name' => 'lender', 'display_name' => 'Lender', 'permissions' => json_encode(['loans', 'repayments', 'analytics'])],
            ['name' => 'retailer', 'display_name' => 'Retailer', 'permissions' => json_encode(['profile', 'loans', 'repayments'])],
            ['name' => 'supplier', 'display_name' => 'Supplier', 'permissions' => json_encode(['orders', 'deliveries'])],
            ['name' => 'field_agent', 'display_name' => 'Field Agent', 'permissions' => json_encode(['deliveries', 'tasks'])]
        ];

        foreach ($defaultRoles as $role) {
            $this->db->execute("
                INSERT INTO tenant_roles (
                    tenant_id, role_name, display_name, permissions, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ", [$tenantId, $role['name'], $role['display_name'], $role['permissions']]);
        }
    }

    /**
     * Assign owner as admin
     */
    private function assignOwnerAsAdmin($tenantId, $ownerId) {
        $adminRole = $this->db->fetchOne("
            SELECT id FROM tenant_roles
            WHERE tenant_id = ? AND role_name = 'admin'
        ", [$tenantId]);

        if ($adminRole) {
            $this->db->execute("
                INSERT INTO tenant_user_roles (tenant_id, user_id, role_id, assigned_at, assigned_by)
                VALUES (?, ?, ?, NOW(), ?)
            ", [$tenantId, $ownerId, $adminRole['id'], $ownerId]);
        }
    }

    /**
     * Check if subdomain is taken
     */
    private function isSubdomainTaken($subdomain) {
        $existing = $this->db->fetchOne("
            SELECT id FROM tenants WHERE subdomain = ? AND status != 'deleted'
        ", [$subdomain]);

        return !empty($existing);
    }

    /**
     * Generate unique tenant code
     */
    private function generateTenantCode($businessName) {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $businessName));
        $base = substr($base, 0, 6);
        $code = $base . rand(1000, 9999);

        // Ensure uniqueness
        while ($this->db->fetchOne("SELECT id FROM tenants WHERE tenant_code = ?", [$code])) {
            $code = $base . rand(1000, 9999);
        }

        return $code;
    }

    /**
     * Get current tenant data
     */
    public function getCurrentTenant() {
        return $this->currentTenant;
    }

    /**
     * Get current tenant ID
     */
    public function getCurrentTenantId() {
        return $this->tenantId;
    }

    /**
     * Check if user belongs to current tenant
     */
    public function isUserInTenant($userId) {
        if ($this->tenantData['tenant_type'] === 'system') {
            return true; // System tenant has access to all
        }

        $userRole = $this->db->fetchOne("
            SELECT tur.* FROM tenant_user_roles tur
            JOIN tenant_roles tr ON tur.role_id = tr.id
            WHERE tur.tenant_id = ? AND tur.user_id = ?
        ", [$this->tenantId, $userId]);

        return !empty($userRole);
    }

    /**
     * Check user permissions
     */
    public function hasPermission($userId, $permission) {
        if ($this->tenantData['tenant_type'] === 'system') {
            return true; // System admin has all permissions
        }

        $userRole = $this->db->fetchOne("
            SELECT tr.permissions FROM tenant_user_roles tur
            JOIN tenant_roles tr ON tur.role_id = tr.id
            WHERE tur.tenant_id = ? AND tur.user_id = ?
        ", [$this->tenantId, $userId]);

        if (!$userRole) {
            return false;
        }

        $permissions = json_decode($userRole['permissions'], true);

        // Check for wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Get tenant users with roles
     */
    public function getTenantUsers() {
        return $this->db->fetchAll("
            SELECT
                u.*,
                tur.role_id,
                tr.display_name as role_name,
                tr.permissions,
                tur.assigned_at
            FROM users u
            JOIN tenant_user_roles tur ON u.id = tur.user_id
            JOIN tenant_roles tr ON tur.role_id = tr.id
            WHERE tur.tenant_id = ?
            ORDER BY tr.display_name, u.name
        ", [$this->tenantId]);
    }

    /**
     * Get tenant statistics
     */
    public function getTenantStatistics() {
        return $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN u.role = 'retailer' THEN u.id END) as retailers,
                COUNT(DISTINCT CASE WHEN u.role = 'supplier' THEN u.id END) as suppliers,
                COUNT(DISTINCT CASE WHEN u.role = 'lender' THEN u.id END) as lenders,
                COUNT(DISTINCT CASE WHEN u.role = 'field_agent' THEN u.id END) as field_agents,
                COUNT(DISTINCT l.id) as total_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'active' THEN l.id END) as active_loans,
                COALESCE(SUM(CASE WHEN l.status = 'active' THEN l.loan_amount ELSE 0 END), 0) as active_portfolio_value
            FROM users u
            LEFT JOIN loans l ON u.id = l.borrower_id
            WHERE u.tenant_id = ?
        ", [$this->tenantId]);
    }

    /**
     * Get tenant settings
     */
    public function getTenantSettings($key = null) {
        if ($key) {
            $setting = $this->db->fetchOne("
                SELECT setting_value FROM tenant_settings
                WHERE tenant_id = ? AND setting_key = ?
            ", [$this->tenantId, $key]);

            return $setting ? json_decode($setting['setting_value'], true) : null;
        }

        $settings = $this->db->fetchAll("
            SELECT setting_key, setting_value FROM tenant_settings
            WHERE tenant_id = ?
        ", [$this->tenantId]);

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = json_decode($setting['setting_value'], true);
        }

        return $result;
    }

    /**
     * Update tenant setting
     */
    public function updateTenantSetting($key, $value) {
        return $this->db->execute("
            INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value), updated_at = NOW()
        ", [$this->tenantId, $key, json_encode($value)]);
    }

    /**
     * Get tenant analytics (isolated to tenant data)
     */
    public function getTenantAnalytics($dateRange = 30) {
        return $this->db->fetchOne("
            SELECT
                COUNT(DISTINCT l.id) as total_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'active' THEN l.id END) as active_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'completed' THEN l.id END) as completed_loans,
                COUNT(DISTINCT CASE WHEN l.status = 'defaulted' THEN l.id END) as defaulted_loans,
                COALESCE(SUM(l.loan_amount), 0) as total_disbursed,
                COALESCE(SUM(CASE WHEN l.status = 'completed' THEN l.total_repayment ELSE 0 END), 0) as total_repaid,
                COALESCE(SUM(CASE WHEN l.status = 'completed' THEN (l.total_repayment - l.loan_amount) ELSE 0 END), 0) as total_interest,
                COUNT(DISTINCT l.borrower_id) as unique_borrowers,
                COUNT(DISTINCT l.supplier_id) as unique_suppliers
            FROM loans l
            WHERE l.tenant_id = ?
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$this->tenantId, $dateRange]);
    }

    /**
     * Switch tenant context
     */
    public function switchTenant($tenantId, $userId) {
        // Check if user has access to tenant
        if (!$this->hasPermission($userId, 'tenant_management')) {
            throw new Exception('Insufficient permissions to switch tenant');
        }

        $tenant = $this->db->fetchOne("
            SELECT * FROM tenants
            WHERE id = ? AND status = 'active'
        ", [$tenantId]);

        if (!$tenant) {
            throw new Exception('Tenant not found or inactive');
        }

        $this->setCurrentTenant($tenant);
        return true;
    }

    /**
     * Create database schema for tenant
     */
    public function createTenantSchema($tenantId) {
        $tenantCode = $this->generateTenantCode('tenant_' . $tenantId);

        // In a real implementation, this would create separate schemas or tables
        // For now, we'll use tenant_id filtering
        return [
            'success' => true,
            'schema_method' => 'row_level_security',
            'tenant_code' => $tenantCode
        ];
    }

    /**
     * Archive or delete tenant data
     */
    public function archiveTenant($tenantId, $action = 'archive') {
        try {
            if ($action === 'delete') {
                // Soft delete
                $this->db->execute("
                    UPDATE tenants SET status = 'deleted', deleted_at = NOW()
                    WHERE id = ?
                ", [$tenantId]);

                return ['success' => true, 'message' => 'Tenant deleted successfully'];
            } else {
                // Archive
                $this->db->execute("
                    UPDATE tenants SET status = 'archived', archived_at = NOW()
                    WHERE id = ?
                ", [$tenantId]);

                return ['success' => true, 'message' => 'Tenant archived successfully'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all tenants (for system admin)
     */
    public function getAllTenants() {
        return $this->db->fetchAll("
            SELECT
                t.*,
                u.name as owner_name,
                COUNT(DISTINCT l.id) as total_loans,
                COALESCE(SUM(l.loan_amount), 0) as total_disbursed,
                COUNT(DISTINCT tur.user_id) as user_count
            FROM tenants t
            LEFT JOIN users u ON t.owner_id = u.id
            LEFT JOIN loans l ON l.tenant_id = t.id
            LEFT JOIN tenant_user_roles tur ON tur.tenant_id = t.id
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
    }

    /**
     * Validate tenant configuration
     */
    public function validateTenantConfiguration($tenantId) {
        $issues = [];

        // Check required settings
        $requiredSettings = ['interest_rates', 'loan_limits', 'penalty_settings'];
        foreach ($requiredSettings as $setting) {
            if (!$this->getTenantSettings($setting)) {
                $issues[] = "Missing required setting: $setting";
            }
        }

        // Check owner assignment
        $ownerRole = $this->db->fetchOne("
            SELECT COUNT(*) as count FROM tenant_user_roles
            WHERE tenant_id = ? AND role_name = 'admin'
        ", [$tenantId]);

        if ($ownerRole['count'] == 0) {
            $issues[] = "No administrator assigned to tenant";
        }

        // Check business logic
        $settings = $this->getTenantSettings();
        if ($settings['interest_rates']['lender']['min'] > $settings['interest_rates']['lender']['max']) {
            $issues[] = "Invalid interest rate configuration";
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
}

/**
 * Global tenant manager instance
 */
global $tenantManager;
$tenantManager = new MultitenancyManager(Database::getInstance());

/**
 * Helper function to check tenant permissions
 */
function hasTenantPermission($userId, $permission) {
    global $tenantManager;
    return $tenantManager->hasPermission($userId, $permission);
}

/**
 * Helper function to get current tenant data
 */
function getCurrentTenant() {
    global $tenantManager;
    return $tenantManager->getCurrentTenant();
}

/**
 * Helper function to get tenant setting
 */
function getTenantSetting($key, $default = null) {
    global $tenantManager;
    $value = $tenantManager->getTenantSettings($key);
    return $value ?? $default;
}
?>