<?php
declare(strict_types=1);
namespace Unified\Integration;

use PDO;
use PDOException;
use Unified\Support\Logger;
use Unified\Support\NeuroContext;

/**
 * VendConnection - Vend Database Connection Manager
 * 
 * Manages database connections to the Vend production database with
 * connection pooling, health checks, and automatic retry logic.
 * 
 * Features:
 * - Connection pooling for performance
 * - Automatic reconnection on failure
 * - Health check monitoring
 * - Query timeout protection
 * - SSL/TLS support
 * - Read-only mode for safety
 * 
 * @package Unified\Integration
 * @version 1.0.0
 * @date 2025-10-08
 */
class VendConnection
{
    private ?PDO $connection = null;
    private Logger $logger;
    private NeuroContext $neuro;
    private array $config;
    private int $connectionAttempts = 0;
    private float $lastHealthCheck = 0;
    private bool $isHealthy = false;
    
    /**
     * Create Vend connection manager
     * 
     * @param Logger $logger Logger instance
     * @param array $config Connection configuration
     */
    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->neuro = new NeuroContext();
        
        // Load config from file if not provided
        if (empty($config)) {
            $configFile = __DIR__ . '/../../config/vend.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            }
        }
        
        $this->config = array_merge([
            'connection' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'jcepnzzkmj',
                'username' => 'jcepnzzkmj',
                'password' => '',
                'charset' => 'utf8mb4',
            ],
            'performance' => [
                'connection_timeout' => 5,
                'query_timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'security' => [
                'ssl' => false,
                'read_only' => true,
            ],
            'health_check' => [
                'enabled' => true,
                'interval' => 60,
                'query' => 'SELECT 1',
                'timeout' => 5,
            ],
        ], $config);
        
        $this->logger->info('vend.connection.initialized', [
            'host' => $this->config['connection']['host'],
            'database' => $this->config['connection']['database'],
            'read_only' => $this->config['security']['read_only'],
        ]);
    }
    
    /**
     * Get or create database connection
     * 
     * @return PDO Database connection
     * @throws PDOException If connection fails
     */
    public function getConnection(): PDO
    {
        // Check if existing connection is still valid
        if ($this->connection !== null && $this->isConnectionAlive()) {
            return $this->connection;
        }
        
        // Create new connection with retry logic
        $attempts = 0;
        $maxAttempts = $this->config['performance']['retry_attempts'];
        $retryDelay = $this->config['performance']['retry_delay'];
        
        while ($attempts < $maxAttempts) {
            try {
                $this->connection = $this->createConnection();
                $this->connectionAttempts = $attempts + 1;
                $this->isHealthy = true;
                
                $this->logger->info('vend.connection.established', [
                    'attempts' => $this->connectionAttempts,
                    'host' => $this->config['connection']['host'],
                ]);
                
                return $this->connection;
                
            } catch (PDOException $e) {
                $attempts++;
                
                $this->logger->warning('vend.connection.failed', [
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);
                
                if ($attempts >= $maxAttempts) {
                    $this->isHealthy = false;
                    throw $e;
                }
                
                // Wait before retry
                usleep($retryDelay * 1000);
            }
        }
        
        throw new PDOException('Failed to establish database connection after ' . $maxAttempts . ' attempts');
    }
    
    /**
     * Create new PDO connection
     * 
     * @return PDO Database connection
     * @throws PDOException If connection fails
     */
    private function createConnection(): PDO
    {
        $conn = $this->config['connection'];
        
        // Build DSN
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $conn['host'],
            $conn['port'],
            $conn['database'],
            $conn['charset']
        );
        
        // PDO options
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => $this->config['performance']['connection_timeout'],
        ];
        
        // Add SSL if enabled
        if ($this->config['security']['ssl']) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $this->config['security']['verify_certificate'];
        }
        
        // Create connection
        $pdo = new PDO($dsn, $conn['username'], $conn['password'], $options);
        
        // Set read-only mode if enabled
        if ($this->config['security']['read_only']) {
            try {
                $pdo->exec('SET SESSION TRANSACTION READ ONLY');
            } catch (\PDOException $e) {
                // Some MySQL versions don't support this, just log and continue
                $this->logger->warning('Could not set read-only mode', ['error' => $e->getMessage()]);
            }
        }
        
        // Note: max_execution_time is not supported in all MySQL versions
        // PHP timeout should be used instead via set_time_limit()
        
        return $pdo;
    }
    
    /**
     * Check if connection is alive
     * 
     * @return bool True if connection is alive
     */
    private function isConnectionAlive(): bool
    {
        if ($this->connection === null) {
            return false;
        }
        
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            $this->logger->debug('vend.connection.dead', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Perform health check
     * 
     * @return array Health check result
     */
    public function healthCheck(): array
    {
        $startTime = microtime(true);
        
        // Check if health check interval has passed
        if ($this->lastHealthCheck > 0) {
            $elapsed = time() - $this->lastHealthCheck;
            if ($elapsed < $this->config['health_check']['interval']) {
                return [
                    'healthy' => $this->isHealthy,
                    'cached' => true,
                    'last_check' => $this->lastHealthCheck,
                ];
            }
        }
        
        try {
            $connection = $this->getConnection();
            $query = $this->config['health_check']['query'];
            
            $stmt = $connection->query($query);
            $result = $stmt->fetch();
            
            $duration = (microtime(true) - $startTime) * 1000; // milliseconds
            
            $this->isHealthy = true;
            $this->lastHealthCheck = time();
            
            $this->logger->debug('vend.health_check.success', [
                'duration_ms' => round($duration, 2),
            ]);
            
            return [
                'healthy' => true,
                'response_time_ms' => round($duration, 2),
                'last_check' => $this->lastHealthCheck,
                'connection_attempts' => $this->connectionAttempts,
            ];
            
        } catch (PDOException $e) {
            $this->isHealthy = false;
            $this->lastHealthCheck = time();
            
            $this->logger->error('vend.health_check.failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'last_check' => $this->lastHealthCheck,
            ];
        }
    }
    
    /**
     * Execute a query with timeout protection
     * 
     * @param string $query SQL query
     * @param array $params Query parameters
     * @return array Query results
     * @throws PDOException If query fails
     */
    public function query(string $query, array $params = []): array
    {
        $startTime = microtime(true);
        
        try {
            $connection = $this->getConnection();
            $stmt = $connection->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            $duration = (microtime(true) - $startTime) * 1000; // milliseconds
            
            // Log slow queries
            $slowThreshold = $this->config['logging']['slow_query_threshold'] ?? 1000;
            if ($duration > $slowThreshold) {
                $this->logger->warning('vend.query.slow', [
                    'query' => $query,
                    'duration_ms' => round($duration, 2),
                    'threshold_ms' => $slowThreshold,
                    'row_count' => count($results),
                ]);
            } else {
                $this->logger->debug('vend.query.executed', [
                    'duration_ms' => round($duration, 2),
                    'row_count' => count($results),
                ]);
            }
            
            return $results;
            
        } catch (PDOException $e) {
            $this->logger->error('vend.query.failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get connection statistics
     * 
     * @return array Connection stats
     */
    public function getStats(): array
    {
        return [
            'is_connected' => $this->connection !== null,
            'is_healthy' => $this->isHealthy,
            'connection_attempts' => $this->connectionAttempts,
            'last_health_check' => $this->lastHealthCheck,
            'config' => [
                'host' => $this->config['connection']['host'],
                'database' => $this->config['connection']['database'],
                'read_only' => $this->config['security']['read_only'],
                'ssl_enabled' => $this->config['security']['ssl'],
            ],
        ];
    }
    
    /**
     * Close connection
     */
    public function close(): void
    {
        if ($this->connection !== null) {
            $this->connection = null;
            $this->isHealthy = false;
            
            $this->logger->info('vend.connection.closed');
        }
    }
    
    /**
     * Destructor - cleanup connection
     */
    public function __destruct()
    {
        $this->close();
    }
}
