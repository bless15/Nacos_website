<?php
/**
 * ============================================
 * NACOS DASHBOARD - DATABASE CONFIGURATION
 * ============================================
 * Purpose: Secure PDO database connection
 * Security: Uses prepared statements, error handling
 * Created: November 2, 2025
 * ============================================
 */

// Prevent direct access
if (!defined('NACOS_ACCESS')) {
    die('Direct access not permitted');
}

// ============================================
// DATABASE CONFIGURATION
// ============================================

// Database credentials (CHANGE THESE FOR PRODUCTION!)
define('DB_HOST', 'localhost');
define('DB_NAME', 'nacos_dashboard');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_CHARSET', 'utf8mb4');

// ============================================
// ENVIRONMENT CONFIGURATION
// ============================================

// Set to 'production' when live
define('ENVIRONMENT', 'development'); // Options: 'development', 'production'

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// ============================================
// PDO CONNECTION CLASS
// ============================================

class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'development') {
                die("Database Connection Error: " . $e->getMessage());
            } else {
                // Log error and show generic message in production
                error_log("Database Connection Error: " . $e->getMessage());
                die("Sorry, we're experiencing technical difficulties. Please try again later.");
            }
        }
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Get the singleton instance
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the PDO connection
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement
     * @param string $query SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'development') {
                die("Query Error: " . $e->getMessage() . "<br>Query: " . $query);
            } else {
                error_log("Query Error: " . $e->getMessage() . " | Query: " . $query);
                throw new Exception("Database query failed");
            }
        }
    }
    
    /**
     * Fetch a single row
     * @param string $query SQL query
     * @param array $params Parameters to bind
     * @return array|false
     */
    public function fetchOne($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     * @param string $query SQL query
     * @param array $params Parameters to bind
     * @return array
     */
    public function fetchAll($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get the last inserted ID
     * @return string
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Get row count from last statement
     * @param PDOStatement $stmt
     * @return int
     */
    public function rowCount($stmt) {
        return $stmt->rowCount();
    }
}

// ============================================
// HELPER FUNCTION FOR QUICK ACCESS
// ============================================

/**
 * Get database instance
 * @return Database
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Get PDO connection directly
 * @return PDO
 */
function getConnection() {
    return Database::getInstance()->getConnection();
}

// ============================================
// CONFIGURATION COMPLETE
// ============================================
