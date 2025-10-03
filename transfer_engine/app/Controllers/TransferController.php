<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Security;
use App\Services\TransferEngineService;

/**
 * Transfer Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Handles transfer execution and management
 */
class TransferController extends BaseController
{
    private TransferEngineService $transferEngine;
    
    public function __construct()
    {
        parent::__construct();
        $this->transferEngine = new TransferEngineService();
    }
    
    public function index(): void
    {
        $this->run();
    }
    
    public function showRunPage(): void
    {
        $this->render('transfers/run', [
            'title' => 'Run Transfer',
            'csrf_token' => Security::generateCSRFToken(),
            'kill_switch_active' => file_exists(STORAGE_PATH . '/KILL_SWITCH')
        ]);
    }
    
    public function executeTransfer(): void
    {
        try {
            // Enforce CSRF (now supports header tokens) and write policy
            Security::requireCSRF();
            Security::ensureWriteAllowed('transfer_execute');
            
            // Get configuration from POST data
            $config = $_POST['config'] ?? [];
            $products = $_POST['products'] ?? [];
            
            // Sanitize inputs
            $config = Security::sanitizeInput($config);
            $products = Security::sanitizeInput($products);

            // Map UI live_mode to engine 'dry' flag (default dry unless explicitly live)
            if (isset($config['live_mode'])) {
                $live = (int)$config['live_mode'] === 1;
                $config['dry'] = $live ? 0 : 1;
            }
            
            // Execute transfer
            $result = $this->transferEngine->executeTransfer($config, $products);
            
            if (Security::isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'ok' => true,
                    'data' => $result
                ]);
            } else {
                $this->render('transfers/results', [
                    'title' => 'Transfer Results',
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Transfer execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if (Security::isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'ok' => false,
                    'error' => $e->getMessage()
                ]);
            } else {
                $this->render('errors/500', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Safely verify CSRF token with user-friendly error handling
     */
    // CSRF verification now centralized via Security::requireCSRF()

    /**
     * Show transfer execution interface
     */
    public function run(): void
    {
        $this->showRunPage();
    }

    /**
     * Execute transfer with given configuration
     */
    public function execute(): void
    {
        $this->executeTransfer();
    }

    /**
     * Show transfer results
     */
    public function results(): void
    {
        $run_id = $_GET['run_id'] ?? null;
        $timestamp = $_GET['timestamp'] ?? null;
        
        if (!$run_id) {
            // Instead of showing error, redirect to run page with helpful message
            if (Security::isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'error' => 'Run ID is required to view results',
                    'redirect' => '/transfer/run'
                ]);
                return;
            } else {
                // Redirect to transfer run page with message
                $this->render('transfers/run', [
                    'title' => 'Run Transfer',
                    'csrf_token' => Security::generateCSRFToken(),
                    'kill_switch_active' => file_exists(STORAGE_PATH . '/KILL_SWITCH'),
                    'message' => [
                        'type' => 'warning',
                        'text' => 'No run ID specified. Start a new transfer below to see results.'
                    ]
                ]);
                return;
            }
        }

        try {
            // Load results from database or logs
            $results = $this->getTransferResults($run_id);
            
            if (!$results) {
                $this->render('transfers/run', [
                    'title' => 'Run Transfer',
                    'csrf_token' => Security::generateCSRFToken(),
                    'kill_switch_active' => file_exists(STORAGE_PATH . '/KILL_SWITCH'),
                    'message' => [
                        'type' => 'info',
                        'text' => "No results found for run ID: {$run_id}. The transfer may still be running or the ID may be invalid."
                    ]
                ]);
                return;
            }
            
            $this->render('transfers/results', [
                'title' => 'Transfer Results',
                'run_id' => $run_id,
                'timestamp' => $timestamp,
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error loading transfer results', [
                'run_id' => $run_id,
                'error' => $e->getMessage()
            ]);
            
            $this->render('transfers/run', [
                'title' => 'Run Transfer',
                'csrf_token' => Security::generateCSRFToken(),
                'kill_switch_active' => file_exists(STORAGE_PATH . '/KILL_SWITCH'),
                'message' => [
                    'type' => 'danger',
                    'text' => 'Error loading transfer results. Please try running a new transfer.'
                ]
            ]);
        }
    }

    /**
     * Get transfer status (JSON API endpoint)
     */
    public function status(): void
    {
        header('Content-Type: application/json');
        
        try {
            $run_id = $_GET['run_id'] ?? null;
            
            if (!$run_id) {
                echo json_encode([
                    'ok' => false,
                    'error' => 'Run ID is required'
                ]);
                return;
            }
            
            // Get status from logs or database
            $status = $this->getTransferStatus($run_id);
            
            echo json_encode([
                'ok' => true,
                'data' => $status
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get transfer status by run ID
     */
    private function getTransferStatus(string $run_id): array
    {
        // In a real implementation, this would query the database or check logs
        return [
            'run_id' => $run_id,
            'status' => 'running',
            'progress' => rand(10, 90),
            'current_step' => 'Processing products',
            'estimated_time_remaining' => rand(30, 180)
        ];
    }

    /**
     * Get transfer results by run ID
     */
    private function getTransferResults(string $run_id): array
    {
        // In a real implementation, this would query the database
        // For now, return simulated data
        return [
            'run_id' => $run_id,
            'status' => 'completed',
            'products_processed' => 42,
            'outlets_updated' => 17,
            'units_allocated' => 156,
            'execution_time' => 2.3,
            'allocations' => [
                ['product' => 'Vape Kit A', 'outlet' => 'Auckland Central', 'allocated' => 12, 'priority_score' => 0.85],
                ['product' => 'E-liquid B', 'outlet' => 'Wellington', 'allocated' => 8, 'priority_score' => 0.72],
                ['product' => 'Coils C', 'outlet' => 'Christchurch', 'allocated' => 15, 'priority_score' => 0.91],
                ['product' => 'Battery D', 'outlet' => 'Hamilton', 'allocated' => 6, 'priority_score' => 0.68]
            ]
        ];
    }
}