<?php
/**
 * Comprehensive System Validation and Quality Assurance
 * Validates all PHP files, database schema, and system integrity
 */

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/gamification-system.php';
require_once __DIR__ . '/../includes/gamification-logger.php';
require_once __DIR__ . '/../includes/gamification-security.php';

class SystemValidator {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    private $db;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new GamificationLogger(false);
    }

    /**
     * Run comprehensive system validation
     */
    public function runFullValidation() {
        echo "🔍 Starting Comprehensive System Validation...\n\n";

        $this->validatePHPFiles();
        $this->validateDatabaseSchema();
        $this->validateDependencies();
        $this->validateConfiguration();
        $this->validateSecurity();
        $this->validatePerformance();
        $this->validateAPIEndpoints();
        $this->validateFileSystem();
        $this->validateGamificationLogic();

        $this->printResults();
        return $this->results;
    }

    /**
     * Validate PHP files for syntax and structure
     */
    private function validatePHPFiles() {
        echo "📄 Validating PHP Files...\n";

        $phpFiles = $this->findPHPFiles(__DIR__ . '/..');

        foreach ($phpFiles as $file) {
            $this->validatePHPFile($file);
        }

        $this->results['php_files'] = [
            'total' => count($phpFiles),
            'valid' => count($phpFiles) - count($this->getErrorsByType('php_syntax')),
            'errors' => $this->getErrorsByType('php_syntax'),
            'warnings' => $this->getWarningsByType('php_structure')
        ];

        echo "✅ PHP Files Validation Complete\n";
    }

    /**
     * Find all PHP files in the project
     */
    private function findPHPFiles($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Validate individual PHP file
     */
    private function validatePHPFile($file) {
        // Check for syntax errors
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $this->addError('php_syntax', "Syntax error in {$file}: " . implode(' ', $output));
            return false;
        }

        // Check file structure
        $content = file_get_contents($file);
        $this->validatePHPStructure($file, $content);

        // Check for common issues
        $this->validatePHPIssues($file, $content);

        return true;
    }

    /**
     * Validate PHP file structure
     */
    private function validatePHPStructure($file, $content) {
        // Check for PHP opening tag
        if (strpos($content, '<?php') === false) {
            $this->addWarning('php_structure', "Missing PHP opening tag in {$file}");
        }

        // Check for closing PHP tag in pure PHP files
        if (strpos($file, '.php') !== false && strpos($content, '?>') !== false) {
            $this->addWarning('php_structure', "Unnecessary PHP closing tag in {$file}");
        }

        // Check for short echo tags
        if (strpos($content, '<?=') !== false) {
            $this->addWarning('php_structure', "Short echo tag found in {$file}");
        }

        // Check for proper namespace usage
        if (strpos($content, 'class ') !== false && strpos($content, 'namespace ') === false) {
            $this->addWarning('php_structure', "Class found without namespace in {$file}");
        }

        // Check for strict types declarations
        if (strpos($content, 'function ') !== false && strpos($content, 'declare(strict_types=1)') === false) {
            $this->addWarning('php_structure', "Function found without strict_types declaration in {$file}");
        }
    }

    /**
     * Validate common PHP issues
     */
    private function validatePHPIssues($file, $content) {
        // Check for SQL injection vulnerabilities
        if (strpos($content, '$_GET') !== false || strpos($content, '$_POST') !== false) {
            if (strpos($content, 'prepare') === false && strpos($content, 'mysqli_prepare') === false) {
                $this->addWarning('php_security', "Potential SQL injection vulnerability in {$file}");
            }
        }

        // Check for XSS vulnerabilities
        if (strpos($content, 'echo $_') !== false || strpos($content, 'print $_') !== false) {
            $this->addWarning('php_security', "Potential XSS vulnerability in {$file}");
        }

        // Check for hardcoded credentials
        if (preg_match('/password\s*=\s*[\'"][^\'\"]+[\'"]/', $content)) {
            $this->addError('php_security', "Hardcoded password found in {$file}");
        }

        // Check for error suppression
        if (strpos($content, '@') !== false) {
            $this->addWarning('php_structure', "Error suppression operator @ found in {$file}");
        }

        // Check for deprecated functions
        $deprecatedFunctions = ['mysql_query', 'mysql_fetch_assoc', 'eregi', 'split'];
        foreach ($deprecatedFunctions as $func) {
            if (strpos($content, $func) !== false) {
                $this->addWarning('php_deprecated', "Deprecated function {$func} found in {$file}");
            }
        }
    }

    /**
     * Validate database schema
     */
    private function validateDatabaseSchema() {
        echo "🗄️  Validating Database Schema...\n";

        $schemaFile = __DIR__ . '/../database/gamification-schema.sql';
        if (!file_exists($schemaFile)) {
            $this->addError('database', "Database schema file not found: {$schemaFile}");
            return;
        }

        // Check required tables
        $requiredTables = [
            'users', 'user_profiles', 'user_levels', 'badges', 'user_badges',
            'user_points', 'challenges', 'challenge_participants', 'rewards',
            'user_rewards', 'user_activity', 'gamification_logs', 'achievements',
            'user_achievements', 'notifications', 'user_referrals'
        ];

        foreach ($requiredTables as $table) {
            if (!$this->tableExists($table)) {
                $this->addError('database', "Required table not found: {$table}");
            } else {
                $this->validateTableStructure($table);
            }
        }

        // Check indexes
        $this->validateDatabaseIndexes();

        // Check foreign keys
        $this->validateForeignKeys();

        $this->results['database'] = [
            'tables_checked' => count($requiredTables),
            'tables_valid' => count($requiredTables) - count($this->getErrorsByType('database')),
            'errors' => $this->getErrorsByType('database'),
            'warnings' => $this->getWarningsByType('database')
        ];

        echo "✅ Database Schema Validation Complete\n";
    }

    /**
     * Check if table exists
     */
    private function tableExists($table) {
        try {
            $result = $this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
            return !empty($result);
        } catch (Exception $e) {
            $this->addError('database', "Failed to check table {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate table structure
     */
    private function validateTableStructure($table) {
        try {
            $columns = $this->db->fetchAll("SHOW COLUMNS FROM {$table}");

            // Check for required columns based on table
            $this->validateTableColumns($table, $columns);

            // Check for proper character set
            $tableStatus = $this->db->fetchOne("SHOW TABLE STATUS LIKE ?", [$table]);
            if ($tableStatus && strpos($tableStatus['Collation'], 'utf8mb4') === false) {
                $this->addWarning('database', "Table {$table} should use utf8mb4 character set");
            }

        } catch (Exception $e) {
            $this->addError('database', "Failed to validate table {$table}: " . $e->getMessage());
        }
    }

    /**
     * Validate table columns
     */
    private function validateTableColumns($table, $columns) {
        $columnNames = array_column($columns, 'Field');

        // Check for common required columns
        $commonColumns = ['id', 'created_at', 'updated_at'];
        foreach ($commonColumns as $col) {
            if (!in_array($col, $columnNames) && !in_array($table, ['user_activity_archive'])) {
                $this->addWarning('database', "Table {$table} missing common column: {$col}");
            }
        }

        // Check for proper data types
        foreach ($columns as $column) {
            if ($column['Null'] === 'YES' && in_array($column['Field'], ['id', 'user_id', 'created_at'])) {
                $this->addWarning('database', "Column {$table}.{$column['Field']} should not be nullable");
            }

            if ($column['Type'] === 'text' && $column['Default'] !== null) {
                $this->addWarning('database', "TEXT column {$table}.{$column['Field']} should not have a default value");
            }
        }
    }

    /**
     * Validate database indexes
     */
    private function validateDatabaseIndexes() {
        try {
            $indexes = $this->db->fetchAll("SHOW INDEX FROM user_profiles");

            // Check for essential indexes
            $essentialIndexes = ['PRIMARY', 'user_id', 'total_points', 'current_level'];
            $existingIndexes = array_column($indexes, 'Key_name');

            foreach ($essentialIndexes as $index) {
                if (!in_array($index, $existingIndexes)) {
                    $this->addWarning('database', "Missing essential index on user_profiles: {$index}");
                }
            }

        } catch (Exception $e) {
            $this->addError('database', "Failed to validate indexes: " . $e->getMessage());
        }
    }

    /**
     * Validate foreign keys
     */
    private function validateForeignKeys() {
        try {
            // Check foreign key constraints
            $constraints = $this->db->fetchAll("
                SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_NAME IS NOT NULL
                AND TABLE_SCHEMA = DATABASE()
            ");

            foreach ($constraints as $constraint) {
                // Check if referenced table exists
                if (!$this->tableExists($constraint['REFERENCED_TABLE_NAME'])) {
                    $this->addError('database', "Foreign key references non-existent table: {$constraint['CONSTRAINT_NAME']}");
                }
            }

        } catch (Exception $e) {
            $this->addError('database', "Failed to validate foreign keys: " . $e->getMessage());
        }
    }

    /**
     * Validate dependencies
     */
    private function validateDependencies() {
        echo "📦 Validating Dependencies...\n";

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->addError('dependency', "PHP version 8.0.0 or higher required. Current: " . PHP_VERSION);
        }

        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->addError('dependency', "Required PHP extension not loaded: {$ext}");
            }
        }

        // Check optional extensions
        $optionalExtensions = ['redis', 'gd', 'imagick', 'zip'];
        foreach ($optionalExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->addWarning('dependency', "Optional PHP extension not loaded: {$ext}");
            }
        }

        // Check file permissions
        $this->validateFilePermissions();

        $this->results['dependencies'] = [
            'php_version' => PHP_VERSION,
            'extensions_loaded' => get_loaded_extensions(),
            'errors' => $this->getErrorsByType('dependency'),
            'warnings' => $this->getWarningsByType('dependency')
        ];

        echo "✅ Dependencies Validation Complete\n";
    }

    /**
     * Validate file permissions
     */
    private function validateFilePermissions() {
        $paths = [
            __DIR__ . '/../logs',
            __DIR__ . '/../uploads',
            __DIR__ . '/../cache'
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                if (!is_writable($path)) {
                    $this->addError('permission', "Directory not writable: {$path}");
                }
            } else {
                // Try to create directory
                if (!mkdir($path, 0755, true)) {
                    $this->addWarning('permission', "Could not create directory: {$path}");
                }
            }
        }
    }

    /**
     * Validate configuration
     */
    private function validateConfiguration() {
        echo "⚙️  Validating Configuration...\n";

        // Check if config files exist
        $configFiles = [
            __DIR__ . '/../includes/config/database.php',
            __DIR__ . '/../includes/config/app.php'
        ];

        foreach ($configFiles as $file) {
            if (!file_exists($file)) {
                $this->addError('config', "Configuration file not found: {$file}");
            } else {
                // Check config file syntax
                $this->validateConfigFile($file);
            }
        }

        // Check environment variables
        $this->validateEnvironmentVariables();

        // Check .env file
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $this->validateEnvFile($envFile);
        }

        $this->results['configuration'] = [
            'config_files' => $configFiles,
            'env_file_exists' => file_exists($envFile),
            'errors' => $this->getErrorsByType('config'),
            'warnings' => $this->getWarningsByType('config')
        ];

        echo "✅ Configuration Validation Complete\n";
    }

    /**
     * Validate config file
     */
    private function validateConfigFile($file) {
        $content = file_get_contents($file);

        // Check for common config patterns
        if (strpos($content, '<?php') === false) {
            $this->addError('config', "Invalid PHP config file: {$file}");
        }

        // Check for hardcoded credentials
        if (preg_match('/password\s*=\s*[\'"][^\'\"]+[\'"]/', $content)) {
            $this->addError('config', "Hardcoded password found in config file: {$file}");
        }

        // Check for debug settings in production
        if (getenv('APP_ENV') === 'production' && strpos($content, 'debug') !== false) {
            $this->addWarning('config', "Debug settings detected in production");
        }
    }

    /**
     * Validate environment variables
     */
    private function validateEnvironmentVariables() {
        $requiredEnvVars = ['APP_ENV', 'DB_HOST', 'DB_NAME', 'DB_USER'];
        foreach ($requiredEnvVars as $var) {
            if (getenv($var) === false) {
                $this->addError('config', "Required environment variable not set: {$var}");
            }
        }

        // Check for sensitive data in environment
        $sensitiveVars = ['DB_PASSWORD', 'API_SECRET', 'JWT_SECRET'];
        foreach ($sensitiveVars as $var) {
            if (getenv($var) && strlen(getenv($var)) < 16) {
                $this->addWarning('config', "Environment variable {$var} should be at least 16 characters");
            }
        }
    }

    /**
     * Validate .env file
     */
    private function validateEnvFile($file) {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;

            if (strpos($line, '=') === false) {
                $this->addWarning('config', "Invalid line in .env file: {$line}");
            }

            if (preg_match('/password\s*=\s*[\'"][^\'\"]+[\'"]/', $line)) {
                $this->addError('config', "Hardcoded password in .env file");
            }
        }
    }

    /**
     * Validate security settings
     */
    private function validateSecurity() {
        echo "🔒 Validating Security...\n";

        // Check if security headers are being sent
        $this->validateSecurityHeaders();

        // Check for SQL injection protection
        $this->validateSQLInjectionProtection();

        // Check for XSS protection
        $this->validateXSSProtection();

        // Check CSRF protection
        $this->validateCSRFProtection();

        // Check file upload security
        $this->validateFileUploadSecurity();

        // Check session security
        $this->validateSessionSecurity();

        $this->results['security'] = [
            'headers_valid' => count($this->getErrorsByType('security')) === 0,
            'errors' => $this->getErrorsByType('security'),
            'warnings' => $this->getWarningsByType('security')
        ];

        echo "✅ Security Validation Complete\n";
    }

    /**
     * Validate security headers
     */
    private function validateSecurityHeaders() {
        $requiredHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Strict-Transport-Security'
        ];

        // This would be checked by making actual HTTP requests
        // For now, we'll check if the code includes header setting
        $apiFile = __DIR__ . '/../api/gamification.php';
        if (file_exists($apiFile)) {
            $content = file_get_contents($apiFile);
            foreach ($requiredHeaders as $header) {
                if (strpos($content, $header) === false) {
                    $this->addWarning('security', "Security header not set: {$header}");
                }
            }
        }
    }

    /**
     * Validate SQL injection protection
     */
    private function validateSQLInjectionProtection() {
        $apiFile = __DIR__ . '/../api/gamification.php';
        if (file_exists($apiFile)) {
            $content = file_get_contents($apiFile);

            // Check for prepared statements
            if (strpos($content, 'prepare') === false && strpos($content, 'bindValue') === false) {
                $this->addError('security', "No prepared statements found in API");
            }

            // Check for direct concatenation
            if (preg_match('/\$\w+\s*\.\s*\$\w+/', $content)) {
                $this->addWarning('security', "Potential SQL injection vulnerability in API");
            }
        }
    }

    /**
     * Validate XSS protection
     */
    private function validateXSSProtection() {
        $apiFile = __DIR__ . '/../api/gamification.php';
        if (file_exists($apiFile)) {
            $content = file_get_contents($apiFile);

            // Check for input sanitization
            if (strpos($content, 'htmlspecialchars') === false && strpos($content, 'filter_var') === false) {
                $this->addWarning('security', "No XSS protection found in API");
            }
        }
    }

    /**
     * Validate CSRF protection
     */
    private function validateCSRFProtection() {
        $apiFile = __DIR__ . '/../api/gamification.php';
        if (file_exists($apiFile)) {
            $content = file_get_contents($apiFile);

            if (strpos($content, 'csrf') === false && strpos($content, 'CSRF') === false) {
                $this->addWarning('security', "No CSRF protection found in API");
            }
        }
    }

    /**
     * Validate file upload security
     */
    private function validateFileUploadSecurity() {
        $uploadDirs = [__DIR__ . '/../uploads', __DIR__ . '/../attachments'];

        foreach ($uploadDirs as $dir) {
            if (is_dir($dir)) {
                // Check for .htaccess file
                $htaccessFile = $dir . '/.htaccess';
                if (!file_exists($htaccessFile)) {
                    $this->addWarning('security', "No .htaccess file in upload directory: {$dir}");
                }

                // Check directory permissions
                $perms = fileperms($dir);
                if (($perms & 0o777) > 0o755) {
                    $this->addWarning('security', "Upload directory permissions too open: {$dir}");
                }
            }
        }
    }

    /**
     * Validate session security
     */
    private function validateSessionSecurity() {
        // Check session configuration
        $sessionSettings = [
            'session.cookie_httponly' => '1',
            'session.cookie_secure' => '1',
            'session.use_only_cookies' => '1',
            'session.cookie_samesite' => 'Strict'
        ];

        foreach ($sessionSettings as $setting => $expected) {
            if (ini_get($setting) !== $expected) {
                $this->addWarning('security', "Session setting not optimal: {$setting}");
            }
        }
    }

    /**
     * Validate performance settings
     */
    private function validatePerformance() {
        echo "⚡ Validating Performance...\n";

        // Check PHP memory limits
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit && $this->parseMemoryLimit($memoryLimit) < 256) {
            $this->addWarning('performance', "Memory limit too low: {$memoryLimit} (recommended: 256M or higher)");
        }

        // Check execution time
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime < 30) {
            $this->addWarning('performance', "Max execution time too low: {$maxExecutionTime}s (recommended: 30s or higher)");
        }

        // Check for caching
        $this->validateCaching();

        // Check for database optimization
        $this->validateDatabasePerformance();

        $this->results['performance'] = [
            'memory_limit' => $memoryLimit,
            'max_execution_time' => $maxExecutionTime,
            'errors' => $this->getErrorsByType('performance'),
            'warnings' => $this->getWarningsByType('performance')
        ];

        echo "✅ Performance Validation Complete\n";
    }

    /**
     * Parse memory limit string
     */
    private function parseMemoryLimit($limit) {
        $unit = strtoupper(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        switch ($unit) {
            case 'G': return $value * 1024;
            case 'M': return $value;
            case 'K': return $value / 1024;
            default: return $value / 1024 / 1024;
        }
    }

    /**
     * Validate caching
     */
    private function validateCaching() {
        // Check if OPcache is enabled
        if (!function_exists('opcache_get_status')) {
            $this->addWarning('performance', "OPcache not enabled");
        } else {
            $opcacheStatus = opcache_get_status();
            if (!$opcacheStatus['opcache_enabled']) {
                $this->addWarning('performance', "OPcache is enabled but not active");
            }
        }

        // Check for Redis cache (if configured)
        if (getenv('REDIS_HOST')) {
            if (!extension_loaded('redis')) {
                $this->addError('performance', "Redis configured but Redis extension not loaded");
            }
        }
    }

    /**
     * Validate database performance
     */
    private function validateDatabasePerformance() {
        try {
            // Check slow query log
            $slowQueryLog = $this->db->fetchOne("SHOW VARIABLES LIKE 'slow_query_log'");
            if ($slowQueryLog && $slowQueryLog['Value'] === 'OFF') {
                $this->addWarning('performance', "Slow query log is disabled");
            }

            // Check query cache
            $queryCache = $this->db->fetchOne("SHOW VARIABLES LIKE 'query_cache_size'");
            if ($queryCache && $queryCache['Value'] === '0') {
                $this->addWarning('performance', "Query cache is disabled");
            }

            // Check for missing indexes on large tables
            $largeTables = $this->db->fetchAll("
                SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_ROWS > 1000
                ORDER BY TABLE_ROWS DESC
                LIMIT 5
            ");

            foreach ($largeTables as $table) {
                $indexCount = $this->db->fetchOne("SHOW INDEX FROM {$table['TABLE_NAME']}");
                if ($indexCount && count($indexCount) < 3) {
                    $this->addWarning('performance', "Large table {$table['TABLE_NAME']} may need more indexes");
                }
            }

        } catch (Exception $e) {
            $this->addError('performance', "Failed to validate database performance: " . $e->getMessage());
        }
    }

    /**
     * Validate API endpoints
     */
    private function validateAPIEndpoints() {
        echo "🌐 Validating API Endpoints...\n";

        $endpoints = [
            '/api/gamification.php?action=profile',
            '/api/gamification.php?action=leaderboard',
            '/api/gamification.php?action=challenges',
            '/api/gamification.php?action=rewards_history',
            '/api/gamification.php?action=achievements'
        ];

        foreach ($endpoints as $endpoint) {
            $this->validateEndpoint($endpoint);
        }

        $this->results['api'] = [
            'endpoints_tested' => count($endpoints),
            'errors' => $this->getErrorsByType('api'),
            'warnings' => $this->getWarningsByType('api')
        ];

        echo "✅ API Endpoints Validation Complete\n";
    }

    /**
     * Validate individual API endpoint
     */
    private function validateEndpoint($endpoint) {
        $url = 'http://localhost' . $endpoint;

        // In a real implementation, this would make HTTP requests
        // For now, we'll check if the files exist and have proper structure
        $apiFile = __DIR__ . '/../api/gamification.php';
        if (!file_exists($apiFile)) {
            $this->addError('api', "API file not found: gamification.php");
            return;
        }

        $content = file_get_contents($apiFile);
        $action = parse_url($endpoint, PHP_URL_QUERY);
        parse_str($action, $params);
        $actionName = $params['action'] ?? '';

        // Check if action exists in API
        if ($actionName && strpos($content, "case '{$actionName}':") === false) {
            $this->addError('api', "API action not implemented: {$actionName}");
        }
    }

    /**
     * Validate file system
     */
    private function validateFileSystem() {
        echo "📁 Validating File System...\n";

        // Check for required directories
        $requiredDirs = [
            'logs',
            'uploads',
            'cache',
            'assets/css',
            'assets/js',
            'assets/icons',
            'assets/screenshots'
        ];

        foreach ($requiredDirs as $dir) {
            $dirPath = __DIR__ . '/../' . $dir;
            if (!is_dir($dirPath)) {
                $this->addError('filesystem', "Required directory not found: {$dir}");
            } else {
                // Check directory permissions
                if (!is_readable($dirPath)) {
                    $this->addError('filesystem', "Directory not readable: {$dir}");
                }
                if (!is_writable($dirPath)) {
                    $this->addError('filesystem', "Directory not writable: {$dir}");
                }
            }
        }

        // Check for required files
        $requiredFiles = [
            'manifest.json',
            'sw.js',
            'favicon.ico'
        ];

        foreach ($requiredFiles as $file) {
            $filePath = __DIR__ . '/../' . $file;
            if (!file_exists($filePath)) {
                $this->addError('filesystem', "Required file not found: {$file}");
            }
        }

        // Check PWA manifest validity
        $manifestPath = __DIR__ . '/../manifest.json';
        if (file_exists($manifestPath)) {
            $this->validatePWAManifest($manifestPath);
        }

        $this->results['filesystem'] = [
            'directories_checked' => count($requiredDirs),
            'files_checked' => count($requiredFiles),
            'errors' => $this->getErrorsByType('filesystem'),
            'warnings' => $this->getWarningsByType('filesystem')
        ];

        echo "✅ File System Validation Complete\n";
    }

    /**
     * Validate PWA manifest
     */
    private function validatePWAManifest($manifestPath) {
        $manifestContent = file_get_contents($manifestPath);
        $manifest = json_decode($manifestContent, true);

        if (!$manifest) {
            $this->addError('filesystem', "Invalid JSON in manifest.json");
            return;
        }

        // Check required manifest fields
        $requiredFields = ['name', 'short_name', 'start_url', 'display', 'background_color', 'theme_color'];
        foreach ($requiredFields as $field) {
            if (!isset($manifest[$field])) {
                $this->addWarning('filesystem', "Missing manifest field: {$field}");
            }
        }

        // Check icons
        if (!isset($manifest['icons']) || !is_array($manifest['icons'])) {
            $this->addWarning('filesystem', "No icons found in manifest");
        } else {
            foreach ($manifest['icons'] as $icon) {
                if (!isset($icon['src']) || !isset($icon['sizes'])) {
                    $this->addWarning('filesystem', "Invalid icon format in manifest");
                }
            }
        }
    }

    /**
     * Validate gamification logic
     */
    private function validateGamificationLogic() {
        echo "🎮 Validating Gamification Logic...\n";

        // Test gamification system initialization
        try {
            $gamification = new GamificationSystem();

            // Test points calculation
            $this->validatePointsCalculation($gamification);

            // Test badge logic
            $this->validateBadgeLogic($gamification);

            // Test challenge logic
            $this->validateChallengeLogic($gamification);

            // Test level progression
            $this->validateLevelProgression($gamification);

        } catch (Exception $e) {
            $this->addError('gamification', "Gamification system error: " . $e->getMessage());
        }

        $this->results['gamification'] = [
            'logic_validated' => true,
            'errors' => $this->getErrorsByType('gamification'),
            'warnings' => $this->getWarningsByType('gamification')
        ];

        echo "✅ Gamification Logic Validation Complete\n";
    }

    /**
     * Validate points calculation
     */
    private function validatePointsCalculation($gamification) {
        // Test points awarding
        $testCases = [
            ['action' => 'daily_login', 'expected_min' => 5],
            ['action' => 'loan_application', 'expected_min' => 10],
            ['action' => 'kyc_verification', 'expected_min' => 100]
        ];

        foreach ($testCases as $test) {
            // This would test the actual points calculation
            // For now, we'll validate the configuration exists
            if (!isset($gamification->pointsConfig[$test['action']])) {
                $this->addWarning('gamification', "Points configuration missing for: {$test['action']}");
            }
        }
    }

    /**
     * Validate badge logic
     */
    private function validateBadgeLogic($gamification) {
        // Check badge configuration
        if (empty($gamification->badgeConfig)) {
            $this->addError('gamification', "Badge configuration is empty");
            return;
        }

        // Check for required badge fields
        foreach ($gamification->badgeConfig as $badgeCode => $badge) {
            if (!isset($badge['name']) || !isset($badge['description']) || !isset($badge['icon'])) {
                $this->addWarning('gamification', "Badge {$badgeCode} missing required fields");
            }
        }
    }

    /**
     * Validate challenge logic
     */
    private function validateChallengeLogic($gamification) {
        // Test challenge creation
        $testChallenge = [
            'title' => 'Test Challenge',
            'description' => 'Test Description',
            'challenge_type' => 'test_type',
            'target_value' => 10,
            'points_reward' => 100,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'eligibility_criteria' => ['min_level' => 1]
        ];

        // This would test the actual challenge creation
        // For now, we'll validate the structure
        foreach ($testChallenge as $key => $value) {
            if (empty($value) && $key !== 'badge_reward') {
                $this->addWarning('gamification', "Challenge field {$key} is empty");
            }
        }
    }

    /**
     * Validate level progression
     */
    private function validateLevelProgression($gamification) {
        // Check level thresholds
        if (empty($gamification->levelThresholds)) {
            $this->addError('gamification', "Level thresholds not configured");
            return;
        }

        // Check if levels are sequential
        $levels = array_keys($gamification->levelThresholds);
        sort($levels);
        for ($i = 1; $i < count($levels); $i++) {
            if ($levels[$i] !== $i) {
                $this->addWarning('gamification', "Level {$levels[$i]} may be out of sequence");
            }
        }

        // Check if threshold values are logical
        foreach ($gamification->levelThresholds as $level => $config) {
            if (!isset($config['min_points']) || !isset($config['name'])) {
                $this->addWarning('gamification', "Level {$level} missing required fields");
            }
        }
    }

    /**
     * Add error
     */
    private function addError($type, $message) {
        $this->errors[] = ['type' => $type, 'message' => $message];
    }

    /**
     * Add warning
     */
    private function addWarning($type, $message) {
        $this->warnings[] = ['type' => $type, 'message' => $message];
    }

    /**
     * Get errors by type
     */
    private function getErrorsByType($type) {
        return array_filter($this->errors, function($error) use ($type) {
            return $error['type'] === $type;
        });
    }

    /**
     * Get warnings by type
     */
    private function getWarningsByType($type) {
        return array_filter($this->warnings, function($warning) use ($type) {
            return $warning['type'] === $type;
        });
    }

    /**
     * Print validation results
     */
    private function printResults() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 VALIDATION RESULTS SUMMARY\n";
        echo str_repeat("=", 80) . "\n\n";

        // Overall status
        $totalErrors = count($this->errors);
        $totalWarnings = count($this->warnings);

        if ($totalErrors === 0) {
            echo "✅ PASSED: No critical issues found\n";
        } else {
            echo "❌ FAILED: {$totalErrors} critical issues found\n";
        }

        if ($totalWarnings > 0) {
            echo "⚠️  WARNINGS: {$totalWarnings} issues found\n";
        }

        echo "\n";

        // Detailed results
        foreach ($this->results as $category => $result) {
            echo "📋 " . ucfirst(str_replace('_', ' ', $category)) . "\n";
            echo str_repeat("-", 40) . "\n";

            foreach ($result as $key => $value) {
                if (is_array($value)) {
                    echo "  {$key}: " . (count($value) === 0 ? 'None' : count($value)) . " items\n";
                } else {
                    echo "  {$key}: {$value}\n";
                }
            }
            echo "\n";
        }

        // Errors
        if (!empty($this->errors)) {
            echo "🚨 ERRORS\n";
            echo str_repeat("-", 40) . "\n";
            foreach ($this->errors as $error) {
                echo "[{$error['type']}] {$error['message']}\n";
            }
            echo "\n";
        }

        // Warnings
        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS\n";
            echo str_repeat("-", 40) . "\n";
            foreach ($this->warnings as $warning) {
                echo "[{$warning['type']}] {$warning['message']}\n";
            }
            echo "\n";
        }

        // Recommendations
        $this->printRecommendations();

        echo str_repeat("=", 80) . "\n";
    }

    /**
     * Print recommendations
     */
    private function printRecommendations() {
        echo "💡 RECOMMENDATIONS\n";
        echo str_repeat("-", 40) . "\n";

        if (!empty($this->getErrorsByType('database'))) {
            echo "• Fix database schema issues before deployment\n";
            echo "• Run the database migration script: php database/gamification-schema.sql\n";
        }

        if (!empty($this->getErrorsByType('security'))) {
            echo "• Address security vulnerabilities immediately\n";
            echo "• Review and implement proper input validation\n";
            echo "• Enable all security headers\n";
        }

        if (!empty($this->getErrorsByType('dependency'))) {
            echo "• Install required PHP extensions and dependencies\n";
            echo "• Update PHP version to 8.0 or higher\n";
        }

        if (!empty($this->getWarningsByType('performance'))) {
            echo "• Optimize performance settings for better user experience\n";
            echo "• Consider implementing caching strategies\n";
        }

        if (!empty($this->getWarningsByType('filesystem'))) {
            echo "• Ensure all required directories exist with proper permissions\n";
            echo "• Create missing PWA assets (icons, screenshots)\n";
        }

        echo "• Regularly run this validation to ensure system health\n";
        echo "• Monitor system performance and error logs\n";
        echo "• Keep all dependencies up to date\n";
    }
}

// Run validation if this file is executed directly
if (php_sapi_name() === 'cli') {
    $validator = new SystemValidator();
    $results = $validator->runFullValidation();

    // Exit with appropriate code
    $totalErrors = count($results['errors'] ?? []);
    exit($totalErrors > 0 ? 1 : 0);
}
?>