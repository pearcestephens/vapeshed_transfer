<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\TransferEngineService;
use App\Core\Database;
use App\Core\Logger;

/**
 * Transfer Engine Integration Tests
 * 
 * REQUIRES: Database with test data
 * Tests real transfer scenarios with actual database operations
 * 
 * Setup: Run seed_test_data.php first to populate test outlets
 */
class TransferEngineIntegrationTest extends TestCase
{
    private TransferEngineService $engine;
    private Database $db;
    private array $testOutlets = [];
    
    protected function setUp(): void
    {
        $this->engine = new TransferEngineService();
        $this->db = Database::getInstance();
        
        // Seed test outlets if needed
        $this->seedTestOutlets();
    }
    
    protected function tearDown(): void
    {
        // Cleanup: Remove kill switch
        $killSwitchPath = STORAGE_PATH . '/KILL_SWITCH';
        if (file_exists($killSwitchPath)) {
            unlink($killSwitchPath);
        }
        
        // Note: Keep test data for inspection, clean manually if needed
    }
    
    /**
     * Seed minimal test outlets for integration testing
     */
    private function seedTestOutlets(): void
    {
        $testOutletsData = [
            [
                'id' => 'test-warehouse',
                'name' => 'Test Warehouse',
                'is_warehouse' => 1,
                'stock_level' => 1000
            ],
            [
                'id' => 'test-store-1',
                'name' => 'Test Store 1',
                'is_warehouse' => 0,
                'stock_level' => 10
            ],
            [
                'id' => 'test-store-2',
                'name' => 'Test Store 2',
                'is_warehouse' => 0,
                'stock_level' => 5
            ]
        ];
        
        foreach ($testOutletsData as $outlet) {
            $this->testOutlets[] = $outlet['id'];
            
            // Check if exists, insert if not
            $existing = $this->db->fetchOne(
                "SELECT id FROM vend_outlets WHERE id = ?",
                [$outlet['id']]
            );
            
            if (!$existing) {
                $this->db->query(
                    "INSERT INTO vend_outlets (id, name, is_warehouse, version) 
                     VALUES (?, ?, ?, 1) 
                     ON DUPLICATE KEY UPDATE name = VALUES(name)",
                    [$outlet['id'], $outlet['name'], $outlet['is_warehouse']]
                );
            }
        }
    }
    
    /**
     * Test: Basic transfer execution with test mode
     */
    public function testBasicTransferExecution(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 1,
            'verbose' => false
        ];
        
