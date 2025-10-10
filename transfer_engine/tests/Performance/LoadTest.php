<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Services\TransferEngineService;
use App\Core\Database;

/**
 * Transfer Engine Performance & Load Tests
 * 
 * Tests system behavior under load:
 * - Concurrent requests
 * - Memory usage
 * - Response times
 * - Connection pool efficiency
 */
class LoadTest extends TestCase
{
    private TransferEngineService $engine;
    private array $performanceMetrics = [];
    
    protected function setUp(): void
    {
        $this->engine = new TransferEngineService();
        $this->performanceMetrics = [
            'start_memory' => memory_get_usage(true),
            'start_time' => microtime(true)
        ];
    }
    
    protected function tearDown(): void
    {
        $this->performanceMetrics['end_memory'] = memory_get_usage(true);
        $this->performanceMetrics['end_time'] = microtime(true);
        $this->performanceMetrics['peak_memory'] = memory_get_peak_usage(true);
        
        // Log performance metrics
        $duration = $this->performanceMetrics['end_time'] - $this->performanceMetrics['start_time'];
        $memoryUsed = $this->performanceMetrics['end_memory'] - $this->performanceMetrics['start_memory'];
        
        echo "\n[PERF] Duration: " . round($duration * 1000, 2) . "ms | ";
        echo "Memory: " . round($memoryUsed / 1024 / 1024, 2) . "MB | ";
        echo "Peak: " . round($this->performanceMetrics['peak_memory'] / 1024 / 1024, 2) . "MB\n";
    }
    
    /**
     * Test: Single request performance baseline
     */
    public function testSingleRequestPerformance(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1
        ];
        
        $startTime = microtime(true);
        $result = $this->engine->executeTransfer($config);
        $duration = (microtime(true) - $startTime) * 1000;
        
        $this->assertIsArray($result);
        $this->assertLessThan(
            1000,
            $duration,
            'Single request should complete within 1 second'
        );
        
