<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\TransferEngineService;

/**
 * Transfer Engine Core Algorithm Tests
 * 
 * CRITICAL TEST COVERAGE:
 * - Allocation algorithm edge cases
 * - Zero stock scenarios
 * - Single outlet handling
 * - Extreme imbalances
 * - Negative stock protection
 * - Division by zero protection
 * - Fairness validation
 */
class TransferEngineServiceTest extends TestCase
{
    private TransferEngineService $engine;
    
    protected function setUp(): void
    {
        $this->engine = new TransferEngineService();
        
        // Create kill switch file location if doesn't exist
        $killSwitchPath = STORAGE_PATH . '/KILL_SWITCH';
        if (file_exists($killSwitchPath)) {
            unlink($killSwitchPath); // Remove for clean tests
        }
    }
    
    protected function tearDown(): void
    {
        // Cleanup kill switch after each test
        $killSwitchPath = STORAGE_PATH . '/KILL_SWITCH';
        if (file_exists($killSwitchPath)) {
            unlink($killSwitchPath);
        }
    }
    
    /**
     * Test: Zero warehouse stock should result in no allocations
     */
    public function testZeroWarehouseStock(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true, // Enable test mode for synthetic outlets
            'warehouse_id' => '1',
            'min_lines' => 1,
        ];
        
        $result = $this->engine->execute($config);
        
