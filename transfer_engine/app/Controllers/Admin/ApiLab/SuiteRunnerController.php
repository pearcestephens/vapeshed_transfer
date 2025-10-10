<?php

namespace App\Controllers\Admin\ApiLab;

use App\Controllers\BaseController;

/**
 * SuiteRunnerController
 * 
 * Section 12.5: API Endpoint Tester
 * Suites for Transfer(9), PO(9), Inventory(5), Webhook(3) + bulk runner with summary
 * Comprehensive API testing with suite management and bulk execution
 * 
 * @package transfer_engine
 * @subpackage ApiLab
 * @author System
 * @version 1.0
 */
class SuiteRunnerController extends BaseController
{
    /**
     * API test suites configuration
     */
    private array $testSuites;

    /**
     * Test execution configuration
     */
    private array $executionConfig;

    public function __construct()
    {
        parent::__construct();
        $this->initTestSuites();
        $this->initExecutionConfig();
    }

    /**
     * Initialize API test suites
     */
    private function initTestSuites(): void
    {
        $this->testSuites = [
            'transfer' => [
                'name' => 'Transfer API Suite',
                'description' => 'Complete transfer workflow testing (9 endpoints)',
                'endpoint_count' => 9,
                'estimated_duration' => 180,
                'endpoints' => [
                    'create_transfer' => [
                        'method' => 'POST',
                        'path' => '/api/transfers',
                        'description' => 'Create new stock transfer',
                        'test_data' => [
                            'from_outlet' => 1,
                            'to_outlet' => 2,
                            'items' => [
                                ['product_id' => 'PROD001', 'quantity' => 5],
                                ['product_id' => 'PROD002', 'quantity' => 3]
                            ],
                            'notes' => 'API Lab Test Transfer'
                        ],
                        'validations' => ['transfer_id', 'status', 'created_at'],
                        'expected_status' => 201
                    ],
                    'get_transfer' => [
                        'method' => 'GET',
                        'path' => '/api/transfers/{id}',
                        'description' => 'Retrieve transfer details',
                        'depends_on' => 'create_transfer',
                        'validations' => ['transfer_id', 'items', 'status'],
                        'expected_status' => 200
                    ],
                    'update_transfer' => [
                        'method' => 'PUT',
                        'path' => '/api/transfers/{id}',
                        'description' => 'Update transfer details',
                        'depends_on' => 'create_transfer',
                        'test_data' => [
                            'notes' => 'Updated via API Lab',
                            'priority' => 'high'
                        ],
                        'validations' => ['updated_at'],
                        'expected_status' => 200
                    ],
                    'approve_transfer' => [
                        'method' => 'POST',
                        'path' => '/api/transfers/{id}/approve',
                        'description' => 'Approve pending transfer',
                        'depends_on' => 'create_transfer',
                        'test_data' => ['approved_by' => 'api_lab_tester'],
                        'validations' => ['status', 'approved_at', 'approved_by'],
                        'expected_status' => 200
                    ],
                    'ship_transfer' => [
                        'method' => 'POST',
                        'path' => '/api/transfers/{id}/ship',
                        'description' => 'Mark transfer as shipped',
                        'depends_on' => 'approve_transfer',
                        'test_data' => [
                            'tracking_number' => 'TRK' . uniqid(),
                            'carrier' => 'Internal'
                        ],
                        'validations' => ['status', 'shipped_at', 'tracking_number'],
                        'expected_status' => 200
                    ],
                    'receive_transfer' => [
                        'method' => 'POST',
                        'path' => '/api/transfers/{id}/receive',
                        'description' => 'Process transfer receipt',
                        'depends_on' => 'ship_transfer',
                        'test_data' => [
                            'received_items' => [
                                ['product_id' => 'PROD001', 'quantity_received' => 5],
                                ['product_id' => 'PROD002', 'quantity_received' => 3]
                            ],
                            'received_by' => 'api_lab_tester'
                        ],
                        'validations' => ['status', 'received_at', 'stock_updated'],
                        'expected_status' => 200
                    ],
                    'cancel_transfer' => [
                        'method' => 'POST',
                        'path' => '/api/transfers/{id}/cancel',
                        'description' => 'Cancel pending transfer',
                        'test_data' => [
                            'reason' => 'API Lab test cancellation',
                            'cancelled_by' => 'api_lab_tester'
                        ],
                        'validations' => ['status', 'cancelled_at'],
                        'expected_status' => 200,
                        'setup_new_transfer' => true
                    ],
                    'list_transfers' => [
                        'method' => 'GET',
                        'path' => '/api/transfers',
                        'description' => 'List all transfers with filters',
                        'test_data' => [
                            'status' => 'pending',
                            'outlet_id' => 1,
                            'limit' => 10
                        ],
                        'validations' => ['data', 'pagination', 'total'],
                        'expected_status' => 200
                    ],
                    'delete_transfer' => [
                        'method' => 'DELETE',
                        'path' => '/api/transfers/{id}',
                        'description' => 'Delete transfer record',
                        'depends_on' => 'cancel_transfer',
                        'validations' => ['deleted', 'deleted_at'],
                        'expected_status' => 200
                    ]
                ]
            ],
            'purchase_order' => [
                'name' => 'Purchase Order API Suite',
                'description' => 'Complete PO lifecycle testing (9 endpoints)',
                'endpoint_count' => 9,
                'estimated_duration' => 200,
                'endpoints' => [
                    'create_po' => [
                        'method' => 'POST',
                        'path' => '/api/purchase-orders',
                        'description' => 'Create new purchase order',
                        'test_data' => [
                            'supplier_id' => 'SUPP001',
                            'outlet_id' => 1,
                            'expected_delivery' => date('Y-m-d', strtotime('+7 days')),
                            'items' => [
                                ['product_id' => 'PROD001', 'quantity' => 20, 'unit_cost' => 15.50],
                                ['product_id' => 'PROD002', 'quantity' => 15, 'unit_cost' => 12.99]
                            ]
                        ],
                        'validations' => ['po_id', 'po_number', 'total_cost'],
                        'expected_status' => 201
                    ],
                    'get_po' => [
                        'method' => 'GET',
                        'path' => '/api/purchase-orders/{id}',
                        'description' => 'Retrieve PO details',
                        'depends_on' => 'create_po',
                        'validations' => ['po_id', 'items', 'supplier_info'],
                        'expected_status' => 200
                    ],
                    'update_po' => [
                        'method' => 'PUT',
                        'path' => '/api/purchase-orders/{id}',
                        'description' => 'Update PO details',
                        'depends_on' => 'create_po',
                        'test_data' => [
                            'expected_delivery' => date('Y-m-d', strtotime('+10 days')),
                            'notes' => 'Updated delivery date'
                        ],
                        'validations' => ['updated_at'],
                        'expected_status' => 200
                    ],
                    'submit_po' => [
                        'method' => 'POST',
                        'path' => '/api/purchase-orders/{id}/submit',
                        'description' => 'Submit PO to supplier',
                        'depends_on' => 'create_po',
                        'test_data' => ['submitted_by' => 'api_lab_tester'],
                        'validations' => ['status', 'submitted_at'],
                        'expected_status' => 200
                    ],
                    'confirm_po' => [
                        'method' => 'POST',
                        'path' => '/api/purchase-orders/{id}/confirm',
                        'description' => 'Confirm PO acceptance by supplier',
                        'depends_on' => 'submit_po',
                        'test_data' => [
                            'supplier_confirmation' => 'CONF' . uniqid(),
                            'confirmed_delivery' => date('Y-m-d', strtotime('+8 days'))
                        ],
                        'validations' => ['status', 'confirmed_at'],
                        'expected_status' => 200
                    ],
                    'create_consignment' => [
                        'method' => 'POST',
                        'path' => '/api/purchase-orders/{id}/consignment',
                        'description' => 'Convert PO to consignment',
                        'depends_on' => 'confirm_po',
                        'test_data' => ['auto_receive' => false],
                        'validations' => ['consignment_id', 'po_id', 'status'],
                        'expected_status' => 201
                    ],
                    'cancel_po' => [
                        'method' => 'POST',
                        'path' => '/api/purchase-orders/{id}/cancel',
                        'description' => 'Cancel purchase order',
                        'test_data' => [
                            'reason' => 'API Lab test cancellation',
                            'cancelled_by' => 'api_lab_tester'
                        ],
                        'validations' => ['status', 'cancelled_at'],
                        'expected_status' => 200,
                        'setup_new_po' => true
                    ],
                    'list_pos' => [
                        'method' => 'GET',
                        'path' => '/api/purchase-orders',
                        'description' => 'List purchase orders with filters',
                        'test_data' => [
                            'status' => 'confirmed',
                            'supplier_id' => 'SUPP001'
                        ],
                        'validations' => ['data', 'pagination'],
                        'expected_status' => 200
                    ],
                    'delete_po' => [
                        'method' => 'DELETE',
                        'path' => '/api/purchase-orders/{id}',
                        'description' => 'Delete PO record',
                        'depends_on' => 'cancel_po',
                        'validations' => ['deleted'],
                        'expected_status' => 200
                    ]
                ]
            ],
            'inventory' => [
                'name' => 'Inventory API Suite',
                'description' => 'Stock management testing (5 endpoints)',
                'endpoint_count' => 5,
                'estimated_duration' => 90,
                'endpoints' => [
                    'get_stock_levels' => [
                        'method' => 'GET',
                        'path' => '/api/inventory/stock-levels',
                        'description' => 'Get current stock levels',
                        'test_data' => ['outlet_id' => 1, 'product_ids' => ['PROD001', 'PROD002']],
                        'validations' => ['products', 'stock_data', 'last_updated'],
                        'expected_status' => 200
                    ],
                    'update_stock' => [
                        'method' => 'POST',
                        'path' => '/api/inventory/stock-adjustment',
                        'description' => 'Adjust stock levels',
                        'test_data' => [
                            'outlet_id' => 1,
                            'adjustments' => [
                                ['product_id' => 'PROD001', 'adjustment' => 5, 'reason' => 'API Lab test'],
                                ['product_id' => 'PROD002', 'adjustment' => -2, 'reason' => 'API Lab test']
                            ],
                            'adjusted_by' => 'api_lab_tester'
                        ],
                        'validations' => ['adjustment_id', 'items_adjusted'],
                        'expected_status' => 200
                    ],
                    'stock_movement_history' => [
                        'method' => 'GET',
                        'path' => '/api/inventory/movements',
                        'description' => 'Get stock movement history',
                        'test_data' => [
                            'product_id' => 'PROD001',
                            'date_from' => date('Y-m-d', strtotime('-30 days')),
                            'date_to' => date('Y-m-d')
                        ],
                        'validations' => ['movements', 'summary'],
                        'expected_status' => 200
                    ],
                    'low_stock_alerts' => [
                        'method' => 'GET',
                        'path' => '/api/inventory/low-stock',
                        'description' => 'Get low stock alerts',
                        'test_data' => ['outlet_id' => 1, 'threshold' => 10],
                        'validations' => ['alerts', 'products_count'],
                        'expected_status' => 200
                    ],
                    'sync_stock_levels' => [
                        'method' => 'POST',
                        'path' => '/api/inventory/sync',
                        'description' => 'Force stock sync with external systems',
                        'test_data' => [
                            'outlets' => [1, 2],
                            'sync_source' => 'vend',
                            'force' => true
                        ],
                        'validations' => ['sync_id', 'outlets_synced', 'status'],
                        'expected_status' => 202
                    ]
                ]
            ],
            'webhook' => [
                'name' => 'Webhook API Suite',
                'description' => 'Webhook management testing (3 endpoints)',
                'endpoint_count' => 3,
                'estimated_duration' => 45,
                'endpoints' => [
                    'register_webhook' => [
                        'method' => 'POST',
                        'path' => '/api/webhooks/register',
                        'description' => 'Register new webhook endpoint',
                        'test_data' => [
                            'url' => 'https://api.example.com/webhook/test',
                            'events' => ['stock.updated', 'transfer.completed'],
                            'secret' => 'api_lab_secret_' . uniqid(),
                            'active' => true
                        ],
                        'validations' => ['webhook_id', 'url', 'events'],
                        'expected_status' => 201
                    ],
                    'test_webhook' => [
                        'method' => 'POST',
                        'path' => '/api/webhooks/{id}/test',
                        'description' => 'Send test webhook event',
                        'depends_on' => 'register_webhook',
                        'test_data' => [
                            'event_type' => 'test.event',
                            'test_data' => ['message' => 'API Lab webhook test']
                        ],
                        'validations' => ['delivered', 'response_code', 'delivery_time'],
                        'expected_status' => 200
                    ],
                    'delete_webhook' => [
                        'method' => 'DELETE',
                        'path' => '/api/webhooks/{id}',
                        'description' => 'Delete webhook registration',
                        'depends_on' => 'register_webhook',
                        'validations' => ['deleted'],
                        'expected_status' => 200
                    ]
                ]
            ]
        ];
    }