        echo "\n[BASELINE] Single request: {$duration}ms\n";
    }
    
    /**
     * Test: Sequential requests (no concurrency)
     */
    public function testSequentialRequests(): void
    {
        $iterations = 10;
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1
        ];
        
        $durations = [];
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $iterStart = microtime(true);
            $result = $this->engine->executeTransfer($config);
            $durations[] = (microtime(true) - $iterStart) * 1000;
            
            $this->assertIsArray($result, "Iteration {$i} failed");
        }
        
        $totalDuration = (microtime(true) - $startTime) * 1000;
        $avgDuration = array_sum($durations) / count($durations);
        $maxDuration = max($durations);
        
        echo "\n[SEQUENTIAL] {$iterations} requests:\n";
        echo "  Total: {$totalDuration}ms\n";
        echo "  Avg: {$avgDuration}ms\n";
        echo "  Max: {$maxDuration}ms\n";
        
        $this->assertLessThan(
            10000,
            $totalDuration,
            '10 sequential requests should complete within 10 seconds'
        );
    }
    
    /**
     * Test: Memory leak detection
     */
    public function testMemoryLeakDetection(): void
    {
        $iterations = 20;
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1
        ];
        
        $memorySnapshots = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->engine->executeTransfer($config);
            $memorySnapshots[] = memory_get_usage(true);
            
            // Force garbage collection
            if ($i % 5 === 0) {
                gc_collect_cycles();
            }
        }
        
        // Check memory trend
        $firstHalf = array_slice($memorySnapshots, 0, $iterations / 2);
        $secondHalf = array_slice($memorySnapshots, $iterations / 2);
        
        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);
        
        $memoryGrowth = $secondAvg - $firstAvg;
        $memoryGrowthPercent = ($memoryGrowth / $firstAvg) * 100;
        
        echo "\n[MEMORY] Growth: " . round($memoryGrowth / 1024 / 1024, 2) . "MB ({$memoryGrowthPercent}%)\n";
        
        $this->assertLessThan(
            50,
            $memoryGrowthPercent,
            'Memory should not grow more than 50% over 20 iterations (possible leak)'
        );
    }
    
    /**
     * Test: Connection pool under load
     */
    public function testConnectionPoolUnderLoad(): void
    {
        $db = Database::getInstance();
        $initialStats = Database::getPoolStats();
        
        $iterations = 15;
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1
        ];
        
        for ($i = 0; $i < $iterations; $i++) {
            $this->engine->executeTransfer($config);
        }
        
        $finalStats = Database::getPoolStats();
        
        echo "\n[POOL STATS]\n";
        echo "  Initial connections: {$initialStats['active_connections']}\n";
        echo "  Final connections: {$finalStats['active_connections']}\n";
        echo "  Total created: {$finalStats['total_connections']}\n";
        echo "  Reused: " . ($iterations - ($finalStats['total_connections'] - $initialStats['total_connections'])) . "\n";
        
        // Pool should reuse connections
        $newConnections = $finalStats['total_connections'] - $initialStats['total_connections'];
        
        $this->assertGreaterThanOrEqual(
            1,
            $finalStats['active_connections'],
            "Should have at least 1 active connection"
        );
        
        $this->assertLessThanOrEqual(
            3, // Should reuse connections, not create many new ones
            $newConnections,
            "Should efficiently reuse connections from pool"
        );
    }
    
    /**
     * Test: Concurrent simulation (rapid sequential)
     */
    public function testRapidSequentialExecution(): void
    {
        $iterations = 25;
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1
        ];
        
        $startTime = microtime(true);
        $errors = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $result = $this->engine->executeTransfer($config);
                if (!is_array($result)) {
                    $errors++;
                }
            } catch (\Exception $e) {
                $errors++;
            }
        }
        
        $duration = (microtime(true) - $startTime) * 1000;
        $throughput = ($iterations / $duration) * 1000;
        
        echo "\n[RAPID] {$iterations} requests in {$duration}ms\n";
        echo "  Throughput: " . round($throughput, 2) . " req/sec\n";
        echo "  Errors: {$errors}\n";
        
        $this->assertEquals(0, $errors, 'No errors should occur during rapid execution');
        $this->assertGreaterThan(5, $throughput, 'Should handle at least 5 requests/sec');
    }
    
    /**
     * Test: Large result set handling
     */
    public function testLargeResultSetHandling(): void
    {
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1,
            'max_per_product' => 100  // Large allocation
        ];
        
        $startMemory = memory_get_usage(true);
        $startTime = microtime(true);
        
        $result = $this->engine->executeTransfer($config);
        
        $duration = (microtime(true) - $startTime) * 1000;
        $memoryUsed = memory_get_usage(true) - $startMemory;
        
        echo "\n[LARGE SET]\n";
        echo "  Duration: {$duration}ms\n";
        echo "  Memory: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
        
        if (isset($result['allocations'])) {
            echo "  Allocations: " . count($result['allocations']) . "\n";
        }
        
        $this->assertLessThan(
            50 * 1024 * 1024, // 50MB
            $memoryUsed,
            'Large result set should not use excessive memory'
        );
    }
    
    /**
     * Test: Response time consistency
     */
    public function testResponseTimeConsistency(): void
    {
        $iterations = 15;
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => WAREHOUSE_ID,
            'min_lines' => 1
        ];
        
        $durations = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $this->engine->executeTransfer($config);
            $durations[] = (microtime(true) - $start) * 1000;
        }
        
        $avg = array_sum($durations) / count($durations);
        $variance = 0;
        foreach ($durations as $duration) {
            $variance += pow($duration - $avg, 2);
        }
        $variance /= count($durations);
        $stdDev = sqrt($variance);
        
        $coefficientOfVariation = ($stdDev / $avg) * 100;
        
        echo "\n[CONSISTENCY]\n";
        echo "  Avg: " . round($avg, 2) . "ms\n";
        echo "  StdDev: " . round($stdDev, 2) . "ms\n";
        echo "  CV: " . round($coefficientOfVariation, 2) . "%\n";
        
        $this->assertLessThan(
            50,
            $coefficientOfVariation,
            'Response times should be consistent (CV < 50%)'
        );
    }
}
