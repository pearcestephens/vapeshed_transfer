<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Database Connection Class
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description MySQL/MariaDB connection with prepared statements
 */
class Database
{
    private ?\mysqli $connection = null;
    private static ?Database $instance = null;
    
    public function __construct()
    {
        // Do not connect immediately if DB credentials are not configured
        if (defined('DB_CONFIGURED') && DB_CONFIGURED) {
            $this->connect();
        }
    }
    
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    private function connect(): void
    {
        // Include CIS config.php to get access to $con and $pdo
        if (file_exists('/home/master/applications/jcepnzzkmj/public_html/assets/functions/config.php')) {
            require_once '/home/master/applications/jcepnzzkmj/public_html/assets/functions/config.php';
        }
        
        // Use existing CIS database connections
        global $con, $pdo;
        
        if (isset($con) && $con instanceof \mysqli) {
            $this->connection = $con;
            error_log('Database: Using existing CIS MySQLi connection ($con)');
            return;
        }
        
        // Fallback to original connection method if CIS connection not available
        try {
            $this->connection = new \mysqli(
                DB_HOST ?? $_ENV['DB_HOST'] ?? 'localhost',
                DB_USERNAME ?? $_ENV['DB_USER'] ?? 'jcepnzzkmj', 
                DB_PASSWORD ?? $_ENV['DB_PASS'] ?? '',
                DB_DATABASE ?? $_ENV['DB_NAME'] ?? 'jcepnzzkmj',
                (int)(DB_PORT ?? $_ENV['DB_PORT'] ?? 3306)
            );
            
            if ($this->connection->connect_error) {
                throw new \Exception("Database connection failed: " . $this->connection->connect_error);
            }
            
            // Set charset
            $this->connection->set_charset('utf8mb4');
            
            // Set timezone
            $this->connection->query("SET time_zone = '+12:00'"); // Pacific/Auckland
            
        } catch (\Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getConnection(): \mysqli
    {
        if (!defined('DB_CONFIGURED') || !DB_CONFIGURED) {
            throw new \Exception('Database is not configured. Please set DB_USERNAME and DB_PASSWORD in .env');
        }
        if ($this->connection === null || $this->connection->ping() === false) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    public function query(string $sql, array $params = []): \mysqli_result|bool
    {
        $conn = $this->getConnection();
        
        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result === false) {
                throw new \Exception("SQL query failed: " . $conn->error);
            }
            return $result;
        }
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    public function fetchAll(string $sql, array $params = []): array
    {
        $result = $this->query($sql, $params);
        
        if ($result === false) {
            return [];
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);
        
        if ($result === false) {
            return null;
        }
        
        return $result->fetch_assoc();
    }
    
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );
        
        $this->query($sql, array_values($data));
        
        return $this->getConnection()->insert_id;
    }
    
    public function update(string $table, array $data, array $where): int
    {
        $setParts = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = "`{$column}` = ?";
            $values[] = $value;
        }
        
        $whereParts = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $values[] = $value;
        }
        
        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );
        
        $this->query($sql, $values);
        
        return $this->getConnection()->affected_rows;
    }
    
    public function beginTransaction(): void
    {
        $this->getConnection()->autocommit(false);
    }
    
    public function commit(): void
    {
        $this->getConnection()->commit();
        $this->getConnection()->autocommit(true);
    }
    
    public function rollback(): void
    {
        $this->getConnection()->rollback();
        $this->getConnection()->autocommit(true);
    }
}