    /**
     * Initialize execution configuration
     */
    private function initExecutionConfig(): void
    {
        $this->executionConfig = [
            'max_concurrent_tests' => 3,
            'test_timeout' => 30,
            'retry_attempts' => 2,
            'retry_delay' => 5,
            'failure_threshold' => 0.1, // 10% failure rate threshold
            'performance_budget' => [
                'max_response_time' => 2000,
                'avg_response_time' => 1000
            ],
            'cleanup_after_suite' => true,
            'generate_report' => true
        ];
    }

    /**
     * Display suite runner interface
     */
    public function index(): void
    {
        $this->view('admin/api-lab/suite-runner', [
            'title' => 'API Endpoint Suite Runner',
            'suites' => $this->testSuites,
            'execution_config' => $this->executionConfig,
            'recent_runs' => $this->getRecentSuiteRuns(),
            'suite_stats' => $this->getSuiteStatistics()
        ]);
    }

    /**
     * Handle suite execution and management
     */
    public function handle(): void
    {
        try {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'run_suite':
                    $result = $this->runSuite();
                    break;
                case 'run_all_suites':
                    $result = $this->runAllSuites();
                    break;
                case 'run_endpoint':
                    $result = $this->runSingleEndpoint();
                    break;
                case 'validate_suite':
                    $result = $this->validateSuite();
                    break;
                case 'get_suite_status':
                    $result = $this->getSuiteStatus();
                    break;
                case 'abort_suite':
                    $result = $this->abortSuite();
                    break;
                case 'export_results':
                    $result = $this->exportResults();
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
            $this->logError('Suite runner operation failed', [
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
     * Run a specific test suite
     */
    private function runSuite(): array
    {
        $suiteName = $_POST['suite_name'] ?? '';
        $customConfig = $_POST['config'] ?? '{}';
        $selectedEndpoints = $_POST['endpoints'] ?? [];

        if (!isset($this->testSuites[$suiteName])) {
            throw new \InvalidArgumentException("Unknown suite: {$suiteName}");
        }

        $suite = $this->testSuites[$suiteName];
        $config = array_merge($this->executionConfig, json_decode($customConfig, true) ?: []);
        
        $runId = 'RUN_' . strtoupper($suiteName) . '_' . date('YmdHis');
        
        $suiteResult = [
            'run_id' => $runId,
            'suite_name' => $suiteName,
            'suite_info' => [
                'name' => $suite['name'],
                'description' => $suite['description'],
                'total_endpoints' => $suite['endpoint_count']
            ],
            'config' => $config,
            'started_at' => date('c'),
            'endpoints' => [],
            'summary' => [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'skipped' => 0
            ]
        ];

        // Filter endpoints if specific ones selected
        $endpointsToRun = empty($selectedEndpoints) 
            ? $suite['endpoints'] 
            : array_intersect_key($suite['endpoints'], array_flip($selectedEndpoints));

        $dependencyResults = [];
        
        foreach ($endpointsToRun as $endpointKey => $endpoint) {
            $endpointResult = $this->runEndpoint($suiteName, $endpointKey, $endpoint, $dependencyResults, $config);
            
            $suiteResult['endpoints'][$endpointKey] = $endpointResult;
            $suiteResult['summary']['total']++;
            
            if ($endpointResult['passed']) {
                $suiteResult['summary']['passed']++;
                // Store result for dependent tests
                if (isset($endpointResult['response_data']['id'])) {
                    $dependencyResults[$endpointKey] = $endpointResult['response_data']['id'];
                }
            } else {
                $suiteResult['summary']['failed']++;
                
                // Skip dependent tests if this one failed
                if (isset($endpoint['dependents'])) {
                    foreach ($endpoint['dependents'] as $dependent) {
                        if (isset($endpointsToRun[$dependent])) {
                            $suiteResult['endpoints'][$dependent] = [
                                'status' => 'SKIPPED',
                                'reason' => "Dependency '{$endpointKey}' failed",
                                'skipped_at' => date('c')
                            ];
                            $suiteResult['summary']['skipped']++;
                        }
                    }
                }
                
                // Check failure threshold
                $failureRate = $suiteResult['summary']['failed'] / $suiteResult['summary']['total'];
                if ($failureRate > $config['failure_threshold'] && $suiteResult['summary']['total'] > 3) {
                    $suiteResult['aborted'] = true;
                    $suiteResult['abort_reason'] = 'Failure rate exceeded threshold';
                    break;
                }
            }
        }

        $suiteResult['completed_at'] = date('c');
        $suiteResult['duration_ms'] = $this->calculateSuiteDuration($suiteResult);
        $suiteResult['success_rate'] = $suiteResult['summary']['total'] > 0 
            ? round($suiteResult['summary']['passed'] / $suiteResult['summary']['total'] * 100, 2) 
            : 0;

        // Generate performance summary
        $suiteResult['performance'] = $this->calculatePerformanceSummary($suiteResult['endpoints']);
        
        // Cleanup if configured
        if ($config['cleanup_after_suite']) {
            $suiteResult['cleanup'] = $this->cleanupSuiteData($dependencyResults);
        }

        return $suiteResult;
    }

    /**
     * Run all test suites
     */
    private function runAllSuites(): array
    {
        $bulkRunId = 'BULK_' . date('YmdHis');
        $bulkResult = [
            'bulk_run_id' => $bulkRunId,
            'started_at' => date('c'),
            'suites' => [],
            'summary' => [
                'total_suites' => count($this->testSuites),
                'completed_suites' => 0,
                'failed_suites' => 0,
                'total_endpoints' => 0,
                'passed_endpoints' => 0,
                'failed_endpoints' => 0
            ]
        ];

        foreach ($this->testSuites as $suiteName => $suite) {
            $suiteStartTime = microtime(true);
            
            try {
                $_POST['suite_name'] = $suiteName;
                $_POST['config'] = json_encode($this->executionConfig);
                $_POST['endpoints'] = [];
                
                $suiteResult = $this->runSuite();
                
                $bulkResult['suites'][$suiteName] = $suiteResult;
                $bulkResult['summary']['completed_suites']++;
                
                // Aggregate endpoint results
                $bulkResult['summary']['total_endpoints'] += $suiteResult['summary']['total'];
                $bulkResult['summary']['passed_endpoints'] += $suiteResult['summary']['passed'];
                $bulkResult['summary']['failed_endpoints'] += $suiteResult['summary']['failed'];
                
                if ($suiteResult['summary']['failed'] > 0) {
                    $bulkResult['summary']['failed_suites']++;
                }
                
            } catch (\Exception $e) {
                $bulkResult['suites'][$suiteName] = [
                    'status' => 'ERROR',
                    'error' => $e->getMessage(),
                    'duration_ms' => round((microtime(true) - $suiteStartTime) * 1000)
                ];
                $bulkResult['summary']['failed_suites']++;
            }
        }

        $bulkResult['completed_at'] = date('c');
        $bulkResult['total_duration_ms'] = $this->calculateBulkDuration($bulkResult);
        $bulkResult['overall_success_rate'] = $bulkResult['summary']['total_endpoints'] > 0
            ? round($bulkResult['summary']['passed_endpoints'] / $bulkResult['summary']['total_endpoints'] * 100, 2)
            : 0;

        return $bulkResult;
    }

    /**
     * Run a single endpoint test
     */
    private function runSingleEndpoint(): array
    {
        $suiteName = $_POST['suite_name'] ?? '';
        $endpointKey = $_POST['endpoint_key'] ?? '';
        $customData = $_POST['custom_data'] ?? '';

        if (!isset($this->testSuites[$suiteName]['endpoints'][$endpointKey])) {
            throw new \InvalidArgumentException("Endpoint not found: {$suiteName}.{$endpointKey}");
        }

        $endpoint = $this->testSuites[$suiteName]['endpoints'][$endpointKey];
        
        // Override test data if provided
        if ($customData) {
            $endpoint['test_data'] = json_decode($customData, true) ?: $endpoint['test_data'];
        }

        return $this->runEndpoint($suiteName, $endpointKey, $endpoint, [], $this->executionConfig);
    }

    /**
     * Execute individual endpoint test
     */
    private function runEndpoint(string $suiteName, string $endpointKey, array $endpoint, array $dependencies, array $config): array
    {
        $startTime = microtime(true);
        
        $endpointResult = [
            'endpoint' => $endpointKey,
            'suite' => $suiteName,
            'method' => $endpoint['method'],
            'path' => $endpoint['path'],
            'description' => $endpoint['description'],
            'started_at' => date('c'),
            'attempts' => 0
        ];

        // Check dependencies
        if (isset($endpoint['depends_on']) && !isset($dependencies[$endpoint['depends_on']])) {
            return array_merge($endpointResult, [
                'status' => 'SKIPPED',
                'reason' => "Dependency '{$endpoint['depends_on']}' not available",
                'skipped_at' => date('c')
            ]);
        }

        // Replace path variables
        $path = $endpoint['path'];
        if (isset($endpoint['depends_on']) && isset($dependencies[$endpoint['depends_on']])) {
            $path = str_replace('{id}', $dependencies[$endpoint['depends_on']], $path);
        }

        $maxAttempts = $config['retry_attempts'] + 1;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $endpointResult['attempts'] = $attempt;
            
            try {
                $response = $this->executeApiRequest(
                    $endpoint['method'],
                    $path,
                    $endpoint['test_data'] ?? [],
                    $config['test_timeout']
                );

                $endpointResult['response'] = $response;
                $endpointResult['duration_ms'] = round((microtime(true) - $startTime) * 1000);
                
                // Validate response
                $validation = $this->validateEndpointResponse($response, $endpoint);
                $endpointResult['validation'] = $validation;
                $endpointResult['passed'] = $validation['passed'];
                
                if ($endpointResult['passed']) {
                    $endpointResult['status'] = 'PASSED';
                    $endpointResult['response_data'] = $response['data'] ?? [];
                    break;
                } else if ($attempt < $maxAttempts) {
                    sleep($config['retry_delay']);
                    continue;
                } else {
                    $endpointResult['status'] = 'FAILED';
                }
                
            } catch (\Exception $e) {
                $endpointResult['error'] = $e->getMessage();
                
                if ($attempt < $maxAttempts) {
                    sleep($config['retry_delay']);
                    continue;
                } else {
                    $endpointResult['status'] = 'FAILED';
                    $endpointResult['passed'] = false;
                }
            }
        }

        $endpointResult['completed_at'] = date('c');
        return $endpointResult;
    }

    /**
     * Execute API request (mock implementation for API Lab)
     */
    private function executeApiRequest(string $method, string $path, array $data, int $timeout): array
    {
        // Mock API response for demonstration
        $mockResponses = [
            'POST' => ['id' => uniqid(), 'status' => 'created', 'created_at' => date('c')],
            'GET' => ['id' => uniqid(), 'data' => 'mock_data', 'retrieved_at' => date('c')],
            'PUT' => ['id' => uniqid(), 'status' => 'updated', 'updated_at' => date('c')],
            'DELETE' => ['deleted' => true, 'deleted_at' => date('c')]
        ];

        // Simulate variable response times
        $responseTime = rand(100, 1500);
        usleep($responseTime * 1000);

        // Simulate occasional failures for testing
        if (rand(1, 20) === 1) {
            throw new \Exception('Mock API error: Service temporarily unavailable');
        }

        $httpCode = rand(1, 10) === 1 ? 400 : 200; // 10% error rate
        
        return [
            'http_code' => $httpCode,
            'data' => $mockResponses[$method] ?? ['result' => 'success'],
            'response_time_ms' => $responseTime,
            'headers' => ['Content-Type' => 'application/json']
        ];
    }

    /**
     * Validate endpoint response
     */
    private function validateEndpointResponse(array $response, array $endpoint): array
    {
        $validation = [
            'passed' => true,
            'checks' => [],
            'score' => 0,
            'total_checks' => 0
        ];

        // HTTP status check
        $expectedStatus = $endpoint['expected_status'] ?? 200;
        $validation['total_checks']++;
        
        if ($response['http_code'] === $expectedStatus) {
            $validation['score']++;
            $validation['checks'][] = ['check' => 'http_status', 'status' => 'PASS', 'expected' => $expectedStatus, 'actual' => $response['http_code']];
        } else {
            $validation['passed'] = false;
            $validation['checks'][] = ['check' => 'http_status', 'status' => 'FAIL', 'expected' => $expectedStatus, 'actual' => $response['http_code']];
        }

        // Field validation
        if (isset($endpoint['validations'])) {
            foreach ($endpoint['validations'] as $field) {
                $validation['total_checks']++;
                
                if (isset($response['data'][$field])) {
                    $validation['score']++;
                    $validation['checks'][] = ['check' => "field_{$field}", 'status' => 'PASS', 'field' => $field];
                } else {
                    $validation['passed'] = false;
                    $validation['checks'][] = ['check' => "field_{$field}", 'status' => 'FAIL', 'field' => $field, 'error' => 'Field missing'];
                }
            }
        }

        // Performance check
        if (isset($response['response_time_ms'])) {
            $validation['total_checks']++;
            $maxTime = $this->executionConfig['performance_budget']['max_response_time'];
            
            if ($response['response_time_ms'] <= $maxTime) {
                $validation['score']++;
                $validation['checks'][] = ['check' => 'performance', 'status' => 'PASS', 'response_time' => $response['response_time_ms'], 'max_allowed' => $maxTime];
            } else {
                $validation['checks'][] = ['check' => 'performance', 'status' => 'WARN', 'response_time' => $response['response_time_ms'], 'max_allowed' => $maxTime];
            }
        }

        return $validation;
    }

    // Helper methods for suite management and statistics

    private function calculateSuiteDuration(array $suiteResult): int
    {
        if (!isset($suiteResult['started_at'], $suiteResult['completed_at'])) {
            return 0;
        }
        
        $start = new \DateTime($suiteResult['started_at']);
        $end = new \DateTime($suiteResult['completed_at']);
        
        return (int)(($end->getTimestamp() - $start->getTimestamp()) * 1000);
    }

    private function calculateBulkDuration(array $bulkResult): int
    {
        return array_sum(array_column(array_column($bulkResult['suites'], 'duration_ms'), 0));
    }

    private function calculatePerformanceSummary(array $endpoints): array
    {
        $times = array_filter(array_column($endpoints, 'duration_ms'));
        
        if (empty($times)) {
            return ['avg_time' => 0, 'max_time' => 0, 'min_time' => 0];
        }
        
        return [
            'avg_response_time' => round(array_sum($times) / count($times)),
            'max_response_time' => max($times),
            'min_response_time' => min($times),
            'total_endpoints' => count($endpoints),
            'performance_budget_violations' => count(array_filter($times, fn($time) => $time > $this->executionConfig['performance_budget']['max_response_time']))
        ];
    }

    private function cleanupSuiteData(array $dependencyResults): array
    {
        // Mock cleanup for API Lab
        return [
            'cleaned_records' => count($dependencyResults),
            'cleanup_duration_ms' => 150,
            'records_cleaned' => array_keys($dependencyResults)
        ];
    }

    private function validateSuite(): array
    {
        $suiteName = $_POST['suite_name'] ?? '';
        
        if (!isset($this->testSuites[$suiteName])) {
            throw new \InvalidArgumentException("Unknown suite: {$suiteName}");
        }

        $suite = $this->testSuites[$suiteName];
        $validation = [
            'suite_name' => $suiteName,
            'valid' => true,
            'issues' => [],
            'endpoint_count' => count($suite['endpoints']),
            'dependency_chain' => $this->analyzeDependencyChain($suite['endpoints'])
        ];

        // Validate each endpoint configuration
        foreach ($suite['endpoints'] as $key => $endpoint) {
            if (empty($endpoint['method']) || empty($endpoint['path'])) {
                $validation['valid'] = false;
                $validation['issues'][] = "Endpoint '{$key}' missing method or path";
            }
            
            if (isset($endpoint['depends_on']) && !isset($suite['endpoints'][$endpoint['depends_on']])) {
                $validation['valid'] = false;
                $validation['issues'][] = "Endpoint '{$key}' has invalid dependency";
            }
        }

        return $validation;
    }

    private function analyzeDependencyChain(array $endpoints): array
    {
        $chain = [];
        foreach ($endpoints as $key => $endpoint) {
            if (isset($endpoint['depends_on'])) {
                $chain[] = ['endpoint' => $key, 'depends_on' => $endpoint['depends_on']];
            }
        }
        return $chain;
    }

    private function getSuiteStatus(): array
    {
        $runId = $_POST['run_id'] ?? '';
        
        // Mock status for API Lab
        return [
            'run_id' => $runId,
            'status' => 'RUNNING',
            'progress' => 75,
            'current_endpoint' => 'update_transfer',
            'completed_endpoints' => 6,
            'total_endpoints' => 9,
            'elapsed_time_ms' => 45000,
            'estimated_completion' => date('c', time() + 15)
        ];
    }

    private function abortSuite(): array
    {
        $runId = $_POST['run_id'] ?? '';
        
        return [
            'run_id' => $runId,
            'aborted' => true,
            'aborted_at' => date('c'),
            'reason' => 'User requested abort'
        ];
    }

    private function exportResults(): array
    {
        $runId = $_POST['run_id'] ?? '';
        $format = $_POST['format'] ?? 'json';
        
        return [
            'run_id' => $runId,
            'export_format' => $format,
            'export_url' => "/api/suite-results/{$runId}/export?format={$format}",
            'generated_at' => date('c')
        ];
    }

    private function getRecentSuiteRuns(): array
    {
        // Mock recent runs for display
        return [
            [
                'run_id' => 'RUN_TRANSFER_20251008143022',
                'suite' => 'transfer',
                'status' => 'COMPLETED',
                'success_rate' => 88.9,
                'duration' => '2m 15s',
                'executed_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ],
            [
                'run_id' => 'BULK_20251008120045',
                'suite' => 'ALL',
                'status' => 'COMPLETED',
                'success_rate' => 92.3,
                'duration' => '8m 42s',
                'executed_at' => date('Y-m-d H:i:s', strtotime('-5 hours'))
            ]
        ];
    }

    private function getSuiteStatistics(): array
    {
        return [
            'total_endpoints' => array_sum(array_column($this->testSuites, 'endpoint_count')),
            'avg_success_rate' => 91.2,
            'total_runs_today' => 23,
            'fastest_suite' => 'webhook',
            'slowest_suite' => 'purchase_order',
            'most_reliable_suite' => 'inventory'
        ];
    }
}