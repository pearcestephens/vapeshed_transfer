<?php

/**
 * Vapeshed Transfer Engine - Database Connection Manager
 * Enterprise-grade database connection and management system
 * 
 * @author Ecigdis Ltd Development Team
 * @version 4.1
 * @package VapeshedTransfer\Database
 */

namespace VapeshedTransfer\Database;

use Exception;
use mysqli;
use mysqli_result;

class DatabaseManager
{
    private static $instance = null;
    private $connection;
    private $config;
    private $logger;
    
    /**
     * Singleton pattern - get database instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $this->loadConfiguration();
        $this->connect();
    }
    
    /**
     * Load database configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'jcepnzzkmj'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'timezone' => env('DB_TIMEZONE', '+00:00')
        ];
        
        // Validate required credentials
        if (empty($this->config['username']) || empty($this->config['password'])) {
            throw new Exception('Database credentials not configured');
        }
    }
    
    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            // Create connection
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database'],
                $this->config['port']
            );
            
            // Check connection
            if ($this->connection->connect_error) {
                throw new Exception('Connection failed: ' . $this->connection->connect_error);
            }
            
            // Set charset
            if (!$this->connection->set_charset($this->config['charset'])) {
                throw new Exception('Error setting charset: ' . $this->connection->error);
            }
            
            // Set timezone
            $this->connection->query("SET time_zone = '{$this->config['timezone']}'");
            
            // Enable strict mode
            $this->connection->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
        } catch (Exception $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get database connection
     */
    public function getConnection(): mysqli
    {
        // Check if connection is still alive
        if (!$this->connection->ping()) {
            $this->connect();
        }
        
        return $this->connection;
    }
    
    /**
     * Execute prepared statement
     */
    public function query(string $sql, array $params = [], string $types = '')
    {
        try {
            $stmt = $this->connection->prepare($sql);
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $this->connection->error);
            }
            
            // Bind parameters if provided
            if (!empty($params)) {
                // Auto-detect types if not provided
                if (empty($types)) {
                    $types = $this->detectParamTypes($params);
                }
                
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new Exception('Parameter binding failed: ' . $stmt->error);
                }
            }
            
            // Execute statement
            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $stmt->close();
            
            // Handle DDL queries that don't return result sets
            if ($result === false) {
                return null;
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute INSERT and return insert ID
     */
    public function insert(string $sql, array $params = [], string $types = ''): int
    {
        $this->query($sql, $params, $types);
        return $this->connection->insert_id;
    }
    
    /**
     * Execute UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = [], string $types = ''): int
    {
        $this->query($sql, $params, $types);
        return $this->connection->affected_rows;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->connection->autocommit(false);
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        $result = $this->connection->commit();
        $this->connection->autocommit(true);
        return $result;
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        $result = $this->connection->rollback();
        $this->connection->autocommit(true);
        return $result;
    }
    
    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        $result = $this->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$this->config['database'], $table],
            'ss'
        );
        
        return $result && $result->num_rows > 0;
    }
    
    /**
     * Get table columns
     */
    public function getTableColumns(string $table): array
    {
        $result = $this->query(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
             FROM information_schema.columns 
             WHERE table_schema = ? AND table_name = ?
             ORDER BY ORDINAL_POSITION",
            [$this->config['database'], $table],
            'ss'
        );
        
        $columns = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row;
            }
        }
        
        return $columns;
    }
    
    /**
     * Test database connectivity and required tables
     */
    public function testConnection(): array
    {
        $results = [
            'connection' => false,
            'tables' => [],
            'vend_integration' => false,
            'errors' => []
        ];
        
        try {
            // Test basic connection
            $this->connection->ping();
            $results['connection'] = true;
            
            // Check core transfer engine tables
            $requiredTables = [
                'transfer_configurations',
                'transfer_executions', 
                'transfer_allocations',
                'system_audit_log'
            ];
            
            foreach ($requiredTables as $table) {
                $exists = $this->tableExists($table);
                $results['tables'][$table] = $exists;
                if (!$exists) {
                    $results['errors'][] = "Missing core table: {$table}";
                }
            }
            
            // Check Vend integration tables
            $vendTables = [
                'webhook_subscriptions',
                'webhook_health',
                'webhook_events', 
                'webhook_stats'
            ];
            
            $vendTablesExist = 0;
            foreach ($vendTables as $table) {
                $exists = $this->tableExists($table);
                $results['tables'][$table] = $exists;
                if ($exists) {
                    $vendTablesExist++;
                }
            }
            
            $results['vend_integration'] = ($vendTablesExist === count($vendTables));
            
            if (!$results['vend_integration']) {
                $results['errors'][] = 'Vend integration tables missing or incomplete';
            }
            
            // Test basic query execution (use safe alias)
            $this->query("SELECT NOW() AS now_ts");
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Auto-detect parameter types for prepared statements
     */
    private function detectParamTypes(array $params): string
    {
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
        return $types;
    }
    
    /**
     * Escape string for safe SQL construction
     */
    public function escapeString(string $string): string
    {
        return $this->connection->real_escape_string($string);
    }
    
    /**
     * Get connection statistics
     */
    public function getStats(): array
    {
        return [
            'host_info' => $this->connection->host_info,
            'server_info' => $this->connection->server_info,
            'client_info' => $this->connection->client_info,
            'protocol_version' => $this->connection->protocol_version,
            'connection_id' => $this->connection->thread_id,
            'charset' => $this->connection->character_set_name()
        ];
    }
    
    /**
     * Close connection
     */
    public function close(): void
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->close();
    }
}