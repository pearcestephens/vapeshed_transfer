<?php

/**
 * VendTesterController
 *
 * Comprehensive Vend API testing with authentication, endpoint validation,
 * and performance monitoring
 *
 * @package VapeshedTransfer\Controllers\Api
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

namespace VapeshedTransfer\Controllers\Api;

use VapeshedTransfer\Controllers\BaseController;
use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\Core\Security;

class VendTesterController extends BaseController
{
    private Logger $logger;
    private Security $security;
    private string $vendBaseUrl;
    private array $vendCredentials;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->security = new Security();
        $this->vendBaseUrl = getenv('VEND_BASE_URL') ?: 'https://api.vendhq.com/api/2.0';
        $this->vendCredentials = [
            'token' => getenv('VEND_ACCESS_TOKEN'),
            'domain' => getenv('VEND_DOMAIN')
        ];
    }

    /**
     * Test Vend API authentication
     */
    public function testAuthentication(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $token = $_POST['token'] ?? $this->vendCredentials['token'];
            $domain = $_POST['domain'] ?? $this->vendCredentials['domain'];

            if (!$token || !$domain) {
                return $this->errorResponse('Token and domain are required');
            }

            $result = $this->makeVendRequest('/users/me', [], $token, $domain);

            return $this->successResponse([
                'authenticated' => $result['success'],
                'user_info' => $result['success'] ? $result['data'] : null,
                'response_time' => $result['response_time'],
                'rate_limit' => $result['rate_limit'] ?? null
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Vend authentication test failed', ['error' => $e->getMessage()]);
            return $this->errorResponse('Authentication test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test specific Vend API endpoint
     */
    public function testEndpoint(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $endpoint = $_POST['endpoint'] ?? '';
            $method = strtoupper($_POST['method'] ?? 'GET');
            $params = json_decode($_POST['params'] ?? '{}', true);
            $token = $_POST['token'] ?? $this->vendCredentials['token'];
            $domain = $_POST['domain'] ?? $this->vendCredentials['domain'];

            if (!$endpoint) {
                return $this->errorResponse('Endpoint is required');
            }

            $result = $this->makeVendRequest($endpoint, $params, $token, $domain, $method);

            $this->logger->info('Vend endpoint test executed', [
                'endpoint' => $endpoint,
                'method' => $method,
                'success' => $result['success'],
                'response_time' => $result['response_time']
            ]);

            return $this->successResponse($result);

        } catch (\Exception $e) {
            return $this->errorResponse('Endpoint test failed: ' . $e->getMessage());
        }
    }

    /**
     * Get Vend API rate limit status
     */
    public function getRateLimit(): array
    {
        try {
            $token = $_GET['token'] ?? $this->vendCredentials['token'];
            $domain = $_GET['domain'] ?? $this->vendCredentials['domain'];

            $result = $this->makeVendRequest('/users/me', [], $token, $domain);

            return $this->successResponse([
                'rate_limit' => $result['rate_limit'] ?? null,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Rate limit check failed: ' . $e->getMessage());
        }
    }

    /**
     * Run comprehensive Vend API health check
     */
    public function healthCheck(): array
    {
        try {
            $token = $_GET['token'] ?? $this->vendCredentials['token'];
            $domain = $_GET['domain'] ?? $this->vendCredentials['domain'];

            $results = [];
            $overallHealth = true;

            // Test key endpoints
            $endpoints = [
                'Authentication' => '/users/me',
                'Products' => '/products?page_size=1',
                'Outlets' => '/outlets?page_size=1',
                'Customers' => '/customers?page_size=1',
                'Sales' => '/sales?page_size=1'
            ];

            foreach ($endpoints as $name => $endpoint) {
                $result = $this->makeVendRequest($endpoint, [], $token, $domain);
                $results[$name] = [
                    'status' => $result['success'] ? 'healthy' : 'error',
                    'response_time' => $result['response_time'],
                    'http_code' => $result['http_code'],
                    'error' => !$result['success'] ? $result['error'] : null
                ];

                if (!$result['success']) {
                    $overallHealth = false;
                }
            }

            return $this->successResponse([
                'overall_health' => $overallHealth ? 'healthy' : 'degraded',
                'endpoints' => $results,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Health check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get Vend API documentation/schema
     */
    public function getSchema(): array
    {
        return $this->successResponse([
            'endpoints' => [
                'Products' => [
                    'GET /products' => 'List products',
                    'GET /products/{id}' => 'Get product details',
                    'POST /products' => 'Create product',
                    'PUT /products/{id}' => 'Update product'
                ],
                'Outlets' => [
                    'GET /outlets' => 'List outlets',
                    'GET /outlets/{id}' => 'Get outlet details'
                ],
                'Customers' => [
                    'GET /customers' => 'List customers',
                    'GET /customers/{id}' => 'Get customer details',
                    'POST /customers' => 'Create customer'
                ],
                'Sales' => [
                    'GET /sales' => 'List sales',
                    'GET /sales/{id}' => 'Get sale details',
                    'POST /sales' => 'Create sale'
                ],
                'Consignments' => [
                    'GET /consignments' => 'List consignments',
                    'POST /consignments' => 'Create consignment'
                ]
            ],
            'common_parameters' => [
                'page_size' => 'Number of items per page (max 10000)',
                'after' => 'Pagination cursor',
                'before' => 'Pagination cursor',
                'deleted' => 'Include deleted items (true/false)'
            ]
        ]);
    }

    /**
     * Test Vend webhook configuration
     */
    public function testWebhooks(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $token = $_POST['token'] ?? $this->vendCredentials['token'];
            $domain = $_POST['domain'] ?? $this->vendCredentials['domain'];

            $result = $this->makeVendRequest('/webhooks', [], $token, $domain);

            return $this->successResponse([
                'webhooks_configured' => $result['success'],
                'webhook_count' => $result['success'] ? count($result['data']['data'] ?? []) : 0,
                'webhooks' => $result['success'] ? $result['data']['data'] : [],
                'response_time' => $result['response_time']
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Webhook test failed: ' . $e->getMessage());
        }
    }

    /**
     * Make authenticated request to Vend API
     */
    private function makeVendRequest(string $endpoint, array $params = [], ?string $token = null, ?string $domain = null, string $method = 'GET'): array
    {
        if (!$token || !$domain) {
            throw new \Exception('Vend token and domain are required');
        }

        $url = "https://{$domain}.vendhq.com/api/2.0" . $endpoint;

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        $startTime = microtime(true);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'User-Agent: VapeshedTransfer-VendTester/1.0'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        if ($method !== 'GET' && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: $error");
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        $decodedResponse = json_decode($responseBody, true);

        // Extract rate limit info from headers
        $rateLimit = $this->extractRateLimitInfo($responseHeaders);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'data' => $decodedResponse,
            'raw_response' => $responseBody,
            'rate_limit' => $rateLimit,
            'error' => $httpCode >= 400 ? ($decodedResponse['error'] ?? 'Unknown error') : null
        ];
    }

    /**
     * Extract rate limit information from response headers
     */
    private function extractRateLimitInfo(string $headerString): ?array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerString));

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        $rateLimit = null;
        if (isset($headers['x-ratelimit-limit']) || isset($headers['x-ratelimit-remaining'])) {
            $rateLimit = [
                'limit' => $headers['x-ratelimit-limit'] ?? null,
                'remaining' => $headers['x-ratelimit-remaining'] ?? null,
                'reset' => $headers['x-ratelimit-reset'] ?? null,
                'retry_after' => $headers['retry-after'] ?? null
            ];
        }

        return $rateLimit;
    }
}