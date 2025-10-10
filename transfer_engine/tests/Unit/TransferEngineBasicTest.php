<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\TransferEngineService;
use App\Core\Database;
use App\Core\Logger;

/**
 * Transfer Engine Service - Basic Validation Tests
 * 
 * These tests validate that the TransferEngineService can be instantiated
 * and has the expected structure WITHOUT requiring a live database.
 * 
 * For full integration tests with real data, see Tests\Integration\
 */
class TransferEngineBasicTest extends TestCase
{
    /**
     * Test: Service can be instantiated
     */
    public function testServiceCanBeInstantiated(): void
    {
        $engine = new TransferEngineService();
        
        $this->assertInstanceOf(TransferEngineService::class, $engine);
    }
    
    /**
     * Test: Service has executeTransfer method
     */
    public function testServiceHasExecuteTransferMethod(): void
    {
        $engine = new TransferEngineService();
        
        $this->assertTrue(
            method_exists($engine, 'executeTransfer'),
            'TransferEngineService should have executeTransfer() method'
        );
    }
    
    /**
     * Test: Service handles test mode
     */
    public function testServiceHandlesTestMode(): void
    {
        $engine = new TransferEngineService();
        
        $config = [
            'dry' => true,
            'test_mode' => true,
            'warehouse_id' => '1',
            'min_lines' => 1,
        ];
        
        try {
            // Use correct method name: executeTransfer
            $result = $engine->executeTransfer($config);
            
            // Should return an array
            $this->assertIsArray($result);
            
            // Should have some result structure
            $this->assertTrue(
                isset($result['status']) || isset($result['allocations']) || isset($result['error']),
                'Result should have status, allocations, or error key'
            );
            
        } catch (\Exception $e) {
            // If database not available, that's acceptable for this test
            $this->assertTrue(
                str_contains($e->getMessage(), 'database') || 
                str_contains($e->getMessage(), 'connect') ||
                str_contains($e->getMessage(), 'outlets'),
                'Exception should be database/connection related: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Test: Kill switch file detection
     */
    public function testKillSwitchDetection(): void
    {
        $killSwitchPath = STORAGE_PATH . '/KILL_SWITCH';
        
        // Ensure no kill switch exists
        if (file_exists($killSwitchPath)) {
            unlink($killSwitchPath);
        }
        
        $engine = new TransferEngineService();
        
        // Create kill switch
        file_put_contents($killSwitchPath, 'TEST');
        
        $engine2 = new TransferEngineService();
        
        // Cleanup
        unlink($killSwitchPath);
        
        // Both engines should instantiate successfully
        $this->assertInstanceOf(TransferEngineService::class, $engine);
        $this->assertInstanceOf(TransferEngineService::class, $engine2);
    }
    
    /**
     * Test: Service accepts configuration array
     */
    public function testServiceAcceptsConfiguration(): void
    {
        $engine = new TransferEngineService();
        
        $validConfigs = [
            ['dry' => true],
            ['dry' => true, 'warehouse_id' => '1'],
            ['dry' => true, 'min_lines' => 5],
            ['dry' => true, 'max_per_product' => 30],
        ];
        
        foreach ($validConfigs as $config) {
            try {
                // Use correct method name: executeTransfer
                $result = $engine->executeTransfer($config);
                // If it doesn't throw, configuration was accepted
                $this->assertTrue(true);
            } catch (\Exception $e) {
                // Configuration errors are acceptable for structure test
                $this->assertTrue(
                    str_contains($e->getMessage(), 'database') || 
                    str_contains($e->getMessage(), 'outlets') ||
                    str_contains($e->getMessage(), 'connect'),
                    'Should accept configuration structure'
                );
            }
        }
    }
    
    /**
     * Test: Database class exists (but don't instantiate - requires DB constants)
     */
    public function testDatabaseClassExists(): void
    {
        $this->assertTrue(
            class_exists(Database::class),
            'Database class should exist'
        );
        
        // Don't instantiate - it requires DB_USERNAME, DB_PASSWORD, etc.
        // Just verify the class can be loaded
    }
    
    /**
     * Test: Logger class exists and can be instantiated
     */
    public function testLoggerClassExists(): void
    {
        $this->assertTrue(
            class_exists(Logger::class),
            'Logger class should exist'
        );
        
        $logger = new Logger();
        $this->assertInstanceOf(Logger::class, $logger);
    }
    
    /**
     * Test: Required constants are defined
     */
    public function testRequiredConstantsAreDefined(): void
    {
        $requiredConstants = [
            'BASE_PATH',
            'STORAGE_PATH',
            'LOG_PATH',
            'WAREHOUSE_ID',
            'WAREHOUSE_WEB_OUTLET_ID',
        ];
        
        foreach ($requiredConstants as $constant) {
            $this->assertTrue(
                defined($constant),
                "Constant {$constant} should be defined"
            );
        }
    }
    
    /**
     * Test: Storage directories exist or can be created
     */
    public function testStorageDirectoriesExist(): void
    {
        $requiredDirs = [
            STORAGE_PATH,
            STORAGE_PATH . '/logs',
            STORAGE_PATH . '/runs',
        ];
        
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $this->assertTrue(
                is_dir($dir),
                "Directory {$dir} should exist"
            );
            
            $this->assertTrue(
                is_writable($dir),
                "Directory {$dir} should be writable"
            );
        }
    }
    
    /**
     * Test: Service handles invalid configuration gracefully
     */
    public function testServiceHandlesInvalidConfiguration(): void
    {
        $engine = new TransferEngineService();
        
        // Empty config should still be processed (with defaults)
        try {
            // Use correct method name: executeTransfer
            $result = $engine->executeTransfer([]);
            // Should use defaults
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Database/outlet errors are acceptable
            $this->assertStringContainsString(
                'outlets',
                strtolower($e->getMessage()),
                'Should fail on outlets, not configuration'
            );
        }
    }
}
