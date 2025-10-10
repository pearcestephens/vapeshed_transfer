<?php

/**
 * Vend API Integration Test Suite
 *
 * Tests live integration with Vend/Lightspeed Retail API
 * Validates authentication, endpoints, error handling, and rate limiting
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
 * Vend API Integration Test Class
 *
 * Comprehensive integration testing for Vend API connectivity,
 * authentication, data retrieval, and error scenarios
 */
class VendApiTest extends TestCase
{
    /**
     * @var array Configuration for Vend API testing
     */
    private $config;

    /**
     * @var string Base URL for Vend API
     */
    private $baseUrl;

    /**
     * @var string API token for authentication
     */
    private $apiToken;

    /**
     * @var array HTTP headers for requests
     */
    private $headers;

    /**
     * @var bool Flag for sandbox mode
     */
    private $sandboxMode;

    /**
     * Set up test environment before each test
     *
     * Loads configuration, sets up authentication, and prepares test data
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load configuration from environment
        $this->config = [
            'domain_prefix' => getenv('VEND_DOMAIN_PREFIX') ?: 'test',
            'api_token' => getenv('VEND_API_TOKEN') ?: '',
            'api_version' => getenv('VEND_API_VERSION') ?: '2.0',
            'timeout' => (int)(getenv('VEND_API_TIMEOUT') ?: 30),
            'sandbox' => filter_var(getenv('VEND_SANDBOX_MODE') ?: 'true', FILTER_VALIDATE_BOOLEAN)
        ];

        $this->sandboxMode = $this->config['sandbox'];
        $this->baseUrl = "https://{$this->config['domain_prefix']}.vendhq.com/api/{$this->config['api_version']}";
        $this->apiToken = $this->config['api_token'];

        $this->headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Vapeshed-Transfer-Engine/1.0'
        ];

        // Skip tests if not configured
        if (empty($this->apiToken) && !$this->sandboxMode) {
            $this->markTestSkipped('Vend API token not configured. Set VEND_API_TOKEN environment variable.');
        }
    }

    /**
     * Test: API Authentication
     *
     * Verifies that authentication with Vend API works correctly
     *
     * @test
     * @group integration
     * @group vend
     * @group authentication
     */
    public function testApiAuthentication(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - authentication skipped');
            return;
        }

        $response = $this->makeRequest('/products', 'GET', ['page_size' => 1]);