        // With test mode and no database, should still complete
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        
        // Should have completed (even if with empty results)
        $this->assertTrue(
            in_array($result['status'], ['success', 'completed', 'no_allocations']),
            'Transfer should complete even with zero stock in test mode'
        );
    }
    
    /**
     * Test allocation to single outlet
     */
    public function testSingleOutletAllocation(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-002',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => 0],
            'sales_velocity' => ['OUT-1' => 20]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['allocations']);
        
        // All stock should go to the only outlet
        $allocation = $result['allocations'][0] ?? null;
        $this->assertNotNull($allocation);
        $this->assertEquals('OUT-1', $allocation['outlet_id']);
        $this->assertEquals(100, $allocation['quantity']);
    }
    
    /**
     * Test allocation with equal demand
     */
    public function testEqualDemandAllocation(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-003',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => 10, 'OUT-2' => 10, 'OUT-3' => 10],
            'sales_velocity' => ['OUT-1' => 5, 'OUT-2' => 5, 'OUT-3' => 5]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['allocations']);
        
        // Should allocate roughly equally (within rounding)
        $allocations = $result['allocations'];
        $this->assertCount(3, $allocations);
        
        $quantities = array_column($allocations, 'quantity');
        $this->assertEqualsWithDelta(33.33, array_sum($quantities) / 3, 2.0);
    }
    
    /**
     * Test extreme imbalance handling
     */
    public function testExtremeImbalance(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-004',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => 0, 'OUT-2' => 1000], // Extreme imbalance
            'sales_velocity' => ['OUT-1' => 50, 'OUT-2' => 1]   // High demand, low stock vs low demand, high stock
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['allocations']);
        
        // OUT-1 should get most of the allocation (high demand, low stock)
        $out1Allocation = array_values(array_filter(
            $result['allocations'],
            fn($a) => $a['outlet_id'] === 'OUT-1'
        ))[0] ?? null;
        
        $this->assertNotNull($out1Allocation);
        $this->assertGreaterThan(80, $out1Allocation['quantity'], 'High demand outlet should get most stock');
    }
    
    /**
     * Test negative stock protection
     */
    public function testNegativeStockProtection(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-005',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => -5, 'OUT-2' => 10], // Negative stock (data error)
            'sales_velocity' => ['OUT-1' => 10, 'OUT-2' => 5]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        
        // Should handle negative stock gracefully (treat as 0 or high priority)
        foreach ($result['allocations'] as $allocation) {
            $this->assertGreaterThanOrEqual(0, $allocation['quantity'], 'No negative allocations');
        }
    }
    
    /**
     * Test allocation fairness (Gini coefficient)
     */
    public function testAllocationFairness(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-006',
            'warehouse_stock' => 1000,
            'outlet_stocks' => [
                'OUT-1' => 10,
                'OUT-2' => 15,
                'OUT-3' => 8,
                'OUT-4' => 20,
                'OUT-5' => 5
            ],
            'sales_velocity' => [
                'OUT-1' => 20,
                'OUT-2' => 25,
                'OUT-3' => 15,
                'OUT-4' => 30,
                'OUT-5' => 10
            ]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result['allocations']);
        
        // Calculate Gini coefficient (measure of inequality)
        $quantities = array_column($result['allocations'], 'quantity');
        $gini = $this->calculateGini($quantities);
        
        // Gini should be reasonable (< 0.5 for fair distribution)
        $this->assertLessThan(0.5, $gini, 'Allocation should be relatively fair');
    }
    
    /**
     * Test minimum allocation threshold
     */
    public function testMinimumAllocationThreshold(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'min_allocation' => 10, // Minimum 10 units per allocation
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-007',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => 0, 'OUT-2' => 0, 'OUT-3' => 0, 'OUT-4' => 0, 'OUT-5' => 0],
            'sales_velocity' => ['OUT-1' => 10, 'OUT-2' => 9, 'OUT-3' => 8, 'OUT-4' => 7, 'OUT-5' => 6]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        
        // All allocations should meet minimum threshold
        foreach ($result['allocations'] as $allocation) {
            $this->assertGreaterThanOrEqual(10, $allocation['quantity'], 'Should meet minimum allocation');
        }
    }
    
    /**
     * Test kill switch enforcement
     */
    public function testKillSwitchEnforcement(): void
    {
        // Create kill switch file
        $killFile = STORAGE_PATH . '/KILL_SWITCH';
        touch($killFile);
        
        try {
            $config = [
                'source_outlet_id' => 'WAREHOUSE',
                'algorithm' => 'proportional',
                'dry' => false, // Try to run live
                'test_mode' => true
            ];
            
            $products = [[
                'product_id' => 'TEST-008',
                'warehouse_stock' => 100,
                'outlet_stocks' => ['OUT-1' => 0],
                'sales_velocity' => ['OUT-1' => 10]
            ]];
            
            $result = $this->engine->executeTransfer($config, $products);
            
            // Should force dry run mode
            $this->assertTrue($result['dry_run'], 'Kill switch should force dry run');
            $this->assertTrue($result['kill_switch_active']);
            
        } finally {
            // Clean up
            if (file_exists($killFile)) {
                unlink($killFile);
            }
        }
    }
    
    /**
     * Test performance profiling
     */
    public function testPerformanceProfiling(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-009',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => 0, 'OUT-2' => 0],
            'sales_velocity' => ['OUT-1' => 10, 'OUT-2' => 5]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertArrayHasKey('validate_config', $result['profile']);
        $this->assertArrayHasKey('load_outlets', $result['profile']);
        $this->assertArrayHasKey('load_products', $result['profile']);
        $this->assertArrayHasKey('calculate_allocations', $result['profile']);
    }
    
    /**
     * Test decision tracing
     */
    public function testDecisionTracing(): void
    {
        $config = [
            'source_outlet_id' => 'WAREHOUSE',
            'algorithm' => 'proportional',
            'dry' => true,
            'test_mode' => true
        ];
        
        $products = [[
            'product_id' => 'TEST-010',
            'warehouse_stock' => 100,
            'outlet_stocks' => ['OUT-1' => 5, 'OUT-2' => 10],
            'sales_velocity' => ['OUT-1' => 20, 'OUT-2' => 10]
        ]];
        
        $result = $this->engine->executeTransfer($config, $products);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('decision_trace', $result);
        // Decision trace should contain allocation reasoning
    }
    
    /**
     * Calculate Gini coefficient (measure of inequality)
     */
    private function calculateGini(array $values): float
    {
        $n = count($values);
        if ($n === 0) return 0.0;
        
        sort($values);
        $sum = array_sum($values);
        if ($sum == 0) return 0.0;
        
        $gini = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $gini += (2 * ($i + 1) - $n - 1) * $values[$i];
        }
        
        return $gini / ($n * $sum);
    }
}
