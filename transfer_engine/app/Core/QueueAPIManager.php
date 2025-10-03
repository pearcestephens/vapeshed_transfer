<?php
/**
 * Queue API Manager - Interface to The Vape Shed's Monitored Queue System
 * 
 * All Vend operations go through the queue API which monitors success/failure
 * and provides comprehensive logging and retry mechanisms.
 * 
 * @author Pearce Stephens / Ecigdis Ltd
 * @version 1.0.0 - Production Ready
 * @date 2025-09-27
 */

class QueueAPIManager {
    
    private $config;
    private $logger;
    private $base_url;
    private $api_key;
    
    public function __construct($config) {
        $this->config = $config;
        $this->logger = new Logger('queue_api');
        $this->base_url = rtrim($config['endpoint'], '/');
        $this->api_key = $config['api_key'];
        
        if (empty($this->api_key)) {
            throw new Exception("Queue API key not configured");
        }
    }
    
    /**
     * Health Check - Verify Queue API Connectivity
     */
    public function healthCheck() {
        $endpoint = $this->base_url . '/health';
        
        $response = $this->makeRequest('GET', $endpoint);
        
        if ($response['http_code'] === 200 && isset($response['data']['status'])) {
            return [
                'success' => true,
                'status' => $response['data']['status'],
                'queue_workers' => $response['data']['workers'] ?? 'unknown',
                'vend_connection' => $response['data']['vend_status'] ?? 'unknown'
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Queue API health check failed',
            'http_code' => $response['http_code'],
            'response' => $response['data']
        ];
    }
    
    /**
     * Submit Job to Queue - All Vend Operations
     */
    public function submitJob($queue_name, $job_data) {
        $endpoint = $this->base_url . '/jobs/submit';
        
        $payload = [
            'queue' => $queue_name,
            'job_type' => $job_data['action'],
            'data' => $job_data,
            'priority' => $job_data['priority'] ?? 'normal',
            'timeout' => $job_data['timeout'] ?? 30,
            'retry_attempts' => $job_data['retry_attempts'] ?? 3,
            'submitted_at' => time(),
            'submitted_by' => 'claude_automation_engine'
        ];
        
        $response = $this->makeRequest('POST', $endpoint, $payload);
        
        if ($response['http_code'] === 200 || $response['http_code'] === 201) {
            $this->logger->info("Queue job submitted successfully: {$job_data['action']}");
            
            return [
                'success' => true,
                'job_id' => $response['data']['job_id'],
                'queue_position' => $response['data']['position'] ?? null,
                'estimated_execution' => $response['data']['estimated_execution'] ?? null,
                'status' => 'queued'
            ];
        }\n        \n        $this->logger->error("Failed to submit queue job: HTTP {$response['http_code']}");\n        \n        return [\n            'success' => false,\n            'error' => 'Job submission failed',\n            'http_code' => $response['http_code'],\n            'response' => $response['data']\n        ];\n    }\n    \n    /**\n     * Get Job Status - Monitor Vend Operation Success\n     */\n    public function getJobStatus($job_id) {\n        $endpoint = $this->base_url . '/jobs/' . $job_id . '/status';\n        \n        $response = $this->makeRequest('GET', $endpoint);\n        \n        if ($response['http_code'] === 200) {\n            $data = $response['data'];\n            \n            return [\n                'job_id' => $job_id,\n                'status' => $data['status'], // queued, processing, completed, failed\n                'success' => $data['success'] ?? null,\n                'progress' => $data['progress'] ?? 0,\n                'started_at' => $data['started_at'] ?? null,\n                'completed_at' => $data['completed_at'] ?? null,\n                'error' => $data['error'] ?? null,\n                'vend_response' => $data['vend_response'] ?? null,\n                'retry_count' => $data['retry_count'] ?? 0,\n                'worker_id' => $data['worker_id'] ?? null\n            ];\n        }\n        \n        return [\n            'job_id' => $job_id,\n            'status' => 'unknown',\n            'success' => false,\n            'error' => 'Failed to retrieve job status',\n            'http_code' => $response['http_code']\n        ];\n    }\n    \n    /**\n     * Get Queue Statistics\n     */\n    public function getQueueStats($queue_name = null) {\n        $endpoint = $this->base_url . '/stats';\n        if ($queue_name) {\n            $endpoint .= '?queue=' . urlencode($queue_name);\n        }\n        \n        $response = $this->makeRequest('GET', $endpoint);\n        \n        if ($response['http_code'] === 200) {\n            return [\n                'success' => true,\n                'stats' => $response['data']\n            ];\n        }\n        \n        return [\n            'success' => false,\n            'error' => 'Failed to retrieve queue stats'\n        ];\n    }\n    \n    /**\n     * Get Recent Job History - For Monitoring\n     */\n    public function getJobHistory($limit = 50, $status_filter = null) {\n        $endpoint = $this->base_url . '/jobs/history?limit=' . $limit;\n        \n        if ($status_filter) {\n            $endpoint .= '&status=' . urlencode($status_filter);\n        }\n        \n        $response = $this->makeRequest('GET', $endpoint);\n        \n        if ($response['http_code'] === 200) {\n            return [\n                'success' => true,\n                'jobs' => $response['data']['jobs'] ?? [],\n                'total_count' => $response['data']['total'] ?? 0\n            ];\n        }\n        \n        return [\n            'success' => false,\n            'error' => 'Failed to retrieve job history',\n            'jobs' => []\n        ];\n    }\n    \n    /**\n     * Cancel Job - Emergency Stop\n     */\n    public function cancelJob($job_id, $reason = null) {\n        $endpoint = $this->base_url . '/jobs/' . $job_id . '/cancel';\n        \n        $payload = [\n            'reason' => $reason ?? 'Cancelled by automation engine',\n            'cancelled_by' => 'claude_automation_engine',\n            'cancelled_at' => time()\n        ];\n        \n        $response = $this->makeRequest('POST', $endpoint, $payload);\n        \n        return [\n            'success' => $response['http_code'] === 200,\n            'job_id' => $job_id,\n            'cancelled' => $response['http_code'] === 200,\n            'message' => $response['data']['message'] ?? 'Cancellation request submitted'\n        ];\n    }\n    \n    /**\n     * Batch Job Submission - Multiple Vend Operations\n     */\n    public function submitBatchJobs($queue_name, $jobs_data) {\n        $endpoint = $this->base_url . '/jobs/batch';\n        \n        $payload = [\n            'queue' => $queue_name,\n            'jobs' => $jobs_data,\n            'batch_id' => uniqid('claude_batch_'),\n            'submitted_at' => time(),\n            'submitted_by' => 'claude_automation_engine'\n        ];\n        \n        $response = $this->makeRequest('POST', $endpoint, $payload);\n        \n        if ($response['http_code'] === 200) {\n            return [\n                'success' => true,\n                'batch_id' => $response['data']['batch_id'],\n                'job_ids' => $response['data']['job_ids'] ?? [],\n                'jobs_submitted' => count($jobs_data)\n            ];\n        }\n        \n        return [\n            'success' => false,\n            'error' => 'Batch job submission failed',\n            'http_code' => $response['http_code']\n        ];\n    }\n    \n    /**\n     * Monitor Batch Job Progress\n     */\n    public function getBatchStatus($batch_id) {\n        $endpoint = $this->base_url . '/jobs/batch/' . $batch_id . '/status';\n        \n        $response = $this->makeRequest('GET', $endpoint);\n        \n        if ($response['http_code'] === 200) {\n            $data = $response['data'];\n            \n            return [\n                'success' => true,\n                'batch_id' => $batch_id,\n                'total_jobs' => $data['total_jobs'] ?? 0,\n                'completed_jobs' => $data['completed_jobs'] ?? 0,\n                'failed_jobs' => $data['failed_jobs'] ?? 0,\n                'progress_percent' => $data['progress_percent'] ?? 0,\n                'estimated_completion' => $data['estimated_completion'] ?? null,\n                'job_statuses' => $data['job_statuses'] ?? []\n            ];\n        }\n        \n        return [\n            'success' => false,\n            'error' => 'Failed to retrieve batch status'\n        ];\n    }\n    \n    /**\n     * Make HTTP Request to Queue API\n     */\n    private function makeRequest($method, $endpoint, $data = null) {\n        $headers = [\n            'Authorization: Bearer ' . $this->api_key,\n            'Content-Type: application/json',\n            'User-Agent: Claude-Automation-Engine/1.0',\n            'X-Request-ID: ' . uniqid('claude_')\n        ];\n        \n        $ch = curl_init();\n        curl_setopt_array($ch, [\n            CURLOPT_URL => $endpoint,\n            CURLOPT_RETURNTRANSFER => true,\n            CURLOPT_TIMEOUT => $this->config['timeout'] ?? 30,\n            CURLOPT_HTTPHEADER => $headers,\n            CURLOPT_SSL_VERIFYPEER => false,\n            CURLOPT_FOLLOWLOCATION => false\n        ]);\n        \n        switch (strtoupper($method)) {\n            case 'POST':\n                curl_setopt($ch, CURLOPT_POST, true);\n                if ($data) {\n                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));\n                }\n                break;\n                \n            case 'PUT':\n                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');\n                if ($data) {\n                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));\n                }\n                break;\n                \n            case 'DELETE':\n                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');\n                break;\n        }\n        \n        $response = curl_exec($ch);\n        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);\n        $error = curl_error($ch);\n        curl_close($ch);\n        \n        if ($response === false) {\n            $this->logger->error(\"Queue API request failed: {$error}\");\n            return [\n                'http_code' => 0,\n                'data' => null,\n                'error' => $error\n            ];\n        }\n        \n        $decoded_response = json_decode($response, true);\n        \n        return [\n            'http_code' => $http_code,\n            'data' => $decoded_response,\n            'raw_response' => $response\n        ];\n    }\n    \n    /**\n     * Get Vend Connection Status Through Queue\n     */\n    public function getVendConnectionStatus() {\n        $endpoint = $this->base_url . '/vend/status';\n        \n        $response = $this->makeRequest('GET', $endpoint);\n        \n        if ($response['http_code'] === 200) {\n            return [\n                'success' => true,\n                'vend_connected' => $response['data']['connected'] ?? false,\n                'last_sync' => $response['data']['last_sync'] ?? null,\n                'api_rate_limit' => $response['data']['rate_limit'] ?? null,\n                'errors' => $response['data']['errors'] ?? []\n            ];\n        }\n        \n        return [\n            'success' => false,\n            'vend_connected' => false,\n            'error' => 'Unable to check Vend connection status'\n        ];\n    }\n}