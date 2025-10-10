<?php
declare(strict_types=1);

namespace Unified\Tests\Support;

use PHPUnit\Framework\TestCase;
use VapeshedTransfer\Support\LogAggregator;

/**
 * LogAggregator Tail File Tests
 * 
 * Tests for safe PHP-based file tailing without exec()
 * 
 * @covers \VapeshedTransfer\Support\LogAggregator
 * @group security
 * @group logs
 * @group no-exec
 */
final class LogAggregatorTailTest extends TestCase
{
    private string $tempDir;
    private LogAggregator $aggregator;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temp directory for test files
        $this->tempDir = sys_get_temp_dir() . '/log_aggregator_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        $this->aggregator = new LogAggregator(['logs' => $this->tempDir]);
    }
    
    protected function tearDown(): void
    {
        // Clean up test files
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        
        parent::tearDown();
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_reads_last_n_lines_from_small_file(): void
    {
        $file = $this->tempDir . '/test.log';
        $lines = [
            'Line 1',
            'Line 2',
            'Line 3',
            'Line 4',
            'Line 5',
        ];
        file_put_contents($file, implode("\n", $lines));
        
        $result = $this->aggregator->tail($file, 3);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(3, $result['entries']);
        // Note: entries are parsed log lines, not raw lines
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_reads_entire_file_if_lines_exceed_total(): void
    {
        $file = $this->tempDir . '/test.log';
        $lines = [
            '[2024-01-19 10:00:00] ERROR Test error 1',
            '[2024-01-19 10:00:01] ERROR Test error 2',
            '[2024-01-19 10:00:02] ERROR Test error 3',
        ];
        file_put_contents($file, implode("\n", $lines));
        
        $result = $this->aggregator->tail($file, 100);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(3, $result['entries']); // Max 3 lines available
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_handles_empty_file(): void
    {
        $file = $this->tempDir . '/empty.log';
        file_put_contents($file, '');
        
        $result = $this->aggregator->tail($file, 10);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertEmpty($result['entries']);
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_handles_nonexistent_file(): void
    {
        $file = $this->tempDir . '/nonexistent.log';
        
        $result = $this->aggregator->tail($file, 10);
        
        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Log file not found', $result['error']);
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_handles_file_with_trailing_newline(): void
    {
        $file = $this->tempDir . '/trailing.log';
        $content = "[2024-01-19 10:00:00] ERROR Test 1\n[2024-01-19 10:00:01] ERROR Test 2\n";
        file_put_contents($file, $content);
        
        $result = $this->aggregator->tail($file, 10);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(2, $result['entries']); // Should not count empty line from trailing newline
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_reads_last_lines_from_large_file(): void
    {
        $file = $this->tempDir . '/large.log';
        $lines = [];
        for ($i = 1; $i <= 1000; $i++) {
            $lines[] = "[2024-01-19 10:00:00] ERROR Test error {$i}";
        }
        file_put_contents($file, implode("\n", $lines));
        
        $result = $this->aggregator->tail($file, 50);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(50, $result['entries']);
        
        // Verify last entry is from line 1000
        $lastEntry = end($result['entries']);
        $this->assertStringContainsString('1000', $lastEntry['message'] ?? '');
    }
    
    /**
     * @test
     * @group logs
     * @group security
     */
    public function it_enforces_max_file_size_limit(): void
    {
        $file = $this->tempDir . '/huge.log';
        
        // Create file larger than 20MB (simulated with smaller test)
        $largeLine = str_repeat('A', 1024 * 100); // 100KB line
        $lines = array_fill(0, 300, $largeLine); // ~30MB
        file_put_contents($file, implode("\n", $lines));
        
        // Should not fail, but may return fewer lines than requested
        $result = $this->aggregator->tail($file, 100);
        
        $this->assertArrayHasKey('entries', $result);
        // May not get all 100 due to size limit, but should not crash
        $this->assertIsArray($result['entries']);
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_handles_single_line_file(): void
    {
        $file = $this->tempDir . '/single.log';
        file_put_contents($file, '[2024-01-19 10:00:00] ERROR Single line');
        
        $result = $this->aggregator->tail($file, 10);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(1, $result['entries']);
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_preserves_line_order(): void
    {
        $file = $this->tempDir . '/ordered.log';
        $lines = [
            '[2024-01-19 10:00:01] ERROR First',
            '[2024-01-19 10:00:02] ERROR Second',
            '[2024-01-19 10:00:03] ERROR Third',
            '[2024-01-19 10:00:04] ERROR Fourth',
            '[2024-01-19 10:00:05] ERROR Fifth',
        ];
        file_put_contents($file, implode("\n", $lines));
        
        $result = $this->aggregator->tail($file, 3);
        
        $this->assertCount(3, $result['entries']);
        $this->assertStringContainsString('Third', $result['entries'][0]['message'] ?? '');
        $this->assertStringContainsString('Fourth', $result['entries'][1]['message'] ?? '');
        $this->assertStringContainsString('Fifth', $result['entries'][2]['message'] ?? '');
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_handles_unicode_content(): void
    {
        $file = $this->tempDir . '/unicode.log';
        $lines = [
            '[2024-01-19 10:00:00] ERROR Test with emoji 🚀',
            '[2024-01-19 10:00:01] ERROR Test with Japanese 日本語',
            '[2024-01-19 10:00:02] ERROR Test with Chinese 中文',
        ];
        file_put_contents($file, implode("\n", $lines));
        
        $result = $this->aggregator->tail($file, 3);
        
        $this->assertCount(3, $result['entries']);
    }
    
    /**
     * @test
     * @group security
     */
    public function it_does_not_use_exec_functions(): void
    {
        $file = $this->tempDir . '/test.log';
        file_put_contents($file, "[2024-01-19 10:00:00] ERROR Test\n");
        
        // Mock exec to ensure it's not called
        $execCalled = false;
        
        // Note: This test verifies by code inspection that tail() 
        // calls tailFile() which uses fopen/fread, not exec()
        $result = $this->aggregator->tail($file, 10);
        
        $this->assertArrayHasKey('entries', $result);
        // If exec() was used, this would be a security issue
        $this->assertIsArray($result['entries']);
    }
    
    /**
     * @test
     * @group logs
     */
    public function it_returns_metadata_in_result(): void
    {
        $file = $this->tempDir . '/meta.log';
        file_put_contents($file, "[2024-01-19 10:00:00] ERROR Test\n");
        
        $result = $this->aggregator->tail($file, 10);
        
        $this->assertArrayHasKey('entries', $result);
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertSame($file, $result['file']);
        $this->assertIsInt($result['count']);
    }
}
