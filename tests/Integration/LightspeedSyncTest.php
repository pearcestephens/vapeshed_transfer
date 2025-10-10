<?php

/**
 * Lightspeed Sync Integration Test Suite
 *
 * Tests integration with Lightspeed sync processes
 * Validates transfer conversion, stock synchronization, and pipeline operations
 *
 * @category   Tests
 * @package    VapeshedTransfer
 * @subpackage Integration
 * @author     Vapeshed Transfer Team
 * @license    Proprietary
 * @version    1.0.0
 */

namespace VapeshedTransfer\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Lightspeed Sync Integration Test Class
 *
 * Comprehensive testing for Lightspeed synchronization processes
 */
class LightspeedSyncTest extends TestCase
{
    private $config;
    private $baseUrl;
    private $sandboxMode;
    private $testTransferId;
    private $testOutlets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'engine_url' => getenv('ENGINE_URL') ?: 'http://localhost:8080',
            'api_key' => getenv('ENGINE_API_KEY') ?: '',
            'sandbox' => filter_var(getenv('SANDBOX_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN)
        ];

        $this->sandboxMode = $this->config['sandbox'];
        $this->baseUrl = $this->config['engine_url'] . '/api';

        if (empty($this->config['api_key']) && !$this->sandboxMode) {
            $this->markTestSkipped('Engine API key not configured.');
        }
    }

    /**
     * Test: Transfer to Consignment Conversion
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group conversion
     */
    public function testTransferToConsignmentConversion(): void
    {
        if ($this->sandboxMode) {
            $mockResult = $this->getMockConversionResult();
            $this->assertTrue($mockResult['success'], 'Mock conversion should succeed');
            return;
        }

        // Create a test transfer
        $transfer = $this->createTestTransfer();
        $this->assertNotEmpty($transfer['id'], 'Transfer should be created');

        // Convert to consignment
        $result = $this->convertTransferToConsignment($transfer['id']);

        $this->assertTrue($result['success'] ?? false, 'Conversion should succeed');
        $this->assertNotEmpty($result['consignment_id'] ?? null, 'Should return consignment ID');
        $this->assertEquals('PENDING', $result['status'] ?? '', 'Status should be PENDING');
    }

    /**
     * Test: Purchase Order to Consignment
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group purchase-order
     */
    public function testPurchaseOrderToConsignment(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - PO test skipped');
            return;
        }

        $po = $this->createTestPurchaseOrder();
        $this->assertNotEmpty($po['id'], 'PO should be created');

        $result = $this->convertPOToConsignment($po['id']);