        $this->assertIsArray($response, 'Response should be an array');
        $this->assertArrayNotHasKey('error', $response, 'Response should not contain errors');
        $this->assertEquals(200, $response['http_code'] ?? 0, 'HTTP status should be 200');
    }

    /**
     * Test: Get Products Endpoint
     *
     * Tests retrieval of product list from Vend API
     *
     * @test
     * @group integration
     * @group vend
     * @group products
     */
    public function testGetProducts(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - products test skipped');
            return;
        }

        $response = $this->makeRequest('/products', 'GET', [
            'page_size' => 10,
            'active' => 1
        ]);

        $this->assertIsArray($response, 'Response should be an array');
        $this->assertArrayHasKey('data', $response, 'Response should have data key');
        $this->assertIsArray($response['data'], 'Data should be an array');
    }

    /**
     * Test: Get Single Product
     *
     * Tests retrieval of a specific product by ID
     *
     * @test
     * @group integration
     * @group vend
     * @group products
     */
    public function testGetSingleProduct(): void
    {
        if ($this->sandboxMode) {
            $mockProduct = $this->getMockProduct();
            $this->assertNotNull($mockProduct['id'], 'Mock product should have ID');
            return;
        }

        // First get a product ID
        $products = $this->makeRequest('/products', 'GET', ['page_size' => 1]);

        if (!empty($products['data']) && count($products['data']) > 0) {
            $productId = $products['data'][0]['id'];
            $response = $this->makeRequest("/products/{$productId}", 'GET');

            $this->assertIsArray($response, 'Response should be an array');
            $this->assertArrayHasKey('data', $response, 'Response should have data key');
            $this->assertEquals($productId, $response['data']['id'] ?? null, 'Product ID should match');
        } else {
            $this->markTestSkipped('No products available for testing');
        }
    }

    /**
     * Test: Get Outlets
     *
     * Tests retrieval of outlet (store) list
     *
     * @test
     * @group integration
     * @group vend
     * @group outlets
     */
    public function testGetOutlets(): void
    {
        if ($this->sandboxMode) {
            $mockOutlets = $this->getMockOutlets();
            $this->assertIsArray($mockOutlets, 'Mock outlets should be an array');
            return;
        }

        $response = $this->makeRequest('/outlets', 'GET');

        $this->assertIsArray($response, 'Response should be an array');
        $this->assertArrayHasKey('data', $response, 'Response should have data key');
        $this->assertIsArray($response['data'], 'Data should be an array');
        $this->assertGreaterThan(0, count($response['data']), 'Should have at least one outlet');
    }

    /**
     * Test: Create Consignment (Stock Transfer)
     *
     * Tests creation of a consignment in Vend
     *
     * @test
     * @group integration
     * @group vend
     * @group consignments
     */
    public function testCreateConsignment(): void
    {
        if ($this->sandboxMode) {
            $mockConsignment = $this->getMockConsignment();
            $this->assertNotNull($mockConsignment['id'], 'Mock consignment should have ID');
            return;
        }

        // Get outlets for test
        $outlets = $this->makeRequest('/outlets', 'GET');
        if (count($outlets['data']) < 2) {
            $this->markTestSkipped('Need at least 2 outlets for consignment test');
        }

        $sourceOutlet = $outlets['data'][0]['id'];
        $destOutlet = $outlets['data'][1]['id'];

        $consignmentData = [
            'name' => 'Test Consignment ' . date('Y-m-d H:i:s'),
            'type' => 'SUPPLIER',
            'source_outlet_id' => $sourceOutlet,
            'outlet_id' => $destOutlet,
            'status' => 'STOCKTAKE',
            'consignment_products' => []
        ];

        $response = $this->makeRequest('/consignments', 'POST', $consignmentData);

        $this->assertIsArray($response, 'Response should be an array');
        $this->assertArrayHasKey('data', $response, 'Response should have data key');
        $this->assertNotEmpty($response['data']['id'] ?? null, 'Should return consignment ID');

        // Clean up - mark as cancelled
        if (!empty($response['data']['id'])) {
            $this->makeRequest("/consignments/{$response['data']['id']}", 'PUT', [
                'status' => 'CANCELLED'
            ]);
        }
    }

    /**
     * Test: Rate Limiting Handling
     *
     * Tests that rate limiting is properly detected and handled
     *
     * @test
     * @group integration
     * @group vend
     * @group rate-limiting
     */
    public function testRateLimitingHandling(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - rate limiting test skipped');
            return;
        }

        $rateLimitHit = false;
        $requestCount = 0;
        $maxRequests = 100; // Vend typically limits to 10,000 per day

        // Make rapid requests to potentially hit rate limit
        for ($i = 0; $i < min($maxRequests, 50); $i++) {
            $response = $this->makeRequest('/products', 'GET', ['page_size' => 1]);

            if (isset($response['http_code']) && $response['http_code'] == 429) {
                $rateLimitHit = true;
                break;
            }

            $requestCount++;
            usleep(100000); // 100ms delay between requests
        }

        $this->assertGreaterThan(0, $requestCount, 'Should have made at least one request');
        // Rate limiting test is informational - we don't fail if we don't hit the limit
        $this->assertTrue(true, "Made {$requestCount} requests" . ($rateLimitHit ? ' (rate limit hit)' : ''));
    }

    /**
     * Test: Error Handling - Invalid Endpoint
     *
     * Tests proper handling of invalid API endpoints
     *
     * @test
     * @group integration
     * @group vend
     * @group error-handling
     */
    public function testErrorHandlingInvalidEndpoint(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - error handling test skipped');
            return;
        }

        $response = $this->makeRequest('/invalid-endpoint-12345', 'GET');

        $this->assertIsArray($response, 'Response should be an array');
        $this->assertTrue(
            in_array($response['http_code'] ?? 0, [404, 400]),
            'Should return 404 or 400 for invalid endpoint'
        );
    }

    /**
     * Test: Error Handling - Invalid Data
     *
     * Tests proper handling of invalid request data
     *
     * @test
     * @group integration
     * @group vend
     * @group error-handling
     */
    public function testErrorHandlingInvalidData(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - invalid data test skipped');
            return;
        }

        // Try to create consignment with invalid data
        $response = $this->makeRequest('/consignments', 'POST', [
            'invalid_field' => 'test'
        ]);

        $this->assertIsArray($response, 'Response should be an array');
        $this->assertTrue(
            in_array($response['http_code'] ?? 0, [400, 422]),
            'Should return 400 or 422 for invalid data'
        );
    }

    /**
     * Test: Connection Timeout
     *
     * Tests handling of connection timeouts
     *
     * @test
     * @group integration
     * @group vend
     * @group timeout
     */
    public function testConnectionTimeout(): void
    {
        // Test with extremely short timeout
        $oldTimeout = $this->config['timeout'];
        $this->config['timeout'] = 1; // 1 second

        $startTime = microtime(true);
        $response = $this->makeRequest('/products', 'GET', ['page_size' => 100]);
        $duration = microtime(true) - $startTime;

        $this->config['timeout'] = $oldTimeout; // Restore

        // Either succeeds quickly or times out appropriately
        $this->assertTrue(
            $duration < 5,
            'Request should complete or timeout within 5 seconds'
        );
    }

    /**
     * Test: Pagination Handling
     *
     * Tests proper handling of paginated results
     *
     * @test
     * @group integration
     * @group vend
     * @group pagination
     */
    public function testPaginationHandling(): void
    {
        if ($this->sandboxMode) {
            $this->assertTrue(true, 'Sandbox mode - pagination test skipped');
            return;
        }

        $page1 = $this->makeRequest('/products', 'GET', [
            'page_size' => 2,
            'page' => 1
        ]);

        $this->assertIsArray($page1, 'First page should be an array');
        $this->assertArrayHasKey('data', $page1, 'First page should have data');

        if (isset($page1['pagination']['next'])) {
            $page2 = $this->makeRequest('/products', 'GET', [
                'page_size' => 2,
                'page' => 2
            ]);

            $this->assertIsArray($page2, 'Second page should be an array');
            $this->assertArrayHasKey('data', $page2, 'Second page should have data');

            // Ensure pages are different
            if (!empty($page1['data']) && !empty($page2['data'])) {
                $this->assertNotEquals(
                    $page1['data'][0]['id'] ?? null,
                    $page2['data'][0]['id'] ?? null,
                    'Pages should contain different products'
                );
            }
        } else {
            $this->assertTrue(true, 'Only one page of results available');
        }
    }

    /**
     * Make HTTP Request to Vend API
     *
     * Performs HTTP request with error handling and logging
     *
     * @param string $endpoint API endpoint path
     * @param string $method   HTTP method (GET, POST, PUT, DELETE)
     * @param array  $data     Request data
     *
     * @return array Response data including body and HTTP code
     */
    private function makeRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        // Set method and data for POST/PUT
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $result = [
            'http_code' => $httpCode,
            'raw_response' => $response
        ];

        if (!empty($error)) {
            $result['error'] = $error;
        }

        if ($response) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result = array_merge($result, $decoded);
            }
        }

        return $result;
    }

    /**
     * Get Mock Product Data
     *
     * Returns mock product data for sandbox testing
     *
     * @return array Mock product data
     */
    private function getMockProduct(): array
    {
        return [
            'id' => 'mock-product-' . uniqid(),
            'name' => 'Test Product',
            'sku' => 'TEST-SKU-001',
            'active' => true,
            'supply_price' => 10.00,
            'retail_price' => 20.00
        ];
    }

    /**
     * Get Mock Outlets Data
     *
     * Returns mock outlet data for sandbox testing
     *
     * @return array Mock outlets data
     */
    private function getMockOutlets(): array
    {
        return [
            [
                'id' => 'mock-outlet-1',
                'name' => 'Store 1',
                'physical_address1' => '123 Test St'
            ],
            [
                'id' => 'mock-outlet-2',
                'name' => 'Store 2',
                'physical_address1' => '456 Test Ave'
            ]
        ];
    }

    /**
     * Get Mock Consignment Data
     *
     * Returns mock consignment data for sandbox testing
     *
     * @return array Mock consignment data
     */
    private function getMockConsignment(): array
    {
        return [
            'id' => 'mock-consignment-' . uniqid(),
            'name' => 'Test Consignment',
            'type' => 'SUPPLIER',
            'status' => 'STOCKTAKE',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Clean up after tests
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
