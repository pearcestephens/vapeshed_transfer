<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PDO Database Connection Class
 * Integrates with CIS PDO system
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description PDO-based database connection using CIS functions
 */
class PDODatabase
{
    private ?\PDO $connection = null;
    private static ?PDODatabase $instance = null;
    
    public function __construct()
    {
        // Include CIS PDO functions
        if (file_exists('/home/master/applications/jcepnzzkmj/public_html/assets/functions/pdo.php')) {
            require_once '/home/master/applications/jcepnzzkmj/public_html/assets/functions/pdo.php';
        }
        
        $this->connect();
    }
    
    public static function getInstance(): PDODatabase
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    private function connect(): void
    {
        try {
            // Use CIS database credentials if available
            if (function_exists('getPDO')) {
                $this->connection = getPDO();
            } else {
                // Fallback to environment variables
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $database = $_ENV['DB_DATABASE'] ?? 'vend_sales';
                $username = $_ENV['DB_USERNAME'] ?? '';
                $password = $_ENV['DB_PASSWORD'] ?? '';
                $port = $_ENV['DB_PORT'] ?? '3306';
                
                if (empty($username) || empty($password)) {
                    throw new \Exception('Database credentials not configured');
                }
                
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                
                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+12:00'"
                ];
                
                $this->connection = new \PDO($dsn, $username, $password, $options);
            }
            
        } catch (\Exception $e) {
            error_log("PDO Database connection error: " . $e->getMessage());
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $pdo = $this->getConnection();
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            error_log("SQL query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a query and return all results
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute a query and return single row
     */
    public function fetchRow(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Execute a query and return single value
     */
    public function fetchValue(string $sql, array $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Execute an INSERT and return the last insert ID
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->query($sql, $params);
        return (int)$this->getConnection()->lastInsertId();
    }
    
    /**
     * Execute an UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollback();
    }
    
    /**
     * Check if database is available and connected
     */
    public function isConnected(): bool
    {
        try {
            $pdo = $this->getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database version info
     */
    public function getVersion(): string
    {
        try {
            return $this->fetchValue('SELECT VERSION()') ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Connection Failed';
        }
    }
    
    /**
     * Execute multiple SQL statements from file
     */
    public function executeSQLFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new \Exception("SQL file not found: " . $filePath);
        }
        
        $sql = file_get_contents($filePath);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $this->beginTransaction();
        
        try {
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->query($statement);
                }
            }
            
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}