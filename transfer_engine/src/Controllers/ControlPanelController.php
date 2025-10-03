<?php

/**
 * Vapeshed Transfer Engine - Control Panel Controller
 * Enterprise-grade control panel backend management
 * 
 * @author Ecigdis Ltd Development Team
 * @version 4.0
 * @package VapeshedTransfer\Controllers
 */

class ControlPanelController extends BaseController
{
    private $engine;
    private $configManager;
    private $runManager;
    private $logger;
    private $killSwitchFile;
    
    public function __construct()
    {
        parent::__construct();
        
        $this->engine = new TransferEngine();
        $this->configManager = new ConfigManager();
        $this->runManager = new RunManager();
        $this->logger = new SystemLogger('control-panel');
        $this->killSwitchFile = __DIR__ . '/../KILL_SWITCH';
        
        // Verify authentication and permissions
        $this->requireAuthentication();
        $this->requirePermission('transfer.control');
    }
    
    /**
     * Display the main control panel interface
     */
    public function index()
    {
        try {
            // Collect dashboard data
            $data = [
                'engine_status' => $this->getEngineStatus(),
                'kill_switch_active' => $this->isKillSwitchActive(),
                'current_config' => $this->configManager->getCurrentConfig(),
                'recent_runs' => $this->runManager->getRecentRuns(10),
                'available_presets' => $this->getAvailablePresets(),
                'system_metrics' => $this->getSystemMetrics(),
                'csrf_token' => $this->generateCSRFToken()
            ];
            
            $this->renderView('control-panel/index', $data);
            
        } catch (Exception $e) {
            $this->logger->error('Control panel index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->renderError('Control panel unavailable', 500);
        }
    }
    
    /**
     * API: Get engine status
     */
    public function apiEngineStatus()
    {
        $this->requireApiCall();
        
        try {
            $status = $this->getEngineStatus();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $status,
                'timestamp' => date('c')
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to get engine status', $e->getMessage());
        }
    }
    
    /**
     * API: Run system diagnostics
     */
    public function apiDiagnostics()
    {
        $this->requireApiCall();
        
        try {
            $diagnostics = [
                'database_connection' => $this->testDatabaseConnection(),
                'file_permissions' => $this->testFilePermissions(),
                'engine_integrity' => $this->testEngineIntegrity(),
                'memory_usage' => $this->testMemoryUsage(),
                'disk_space' => $this->testDiskSpace(),
                'configuration' => $this->testConfiguration()
            ];
            
            $allPassed = array_reduce($diagnostics, function($carry, $test) {
                return $carry && $test['passed'];
            }, true);
            
            $this->logger->info('System diagnostics completed', [
                'all_passed' => $allPassed,
                'results' => $diagnostics
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $diagnostics,
                'all_passed' => $allPassed
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Diagnostics failed', $e->getMessage());
        }
    }
    
    /**
     * API: Activate kill switch
     */
    public function apiKillSwitchActivate()
    {
        $this->requireApiCall('POST');
        
        try {
            $reason = $this->input('reason', 'Emergency stop activated via control panel');
            
            // Create kill switch file
            file_put_contents($this->killSwitchFile, json_encode([
                'activated_at' => date('c'),
                'activated_by' => $this->getCurrentUser()['username'],
                'reason' => $reason,
                'source' => 'control_panel'
            ], JSON_PRETTY_PRINT));
            
            // Stop any running processes
            $this->engine->emergencyStop();
            
            $this->logger->critical('Kill switch activated', [
                'activated_by' => $this->getCurrentUser()['username'],
                'reason' => $reason
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Emergency stop activated'
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to activate kill switch', $e->getMessage());
        }
    }
    
    /**
     * API: Deactivate kill switch
     */
    public function apiKillSwitchDeactivate()
    {
        $this->requireApiCall('POST');
        
        try {
            if (file_exists($this->killSwitchFile)) {
                unlink($this->killSwitchFile);
            }
            
            $this->logger->info('Kill switch deactivated', [
                'deactivated_by' => $this->getCurrentUser()['username']
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'System resumed'
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to deactivate kill switch', $e->getMessage());
        }
    }
    
    /**
     * API: Load preset configuration
     */
    public function apiPresetLoad($presetName)
    {
        $this->requireApiCall();
        
        try {
            $config = $this->configManager->loadPreset($presetName);
            
            if (!$config) {
                throw new Exception("Preset '{$presetName}' not found");
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $config
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to load preset', $e->getMessage());
        }
    }
    
    /**
     * API: Execute transfer
     */
    public function apiTransferExecute()
    {
        $this->requireApiCall('POST');
        
        try {
            // Verify kill switch is not active
            if ($this->isKillSwitchActive()) {
                throw new Exception('Cannot execute transfer: kill switch is active');
            }
            
            // Validate configuration
            $config = $this->validateTransferConfig();
            
            // Check for existing runs
            if ($this->runManager->hasActiveRun()) {
                throw new Exception('Another transfer is already in progress');
            }
            
            // Create new run record
            $runId = $this->runManager->createRun([
                'config' => $config,
                'started_by' => $this->getCurrentUser()['id'],
                'started_at' => date('c')
            ]);
            
            // Execute transfer in background
            $result = $this->engine->executeTransfer($config, $runId);
            
            $this->logger->info('Transfer execution started', [
                'run_id' => $runId,
                'started_by' => $this->getCurrentUser()['username'],
                'config_hash' => md5(json_encode($config))
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'run_id' => $runId,
                    'message' => 'Transfer execution started'
                ]
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Transfer execution failed', [
                'error' => $e->getMessage(),
                'user' => $this->getCurrentUser()['username']
            ]);
            
            $this->jsonError('Transfer execution failed', $e->getMessage());
        }
    }
    
    /**
     * API: Preview transfer
     */
    public function apiTransferPreview()
    {
        $this->requireApiCall('POST');
        
        try {
            $config = $this->validateTransferConfig();
            $config['preview_mode'] = true;
            
            $preview = $this->engine->generatePreview($config);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $preview
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Preview generation failed', $e->getMessage());
        }
    }
    
    /**
     * API: Load products
     */
    public function apiProductsLoad()
    {
        $this->requireApiCall();
        
        try {
            $products = $this->configManager->getProductMemory();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $products
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to load products', $e->getMessage());
        }
    }
    
    /**
     * API: Save products
     */
    public function apiProductsSave()
    {
        $this->requireApiCall('POST');
        
        try {
            $products = $this->input('products', []);
            
            if (!is_array($products)) {
                throw new Exception('Products must be an array');
            }
            
            $this->configManager->saveProductMemory($products);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Products saved successfully'
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to save products', $e->getMessage());
        }
    }
    
    /**
     * API: Get recent runs
     */
    public function apiRunsRecent()
    {
        $this->requireApiCall();
        
        try {
            $limit = min((int)$this->input('limit', 10), 50);
            $runs = $this->runManager->getRecentRuns($limit);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $runs
            ]);
            
        } catch (Exception $e) {
            $this->jsonError('Failed to load recent runs', $e->getMessage());
        }
    }
    
    /**
     * Get current engine status
     */
    private function getEngineStatus(): array
    {
        try {
            $status = [
                'status' => 'healthy',
                'kill_switch' => $this->isKillSwitchActive(),
                'metrics' => [
                    'uptime' => $this->getSystemUptime(),
                    'latency' => $this->measureLatency(),
                    'memory' => memory_get_usage(true),
                    'last_run' => $this->runManager->getLastRunStatus()
                ],
                'checks' => [
                    'database' => $this->testDatabaseConnection()['passed'],
                    'files' => $this->testFilePermissions()['passed'],
                    'config' => $this->testConfiguration()['passed']
                ]
            ];
            
            // Determine overall status
            if (!$status['checks']['database'] || !$status['checks']['files']) {
                $status['status'] = 'error';
            } elseif (!$status['checks']['config'] || $status['kill_switch']) {
                $status['status'] = 'warning';
            }
            
            return $status;
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'kill_switch' => $this->isKillSwitchActive(),
                'metrics' => [],
                'checks' => []
            ];
        }
    }
    
    /**
     * Check if kill switch is active
     */
    private function isKillSwitchActive(): bool
    {
        return file_exists($this->killSwitchFile);
    }
    
    /**
     * Get available configuration presets
     */
    private function getAvailablePresets(): array
    {
        return $this->configManager->getAvailablePresets();
    }
    
    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'php_memory_limit' => ini_get('memory_limit'),
            'php_memory_usage' => memory_get_usage(true),
            'php_memory_peak' => memory_get_peak_usage(true),
            'disk_free' => disk_free_space(__DIR__),
            'disk_total' => disk_total_space(__DIR__),
            'server_load' => $this->getServerLoad(),
            'php_version' => PHP_VERSION,
            'server_time' => date('c')
        ];
    }
    
    /**
     * Validate transfer configuration from input
     */
    private function validateTransferConfig(): array
    {
        $config = [
            'preset' => $this->input('preset', 'balanced'),
            'weight_method' => $this->input('weight_method', 'power'),
            'reserve_percent' => (float)$this->input('reserve_percent', 0.1),
            'max_per_product' => (int)$this->input('max_per_product', 50),
            'weight_gamma' => (float)$this->input('weight_gamma', 1.5),
            'softmax_tau' => (float)$this->input('softmax_tau', 1.0),
            'weight_mix_beta' => (float)$this->input('weight_mix_beta', 0.5),
            'min_cap_per_outlet' => (int)$this->input('min_cap_per_outlet', 1),
            'live_mode' => (bool)$this->input('live_mode', false),
            'save_snapshot' => (bool)$this->input('save_snapshot', true),
            'products' => $this->input('products', [])
        ];
        
        // Validate ranges
        if ($config['reserve_percent'] < 0 || $config['reserve_percent'] > 1) {
            throw new Exception('Reserve percent must be between 0 and 1');
        }
        
        if ($config['max_per_product'] < 1 || $config['max_per_product'] > 1000) {
            throw new Exception('Max per product must be between 1 and 1000');
        }
        
        if ($config['weight_gamma'] < 0.1 || $config['weight_gamma'] > 10) {
            throw new Exception('Weight gamma must be between 0.1 and 10');
        }
        
        return $config;
    }
    
    /**
     * Test database connection
     */
    private function testDatabaseConnection(): array
    {
        try {
            $db = new PDO(
                "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']}",
                $_ENV['DB_USERNAME'],
                $_ENV['DB_PASSWORD'],
                [PDO::ATTR_TIMEOUT => 5]
            );
            
            $stmt = $db->query("SELECT 1");
            $result = $stmt->fetchColumn();
            
            return [
                'passed' => $result === 1,
                'message' => 'Database connection successful',
                'latency' => 0 // Could implement timing
            ];
            
        } catch (Exception $e) {
            return [
                'passed' => false,
                'message' => "Database connection failed: {$e->getMessage()}"
            ];
        }
    }
    
    /**
     * Test file permissions
     */
    private function testFilePermissions(): array
    {
        $paths = [
            __DIR__ . '/../var/logs' => 'write',
            __DIR__ . '/../var/runs' => 'write',
            __DIR__ . '/../var/tmp' => 'write',
            __DIR__ . '/../presets' => 'read'
        ];
        
        $failed = [];
        
        foreach ($paths as $path => $permission) {
            if (!file_exists($path)) {
                $failed[] = "{$path} does not exist";
                continue;
            }
            
            if ($permission === 'write' && !is_writable($path)) {
                $failed[] = "{$path} is not writable";
            }
            
            if ($permission === 'read' && !is_readable($path)) {
                $failed[] = "{$path} is not readable";
            }
        }
        
        return [
            'passed' => empty($failed),
            'message' => empty($failed) ? 'All file permissions OK' : implode(', ', $failed)
        ];
    }
    
    /**
     * Test engine integrity
     */
    private function testEngineIntegrity(): array
    {
        try {
            $engineFile = __DIR__ . '/../engine.php';
            
            if (!file_exists($engineFile)) {
                return ['passed' => false, 'message' => 'Engine file not found'];
            }
            
            if (!is_readable($engineFile)) {
                return ['passed' => false, 'message' => 'Engine file not readable'];
            }
            
            // Test basic engine instantiation
            $engine = new TransferEngine();
            
            return [
                'passed' => true,
                'message' => 'Engine integrity verified'
            ];
            
        } catch (Exception $e) {
            return [
                'passed' => false,
                'message' => "Engine integrity check failed: {$e->getMessage()}"
            ];
        }
    }
    
    /**
     * Test memory usage
     */
    private function testMemoryUsage(): array
    {
        $current = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $percentUsed = ($current / $limit) * 100;
        
        return [
            'passed' => $percentUsed < 80,
            'message' => sprintf(
                'Memory usage: %s / %s (%.1f%%)',
                $this->formatBytes($current),
                $this->formatBytes($limit),
                $percentUsed
            )
        ];
    }
    
    /**
     * Test disk space
     */
    private function testDiskSpace(): array
    {
        $free = disk_free_space(__DIR__);
        $total = disk_total_space(__DIR__);
        
        $percentUsed = (($total - $free) / $total) * 100;
        
        return [
            'passed' => $percentUsed < 90,
            'message' => sprintf(
                'Disk usage: %s / %s (%.1f%% used)',
                $this->formatBytes($total - $free),
                $this->formatBytes($total),
                $percentUsed
            )
        ];
    }
    
    /**
     * Test configuration validity
     */
    private function testConfiguration(): array
    {
        try {
            $config = $this->configManager->getCurrentConfig();
            
            if (empty($config)) {
                return ['passed' => false, 'message' => 'No configuration loaded'];
            }
            
            // Validate required fields
            $required = ['weight_method', 'reserve_percent', 'max_per_product'];
            $missing = array_diff($required, array_keys($config));
            
            if (!empty($missing)) {
                return [
                    'passed' => false,
                    'message' => 'Missing config fields: ' . implode(', ', $missing)
                ];
            }
            
            return [
                'passed' => true,
                'message' => 'Configuration valid'
            ];
            
        } catch (Exception $e) {
            return [
                'passed' => false,
                'message' => "Configuration test failed: {$e->getMessage()}"
            ];
        }
    }
    
    /**
     * Get system uptime in seconds
     */
    private function getSystemUptime(): int
    {
        if (function_exists('sys_getloadavg')) {
            $uptime_file = '/proc/uptime';
            if (file_exists($uptime_file)) {
                $uptime = floatval(file_get_contents($uptime_file));
                return (int)$uptime;
            }
        }
        
        return 0;
    }
    
    /**
     * Measure system latency
     */
    private function measureLatency(): string
    {
        $start = microtime(true);
        
        // Simple operation to measure
        $db = $this->getDatabase();
        $stmt = $db->query("SELECT 1");
        $stmt->fetchColumn();
        
        $end = microtime(true);
        $latency = ($end - $start) * 1000;
        
        return number_format($latency, 2) . 'ms';
    }
    
    /**
     * Get server load average
     */
    private function getServerLoad(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2)
            ];
        }
        
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }
    
    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int)$limit;
        
        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }
        
        return $limit;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}