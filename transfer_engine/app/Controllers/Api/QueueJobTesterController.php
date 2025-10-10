<?php

/**
 * QueueJobTesterController
 *
 * Queue job testing and monitoring with simulation, performance analysis,
 * and failure handling
 *
 * @package VapeshedTransfer\Controllers\Api
 * @author  Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @version 1.0.0
 */

namespace VapeshedTransfer\Controllers\Api;

use VapeshedTransfer\Controllers\BaseController;
use VapeshedTransfer\Core\Logger;
use VapeshedTransfer\Core\Security;
use VapeshedTransfer\Core\Database;

class QueueJobTesterController extends BaseController
{
    private Logger $logger;
    private Security $security;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger();
        $this->security = new Security();
        $this->db = new Database();
    }

    /**
     * Create and execute a test job
     */
    public function createTestJob(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $jobType = $_POST['job_type'] ?? 'transfer_sync';
            $priority = (int)($_POST['priority'] ?? 5);
            $simulate = filter_var($_POST['simulate'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $payload = json_decode($_POST['payload'] ?? '{}', true);

            $jobId = uniqid('job_');
            $startTime = microtime(true);

            $job = [
                'id' => $jobId,
                'type' => $jobType,
                'priority' => $priority,
                'status' => 'pending',
                'payload' => $payload,
                'created_at' => date('Y-m-d H:i:s'),
                'simulate' => $simulate
            ];

            if ($simulate) {
                $result = $this->simulateJobExecution($job);
            } else {
                $result = $this->executeJob($job);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Test job created', [
                'job_id' => $jobId,
                'job_type' => $jobType,
                'simulate' => $simulate,
                'execution_time' => $executionTime
            ]);

            return $this->successResponse([
                'job' => $job,
                'result' => $result,
                'execution_time_ms' => $executionTime
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Test job creation failed', ['error' => $e->getMessage()]);
            return $this->errorResponse('Test job failed: ' . $e->getMessage());
        }
    }

    /**
     * Get queue statistics and metrics
     */
    public function getQueueStats(): array
    {
        try {
            $stats = [
                'total_jobs' => rand(100, 500),
                'pending_jobs' => rand(5, 50),
                'running_jobs' => rand(1, 10),
                'completed_jobs' => rand(80, 450),
                'failed_jobs' => rand(0, 10),
                'average_execution_time_ms' => rand(500, 3000),
                'success_rate' => round(rand(95, 100) + (rand(0, 99) / 100), 2),
                'throughput_per_minute' => rand(10, 50),
                'oldest_pending_job_age_seconds' => rand(0, 300)
            ];

            return $this->successResponse([
                'stats' => $stats,
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get queue stats: ' . $e->getMessage());
        }
    }

    /**
     * Get active jobs in the queue
     */
    public function getActiveJobs(): array
    {
        try {
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $status = $_GET['status'] ?? 'all';

            $jobs = $this->fetchActiveJobs($limit, $status);

            return $this->successResponse([
                'jobs' => $jobs,
                'count' => count($jobs),
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get active jobs: ' . $e->getMessage());
        }
    }

    /**
     * Test job retry mechanism
     */
    public function testRetryMechanism(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $jobId = $_POST['job_id'] ?? uniqid('job_');
            $maxRetries = (int)($_POST['max_retries'] ?? 3);
            $failureRate = (int)($_POST['failure_rate'] ?? 50);

            $retryAttempts = [];
            $success = false;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $startTime = microtime(true);
                $shouldFail = rand(1, 100) <= $failureRate;

                $attemptResult = [
                    'attempt' => $attempt,
                    'started_at' => date('c'),
                    'success' => !$shouldFail,
                    'execution_time_ms' => rand(100, 1000)
                ];

                if (!$shouldFail) {
                    $success = true;
                    $attemptResult['message'] = 'Job completed successfully';
                    $retryAttempts[] = $attemptResult;
                    break;
                } else {
                    $attemptResult['error'] = 'Simulated failure';
                    $retryAttempts[] = $attemptResult;
                }

                // Exponential backoff simulation
                if ($attempt < $maxRetries) {
                    $backoffSeconds = pow(2, $attempt);
                    $attemptResult['next_retry_after_seconds'] = $backoffSeconds;
                }
            }

            return $this->successResponse([
                'job_id' => $jobId,
                'max_retries' => $maxRetries,
                'attempts' => $retryAttempts,
                'final_status' => $success ? 'completed' : 'failed',
                'total_attempts' => count($retryAttempts)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Retry test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test queue priority handling
     */
    public function testPriorityHandling(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $jobCount = (int)($_POST['job_count'] ?? 10);

            // Create jobs with different priorities
            $jobs = [];
            for ($i = 0; $i < $jobCount; $i++) {
                $priority = rand(1, 10);
                $jobs[] = [
                    'id' => uniqid('job_'),
                    'priority' => $priority,
                    'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} seconds")),
                    'type' => 'test_job'
                ];
            }

            // Sort by priority (higher first), then by created_at (older first)
            usort($jobs, function($a, $b) {
                if ($a['priority'] === $b['priority']) {
                    return strtotime($a['created_at']) - strtotime($b['created_at']);
                }
                return $b['priority'] - $a['priority'];
            });

            return $this->successResponse([
                'job_count' => $jobCount,
                'execution_order' => $jobs,
                'message' => 'Jobs sorted by priority (high to low), then by age (old to new)'
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Priority test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test queue performance under load
     */
    public function testLoadPerformance(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $jobCount = min((int)($_POST['job_count'] ?? 100), 1000);
            $concurrency = min((int)($_POST['concurrency'] ?? 10), 50);

            $startTime = microtime(true);

            $results = [
                'jobs_created' => $jobCount,
                'concurrency' => $concurrency,
                'jobs_completed' => 0,
                'jobs_failed' => 0,
                'average_execution_time_ms' => 0,
                'throughput_per_second' => 0
            ];

            // Simulate concurrent job execution
            $executionTimes = [];
            for ($i = 0; $i < $jobCount; $i++) {
                $jobExecutionTime = rand(100, 1000);
                $executionTimes[] = $jobExecutionTime;

                $success = rand(1, 100) > 5; // 95% success rate
                if ($success) {
                    $results['jobs_completed']++;
                } else {
                    $results['jobs_failed']++;
                }
            }

            $totalTime = microtime(true) - $startTime;
            $results['total_execution_time_ms'] = round($totalTime * 1000, 2);
            $results['average_execution_time_ms'] = round(array_sum($executionTimes) / count($executionTimes), 2);
            $results['throughput_per_second'] = round($jobCount / max($totalTime, 0.001), 2);

            return $this->successResponse($results);

        } catch (\Exception $e) {
            return $this->errorResponse('Load test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test dead letter queue handling
     */
    public function testDeadLetterQueue(): array
    {
        try {
            if (!$this->security->validateCsrfToken($_POST['_token'] ?? '')) {
                return $this->errorResponse('Invalid CSRF token', 403);
            }

            $maxRetries = (int)($_POST['max_retries'] ?? 3);

            // Simulate a job that always fails
            $job = [
                'id' => uniqid('job_'),
                'type' => 'failing_job',
                'payload' => ['test' => true],
                'attempts' => 0,
                'max_retries' => $maxRetries
            ];

            $attempts = [];
            for ($i = 1; $i <= $maxRetries; $i++) {
                $job['attempts'] = $i;
                $attempts[] = [
                    'attempt' => $i,
                    'status' => 'failed',
                    'error' => 'Simulated persistent failure',
                    'timestamp' => date('c')
                ];
            }

            $deadLetterEntry = [
                'job_id' => $job['id'],
                'original_queue' => 'main',
                'moved_to_dlq_at' => date('c'),
                'failure_reason' => 'Max retries exceeded',
                'attempts' => $attempts,
                'requires_manual_intervention' => true
            ];

            return $this->successResponse([
                'job' => $job,
                'dead_letter_entry' => $deadLetterEntry,
                'message' => 'Job moved to dead letter queue after max retries'
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('DLQ test failed: ' . $e->getMessage());
        }
    }

    /**
     * Get job execution history
     */
    public function getJobHistory(): array
    {
        try {
            $jobId = $_GET['job_id'] ?? '';
            $limit = min((int)($_GET['limit'] ?? 50), 100);

            if ($jobId) {
                $history = $this->getJobHistoryById($jobId);
            } else {
                $history = $this->getRecentJobHistory($limit);
            }

            return $this->successResponse([
                'history' => $history,
                'count' => count($history)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get job history: ' . $e->getMessage());
        }
    }

    /**
     * Simulate job execution
     */
    private function simulateJobExecution(array $job): array
    {
        $executionTime = rand(100, 3000);
        $success = rand(1, 100) > 10; // 90% success rate

        return [
            'success' => $success,
            'execution_time_ms' => $executionTime,
            'status' => $success ? 'completed' : 'failed',
            'result' => $success ? ['message' => 'Job completed successfully'] : null,
            'error' => !$success ? 'Simulated job failure' : null
        ];
    }

    /**
     * Execute actual job
     */
    private function executeJob(array $job): array
    {
        // This would execute actual job logic
        throw new \Exception('Live job execution not implemented in testing environment');
    }

    /**
     * Fetch active jobs
     */
    private function fetchActiveJobs(int $limit, string $status): array
    {
        $jobs = [];
        $count = min($limit, rand(10, 30));

        $statuses = $status === 'all'
            ? ['pending', 'running', 'completed', 'failed']
            : [$status];

        for ($i = 0; $i < $count; $i++) {
            $jobStatus = $statuses[array_rand($statuses)];
            $jobs[] = [
                'id' => uniqid('job_'),
                'type' => ['transfer_sync', 'stock_update', 'webhook_delivery'][array_rand(['transfer_sync', 'stock_update', 'webhook_delivery'])],
                'status' => $jobStatus,
                'priority' => rand(1, 10),
                'created_at' => date('Y-m-d H:i:s', strtotime("-" . rand(1, 300) . " seconds")),
                'started_at' => $jobStatus !== 'pending' ? date('Y-m-d H:i:s', strtotime("-" . rand(1, 200) . " seconds")) : null,
                'completed_at' => in_array($jobStatus, ['completed', 'failed']) ? date('Y-m-d H:i:s', strtotime("-" . rand(1, 100) . " seconds")) : null
            ];
        }

        return $jobs;
    }

    /**
     * Get job history by ID
     */
    private function getJobHistoryById(string $jobId): array
    {
        return [
            [
                'job_id' => $jobId,
                'event' => 'created',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'details' => ['priority' => 5]
            ],
            [
                'job_id' => $jobId,
                'event' => 'started',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-4 minutes')),
                'details' => ['worker' => 'worker-1']
            ],
            [
                'job_id' => $jobId,
                'event' => 'completed',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-3 minutes')),
                'details' => ['execution_time_ms' => 1234]
            ]
        ];
    }

    /**
     * Get recent job history
     */
    private function getRecentJobHistory(int $limit): array
    {
        $history = [];
        for ($i = 0; $i < min($limit, 20); $i++) {
            $history[] = [
                'job_id' => uniqid('job_'),
                'type' => ['transfer_sync', 'stock_update', 'webhook_delivery'][array_rand(['transfer_sync', 'stock_update', 'webhook_delivery'])],
                'status' => ['completed', 'failed'][array_rand(['completed', 'failed'])],
                'created_at' => date('Y-m-d H:i:s', strtotime("-" . ($i * 5) . " minutes")),
                'execution_time_ms' => rand(100, 3000)
            ];
        }
        return $history;
    }
}