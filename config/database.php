<?php
class Database {
    private static $instance = null;
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $conn;
    private $stmt;
    
    // FIXED: Declare all properties to avoid deprecation warnings
    private $types = '';
    private $values = [];

    private function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db_name);

        if ($this->conn->connect_error) {
            if (APP_ENV === 'development') {
                die('Connection Error: ' . $this->conn->connect_error);
            } else {
                die('Database connection error. Please contact support.');
            }
        }

        $this->conn->set_charset("utf8mb4");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function connect() {
        return $this->conn;
    }

    public function query($sql) {
        $this->stmt = $this->conn->prepare($sql);
        if (!$this->stmt) {
            if (APP_ENV === 'development') {
                error_log('Query Error: ' . $this->conn->error . ' | SQL: ' . $sql);
                throw new Exception('Query Error: ' . $this->conn->error);
            } else {
                error_log('Query Error: ' . $this->conn->error);
                throw new Exception('An error occurred while executing your request.');
            }
        }
        
        // Reset bindings for new query
        $this->types = '';
        $this->values = [];
        
        return $this->stmt;
    }

    public function bind($type, $value) {
        $this->types .= $type;
        $this->values[] = $value;
        return $this;
    }

    public function execute() {
        if (!empty($this->values)) {
            $this->stmt->bind_param($this->types, ...$this->values);
        }
        $result = $this->stmt->execute();
        
        if (!$result && APP_ENV === 'development') {
            error_log('Execute Error: ' . $this->stmt->error);
        }
        
        // Reset for next execution
        $this->types = '';
        $this->values = [];
        
        return $result;
    }

    public function resultSet() {
        $this->execute();
        $result = $this->stmt->get_result();
        
        if (!$result) {
            if (APP_ENV === 'development') {
                throw new Exception('ResultSet Error: ' . $this->stmt->error);
            } else {
                throw new Exception('An error occurred while fetching data.');
            }
        }
        
        return $result;
    }

    public function single() {
        $result = $this->resultSet();
        $data = $result->fetch_assoc();
        $result->free();
        return $data ?: null;
    }

    public function rowCount() {
        return $this->stmt->affected_rows;
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }

    public function close() {
        if ($this->stmt) {
            $this->stmt->close();
            $this->stmt = null;
        }
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
        
        // Reset properties
        $this->types = '';
        $this->values = [];
    }

    public function __destruct() {
        $this->close();
    }
}
?>