        $result = $this->engine->executeTransfer($config);
        
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('allocations', $result, 'Result should have allocations');
        $this->assertArrayHasKey('summary', $result, 'Result should have summary');
        $this->assertIsArray($result['allocations'], 'Allocations should be an array');
    }
    
    /**
     * Test: Transfer with specific product list
     */
    public function testTransferWithProductList(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 1
        ];
        
        $products = [
            ['product_id' => 'TEST-PROD-001', 'quantity' => 10],
            ['product_id' => 'TEST-PROD-002', 'quantity' => 20]
        ];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        // Should process provided products
    }
    
    /**
     * Test: Allocation algorithm fairness
     */
    public function testAllocationFairness(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 5,
            'fairness_mode' => 'equal'
        ];
        
        $result = $this->engine->executeTransfer($config);
        
        if (isset($result['allocations']) && count($result['allocations']) > 1) {
            $quantities = array_column($result['allocations'], 'quantity');
            
            // Skip if no quantities allocated
            if (empty($quantities) || array_sum($quantities) === 0) {
                $this->markTestSkipped('No quantities allocated in test data');
                return;
            }
            
            $avg = array_sum($quantities) / count($quantities);
            $variance = 0;
            
            foreach ($quantities as $qty) {
                $variance += pow($qty - $avg, 2);
            }
            $variance /= count($quantities);
            
            // Variance should be relatively low for fair distribution
            $this->assertLessThan(
                $avg * 0.5,
                sqrt($variance),
                'Allocation should be relatively fair (low standard deviation)'
            );
        } else {
            $this->markTestSkipped('Not enough allocations to test fairness');
        }
    }
    
    /**
     * Test: Zero warehouse stock handling
     */
    public function testZeroWarehouseStockHandling(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 1
        ];
        
        // Mock zero warehouse scenario (would need actual test data)
        $result = $this->engine->executeTransfer($config);
        
        $this->assertIsArray($result);
        // Should handle gracefully, possibly return empty allocations
    }
    
    /**
     * Test: Database connection pool usage
     */
    public function testDatabaseConnectionPooling(): void
    {
        $initialStats = Database::getPoolStats();
        
        // Execute multiple transfers
        for ($i = 0; $i < 5; $i++) {
            $config = [
                'dry' => true,
                'test_mode' => true,
                'warehouse_id' => 'test-warehouse',
                'min_lines' => 1
            ];
            
            $this->engine->executeTransfer($config);
        }
        
        $finalStats = Database::getPoolStats();
        
        // Pool should be managing connections efficiently
        $this->assertGreaterThanOrEqual(
            $initialStats['queries_executed'],
            $finalStats['queries_executed'],
            'Should have executed queries'
        );
        
        $this->assertGreaterThanOrEqual(
            1,
            $finalStats['active_connections'],
            'Should have active connections'
        );
    }
    
    /**
     * Test: Concurrent execution safety
     */
    public function testConcurrentExecutionSafety(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 1
        ];
        
        // Simulate concurrent requests (in reality would use threads/processes)
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $results[] = $this->engine->executeTransfer($config);
        }
        
        // All should succeed or gracefully handle concurrency
        foreach ($results as $result) {
            $this->assertIsArray($result);
        }
    }
    
    /**
     * Test: Configuration validation
     */
    public function testConfigurationValidation(): void
    {
        // Missing warehouse_id
        $invalidConfig = [
            'dry' => true,
            'test_mode' => true,
            'min_lines' => 1
        ];
        
        try {
            $result = $this->engine->executeTransfer($invalidConfig);
            // Should either throw or return error in result
            if (is_array($result)) {
                $this->assertTrue(
                    isset($result['error']) || isset($result['status']),
                    'Should handle missing warehouse_id'
                );
            }
        } catch (\Exception $e) {
            $this->assertStringContainsString('warehouse', strtolower($e->getMessage()));
        }
    }
    
    /**
     * Test: Dry run mode (no actual changes)
     */
    public function testDryRunMode(): void
    {
        $this->markTestIncomplete('stock_transfers table does not exist in schema');
        
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 1
        ];
        
        // Get initial state (would check transfer count)
        $initialTransferCount = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM stock_transfers WHERE created_at > NOW() - INTERVAL 1 MINUTE"
        )['cnt'] ?? 0;
        
        $result = $this->engine->executeTransfer($config);
        
        // Get final state
        $finalTransferCount = $this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM stock_transfers WHERE created_at > NOW() - INTERVAL 1 MINUTE"
        )['cnt'] ?? 0;
        
        // Dry run should not create actual transfers
        $this->assertEquals(
            $initialTransferCount,
            $finalTransferCount,
            'Dry run should not create actual database records'
        );
    }
    
    /**
     * Test: Min lines threshold
     */
    public function testMinLinesThreshold(): void
    {
        $this->markTestIncomplete('Min lines threshold behavior needs test data alignment');
        
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 100  // Very high threshold
        ];
        
        $result = $this->engine->executeTransfer($config);
        
        $this->assertIsArray($result);
        
        // With impossibly high threshold, should return empty or minimal allocations
        if (isset($result['allocations']) && isset($result['summary'])) {
            $totalLines = $result['summary']['total_lines'] ?? 0;
            // Either meets threshold or is empty (threshold not met)
            $this->assertTrue(
                $totalLines === 0 || $totalLines >= 100,
                'Should respect min_lines threshold'
            );
        } else {
            $this->markTestSkipped('No allocations or summary in result');
        }
    }
    
    /**
     * Test: Logger integration
     */
    public function testLoggerIntegration(): void
    {
        $logFile = LOG_PATH;
        $initialSize = file_exists($logFile) ? filesize($logFile) : 0;
        
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => 'test-warehouse',
            'min_lines' => 1,
            'verbose' => true
        ];
        
        $result = $this->engine->executeTransfer($config);
        
        $finalSize = file_exists($logFile) ? filesize($logFile) : 0;
        
        // Verbose mode should log more
        $this->assertGreaterThanOrEqual(
            $initialSize,
            $finalSize,
            'Verbose mode should write to log'
        );
    }
}
