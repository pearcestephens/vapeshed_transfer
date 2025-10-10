<?php

/**
 * WebhookLabController
 *
 * Advanced webhook testing laboratory with live event simulation,
 * payload validation, and response analysis
 *
 * @package VapeshedTransfer\Controllers\Api
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

namespace VapeshedTransfer\Controllers\Api;

use VapeshedTransfer\Controllers\BaseController;
use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\Core\Security;

class WebhookLabController extends BaseController
{
    private Logger $logger;
    private Security $security;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->security = new Security();
    }

    /**
     * Test webhook endpoints with custom payloads
     */
    public function testWebhook(): array
    {
        try {
            // Validate CSRF token
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
            $payload = $_POST['payload'] ?? '{}';
            $method = strtoupper($_POST['method'] ?? 'POST');
            $headers = $this->parseHeaders($_POST['headers'] ?? '');

            if (!$url) {
                return $this->errorResponse('Valid URL required');
            }

            // Validate JSON payload
            $decodedPayload = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->errorResponse('Invalid JSON payload: ' . json_last_error_msg());
            }

            // Send webhook request
            $result = $this->sendWebhookRequest($url, $decodedPayload, $method, $headers);

            $this->logger->info('Webhook test executed', [
                'url' => $url,
                'method' => $method,
                'response_code' => $result['http_code'],
                'response_time' => $result['response_time']
            ]);

            return $this->successResponse($result);

        } catch (\Exception $e) {
            $this->logger->error('Webhook test failed', ['error' => $e->getMessage()]);
            return $this->errorResponse('Webhook test failed: ' . $e->getMessage());
        }
    }

    /**
     * Simulate common webhook events (Vend, Lightspeed, custom)
     */
    public function simulateEvent(): array
    {
        try {
            $eventType = $_POST['event_type'] ?? '';
            $targetUrl = filter_var($_POST['target_url'] ?? '', FILTER_VALIDATE_URL);

            if (!$targetUrl) {
                return $this->errorResponse('Valid target URL required');
            }

            $payload = $this->generateEventPayload($eventType);
            $result = $this->sendWebhookRequest($targetUrl, $payload, 'POST');

            return $this->successResponse([
                'event_type' => $eventType,
                'payload' => $payload,
                'response' => $result
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Event simulation failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate webhook signatures and security
     */
    public function validateSecurity(): array
    {
        try {
            $payload = $_POST['payload'] ?? '';
            $signature = $_POST['signature'] ?? '';
            $secret = $_POST['secret'] ?? '';
            $algorithm = $_POST['algorithm'] ?? 'sha256';

            $isValid = $this->validateWebhookSignature($payload, $signature, $secret, $algorithm);

            return $this->successResponse([
                'valid' => $isValid,
                'algorithm' => $algorithm,
                'calculated_signature' => $this->calculateSignature($payload, $secret, $algorithm)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Security validation failed: ' . $e->getMessage());
        }
    }

    /**
     * Get webhook event history and analytics
     */
    public function getHistory(): array
    {
        try {
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $offset = max((int)($_GET['offset'] ?? 0), 0);

            // Get webhook history from logs
            $history = $this->getWebhookHistory($limit, $offset);

            return $this->successResponse([
                'history' => $history,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => count($history) === $limit
                ]
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get history: ' . $e->getMessage());
        }
    }

    /**
     * Send webhook request with comprehensive response analysis
     */
    private function sendWebhookRequest(string $url, array $payload, string $method = 'POST', array $headers = []): array
    {
        $ch = curl_init();
        $startTime = microtime(true);

        $defaultHeaders = [
            'Content-Type: application/json',
            'User-Agent: VapeshedTransfer-WebhookLab/1.0'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

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

        return [
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'response_headers' => $this->parseResponseHeaders($responseHeaders),
            'response_body' => $responseBody,
            'success' => $httpCode >= 200 && $httpCode < 300
        ];
    }

    /**
     * Generate event payload based on type
     */
    private function generateEventPayload(string $eventType): array
    {
        $basePayload = [
            'timestamp' => date('c'),
            'id' => uniqid('evt_'),
            'source' => 'vapeshed_transfer'
        ];

        switch ($eventType) {
            case 'vend_product_update':
                return array_merge($basePayload, [
                    'event' => 'product.updated',
                    'data' => [
                        'id' => 'prod_' . uniqid(),
                        'name' => 'Test Product',
                        'sku' => 'TEST-' . rand(1000, 9999),
                        'price' => 19.99,
                        'inventory' => rand(0, 100)
                    ]
                ]);

            case 'lightspeed_sale':
                return array_merge($basePayload, [
                    'event' => 'sale.created',
                    'data' => [
                        'saleID' => rand(10000, 99999),
                        'total' => 29.99,
                        'customerID' => rand(1000, 9999),
                        'items' => [
                            [
                                'itemID' => rand(1000, 9999),
                                'quantity' => 1,
                                'unitPrice' => 29.99
                            ]
                        ]
                    ]
                ]);

            case 'transfer_complete':
                return array_merge($basePayload, [
                    'event' => 'transfer.completed',
                    'data' => [
                        'transfer_id' => 'xfer_' . uniqid(),
                        'from_outlet' => 'Main Store',
                        'to_outlet' => 'Branch Store',
                        'items_count' => rand(5, 25),
                        'total_value' => rand(100, 1000)
                    ]
                ]);

            default:
                return array_merge($basePayload, [
                    'event' => 'custom.event',
                    'data' => [
                        'message' => 'Custom webhook event'
                    ]
                ]);
        }
    }

    /**
     * Parse header string into array
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\n", trim($headerString));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (!str_contains($line, ':')) {
                $headers[] = $line;
            } else {
                [$key, $value] = explode(':', $line, 2);
                $headers[] = trim($key) . ': ' . trim($value);
            }
        }

        return $headers;
    }

    /**
     * Parse response headers into associative array
     */
    private function parseResponseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerString));

        foreach ($lines as $line) {
            if (empty($line)) continue;

            if (str_contains($line, 'HTTP/')) {
                $headers['status_line'] = $line;
            } elseif (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    /**
     * Validate webhook signature
     */
    private function validateWebhookSignature(string $payload, string $signature, string $secret, string $algorithm): bool
    {
        $calculatedSignature = $this->calculateSignature($payload, $secret, $algorithm);
        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Calculate signature for payload
     */
    private function calculateSignature(string $payload, string $secret, string $algorithm): string
    {
        switch ($algorithm) {
            case 'sha1':
                return 'sha1=' . hash_hmac('sha1', $payload, $secret);
            case 'sha256':
                return 'sha256=' . hash_hmac('sha256', $payload, $secret);
            default:
                throw new \InvalidArgumentException("Unsupported algorithm: $algorithm");
        }
    }

    /**
     * Get webhook history from logs
     */
    private function getWebhookHistory(int $limit, int $offset): array
    {
        // Mock implementation - in real app, query logs database
        $history = [];

        for ($i = 0; $i < min($limit, 10); $i++) {
            $history[] = [
                'id' => 'wh_' . uniqid(),
                'timestamp' => date('c', strtotime("-$i hours")),
                'url' => 'https://example.com/webhook',
                'method' => 'POST',
                'http_code' => [200, 201, 400, 500][array_rand([200, 201, 400, 500])],
                'response_time' => rand(50, 2000),
                'event_type' => ['vend_product_update', 'lightspeed_sale', 'transfer_complete'][array_rand(['vend_product_update', 'lightspeed_sale', 'transfer_complete'])]
            ];
        }

        return $history;
    }
}