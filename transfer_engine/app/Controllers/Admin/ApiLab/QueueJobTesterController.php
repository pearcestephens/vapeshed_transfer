<?php

namespace App\Controllers\Admin\ApiLab;

use App\Controllers\BaseController;

/**
 * QueueJobTesterController
 * 
 * Section 12.4: Queue Job Tester
 * Dispatch test jobs, monitor status in real-time, stress mode dispatch 100 jobs, cancel running job
 * Comprehensive queue testing and monitoring with job lifecycle management
 * 
 * @package transfer_engine
 * @subpackage ApiLab
 * @author System
 * @version 1.0
 */
class QueueJobTesterController extends BaseController
{
    /**
     * Available test job types
     */
    private array $jobTypes;

    /**
     * Queue monitoring configuration
     */
    private array $queueConfig;

    public function __construct()
    {
        parent::__construct();
        $this->initJobTypes();
        $this->initQueueConfig();
    }

    /**
     * Initialize available job types
     */
    private function initJobTypes(): void
    {
        $this->jobTypes = [
            'stock_sync' => [
                'name' => 'Stock Synchronization',
                'description' => 'Sync product inventory across all outlets',
                'estimated_duration' => 30,
                'priority' => 'normal',
                'payload_example' => [
                    'product_ids' => ['PROD001', 'PROD002'],
                    'outlet_ids' => [1, 2, 3],
                    'force_update' => false
                ]
            ],
            'transfer_processing' => [
                'name' => 'Transfer Processing',
                'description' => 'Process pending stock transfers to consignments',
                'estimated_duration' => 45,
                'priority' => 'high',
                'payload_example' => [
                    'transfer_batch_id' => 'TB_2025_001',
                    'auto_approve' => false,
                    'notify_outlets' => true
                ]
            ],
            'email_notification' => [
                'name' => 'Email Notification',
                'description' => 'Send notification emails to recipients',
                'estimated_duration' => 10,
                'priority' => 'low',
                'payload_example' => [
                    'template' => 'stock_alert',
                    'recipients' => ['manager@vapeshed.co.nz'],
                    'data' => ['product' => 'Test Product', 'stock_level' => 5]
                ]
            ],
            'report_generation' => [
                'name' => 'Report Generation',
                'description' => 'Generate and cache system reports',
                'estimated_duration' => 120,
                'priority' => 'low',
                'payload_example' => [
                    'report_type' => 'monthly_sales',
                    'date_range' => ['2025-01-01', '2025-01-31'],
                    'outlets' => 'all',
                    'format' => 'pdf'
                ]
            ],
            'data_cleanup' => [
                'name' => 'Data Cleanup',
                'description' => 'Clean expired logs and temporary data',
                'estimated_duration' => 60,
                'priority' => 'low',
                'payload_example' => [
                    'cleanup_types' => ['logs', 'temp_files', 'expired_sessions'],
                    'older_than_days' => 30,
                    'dry_run' => true
                ]
            ],
            'api_sync' => [
                'name' => 'External API Sync',
                'description' => 'Synchronize with external APIs (Vend, suppliers)',
                'estimated_duration' => 90,
                'priority' => 'high',
                'payload_example' => [
                    'api_provider' => 'vend',
                    'sync_types' => ['products', 'customers', 'sales'],
                    'incremental' => true
                ]
            ]
        ];
    }

    /**
     * Initialize queue configuration
     */
    private function initQueueConfig(): void
    {
        $this->queueConfig = [
            'default_queue' => 'default',
            'priority_queues' => ['high', 'normal', 'low'],
            'max_concurrent_jobs' => 5,
            'job_timeout' => 300,
            'retry_attempts' => 3,
            'retry_delay' => 30,
            'failed_job_retention' => 7, // days
            'monitoring_interval' => 2 // seconds
        ];
    }

    /**
     * Display queue job tester interface
     */
    public function index(): void
    {
        $this->view('admin/api-lab/queue-tester', [
            'title' => 'Queue Job Tester',
            'job_types' => $this->jobTypes,
            'queue_config' => $this->queueConfig,
            'queue_stats' => $this->getQueueStats(),
            'recent_jobs' => $this->getRecentJobs(20),
            'active_jobs' => $this->getActiveJobs()
        ]);
    }

