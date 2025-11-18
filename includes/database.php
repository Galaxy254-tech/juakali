<?php
/**
 * Modern PDO-based Database Class (PHP 8.1+)
 * Implements connection pooling, prepared statements, and proper error handling
 */

class Database {
    private static ?self $instance = null;
    private ?\PDO $pdo = null;
    private array $config = [];
    
    private function __construct() {
        $this->config = [
            'host' => DB_HOST,
            'db_name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS,
            'charset' => 'utf8mb4',
        ];
        
        $this->connect();
    }
    
    /**
     * Singleton pattern for database connection
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish PDO connection with error handling
     */
    private function connect(): void {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['db_name'],
                $this->config['charset']
            );
            
            $this->pdo = new \PDO(
                $dsn,
                $this->config['user'],
                $this->config['pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::ATTR_PERSISTENT => false,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    \PDO::ATTR_TIMEOUT => 30,
                ]
            );
        } catch (\PDOException $e) {
            if (APP_ENV === 'development') {
                throw new \RuntimeException('Database Connection Failed: ' . $e->getMessage());
            } else {
                error_log('Database connection error: ' . $e->getMessage());
                throw new \RuntimeException('Database connection error. Please contact support.');
            }
        }
    }
    
    /**
     * Prepare and execute a query
     */
    public function query(string $sql, array $params = []): \PDOStatement {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            if (APP_ENV === 'development') {
                throw new \RuntimeException('Query Error: ' . $e->getMessage() . ' | SQL: ' . $sql);
            } else {
                error_log('Query error: ' . $e->getMessage() . ' | SQL: ' . $sql);
                throw new \RuntimeException('An error occurred while executing your request.');
            }
        }
    }
    
    /**
     * Fetch a single row
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * Fetch a single column value
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchColumn($column);
        return $result !== false ? $result : null;
    }
    
    /**
     * Execute insert/update/delete
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Insert a row and return last insert ID
     */
    public function insert(string $sql, array $params = []): string {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get last inserted ID
     */
    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollBack(): bool {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }
        return false;
    }
    
    /**
     * Check if connection is alive, reconnect if needed
     */
    public function checkConnection(): bool {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            $this->connect();
            return $this->pdo !== null;
        }
    }
    
    /**
     * Get PDO connection instance
     */
    public function getConnection(): \PDO {
        return $this->pdo;
    }
    
    /**
     * Close connection
     */
    public function close(): void {
        $this->pdo = null;
        self::$instance = null;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
    
    /**
     * Destructor to ensure proper connection closure
     */
    public function __destruct() {
        $this->close();
    }
}
?>