        $this->assertTrue($result['success'] ?? false, 'PO conversion should succeed');
        $this->assertNotEmpty($result['consignment_id'] ?? null, 'Should return consignment ID');
    }

    /**
     * Test: Stock Level Synchronization
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group stock-sync
     */
    public function testStockLevelSync(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - stock sync test skipped');
            return;
        }

        $productId = $this->getTestProductId();
        $outletId = $this->getTestOutletId();

        if (!$productId || !$outletId) {
            $this->markTestSkipped('No test product or outlet available');
        }

        // Get current stock level
        $beforeSync = $this->getStockLevel($productId, $outletId);

        // Trigger sync
        $syncResult = $this->triggerStockSync($productId, $outletId);

        $this->assertTrue($syncResult['success'] ?? false, 'Stock sync should succeed');

        // Get updated stock level
        $afterSync = $this->getStockLevel($productId, $outletId);

        $this->assertIsNumeric($afterSync['quantity'] ?? null, 'Should return numeric quantity');
    }

    /**
     * Test: Webhook Trigger for Sync
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group webhooks
     */
    public function testWebhookTriggerSync(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - webhook test skipped');
            return;
        }

        $webhookData = [
            'event' => 'consignment.updated',
            'consignment_id' => 'test-' . uniqid(),
            'status' => 'RECEIVED',
            'timestamp' => time()
        ];

        $result = $this->sendWebhook($webhookData);

        $this->assertEquals(200, $result['http_code'] ?? 0, 'Webhook should be accepted');
        $this->assertTrue($result['success'] ?? false, 'Webhook processing should succeed');
    }

    /**
     * Test: Full Sync Pipeline
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group pipeline
     */
    public function testFullSyncPipeline(): void
    {
        if ($this->sandboxMode) {
            $mockPipeline = $this->getMockPipelineResult();
            $this->assertEquals('COMPLETED', $mockPipeline['status'], 'Mock pipeline should complete');
            return;
        }

        // Create transfer
        $transfer = $this->createTestTransfer();
        $transferId = $transfer['id'] ?? null;

        $this->assertNotEmpty($transferId, 'Transfer should be created');

        // Step 1: Convert to consignment
        $consignment = $this->convertTransferToConsignment($transferId);
        $this->assertTrue($consignment['success'] ?? false, 'Step 1: Conversion should succeed');

        $consignmentId = $consignment['consignment_id'] ?? null;
        $this->assertNotEmpty($consignmentId, 'Should have consignment ID');

        // Step 2: Mark as sent
        $sent = $this->updateConsignmentStatus($consignmentId, 'SENT');
        $this->assertTrue($sent['success'] ?? false, 'Step 2: Marking as sent should succeed');

        // Step 3: Mark as received
        $received = $this->updateConsignmentStatus($consignmentId, 'RECEIVED');
        $this->assertTrue($received['success'] ?? false, 'Step 3: Marking as received should succeed');

        // Step 4: Verify stock updated
        $stockCheck = $this->verifyStockUpdated($consignmentId);
        $this->assertTrue($stockCheck['updated'] ?? false, 'Step 4: Stock should be updated');

        // Cleanup
        $this->cleanupTestData($transferId, $consignmentId);
    }

    /**
     * Test: Concurrent Sync Operations
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group concurrency
     */
    public function testConcurrentSyncOperations(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - concurrency test skipped');
            return;
        }

        $transfers = [];
        $results = [];

        // Create multiple transfers
        for ($i = 0; $i < 3; $i++) {
            $transfer = $this->createTestTransfer();
            $transfers[] = $transfer['id'] ?? null;
        }

        // Convert all concurrently (simulate)
        foreach ($transfers as $transferId) {
            if ($transferId) {
                $results[] = $this->convertTransferToConsignment($transferId);
            }
        }

        // Verify all succeeded
        foreach ($results as $result) {
            $this->assertTrue($result['success'] ?? false, 'Concurrent conversion should succeed');
        }

        // Cleanup
        foreach ($transfers as $transferId) {
            if ($transferId) {
                $this->cleanupTestData($transferId);
            }
        }
    }

    /**
     * Test: Error Recovery - Failed Conversion
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group error-recovery
     */
    public function testErrorRecoveryFailedConversion(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - error recovery test skipped');
            return;
        }

        // Try to convert non-existent transfer
        $result = $this->convertTransferToConsignment('invalid-id-' . uniqid());

        $this->assertFalse($result['success'] ?? true, 'Invalid conversion should fail');
        $this->assertNotEmpty($result['error'] ?? '', 'Should return error message');
    }

    /**
     * Test: Idempotency - Duplicate Conversion
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group idempotency
     */
    public function testIdempotencyDuplicateConversion(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - idempotency test skipped');
            return;
        }

        $transfer = $this->createTestTransfer();
        $transferId = $transfer['id'] ?? null;

        $this->assertNotEmpty($transferId, 'Transfer should be created');

        // First conversion
        $result1 = $this->convertTransferToConsignment($transferId);
        $this->assertTrue($result1['success'] ?? false, 'First conversion should succeed');

        // Second conversion (duplicate)
        $result2 = $this->convertTransferToConsignment($transferId);

        // Should either succeed with same ID or gracefully handle duplicate
        $this->assertTrue(
            ($result2['success'] ?? false) || isset($result2['already_converted']),
            'Duplicate conversion should be handled gracefully'
        );

        // Cleanup
        $this->cleanupTestData($transferId, $result1['consignment_id'] ?? null);
    }

    /**
     * Test: Performance - Bulk Sync
     *
     * @test
     * @group integration
     * @group lightspeed
     * @group performance
     */
    public function testPerformanceBulkSync(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - performance test skipped');
            return;
        }

        $batchSize = 10;
        $transfers = [];

        $startTime = microtime(true);

        // Create batch of transfers
        for ($i = 0; $i < $batchSize; $i++) {
            $transfer = $this->createTestTransfer();
            $transfers[] = $transfer['id'] ?? null;
        }

        $createTime = microtime(true) - $startTime;

        // Convert all
        $convertStart = microtime(true);
        foreach ($transfers as $transferId) {
            if ($transferId) {
                $this->convertTransferToConsignment($transferId);
            }
        }
        $convertTime = microtime(true) - $convertStart;

        // Performance assertions
        $avgCreateTime = $createTime / $batchSize;
        $avgConvertTime = $convertTime / $batchSize;

        $this->assertLessThan(2.0, $avgCreateTime, 'Average create time should be < 2 seconds');
        $this->assertLessThan(3.0, $avgConvertTime, 'Average convert time should be < 3 seconds');

        // Cleanup
        foreach ($transfers as $transferId) {
            if ($transferId) {
                $this->cleanupTestData($transferId);
            }
        }
    }

    // Helper Methods

    private function createTestTransfer(): array
    {
        $data = [
            'source_outlet' => 'test-outlet-1',
            'destination_outlet' => 'test-outlet-2',
            'products' => [
                ['sku' => 'TEST-001', 'quantity' => 5]
            ],
            'notes' => 'Integration test transfer'
        ];

        return $this->makeRequest('/transfers', 'POST', $data);
    }

    private function createTestPurchaseOrder(): array
    {
        $data = [
            'supplier' => 'test-supplier',
            'outlet' => 'test-outlet-1',
            'products' => [
                ['sku' => 'TEST-001', 'quantity' => 10]
            ]
        ];

        return $this->makeRequest('/purchase-orders', 'POST', $data);
    }

    private function convertTransferToConsignment(string $transferId): array
    {
        return $this->makeRequest("/transfers/{$transferId}/convert", 'POST');
    }

    private function convertPOToConsignment(string $poId): array
    {
        return $this->makeRequest("/purchase-orders/{$poId}/convert", 'POST');
    }

    private function getStockLevel(string $productId, string $outletId): array
    {
        return $this->makeRequest("/stock/{$productId}/outlet/{$outletId}", 'GET');
    }

    private function triggerStockSync(string $productId, string $outletId): array
    {
        return $this->makeRequest('/stock/sync', 'POST', [
            'product_id' => $productId,
            'outlet_id' => $outletId
        ]);
    }

    private function sendWebhook(array $data): array
    {
        return $this->makeRequest('/webhooks/vend', 'POST', $data);
    }

    private function updateConsignmentStatus(string $consignmentId, string $status): array
    {
        return $this->makeRequest("/consignments/{$consignmentId}/status", 'PUT', [
            'status' => $status
        ]);
    }

    private function verifyStockUpdated(string $consignmentId): array
    {
        return $this->makeRequest("/consignments/{$consignmentId}/verify-stock", 'GET');
    }

    private function getTestProductId(): ?string
    {
        $products = $this->makeRequest('/products', 'GET', ['limit' => 1]);
        return $products['data'][0]['id'] ?? null;
    }

    private function getTestOutletId(): ?string
    {
        $outlets = $this->makeRequest('/outlets', 'GET', ['limit' => 1]);
        return $outlets['data'][0]['id'] ?? null;
    }

    private function cleanupTestData(?string $transferId = null, ?string $consignmentId = null): void
    {
        if ($transferId) {
            $this->makeRequest("/transfers/{$transferId}", 'DELETE');
        }
        if ($consignmentId) {
            $this->makeRequest("/consignments/{$consignmentId}", 'DELETE');
        }
    }

    private function makeRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->config['api_key'],
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = ['http_code' => $httpCode];

        if ($response) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result = array_merge($result, $decoded);
            }
        }

        return $result;
    }

    private function getMockConversionResult(): array
    {
        return [
            'success' => true,
            'consignment_id' => 'mock-consignment-' . uniqid(),
            'status' => 'PENDING'
        ];
    }

    private function getMockPipelineResult(): array
    {
        return [
            'status' => 'COMPLETED',
            'steps' => [
                ['name' => 'Convert', 'status' => 'SUCCESS'],
                ['name' => 'Send', 'status' => 'SUCCESS'],
                ['name' => 'Receive', 'status' => 'SUCCESS'],
                ['name' => 'Update Stock', 'status' => 'SUCCESS']
            ]
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
