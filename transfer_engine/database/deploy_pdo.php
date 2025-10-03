<?php
/**
 * Database Deployment Script using PDO
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Deploy database schema using CIS PDO connection
 */

require_once __DIR__ . '/../app/Core/PDODatabase.php';

use App\Core\PDODatabase;

class DatabaseDeployer
{
    private PDODatabase $db;
    private array $deployLog = [];
    
    public function __construct()
    {
        $this->db = PDODatabase::getInstance();
    }
    
    /**
     * Deploy the complete database schema
     */
    public function deploy(): bool
    {
        $this->log("=== Database Deployment Started ===");
        
        try {
            // Check database connection
            if (!$this->db->isConnected()) {
                throw new \Exception("Database connection failed");
            }
            
            $this->log("✅ Database connection successful");
            $this->log("📊 Database version: " . $this->db->getVersion());
            
            // Deploy schema
            $schemaFile = __DIR__ . '/../database/schema.sql';
            if (file_exists($schemaFile)) {
                $this->log("🚀 Deploying schema from: " . basename($schemaFile));
                $this->db->executeSQLFile($schemaFile);
                $this->log("✅ Schema deployment completed");
            } else {
                $this->log("⚠️  Schema file not found: " . $schemaFile);
            }
            
            // Run migrations if they exist
            $this->runMigrations();
            
            // Seed initial data
            $this->seedInitialData();
            
            $this->log("🎉 Database deployment completed successfully!");
            return true;
            
        } catch (\Exception $e) {
            $this->log("❌ Database deployment failed: " . $e->getMessage());
            error_log("Database deployment error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        $migrationsDir = __DIR__ . '/../database/migrations';
        
        if (!is_dir($migrationsDir)) {
            $this->log("📁 No migrations directory found");
            return;
        }
        
        // Create migrations table if it doesn't exist
        $this->db->query("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_migration (migration)
            ) ENGINE=InnoDB
        ");
        
        // Get executed migrations
        $executed = $this->db->fetchAll("SELECT migration FROM migrations");
        $executedMigrations = array_column($executed, 'migration');
        
        // Find migration files
        $migrationFiles = glob($migrationsDir . '/*.sql');
        sort($migrationFiles);
        
        foreach ($migrationFiles as $file) {
            $migrationName = basename($file);
            
            if (in_array($migrationName, $executedMigrations)) {
                $this->log("⏭️  Skipping migration: " . $migrationName . " (already executed)");
                continue;
            }
            
            try {
                $this->log("🔄 Running migration: " . $migrationName);
                $this->db->executeSQLFile($file);
                
                // Record migration
                $this->db->query("INSERT INTO migrations (migration) VALUES (?)", [$migrationName]);
                
                $this->log("✅ Migration completed: " . $migrationName);
                
            } catch (\Exception $e) {
                $this->log("❌ Migration failed: " . $migrationName . " - " . $e->getMessage());
                throw $e;
            }
        }
    }
    
    /**
     * Seed initial data
     */
    private function seedInitialData(): void
    {
        $this->log("🌱 Seeding initial data...");
        
        try {
            // Check if we already have data
            $existingData = $this->db->fetchValue("
                SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'transfer_configurations'
            ");
            
            if ($existingData) {
                $configCount = $this->db->fetchValue("SELECT COUNT(*) FROM transfer_configurations");
                if ($configCount > 0) {
                    $this->log("📊 Initial data already exists ({$configCount} configurations)");
                    return;
                }
            }
            
            // Seed initial transfer configurations
            $this->db->query("
                INSERT IGNORE INTO transfer_configurations (name, source_type, target_type, settings, is_active) VALUES
                ('Default Stock Transfer', 'vend', 'vend', '{\"batch_size\": 50, \"delay_ms\": 100}', 1),
                ('Inventory Sync', 'api', 'database', '{\"sync_interval\": 3600}', 1),
                ('Price Update', 'csv', 'vend', '{\"price_column\": \"retail_price\"}', 0)
            ");
            
            // Seed initial users if user table exists
            $userTableExists = $this->db->fetchValue("
                SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'users'
            ");
            
            if ($userTableExists) {
                $adminExists = $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE username = 'admin'");
                
                if (!$adminExists) {
                    $this->db->query("
                        INSERT INTO users (username, email, password_hash, role, is_active) VALUES
                        ('admin', 'admin@vapeshed.co.nz', ?, 'admin', 1)
                    ", [password_hash('admin123', PASSWORD_DEFAULT)]);
                    
                    $this->log("👤 Created admin user (username: admin, password: admin123)");
                }
            }
            
            $this->log("✅ Initial data seeding completed");
            
        } catch (\Exception $e) {
            $this->log("⚠️  Seeding warning: " . $e->getMessage());
        }
    }
    
    /**
     * Test database connectivity and basic operations
     */
    public function testConnection(): array
    {
        $results = [
            'connected' => false,
            'version' => '',
            'tables' => 0,
            'test_query' => false,
            'errors' => []
        ];
        
        try {
            // Test connection
            $results['connected'] = $this->db->isConnected();
            
            if ($results['connected']) {
                // Get version
                $results['version'] = $this->db->getVersion();
                
                // Count tables
                $tables = $this->db->fetchAll("
                    SELECT COUNT(*) as count 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()
                ");
                $results['tables'] = (int)$tables[0]['count'];
                
                // Test a simple query
                $testResult = $this->db->fetchValue("SELECT 1 + 1 as test");
                $results['test_query'] = ($testResult == 2);
            }
            
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Log deployment progress
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";
        
        $this->deployLog[] = $logEntry;
        echo $logEntry . "\n";
        
        // Also log to file
        $logFile = __DIR__ . '/../storage/logs/deployment.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logEntry . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get deployment log
     */
    public function getLog(): array
    {
        return $this->deployLog;
    }
}

// If called directly, run deployment
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $deployer = new DatabaseDeployer();
    
    echo "🚀 Starting Database Deployment...\n\n";
    
    // Test connection first
    echo "Testing database connection...\n";
    $testResults = $deployer->testConnection();
    
    if (!$testResults['connected']) {
        echo "❌ Database connection failed!\n";
        if (!empty($testResults['errors'])) {
            foreach ($testResults['errors'] as $error) {
                echo "   Error: {$error}\n";
            }
        }
        exit(1);
    }
    
    echo "✅ Connection successful!\n";
    echo "📊 Database version: {$testResults['version']}\n";
    echo "📋 Existing tables: {$testResults['tables']}\n\n";
    
    // Deploy database
    $success = $deployer->deploy();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if ($success) {
        echo "🎉 DATABASE DEPLOYMENT COMPLETED SUCCESSFULLY! 🎉\n";
        exit(0);
    } else {
        echo "❌ DATABASE DEPLOYMENT FAILED!\n";
        echo "Check logs for details.\n";
        exit(1);
    }
}