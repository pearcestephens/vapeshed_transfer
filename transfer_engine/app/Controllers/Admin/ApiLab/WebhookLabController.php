<?php
declare(strict_types=1);

namespace App\Controllers\Admin\ApiLab;

use App\Support\Response;

/**
 * Webhook Test Lab - Section 12.1
 * Send test webhooks to system endpoints and external URLs.
 */
final class WebhookLabController
{
    /**
     * GET ?endpoint=admin/api-lab/webhook
     * Shows webhook testing interface.
     */
    public function index(): void
    {
        $presets = [
            'transfer_complete' => [
                'url' => '/api/webhooks/transfer-complete',
                'method' => 'POST',
                'payload' => json_encode([
                    'event' => 'transfer.completed',
                    'transfer_id' => 'TR_' . uniqid(),
                    'timestamp' => date('c'),
                    'data' => [
                        'from_store' => 1,
                        'to_store' => 2,
                        'items_transferred' => 5,
                        'total_value' => 149.95,
                    ],
                ], JSON_PRETTY_PRINT),
            ],
            'inventory_alert' => [
                'url' => '/api/webhooks/inventory-alert',
                'method' => 'POST',
                'payload' => json_encode([
                    'event' => 'inventory.low_stock',
                    'timestamp' => date('c'),
                    'data' => [
                        'sku' => 'VAPE_001',
                        'store_id' => 1,
                        'current_stock' => 2,
                        'minimum_threshold' => 5,
                    ],
                ], JSON_PRETTY_PRINT),
            ],
        ];

        Response::success([
            'presets' => $presets,
            'supported_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'content_types' => ['application/json', 'application/x-www-form-urlencoded', 'text/plain'],
        ]);
    }

    /**
     * POST ?endpoint=admin/api-lab/webhook
     * Executes webhook test request.
     */
    public function handle(): void
    {
        $input = $this->parseInput();
        $url = (string)($input['url'] ?? '');
        $method = strtoupper((string)($input['method'] ?? 'POST'));
        $payload = (string)($input['payload'] ?? '');
        $headers = (array)($input['headers'] ?? []);
        $timeout = min(30, max(1, (int)($input['timeout'] ?? 10)));

        if ($url === '') {
            Response::error('URL is required', 'VALIDATION_ERROR', ['field' => 'url'], 400);
            return;
        }

        // Security: restrict to safe URLs in development
        if (!$this->isUrlSafe($url)) {
            Response::error('URL not allowed', 'SECURITY_ERROR', ['url' => $url], 403);
            return;
        }

        $startTime = microtime(true);
        $result = $this->executeWebhook($url, $method, $payload, $headers, $timeout);
        $duration = (microtime(true) - $startTime) * 1000;

        Response::success([
            'request' => [
                'url' => $url,
                'method' => $method,
                'payload' => $payload,
                'headers' => $headers,
                'timeout' => $timeout,
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

    private function isUrlSafe(string $url): bool
    {
        // Allow relative URLs (internal endpoints)
        if (str_starts_with($url, '/')) {
            return true;
        }

        // Allow localhost/127.0.0.1 in development
        if (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?(/.*)?$#i', $url)) {
            return true;
        }

        // Allow specific external testing services
        $allowedDomains = [
            'httpbin.org',
            'webhook.site',
            'requestcatcher.com',
        ];

        $host = parse_url($url, PHP_URL_HOST);
        return $host && in_array(strtolower($host), $allowedDomains, true);
    }

    private function executeWebhook(string $url, string $method, string $payload, array $headers, int $timeout): array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => false, // For local testing only
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => false,
            ]);

            if (!empty($payload) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }

            $curlHeaders = ['User-Agent: VapeShed-WebhookLab/1.0'];
            foreach ($headers as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $curlHeaders[] = $key . ': ' . $value;
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

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

            [$responseHeaders, $responseBody] = $this->parseHttpResponse((string)$response);

            return [
                'success' => true,
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'headers' => $responseHeaders,
                'body' => $responseBody,
                'body_size' => strlen($responseBody),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage(),
                'http_code' => 0,
            ];
        }
    }

    private function parseHttpResponse(string $response): array
    {
        $parts = explode("\r\n\r\n", $response, 2);
        if (count($parts) !== 2) {
            return [[], $response];
        }

        [$headerBlock, $body] = $parts;
        $headers = [];
        $lines = explode("\r\n", $headerBlock);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return [$headers, $body];
    }
}