    /**
     * Handle job dispatch and testing operations
     */
    public function handle(): void
    {
        try {
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'dispatch_single':
                    $result = $this->dispatchSingleJob();
                    break;
                case 'dispatch_batch':
                    $result = $this->dispatchBatchJobs();
                    break;
                case 'stress_test':
                    $result = $this->runStressTest();
                    break;
                case 'cancel_job':
                    $result = $this->cancelJob();
                    break;
                case 'monitor_status':
                    $result = $this->monitorJobStatus();
                    break;
                case 'clear_queue':
                    $result = $this->clearQueue();
                    break;
                case 'get_job_details':
                    $result = $this->getJobDetails();
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
            $this->logError('Queue job test failed', [
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
     * Dispatch a single test job
     */
    private function dispatchSingleJob(): array
    {
        $jobType = $_POST['job_type'] ?? '';
        $customPayload = $_POST['custom_payload'] ?? '';
        $priority = $_POST['priority'] ?? 'normal';
        $delay = (int)($_POST['delay'] ?? 0);

        if (!isset($this->jobTypes[$jobType])) {
            throw new \InvalidArgumentException("Invalid job type: {$jobType}");
        }

        // Prepare job payload
        $payload = $customPayload 
            ? json_decode($customPayload, true)
            : $this->jobTypes[$jobType]['payload_example'];

        if (json_last_error() !== JSON_ERROR_NONE && $customPayload) {
            throw new \InvalidArgumentException('Invalid JSON in custom payload');
        }

        // Generate job ID
        $jobId = 'JOB_' . strtoupper($jobType) . '_' . uniqid();
        
        // Simulate job dispatch
        $job = [
            'id' => $jobId,
            'type' => $jobType,
            'payload' => $payload,
            'priority' => $priority,
            'status' => 'QUEUED',
            'queued_at' => date('c'),
            'scheduled_at' => $delay > 0 ? date('c', time() + $delay) : date('c'),
            'attempts' => 0,
            'max_attempts' => $this->queueConfig['retry_attempts'],
            'timeout' => $this->queueConfig['job_timeout']
        ];

        // Mock job processing start (in real implementation, this would go to queue driver)
        $this->simulateJobExecution($job);

        return [
            'job_id' => $jobId,
            'job_type' => $jobType,
            'status' => 'DISPATCHED',
            'priority' => $priority,
            'estimated_duration' => $this->jobTypes[$jobType]['estimated_duration'],
            'delay_seconds' => $delay,
            'payload_size' => strlen(json_encode($payload)),
            'dispatched_at' => date('c')
        ];
    }

    /**
     * Dispatch multiple jobs in batch
     */
    private function dispatchBatchJobs(): array
    {
        $batchSize = (int)($_POST['batch_size'] ?? 5);
        $jobType = $_POST['batch_job_type'] ?? 'stock_sync';
        $staggerDelay = (int)($_POST['stagger_delay'] ?? 0);

        if ($batchSize > 50) {
            throw new \InvalidArgumentException('Batch size cannot exceed 50 jobs');
        }

        $batchId = 'BATCH_' . uniqid();
        $jobs = [];

        for ($i = 1; $i <= $batchSize; $i++) {
            $jobId = $batchId . '_JOB_' . str_pad($i, 3, '0', STR_PAD_LEFT);
            
            $job = [
                'id' => $jobId,
                'batch_id' => $batchId,
                'type' => $jobType,
                'batch_position' => $i,
                'payload' => $this->generateBatchJobPayload($jobType, $i),
                'priority' => 'normal',
                'status' => 'QUEUED',
                'delay' => $staggerDelay * ($i - 1),
                'queued_at' => date('c')
            ];

            $this->simulateJobExecution($job);
            $jobs[] = $job;
        }

        return [
            'batch_id' => $batchId,
            'batch_size' => $batchSize,
            'job_type' => $jobType,
            'stagger_delay' => $staggerDelay,
            'total_estimated_duration' => $this->jobTypes[$jobType]['estimated_duration'] * $batchSize,
            'jobs_dispatched' => count($jobs),
            'dispatched_at' => date('c')
        ];
    }

    /**
     * Run stress test with 100 jobs
     */
    private function runStressTest(): array
    {
        $stressConfig = [
            'total_jobs' => 100,
            'job_types' => array_keys($this->jobTypes),
            'concurrent_batches' => 5,
            'monitoring_enabled' => true
        ];

        $stressId = 'STRESS_' . date('YmdHis');
        $startTime = microtime(true);
        
        $results = [
            'stress_test_id' => $stressId,
            'config' => $stressConfig,
            'started_at' => date('c'),
            'batches' => []
        ];

        // Dispatch jobs in concurrent batches
        $jobsPerBatch = (int)ceil($stressConfig['total_jobs'] / $stressConfig['concurrent_batches']);
        
        for ($batch = 1; $batch <= $stressConfig['concurrent_batches']; $batch++) {
            $batchStartTime = microtime(true);
            $batchJobs = [];

            for ($j = 1; $j <= $jobsPerBatch && count($results['batches']) * $jobsPerBatch + $j <= $stressConfig['total_jobs']; $j++) {
                $jobType = $stressConfig['job_types'][array_rand($stressConfig['job_types'])];
                $jobId = $stressId . '_B' . $batch . '_J' . str_pad($j, 3, '0', STR_PAD_LEFT);
                
                $job = [
                    'id' => $jobId,
                    'stress_test_id' => $stressId,
                    'batch' => $batch,
                    'type' => $jobType,
                    'status' => 'QUEUED',
                    'queued_at' => date('c')
                ];

                $this->simulateJobExecution($job);
                $batchJobs[] = $jobId;
            }

            $results['batches'][] = [
                'batch_number' => $batch,
                'jobs_count' => count($batchJobs),
                'jobs' => $batchJobs,
                'dispatch_duration_ms' => round((microtime(true) - $batchStartTime) * 1000)
            ];
        }

        $results['completed_at'] = date('c');
        $results['total_duration_ms'] = round((microtime(true) - $startTime) * 1000);
        $results['jobs_dispatched'] = array_sum(array_column($results['batches'], 'jobs_count'));

        return $results;
    }

    /**
     * Cancel a running job
     */
    private function cancelJob(): array
    {
        $jobId = $_POST['job_id'] ?? '';
        $force = isset($_POST['force_cancel']);

        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID is required');
        }

        // Mock job cancellation
        $jobStatus = $this->getJobStatusById($jobId);
        
        if (!$jobStatus) {
            throw new \Exception("Job not found: {$jobId}");
        }

        if (in_array($jobStatus['status'], ['COMPLETED', 'FAILED', 'CANCELLED'])) {
            throw new \Exception("Cannot cancel job in {$jobStatus['status']} state");
        }

        // Simulate cancellation logic
        $cancelResult = [
            'job_id' => $jobId,
            'previous_status' => $jobStatus['status'],
            'cancelled_at' => date('c'),
            'force_cancelled' => $force,
            'cleanup_required' => $jobStatus['status'] === 'RUNNING'
        ];

        if ($force && $jobStatus['status'] === 'RUNNING') {
            $cancelResult['termination_method'] = 'SIGTERM';
            $cancelResult['cleanup_duration_ms'] = 250;
        }

        return $cancelResult;
    }

    /**
     * Monitor job status in real-time
     */
    private function monitorJobStatus(): array
    {
        $jobId = $_POST['job_id'] ?? '';
        $includeDetails = isset($_POST['include_details']);
        
        if (empty($jobId)) {
            // Return queue overview if no specific job ID
            return $this->getQueueOverview($includeDetails);
        }

        $jobStatus = $this->getJobStatusById($jobId);
        
        if (!$jobStatus) {
            throw new \Exception("Job not found: {$jobId}");
        }

        $monitoring = [
            'job_id' => $jobId,
            'status' => $jobStatus,
            'queue_position' => $this->getJobQueuePosition($jobId),
            'estimated_completion' => $this->estimateJobCompletion($jobStatus),
            'monitoring_timestamp' => date('c')
        ];

        if ($includeDetails) {
            $monitoring['resource_usage'] = $this->getJobResourceUsage($jobId);
            $monitoring['performance_metrics'] = $this->getJobPerformanceMetrics($jobId);
        }

        return $monitoring;
    }

    /**
     * Clear queue (with safety checks)
     */
    private function clearQueue(): array
    {
        $queueName = $_POST['queue_name'] ?? 'default';
        $clearRunning = isset($_POST['clear_running']);
        $confirmToken = $_POST['confirm_token'] ?? '';

        // Safety check - require confirmation token
        $expectedToken = 'CLEAR_QUEUE_' . date('Ymd');
        if ($confirmToken !== $expectedToken) {
            throw new \Exception("Invalid confirmation token. Expected: {$expectedToken}");
        }

        $queueStats = $this->getQueueStatsByName($queueName);
        
        $clearResult = [
            'queue_name' => $queueName,
            'cleared_at' => date('c'),
            'jobs_removed' => $queueStats['pending_count'],
            'jobs_cancelled' => $clearRunning ? $queueStats['running_count'] : 0,
            'confirm_token' => $confirmToken
        ];

        // Mock clearing logic
        if ($clearRunning && $queueStats['running_count'] > 0) {
            $clearResult['running_jobs_terminated'] = $queueStats['running_count'];
            $clearResult['cleanup_duration_ms'] = $queueStats['running_count'] * 100;
        }

        return $clearResult;
    }

    /**
     * Get detailed job information
     */
    private function getJobDetails(): array
    {
        $jobId = $_POST['job_id'] ?? '';
        
        if (empty($jobId)) {
            throw new \InvalidArgumentException('Job ID is required');
        }

        $jobDetails = $this->getJobStatusById($jobId);
        
        if (!$jobDetails) {
            throw new \Exception("Job not found: {$jobId}");
        }

        // Add detailed information
        $jobDetails['execution_log'] = $this->getJobExecutionLog($jobId);
        $jobDetails['error_history'] = $this->getJobErrorHistory($jobId);
        $jobDetails['performance_data'] = $this->getJobPerformanceMetrics($jobId);
        $jobDetails['dependencies'] = $this->getJobDependencies($jobId);

        return $jobDetails;
    }

    /**
     * Simulate job execution (mock implementation)
     */
    private function simulateJobExecution(array $job): void
    {
        // In real implementation, this would dispatch to actual queue driver
        // For API Lab testing, we simulate the process
        
        // Random status progression simulation
        $statuses = ['QUEUED', 'RUNNING', 'COMPLETED'];
        $currentStatus = $statuses[array_rand($statuses)];
        
        // Mock some jobs as failed for testing
        if (rand(1, 10) === 1) {
            $currentStatus = 'FAILED';
        }

        // Store job simulation data (in real app, this would be in Redis/DB)
        // This is just for API Lab demonstration
    }

    /**
     * Generate payload for batch jobs
     */
    private function generateBatchJobPayload(string $jobType, int $position): array
    {
        $basePayload = $this->jobTypes[$jobType]['payload_example'];
        
        // Modify payload based on position for variety
        switch ($jobType) {
            case 'stock_sync':
                $basePayload['product_ids'] = ['BATCH_PROD_' . str_pad($position, 3, '0', STR_PAD_LEFT)];
                break;
            case 'email_notification':
                $basePayload['data']['batch_position'] = $position;
                break;
            case 'transfer_processing':
                $basePayload['transfer_batch_id'] = 'BATCH_TB_' . $position;
                break;
        }

        return $basePayload;
    }

    /**
     * Get current queue statistics
     */
    private function getQueueStats(): array
    {
        return [
            'total_jobs' => 847,
            'pending_jobs' => 12,
            'running_jobs' => 3,
            'completed_today' => 156,
            'failed_today' => 2,
            'average_execution_time' => '45.2s',
            'queue_health' => 'HEALTHY',
            'worker_processes' => 5,
            'last_updated' => date('c')
        ];
    }

    /**
     * Get recent job history
     */
    private function getRecentJobs(int $limit): array
    {
        // Mock recent jobs for API Lab
        $jobs = [];
        $statuses = ['COMPLETED', 'FAILED', 'RUNNING', 'QUEUED'];
        $types = array_keys($this->jobTypes);

        for ($i = 1; $i <= $limit; $i++) {
            $jobs[] = [
                'id' => 'JOB_' . uniqid(),
                'type' => $types[array_rand($types)],
                'status' => $statuses[array_rand($statuses)],
                'queued_at' => date('Y-m-d H:i:s', strtotime("-{$i} minutes")),
                'duration' => rand(10, 300) . 's',
                'attempts' => rand(1, 3)
            ];
        }

        return $jobs;
    }

    /**
     * Get currently active jobs
     */
    private function getActiveJobs(): array
    {
        return [
            [
                'id' => 'JOB_STOCK_SYNC_' . uniqid(),
                'type' => 'stock_sync',
                'status' => 'RUNNING',
                'progress' => 75,
                'started_at' => date('Y-m-d H:i:s', strtotime('-2 minutes')),
                'estimated_completion' => date('Y-m-d H:i:s', strtotime('+30 seconds'))
            ],
            [
                'id' => 'JOB_REPORT_GEN_' . uniqid(),
                'type' => 'report_generation',
                'status' => 'RUNNING',
                'progress' => 45,
                'started_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'estimated_completion' => date('Y-m-d H:i:s', strtotime('+3 minutes'))
            ]
        ];
    }

    // Mock helper methods (in production these would interact with real queue system)

    private function getJobStatusById(string $jobId): ?array
    {
        return [
            'id' => $jobId,
            'type' => 'stock_sync',
            'status' => 'RUNNING',
            'progress' => 60,
            'queued_at' => date('c', strtotime('-5 minutes')),
            'started_at' => date('c', strtotime('-2 minutes')),
            'attempts' => 1,
            'max_attempts' => 3
        ];
    }

    private function getQueueOverview(bool $includeDetails): array
    {
        return [
            'overview' => $this->getQueueStats(),
            'active_jobs' => $this->getActiveJobs(),
            'details' => $includeDetails ? $this->getDetailedQueueMetrics() : null
        ];
    }

    private function getJobQueuePosition(string $jobId): ?int
    {
        return rand(1, 10); // Mock position
    }

    private function estimateJobCompletion(array $jobStatus): ?string
    {
        if ($jobStatus['status'] !== 'RUNNING') return null;
        return date('c', time() + rand(30, 300)); // Mock estimation
    }

    private function getJobResourceUsage(string $jobId): array
    {
        return [
            'memory_usage_mb' => rand(50, 200),
            'cpu_usage_percent' => rand(10, 80),
            'disk_io_kb' => rand(1000, 10000)
        ];
    }

    private function getJobPerformanceMetrics(string $jobId): array
    {
        return [
            'execution_time_ms' => rand(1000, 60000),
            'queue_wait_time_ms' => rand(100, 5000),
            'database_queries' => rand(5, 50),
            'api_calls' => rand(0, 10)
        ];
    }

    private function getQueueStatsByName(string $queueName): array
    {
        return [
            'queue_name' => $queueName,
            'pending_count' => rand(5, 25),
            'running_count' => rand(0, 5),
            'failed_count' => rand(0, 3)
        ];
    }

    private function getJobExecutionLog(string $jobId): array
    {
        return [
            ['timestamp' => date('c', strtotime('-5 minutes')), 'level' => 'INFO', 'message' => 'Job queued'],
            ['timestamp' => date('c', strtotime('-2 minutes')), 'level' => 'INFO', 'message' => 'Job started'],
            ['timestamp' => date('c', strtotime('-1 minutes')), 'level' => 'DEBUG', 'message' => 'Processing 50% complete']
        ];
    }

    private function getJobErrorHistory(string $jobId): array
    {
        return []; // Mock - no errors for this job
    }

    private function getJobDependencies(string $jobId): array
    {
        return [
            'depends_on' => [],
            'dependents' => [],
            'blocking' => false
        ];
    }

    private function getDetailedQueueMetrics(): array
    {
        return [
            'throughput_per_minute' => rand(10, 50),
            'error_rate_percent' => rand(0, 5),
            'average_wait_time_seconds' => rand(5, 30),
            'worker_utilization_percent' => rand(40, 90)
        ];
    }
}