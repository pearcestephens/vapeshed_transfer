<?php

namespace App\Controllers\Admin\ApiLab;

use App\Controllers\BaseController;

/**
 * SnippetLibraryController
 * 
 * Section 12.6: Code Snippet Library
 * Copy-pasteable examples with "Try it" functionality that opens relevant tester prefilled
 * Comprehensive code snippet management with test integration
 * 
 * @package transfer_engine
 * @subpackage ApiLab
 * @author System
 * @version 1.0
 */
class SnippetLibraryController extends BaseController
{
    /**
     * Code snippet library organized by category
     */
    private array $snippetLibrary;

    /**
     * Snippet configuration
     */
    private array $snippetConfig;

    public function __construct()
    {
        parent::__construct();
        $this->initSnippetLibrary();
        $this->initSnippetConfig();
    }

    /**
     * Initialize code snippet library
     */
    private function initSnippetLibrary(): void
    {
        $this->snippetLibrary = [
            'transfer_api' => [
                'category' => 'Transfer API',
                'description' => 'Stock transfer operations and management',
                'snippets' => [
                    'create_transfer_curl' => [
                        'title' => 'Create Transfer - cURL',
                        'description' => 'Create a new stock transfer using cURL',
                        'language' => 'bash',
                        'tags' => ['curl', 'transfer', 'post'],
                        'test_integration' => [
                            'controller' => 'SuiteRunner',
                            'suite' => 'transfer',
                            'endpoint' => 'create_transfer'
                        ],
                        'code' => 'curl -X POST https://api.vapeshed.co.nz/transfers \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d \'{
    "from_outlet": 1,
    "to_outlet": 2,
    "items": [
      {
        "product_id": "PROD001",
        "quantity": 5,
        "notes": "Restock request"
      }
    ],
    "priority": "normal",
    "notes": "Inter-store transfer"
  }\''
                    ],
                    'create_transfer_php' => [
                        'title' => 'Create Transfer - PHP',
                        'description' => 'Create transfer using PHP with cURL',
                        'language' => 'php',
                        'tags' => ['php', 'transfer', 'curl'],
                        'test_integration' => [
                            'controller' => 'SuiteRunner',
                            'suite' => 'transfer',
                            'endpoint' => 'create_transfer'
                        ],
                        'code' => '<?php
$transfer_data = [
    \'from_outlet\' => 1,
    \'to_outlet\' => 2,
    \'items\' => [
        [
            \'product_id\' => \'PROD001\',
            \'quantity\' => 5,
            \'notes\' => \'Restock request\'
        ]
    ],
    \'priority\' => \'normal\',
    \'notes\' => \'Inter-store transfer\'
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => \'https://api.vapeshed.co.nz/transfers\',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($transfer_data),
    CURLOPT_HTTPHEADER => [
        \'Authorization: Bearer \' . $api_token,
        \'Content-Type: application/json\'
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201) {
    $transfer = json_decode($response, true);
    echo "Transfer created: " . $transfer[\'transfer_id\'];
} else {
    echo "Error creating transfer: " . $response;
}
?>'
                    ],
                    'create_transfer_javascript' => [
                        'title' => 'Create Transfer - JavaScript',
                        'description' => 'Create transfer using JavaScript fetch API',
                        'language' => 'javascript',
                        'tags' => ['javascript', 'transfer', 'fetch'],
                        'test_integration' => [
                            'controller' => 'SuiteRunner',
                            'suite' => 'transfer',
                            'endpoint' => 'create_transfer'
                        ],
                        'code' => 'const createTransfer = async (transferData) => {
  try {
    const response = await fetch(\'https://api.vapeshed.co.nz/transfers\', {
      method: \'POST\',
      headers: {
        \'Authorization\': `Bearer ${API_TOKEN}`,
        \'Content-Type\': \'application/json\'
      },
      body: JSON.stringify({
        from_outlet: 1,
        to_outlet: 2,
        items: [
          {
            product_id: \'PROD001\',
            quantity: 5,
            notes: \'Restock request\'
          }
        ],
        priority: \'normal\',
        notes: \'Inter-store transfer\'
      })
    });

    if (response.ok) {
      const transfer = await response.json();
      console.log(\'Transfer created:\', transfer.transfer_id);
      return transfer;
    } else {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
  } catch (error) {
    console.error(\'Error creating transfer:\', error);
    throw error;
  }
};'
                    ]
                ]
            ],
            'vend_integration' => [
                'category' => 'Vend/Lightspeed Integration',
                'description' => 'External API integration examples',
                'snippets' => [
                    'vend_auth_curl' => [
                        'title' => 'Vend Authentication - cURL',
                        'description' => 'Authenticate with Vend API',
                        'language' => 'bash',
                        'tags' => ['curl', 'vend', 'auth'],
                        'test_integration' => [
                            'controller' => 'VendTester',
                            'preset' => 'auth_test'
                        ],
                        'code' => '# Get OAuth token
curl -X POST https://secure.vendhq.com/connect \\
  -H "Content-Type: application/x-www-form-urlencoded" \\
  -d "grant_type=client_credentials" \\
  -d "client_id=YOUR_CLIENT_ID" \\
  -d "client_secret=YOUR_CLIENT_SECRET"

# Use token for API calls
curl -X GET https://domain_prefix.vendhq.com/api/products \\
  -H "Authorization: Bearer ACCESS_TOKEN" \\
  -H "Accept: application/json"'
                    ],
                    'vend_sync_php' => [
                        'title' => 'Vend Product Sync - PHP',
                        'description' => 'Synchronize products with Vend',
                        'language' => 'php',
                        'tags' => ['php', 'vend', 'sync'],
                        'test_integration' => [
                            'controller' => 'LightspeedTester',
                            'preset' => 'stock_sync'
                        ],
                        'code' => '<?php
class VendSync {
    private $baseUrl;
    private $token;
    
    public function __construct($domain, $token) {
        $this->baseUrl = "https://{$domain}.vendhq.com/api";
        $this->token = $token;
    }
    
    public function syncProducts($products) {
        foreach ($products as $product) {
            $this->updateProduct($product);
        }
    }
    
    private function updateProduct($product) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . \'/products/\' . $product[\'id\'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => \'PUT\',
            CURLOPT_POSTFIELDS => json_encode($product),
            CURLOPT_HTTPHEADER => [
                \'Authorization: Bearer \' . $this->token,
                \'Content-Type: application/json\'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Sync failed for product {$product[\'id\']}: {$response}");
        }
        
        return json_decode($response, true);
    }
}
?>'
                    ]
                ]
            ],
            'webhook_handling' => [
                'category' => 'Webhook Management',
                'description' => 'Webhook creation, testing, and handling',
                'snippets' => [
                    'webhook_receiver_php' => [
                        'title' => 'Webhook Receiver - PHP',
                        'description' => 'Handle incoming webhook events',
                        'language' => 'php',
                        'tags' => ['php', 'webhook', 'receiver'],
                        'test_integration' => [
                            'controller' => 'WebhookLab',
                            'preset' => 'test_receiver'
                        ],
                        'code' => '<?php
// webhook_receiver.php
header(\'Content-Type: application/json\');

// Verify webhook signature (if using)
$secret = $_ENV[\'WEBHOOK_SECRET\'];
$payload = file_get_contents(\'php://input\');
$signature = hash_hmac(\'sha256\', $payload, $secret);
$expected = $_SERVER[\'HTTP_X_WEBHOOK_SIGNATURE\'] ?? \'\';

if (!hash_equals(\'sha256=\' . $signature, $expected)) {
    http_response_code(401);
    echo json_encode([\'error\' => \'Invalid signature\']);
    exit;
}

// Parse webhook data
$data = json_decode($payload, true);
if (!$data) {
    http_response_code(400);
    echo json_encode([\'error\' => \'Invalid JSON\']);
    exit;
}

// Process webhook event
try {
    switch ($data[\'event_type\']) {
        case \'stock.updated\':
            handleStockUpdate($data);
            break;
        case \'transfer.completed\':
            handleTransferComplete($data);
            break;
        default:
            error_log("Unknown webhook event: {$data[\'event_type\']}");
    }
    
    echo json_encode([\'status\' => \'success\', \'processed_at\' => date(\'c\')]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Webhook processing error: " . $e->getMessage());
    echo json_encode([\'error\' => \'Processing failed\']);
}

function handleStockUpdate($data) {
    // Update local stock levels
    $product_id = $data[\'product_id\'];
    $new_count = $data[\'new_count\'];
    
    // Database update logic here
    error_log("Stock updated for {$product_id}: {$new_count}");
}

function handleTransferComplete($data) {
    // Process completed transfer
    $transfer_id = $data[\'transfer_id\'];
    
    // Update transfer status logic here
    error_log("Transfer completed: {$transfer_id}");
}
?>'
                    ],
                    'webhook_sender_curl' => [
                        'title' => 'Send Test Webhook - cURL',
                        'description' => 'Send test webhook event',
                        'language' => 'bash',
                        'tags' => ['curl', 'webhook', 'test'],
                        'test_integration' => [
                            'controller' => 'WebhookLab',
                            'preset' => 'stock_update'
                        ],
                        'code' => '#!/bin/bash

# Test webhook endpoint
WEBHOOK_URL="https://your-app.com/webhook/receiver"
SECRET="your_webhook_secret"

# Test payload
PAYLOAD=\'{
  "event_type": "stock.updated",
  "product_id": "PROD001",
  "old_count": 10,
  "new_count": 15,
  "outlet_id": 1,
  "timestamp": "\'\'"$(date -Iseconds)\'\'\"",
  "source": "api_lab_test"
}\'

# Generate signature
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)

# Send webhook
curl -X POST "$WEBHOOK_URL" \\
  -H "Content-Type: application/json" \\
  -H "X-Webhook-Signature: sha256=$SIGNATURE" \\
  -H "User-Agent: VapeShed-Webhook/1.0" \\
  -d "$PAYLOAD" \\
  --max-time 30 \\
  -w "HTTP Status: %{http_code}\\nResponse Time: %{time_total}s\\n"'
                    ]
                ]
            ],
            'queue_operations' => [
                'category' => 'Queue & Background Jobs',
                'description' => 'Job queue management and monitoring',
                'snippets' => [
                    'dispatch_job_php' => [
                        'title' => 'Dispatch Background Job - PHP',
                        'description' => 'Queue a background job for processing',
                        'language' => 'php',
                        'tags' => ['php', 'queue', 'job'],
                        'test_integration' => [
                            'controller' => 'QueueJobTester',
                            'job_type' => 'stock_sync'
                        ],
                        'code' => '<?php
class JobDispatcher {
    private $queueConnection;
    
    public function __construct($connection) {
        $this->queueConnection = $connection;
    }
    
    public function dispatchStockSync($productIds, $outletIds) {
        $job = [
            \'id\' => uniqid(\'job_\'),
            \'type\' => \'stock_sync\',
            \'payload\' => [
                \'product_ids\' => $productIds,
                \'outlet_ids\' => $outletIds,
                \'sync_timestamp\' => date(\'c\'),
                \'priority\' => \'normal\'
            ],
            \'attempts\' => 0,
            \'max_attempts\' => 3,
            \'queued_at\' => date(\'c\')
        ];
        
        return $this->queueJob($job);
    }
    
    private function queueJob($job) {
        // Add to queue (Redis, database, etc.)
        $queued = $this->queueConnection->push(\'default\', $job);
        
        if ($queued) {
            error_log("Job queued: {$job[\'id\']} ({$job[\'type\']})");
            return $job[\'id\'];
        } else {
            throw new Exception("Failed to queue job: {$job[\'id\']}");
        }
    }
    
    public function getJobStatus($jobId) {
        return $this->queueConnection->getStatus($jobId);
    }
    
    public function cancelJob($jobId) {
        return $this->queueConnection->cancel($jobId);
    }
}

// Usage example
$dispatcher = new JobDispatcher($queueConnection);
$jobId = $dispatcher->dispatchStockSync([\'PROD001\', \'PROD002\'], [1, 2, 3]);
echo "Job dispatched: {$jobId}";
?>'
                    ]
                ]
            ],
            'error_handling' => [
                'category' => 'Error Handling & Logging',
                'description' => 'Robust error handling patterns',
                'snippets' => [
                    'api_error_handler_php' => [
                        'title' => 'API Error Handler - PHP',
                        'description' => 'Comprehensive API error handling',
                        'language' => 'php',
                        'tags' => ['php', 'error', 'logging'],
                        'test_integration' => null,
                        'code' => '<?php
class ApiErrorHandler {
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    public function handleException($exception, $context = []) {
        $errorData = [
            \'message\' => $exception->getMessage(),
            \'code\' => $exception->getCode(),
            \'file\' => $exception->getFile(),
            \'line\' => $exception->getLine(),
            \'trace\' => $exception->getTraceAsString(),
            \'context\' => $context,
            \'timestamp\' => date(\'c\'),
            \'request_id\' => $_SERVER[\'HTTP_X_REQUEST_ID\'] ?? uniqid()
        ];
        
        // Log error
        $this->logger->error(\'API Exception\', $errorData);
        
        // Return user-friendly error
        return $this->formatErrorResponse($exception, $errorData);
    }
    
    private function formatErrorResponse($exception, $errorData) {
        $statusCode = $this->getHttpStatusCode($exception);
        
        $response = [
            \'success\' => false,
            \'error\' => [
                \'code\' => $exception->getCode() ?: \'INTERNAL_ERROR\',
                \'message\' => $this->getUserMessage($exception),
                \'request_id\' => $errorData[\'request_id\']
            ],
            \'timestamp\' => $errorData[\'timestamp\']
        ];
        
        // Add debug info in development
        if (($_ENV[\'APP_ENV\'] ?? \'production\') === \'development\') {
            $response[\'debug\'] = [
                \'file\' => $errorData[\'file\'],
                \'line\' => $errorData[\'line\'],
                \'trace\' => explode("\\n", $errorData[\'trace\'])
            ];
        }
        
        http_response_code($statusCode);
        header(\'Content-Type: application/json\');
        
        return $response;
    }
    
    private function getHttpStatusCode($exception) {
        switch (get_class($exception)) {
            case \'InvalidArgumentException\':
                return 400;
            case \'UnauthorizedException\':
                return 401;
            case \'ForbiddenException\':
                return 403;
            case \'NotFoundException\':
                return 404;
            case \'ValidationException\':
                return 422;
            default:
                return 500;
        }
    }
    
    private function getUserMessage($exception) {
        // Return safe user message
        if ($exception instanceof ValidationException) {
            return $exception->getMessage();
        }
        
        return \'An error occurred while processing your request\';
    }
}
?>'
                    ]
                ]
            ]
        ];
    }

    /**
     * Initialize snippet configuration
     */
    private function initSnippetConfig(): void
    {
        $this->snippetConfig = [
            'supported_languages' => ['bash', 'php', 'javascript', 'python', 'json'],
            'syntax_highlighting' => true,
            'copy_to_clipboard' => true,
            'test_integration' => true,
            'search_enabled' => true,
            'categories_collapsible' => true,
            'export_formats' => ['zip', 'gist', 'markdown'],
            'version_control' => false // Future feature
        ];
    }

    /**
     * Display snippet library interface
     */
    public function index(): void
    {
        $this->view('admin/api-lab/snippet-library', [
            'title' => 'Code Snippet Library',
            'library' => $this->snippetLibrary,
            'config' => $this->snippetConfig,
            'categories' => $this->getCategories(),
            'popular_snippets' => $this->getPopularSnippets(),
            'recent_snippets' => $this->getRecentSnippets()
        ]);
    }

    /**
     * Handle snippet operations
     */
    public function handle(): void
    {
        try {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'get_snippet':
                    $result = $this->getSnippet();
                    break;
                case 'search_snippets':
                    $result = $this->searchSnippets();
                    break;
                case 'test_snippet':
                    $result = $this->testSnippet();
                    break;
                case 'copy_snippet':
                    $result = $this->copySnippet();
                    break;
                case 'export_snippets':
                    $result = $this->exportSnippets();
                    break;
                case 'validate_syntax':
                    $result = $this->validateSyntax();
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown action: {$action}");
            }

            $this->jsonResponse([
                'success' => true,
                'action' => $action,
                'result' => $result,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            $this->logError('Snippet library operation failed', [
                'error' => $e->getMessage(),
                'action' => $action ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);

            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'action' => $action ?? 'unknown',
                'timestamp' => date('c')
            ], 400);
        }
    }

    /**
     * Get specific snippet with metadata
     */
    private function getSnippet(): array
    {
        $category = $_POST['category'] ?? '';
        $snippetKey = $_POST['snippet_key'] ?? '';

        if (!isset($this->snippetLibrary[$category]['snippets'][$snippetKey])) {
            throw new \InvalidArgumentException("Snippet not found: {$category}.{$snippetKey}");
        }

        $snippet = $this->snippetLibrary[$category]['snippets'][$snippetKey];
        
        return [
            'category' => $category,
            'snippet_key' => $snippetKey,
            'snippet' => $snippet,
            'metadata' => [
                'language' => $snippet['language'],
                'tags' => $snippet['tags'],
                'line_count' => substr_count($snippet['code'], "\n") + 1,
                'character_count' => strlen($snippet['code']),
                'has_test_integration' => !empty($snippet['test_integration'])
            ]
        ];
    }

    /**
     * Search snippets by query
     */
    private function searchSnippets(): array
    {
        $query = $_POST['query'] ?? '';
        $language = $_POST['language'] ?? '';
        $tags = $_POST['tags'] ?? [];

        if (empty($query)) {
            throw new \InvalidArgumentException('Search query is required');
        }

        $results = [];
        
        foreach ($this->snippetLibrary as $categoryKey => $category) {
            foreach ($category['snippets'] as $snippetKey => $snippet) {
                $score = 0;
                
                // Title match
                if (stripos($snippet['title'], $query) !== false) {
                    $score += 10;
                }
                
                // Description match
                if (stripos($snippet['description'], $query) !== false) {
                    $score += 5;
                }
                
                // Code content match
                if (stripos($snippet['code'], $query) !== false) {
                    $score += 3;
                }
                
                // Tag match
                foreach ($snippet['tags'] as $tag) {
                    if (stripos($tag, $query) !== false) {
                        $score += 7;
                    }
                }
                
                // Language filter
                if ($language && $snippet['language'] !== $language) {
                    continue;
                }
                
                // Tag filter
                if (!empty($tags) && !array_intersect($tags, $snippet['tags'])) {
                    continue;
                }
                
                if ($score > 0) {
                    $results[] = [
                        'category' => $categoryKey,
                        'snippet_key' => $snippetKey,
                        'title' => $snippet['title'],
                        'description' => $snippet['description'],
                        'language' => $snippet['language'],
                        'tags' => $snippet['tags'],
                        'relevance_score' => $score
                    ];
                }
            }
        }

        // Sort by relevance score
        usort($results, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return [
            'query' => $query,
            'filters' => compact('language', 'tags'),
            'results' => array_slice($results, 0, 20), // Limit results
            'total_found' => count($results)
        ];
    }

    /**
     * Test snippet using integrated testers
     */
    private function testSnippet(): array
    {
        $category = $_POST['category'] ?? '';
        $snippetKey = $_POST['snippet_key'] ?? '';
        $customParams = $_POST['custom_params'] ?? '{}';

        if (!isset($this->snippetLibrary[$category]['snippets'][$snippetKey])) {
            throw new \InvalidArgumentException("Snippet not found: {$category}.{$snippetKey}");
        }

        $snippet = $this->snippetLibrary[$category]['snippets'][$snippetKey];
        $testIntegration = $snippet['test_integration'];

        if (!$testIntegration) {
            throw new \InvalidArgumentException("No test integration available for this snippet");
        }

        $params = json_decode($customParams, true) ?: [];

        // Route to appropriate tester
        $testResult = $this->executeSnippetTest($testIntegration, $params);
        
        return [
            'snippet_key' => $snippetKey,
            'test_integration' => $testIntegration,
            'test_result' => $testResult,
            'executed_at' => date('c')
        ];
    }

    /**
     * Copy snippet to clipboard (returns formatted code)
     */
    private function copySnippet(): array
    {
        $category = $_POST['category'] ?? '';
        $snippetKey = $_POST['snippet_key'] ?? '';
        $format = $_POST['format'] ?? 'raw';

        if (!isset($this->snippetLibrary[$category]['snippets'][$snippetKey])) {
            throw new \InvalidArgumentException("Snippet not found: {$category}.{$snippetKey}");
        }

        $snippet = $this->snippetLibrary[$category]['snippets'][$snippetKey];
        
        $formatted = $this->formatSnippetForCopy($snippet, $format);
        
        return [
            'snippet_key' => $snippetKey,
            'format' => $format,
            'code' => $formatted,
            'metadata' => [
                'title' => $snippet['title'],
                'language' => $snippet['language'],
                'copied_at' => date('c')
            ]
        ];
    }

    /**
     * Export snippets in various formats
     */
    private function exportSnippets(): array
    {
        $categories = $_POST['categories'] ?? [];
        $format = $_POST['format'] ?? 'zip';
        $includeMetadata = isset($_POST['include_metadata']);

        if (empty($categories)) {
            $categories = array_keys($this->snippetLibrary);
        }

        $exportData = [];
        
        foreach ($categories as $category) {
            if (isset($this->snippetLibrary[$category])) {
                $exportData[$category] = $this->snippetLibrary[$category];
            }
        }

        $exportId = 'EXPORT_' . date('YmdHis');
        $exportPath = $this->generateExport($exportData, $format, $includeMetadata, $exportId);
        
        return [
            'export_id' => $exportId,
            'format' => $format,
            'categories_included' => $categories,
            'total_snippets' => $this->countSnippetsInCategories($categories),
            'export_path' => $exportPath,
            'download_url' => "/api/snippet-library/download/{$exportId}",
            'generated_at' => date('c')
        ];
    }

    /**
     * Validate snippet syntax
     */
    private function validateSyntax(): array
    {
        $code = $_POST['code'] ?? '';
        $language = $_POST['language'] ?? '';

        if (empty($code)) {
            throw new \InvalidArgumentException('Code is required for validation');
        }

        $validation = [
            'language' => $language,
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];

        // Basic validation based on language
        switch ($language) {
            case 'php':
                $validation = $this->validatePhpSyntax($code);
                break;
            case 'javascript':
                $validation = $this->validateJavaScriptSyntax($code);
                break;
            case 'bash':
                $validation = $this->validateBashSyntax($code);
                break;
            case 'json':
                $validation = $this->validateJsonSyntax($code);
                break;
            default:
                $validation['warnings'][] = 'No syntax validation available for this language';
        }

        return $validation;
    }

    /**
     * Execute snippet test through appropriate tester
     */
    private function executeSnippetTest(array $testIntegration, array $params): array
    {
        $controller = $testIntegration['controller'];
        
        switch ($controller) {
            case 'SuiteRunner':
                return $this->testWithSuiteRunner($testIntegration, $params);
            case 'VendTester':
                return $this->testWithVendTester($testIntegration, $params);
            case 'WebhookLab':
                return $this->testWithWebhookLab($testIntegration, $params);
            case 'QueueJobTester':
                return $this->testWithQueueTester($testIntegration, $params);
            default:
                throw new \InvalidArgumentException("Unknown test controller: {$controller}");
        }
    }

    /**
     * Format snippet for copying
     */
    private function formatSnippetForCopy(array $snippet, string $format): string
    {
        switch ($format) {
            case 'raw':
                return $snippet['code'];
            case 'commented':
                return $this->addCommentHeader($snippet);
            case 'markdown':
                return $this->formatAsMarkdown($snippet);
            default:
                return $snippet['code'];
        }
    }

    /**
     * Generate export file
     */
    private function generateExport(array $data, string $format, bool $includeMetadata, string $exportId): string
    {
        $exportDir = '/tmp/snippet_exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $exportPath = "{$exportDir}/{$exportId}";
        
        switch ($format) {
            case 'zip':
                return $this->createZipExport($data, $exportPath, $includeMetadata);
            case 'markdown':
                return $this->createMarkdownExport($data, $exportPath, $includeMetadata);
            case 'gist':
                return $this->createGistExport($data, $exportPath, $includeMetadata);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    // Helper methods for different operations

    private function getCategories(): array
    {
        return array_map(function($category) {
            return [
                'name' => $category['category'],
                'description' => $category['description'],
                'snippet_count' => count($category['snippets'])
            ];
        }, $this->snippetLibrary);
    }

    private function getPopularSnippets(): array
    {
        // Mock popular snippets - in production would track usage
        return [
            ['category' => 'transfer_api', 'snippet' => 'create_transfer_curl', 'usage_count' => 45],
            ['category' => 'webhook_handling', 'snippet' => 'webhook_receiver_php', 'usage_count' => 32],
            ['category' => 'vend_integration', 'snippet' => 'vend_sync_php', 'usage_count' => 28]
        ];
    }

    private function getRecentSnippets(): array
    {
        // Mock recent activity
        return [
            ['category' => 'error_handling', 'snippet' => 'api_error_handler_php', 'accessed_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
            ['category' => 'queue_operations', 'snippet' => 'dispatch_job_php', 'accessed_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))]
        ];
    }

    // Mock test integration methods

    private function testWithSuiteRunner(array $integration, array $params): array
    {
        return [
            'tester' => 'SuiteRunner',
            'suite' => $integration['suite'] ?? '',
            'endpoint' => $integration['endpoint'] ?? '',
            'test_result' => 'Mock test executed successfully',
            'redirect_url' => "/admin/api-lab/suite-runner?suite={$integration['suite']}&endpoint={$integration['endpoint']}"
        ];
    }

    private function testWithVendTester(array $integration, array $params): array
    {
        return [
            'tester' => 'VendTester',
            'preset' => $integration['preset'] ?? '',
            'test_result' => 'Mock Vend test executed',
            'redirect_url' => "/admin/api-lab/vend-tester?preset={$integration['preset']}"
        ];
    }

    private function testWithWebhookLab(array $integration, array $params): array
    {
        return [
            'tester' => 'WebhookLab',
            'preset' => $integration['preset'] ?? '',
            'test_result' => 'Mock webhook test sent',
            'redirect_url' => "/admin/api-lab/webhook-lab?preset={$integration['preset']}"
        ];
    }

    private function testWithQueueTester(array $integration, array $params): array
    {
        return [
            'tester' => 'QueueJobTester',
            'job_type' => $integration['job_type'] ?? '',
            'test_result' => 'Mock job queued',
            'redirect_url' => "/admin/api-lab/queue-tester?job_type={$integration['job_type']}"
        ];
    }

    // Mock validation methods

    private function validatePhpSyntax(string $code): array
    {
        // Basic PHP syntax check
        $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_check');
        file_put_contents($tempFile, $code);
        
        $output = shell_exec("php -l {$tempFile} 2>&1");
        unlink($tempFile);
        
        return [
            'language' => 'php',
            'valid' => strpos($output, 'No syntax errors') !== false,
            'errors' => strpos($output, 'No syntax errors') === false ? [$output] : [],
            'warnings' => [],
            'suggestions' => []
        ];
    }

    private function validateJavaScriptSyntax(string $code): array
    {
        // Mock JS validation
        return [
            'language' => 'javascript',
            'valid' => true,
            'errors' => [],
            'warnings' => ['Basic validation only - use proper linter for production'],
            'suggestions' => []
        ];
    }

    private function validateBashSyntax(string $code): array
    {
        return [
            'language' => 'bash',
            'valid' => true,
            'errors' => [],
            'warnings' => ['Bash validation not implemented'],
            'suggestions' => []
        ];
    }

    private function validateJsonSyntax(string $code): array
    {
        json_decode($code);
        
        return [
            'language' => 'json',
            'valid' => json_last_error() === JSON_ERROR_NONE,
            'errors' => json_last_error() !== JSON_ERROR_NONE ? [json_last_error_msg()] : [],
            'warnings' => [],
            'suggestions' => []
        ];
    }

    // Mock export methods

    private function createZipExport(array $data, string $path, bool $includeMetadata): string
    {
        return $path . '.zip'; // Mock path
    }

    private function createMarkdownExport(array $data, string $path, bool $includeMetadata): string
    {
        return $path . '.md'; // Mock path
    }

    private function createGistExport(array $data, string $path, bool $includeMetadata): string
    {
        return $path . '_gist.json'; // Mock path
    }

    private function countSnippetsInCategories(array $categories): int
    {
        $count = 0;
        foreach ($categories as $category) {
            if (isset($this->snippetLibrary[$category])) {
                $count += count($this->snippetLibrary[$category]['snippets']);
            }
        }
        return $count;
    }

    private function addCommentHeader(array $snippet): string
    {
        $header = "/**\n";
        $header .= " * {$snippet['title']}\n";
        $header .= " * {$snippet['description']}\n";
        $header .= " * Language: {$snippet['language']}\n";
        $header .= " * Tags: " . implode(', ', $snippet['tags']) . "\n";
        $header .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= " */\n\n";
        
        return $header . $snippet['code'];
    }

    private function formatAsMarkdown(array $snippet): string
    {
        $markdown = "# {$snippet['title']}\n\n";
        $markdown .= "{$snippet['description']}\n\n";
        $markdown .= "**Language:** {$snippet['language']}  \n";
        $markdown .= "**Tags:** " . implode(', ', $snippet['tags']) . "\n\n";
        $markdown .= "```{$snippet['language']}\n";
        $markdown .= $snippet['code'] . "\n";
        $markdown .= "```\n";
        
        return $markdown;
    }
}