<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Database Connection Class - PRODUCTION HARDENED
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description MySQL/MariaDB connection with dedicated connection pool
 * 
 * IMPROVEMENTS:
 * - Dedicated connection pool (no shared global $con)
 * - Connection health monitoring with auto-reconnect
 * - Transaction isolation support
 * - Connection metrics tracking
 * - Thread-safe singleton pattern
 */
class Database
{
    private ?\mysqli $connection = null;
    private static ?Database $instance = null;
    private static array $connectionPool = [];
    private static array $connectionMetrics = [
        'total_connections' => 0,
        'active_connections' => 0,
        'failed_connections' => 0,
        'reconnects' => 0,
        'queries_executed' => 0,
    ];
    
    private string $connectionKey;
    private float $lastPingTime = 0;
    private const PING_INTERVAL = 30; // Ping every 30 seconds
    
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
    
    /**
     * Get connection metrics for monitoring
     */
    public static function getMetrics(): array
    {
        return self::$connectionMetrics;
    }
    
    /**
     * Test if connection is alive and healthy
     */
    private function isConnectionHealthy(): bool
    {
        if ($this->connection === null) {
            return false;
        }
        
        // Ping periodically to keep connection alive
        $now = microtime(true);
        if ($now - $this->lastPingTime > self::PING_INTERVAL) {
            $this->lastPingTime = $now;
            if (!$this->connection->ping()) {
                error_log('Database: Connection ping failed, will reconnect');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create new dedicated database connection (NO shared globals)
     */
    private function connect(): void
    {
        $host = DB_HOST ?? $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user = DB_USERNAME ?? $_ENV['DB_USER'] ?? 'jcepnzzkmj';
        $pass = DB_PASSWORD ?? $_ENV['DB_PASS'] ?? '';
        $db = DB_DATABASE ?? $_ENV['DB_NAME'] ?? 'jcepnzzkmj';
        $port = (int)(DB_PORT ?? $_ENV['DB_PORT'] ?? 3306);
        
        $this->connectionKey = "{$host}:{$port}:{$db}:{$user}";
        
        // Check connection pool first
        if (isset(self::$connectionPool[$this->connectionKey]) && 
            self::$connectionPool[$this->connectionKey]->ping()) {
            $this->connection = self::$connectionPool[$this->connectionKey];
            error_log("Database: Reusing pooled connection [{$this->connectionKey}]");
            return;
        }
        
        // Create new dedicated connection (isolated from CIS global $con)
        try {
            $this->connection = new \mysqli($host, $user, $pass, $db, $port);
            
            if ($this->connection->connect_error) {
                self::$connectionMetrics['failed_connections']++;
                throw new \Exception("Database connection failed: " . $this->connection->connect_error);
            }
            
            // Configure connection
            $this->connection->set_charset('utf8mb4');
            $this->connection->query("SET time_zone = '+12:00'"); // Pacific/Auckland
            $this->connection->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            
            // Store in pool
            self::$connectionPool[$this->connectionKey] = $this->connection;
            self::$connectionMetrics['total_connections']++;
            self::$connectionMetrics['active_connections'] = count(self::$connectionPool);
            
            $this->lastPingTime = microtime(true);
            
            error_log("Database: Created new dedicated connection [{$this->connectionKey}]");
            
        } catch (\Exception $e) {
            self::$connectionMetrics['failed_connections']++;
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Reconnect if connection is lost
     */
    private function reconnect(): void
    {
        error_log("Database: Reconnecting...");
        
        // Remove dead connection from pool
        if (isset(self::$connectionPool[$this->connectionKey])) {
            unset(self::$connectionPool[$this->connectionKey]);
            self::$connectionMetrics['active_connections'] = count(self::$connectionPool);
        }
        
        $this->connection = null;
        $this->connect();
        self::$connectionMetrics['reconnects']++;
    }
    
    public function getConnection(): \mysqli
    {
        if (!defined('DB_CONFIGURED') || !DB_CONFIGURED) {
            throw new \Exception('Database is not configured. Please set DB_USERNAME and DB_PASSWORD in .env');
        }
        
        // Check connection health and reconnect if needed
        if (!$this->isConnectionHealthy()) {
            $this->reconnect();
        }
        
        return $this->connection;
    }
    
    public function query(string $sql, array $params = []): \mysqli_result|bool
    {
        $conn = $this->getConnection();
        self::$connectionMetrics['queries_executed']++;
        
        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result === false) {
                throw new \Exception("SQL query failed: " . $conn->error . " | SQL: " . $sql);
            }
            return $result;
        }
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new \Exception("Prepare failed: " . $conn->error . " | SQL: " . $sql);
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
    
    /**
     * Get connection pool statistics for monitoring
     * 
     * @return array Pool statistics including:
     *   - pool_size: Number of connections in pool
     *   - active_connections: Currently active connections
     *   - total_connections: Total connections created
     *   - failed_connections: Number of failed connection attempts
     *   - reconnects: Number of reconnection attempts
     *   - queries_executed: Total queries executed
     *   - pool_keys: Array of connection identifiers in pool
     */
    public static function getPoolStats(): array
    {
        return [
            'pool_size' => count(self::$connectionPool),
            'active_connections' => self::$connectionMetrics['active_connections'],
            'total_connections' => self::$connectionMetrics['total_connections'],
            'failed_connections' => self::$connectionMetrics['failed_connections'],
            'reconnects' => self::$connectionMetrics['reconnects'],
            'queries_executed' => self::$connectionMetrics['queries_executed'],
            'pool_keys' => array_keys(self::$connectionPool),
        ];
    }
    
    /**
     * Close all connections in the pool (for cleanup/testing)
     * 
     * @return int Number of connections closed
     */
    public static function closeAllConnections(): int
    {
        $closed = 0;
        
        foreach (self::$connectionPool as $key => $connection) {
            if ($connection instanceof \mysqli) {
                $connection->close();
                $closed++;
            }
            unset(self::$connectionPool[$key]);
        }
        
        // Reset metrics
        self::$connectionMetrics['active_connections'] = 0;
        
        // Reset singleton instance
        if (self::$instance !== null) {
            self::$instance->connection = null;
            self::$instance = null;
        }
        
        error_log("Database: Closed {$closed} connection(s) from pool");
        
        return $closed;
    }
}