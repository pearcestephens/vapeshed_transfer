<?php
declare(strict_types=1);

namespace App\Controllers\Admin\ApiLab;

use App\Support\Response;

/**
 * Vend API Tester - Section 12.2
 * Test and debug Vend/Lightspeed API interactions.
 */
final class VendTesterController
{
    /**
     * GET ?endpoint=admin/api-lab/vend
     * Shows available Vend API endpoints and test interface.
     */
    public function index(): void
    {
        $endpoints = [
            'products' => [
                'GET /products' => 'List all products',
                'GET /products/{id}' => 'Get specific product',
                'POST /products' => 'Create new product',
                'PUT /products/{id}' => 'Update product',
            ],
            'consignments' => [
                'GET /consignments' => 'List consignments',
                'POST /consignments' => 'Create consignment',
                'PUT /consignments/{id}' => 'Update consignment',
            ],
            'outlets' => [
                'GET /outlets' => 'List all outlets/stores',
                'GET /outlets/{id}' => 'Get specific outlet',
            ],
            'inventory' => [
                'GET /product_inventories' => 'Get inventory levels',
                'PUT /product_inventories/{id}' => 'Update inventory',
            ],
            'sales' => [
                'GET /register_sales' => 'List sales',
                'GET /register_sales/{id}' => 'Get specific sale',
            ],
        ];

        $presets = [
            'list_products' => [
                'endpoint' => 'GET /products',
                'params' => ['page_size' => 20, 'active' => 1],
                'description' => 'List first 20 active products',
            ],
            'get_inventory' => [
                'endpoint' => 'GET /product_inventories',
                'params' => ['outlet_id' => '{outlet_id}'],
                'description' => 'Get inventory for specific outlet',
            ],
            'create_consignment' => [
                'endpoint' => 'POST /consignments',
                'body' => json_encode([
                    'name' => 'Test Transfer ' . date('Y-m-d H:i'),
                    'type' => 'SUPPLIER',
                    'outlet_id' => '{outlet_id}',
                    'consignment_date' => date('Y-m-d H:i:s'),
                ], JSON_PRETTY_PRINT),
                'description' => 'Create test consignment',
            ],
        ];

        Response::success([
            'endpoints' => $endpoints,
            'presets' => $presets,
            'auth_status' => $this->checkVendAuth(),
            'base_url' => $this->getVendBaseUrl(),
        ]);
    }

    /**
     * POST ?endpoint=admin/api-lab/vend
     * Execute Vend API test request.
     */
    public function handle(): void
    {
        $input = $this->parseInput();
        $endpoint = (string)($input['endpoint'] ?? '');
        $params = (array)($input['params'] ?? []);
        $body = (string)($input['body'] ?? '');
        $method = $this->extractMethod($endpoint);
        $path = $this->extractPath($endpoint);

        if ($endpoint === '') {
            Response::error('Endpoint is required', 'VALIDATION_ERROR', ['field' => 'endpoint'], 400);
            return;
        }

        $authCheck = $this->checkVendAuth();
        if (!$authCheck['valid']) {
            Response::error('Vend API credentials not configured', 'AUTH_ERROR', $authCheck, 401);
            return;
        }

        $startTime = microtime(true);
        $result = $this->executeVendRequest($method, $path, $params, $body);
        $duration = (microtime(true) - $startTime) * 1000;

        Response::success([
            'request' => [
                'endpoint' => $endpoint,
                'method' => $method,
                'path' => $path,
                'params' => $params,
                'body' => $body,
            ],
            'response' => $result,
            'duration_ms' => round($duration, 2),
            'timestamp' => date('c'),
        ]);
    }

    private function parseInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $body = file_get_contents('php://input') ?: '';
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function checkVendAuth(): array
    {
        $baseUrl = $this->getVendBaseUrl();
        $token = $this->getVendToken();

        if (!$baseUrl || !$token) {
            return [
                'valid' => false,
                'error' => 'Missing VEND_BASE_URL or VEND_TOKEN in environment',
                'base_url' => $baseUrl ?: 'not_set',
                'token_present' => !empty($token),
            ];
        }

        // Quick auth test - try to fetch outlets
        try {
            $result = $this->executeVendRequest('GET', '/outlets', ['page_size' => 1], '');
            return [
                'valid' => $result['success'] && isset($result['data']),
                'error' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
                'base_url' => $baseUrl,
                'token_present' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'error' => 'Auth test failed: ' . $e->getMessage(),
                'base_url' => $baseUrl,
                'token_present' => true,
            ];
        }
    }

    private function getVendBaseUrl(): string
    {
        return (string)($_ENV['VEND_BASE_URL'] ?? getenv('VEND_BASE_URL') ?: '');
    }

    private function getVendToken(): string
    {
        return (string)($_ENV['VEND_TOKEN'] ?? getenv('VEND_TOKEN') ?: '');
    }

    private function extractMethod(string $endpoint): string
    {
        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+/', $endpoint, $matches)) {
            return $matches[1];
        }
        return 'GET';
    }

    private function extractPath(string $endpoint): string
    {
        return preg_replace('/^(GET|POST|PUT|PATCH|DELETE)\s+/', '', $endpoint);
    }

    private function executeVendRequest(string $method, string $path, array $params, string $body): array
    {
        $baseUrl = rtrim($this->getVendBaseUrl(), '/');
        $token = $this->getVendToken();

        if (!$baseUrl || !$token) {
            return [
                'success' => false,
                'error' => 'Vend credentials not configured',
            ];
        }

        try {
            $url = $baseUrl . '/' . ltrim($path, '/');
            
            // Add query parameters for GET requests
            if (!empty($params) && $method === 'GET') {
                $url .= '?' . http_build_query($params);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: VapeShed-VendTester/1.0',
                ],
            ]);

            // Add body for POST/PUT requests
            if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $error !== '') {
                return [
                    'success' => false,
                    'error' => $error ?: 'Unknown cURL error',
                    'http_code' => 0,
                ];
            }

            // Parse JSON response
            $responseData = json_decode((string)$response, true);
            
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'data' => $responseData,
                'raw_response' => is_array($responseData) ? null : (string)$response,
                'response_size' => strlen((string)$response),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'http_code' => 0,
            ];
        }
    }
}