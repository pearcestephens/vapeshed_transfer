<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Configuration Controller
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Handles configuration management operations
 */

class ConfigController extends BaseController
{
    private object $configManager;

    public function __construct()
    {
        parent::__construct();
        $this->configManager = new \App\Core\ConfigManager();
    }

    /**
     * Display configuration management interface
     */
    public function index(): void
    {
        // $this->requireAuth(); // Disabled for testing

        try {
            // Get all configurations
            $configurations = $this->configManager->getAvailablePresets();
            
            // Get available presets
            $presets = $this->configManager->getAvailablePresets();
            
            // Get configuration statistics (simplified for testing)
            $stats = ['presets_count' => count($presets)];
            
            $this->render('config/index', [
                'configurations' => $configurations,
                'presets' => $presets,
                'stats' => $stats,
                'title' => 'Configuration Management'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to load configuration index', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirect(url('/?error=' . urlencode('Failed to load configurations')));
        }
    }

    /**
     * Show create configuration form
     */
    public function create(): void
    {
        $this->requireAuth();

        try {
            // Get available presets for reference
            $presets = $this->configManager->getAvailablePresets();
            
            $this->render('config/create', [
                'presets' => $presets,
                'title' => 'Create Configuration'
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to load create configuration form', [
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirect(url('/config?error=' . urlencode('Failed to load form')));
        }
    }

    /**
     * Store new configuration
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Validate input
            $configData = $this->validateConfigurationInput($_POST);
            
            // Add audit information
            $configData['created_by'] = $_SESSION['user_id'];
            $configData['created_at'] = date('Y-m-d H:i:s');
            
            // Save configuration
            $configId = $this->configManager->createConfiguration($configData);
            
            if ($configId) {
                $this->logger->info('Configuration created', [
                    'config_id' => $configId,
                    'name' => $configData['name'],
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $this->redirectWithMessage(url('/config'), 'Configuration created successfully', 'success');
            } else {
                throw new Exception('Failed to create configuration');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to create configuration', [
                'error' => $e->getMessage(),
                'input' => $_POST,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirectWithMessage(url('/config/create'), $e->getMessage(), 'error');
        }
    }

    /**
     * Show edit configuration form
     */
    public function edit(string $id): void
    {
        $this->requireAuth();

        try {
            $configId = (int) $id;
            
            // Get configuration
            $config = $this->configManager->getConfiguration($configId);
            
            if (!$config) {
                throw new Exception('Configuration not found');
            }
            
            // Get available presets for reference
            $presets = $this->configManager->getAvailablePresets();
            
            $this->render('config/edit', [
                'config' => $config,
                'presets' => $presets,
                'title' => 'Edit Configuration: ' . htmlspecialchars($config['name'])
            ]);
            
        } catch (Exception $e) {
            $this->logger->error('Failed to load configuration for editing', [
                'config_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirect(url('/config?error=' . urlencode('Configuration not found')));
        }
    }

    /**
     * Update configuration
     */
    public function update(string $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $configId = (int) $id;
            
            // Check if configuration exists
            $existingConfig = $this->configManager->getConfiguration($configId);
            if (!$existingConfig) {
                throw new Exception('Configuration not found');
            }
            
            // Validate input
            $configData = $this->validateConfigurationInput($_POST);
            
            // Add audit information
            $configData['updated_by'] = $_SESSION['user_id'];
            $configData['updated_at'] = date('Y-m-d H:i:s');
            
            // Update configuration
            $success = $this->configManager->updateConfiguration($configId, $configData);
            
            if ($success) {
                $this->logger->info('Configuration updated', [
                    'config_id' => $configId,
                    'name' => $configData['name'],
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $this->redirectWithMessage(url('/config'), 'Configuration updated successfully', 'success');
            } else {
                throw new Exception('Failed to update configuration');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to update configuration', [
                'config_id' => $id,
                'error' => $e->getMessage(),
                'input' => $_POST,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirectWithMessage(url("/config/edit/{$id}"), $e->getMessage(), 'error');
        }
    }

    /**
     * Delete configuration
     */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $configId = (int) $id;
            
            // Check if configuration exists
            $config = $this->configManager->getConfiguration($configId);
            if (!$config) {
                throw new Exception('Configuration not found');
            }
            
            // Check if configuration is in use
            if ($this->configManager->isConfigurationInUse($configId)) {
                throw new Exception('Cannot delete configuration - it has been used in transfers');
            }
            
            // Delete configuration
            $success = $this->configManager->deleteConfiguration($configId);
            
            if ($success) {
                $this->logger->info('Configuration deleted', [
                    'config_id' => $configId,
                    'name' => $config['name'],
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $this->redirectWithMessage(url('/config'), 'Configuration deleted successfully', 'success');
            } else {
                throw new Exception('Failed to delete configuration');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete configuration', [
                'config_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirectWithMessage(url('/config'), $e->getMessage(), 'error');
        }
    }

    /**
     * Clone configuration
     */
    public function clone(string $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $configId = (int) $id;
            
            // Get source configuration
            $sourceConfig = $this->configManager->getConfiguration($configId);
            if (!$sourceConfig) {
                throw new Exception('Configuration not found');
            }
            
            // Prepare cloned configuration data
            $clonedData = $sourceConfig;
            unset($clonedData['id']);
            $clonedData['name'] = $sourceConfig['name'] . ' (Copy)';
            $clonedData['created_by'] = $_SESSION['user_id'];
            $clonedData['created_at'] = date('Y-m-d H:i:s');
            $clonedData['is_preset'] = 0; // Clones are never presets
            
            // Create cloned configuration
            $newConfigId = $this->configManager->createConfiguration($clonedData);
            
            if ($newConfigId) {
                $this->logger->info('Configuration cloned', [
                    'source_config_id' => $configId,
                    'new_config_id' => $newConfigId,
                    'user_id' => $_SESSION['user_id']
                ]);
                
                $this->redirectWithMessage(url('/config'), 'Configuration cloned successfully', 'success');
            } else {
                throw new Exception('Failed to clone configuration');
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clone configuration', [
                'config_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            
            $this->redirectWithMessage(url('/config'), $e->getMessage(), 'error');
        }
    }

    /**
     * Validate configuration input data
     */
    private function validateConfigurationInput(array $input): array
    {
        $errors = [];
        $cleaned = [];

        // Required fields
        if (empty($input['name'])) {
            $errors[] = 'Configuration name is required';
        } else {
            $cleaned['name'] = trim($input['name']);
            if (strlen($cleaned['name']) > 100) {
                $errors[] = 'Configuration name must be 100 characters or less';
            }
        }

        // Optional description
        $cleaned['description'] = trim($input['description'] ?? '');
        if (strlen($cleaned['description']) > 500) {
            $errors[] = 'Description must be 500 characters or less';
        }

        // Numeric validations with defaults
        $numericFields = [
            'allocation_method' => [1, 'Allocation method is required'],
            'power_factor' => [2.0, 'Power factor must be a number'],
            'min_allocation_pct' => [5.0, 'Minimum allocation percentage must be a number'],
            'max_allocation_pct' => [50.0, 'Maximum allocation percentage must be a number'],
            'rounding_method' => [0, 'Rounding method must be specified']
        ];

        foreach ($numericFields as $field => [$default, $errorMsg]) {
            if (isset($input[$field]) && $input[$field] !== '') {
                if (!is_numeric($input[$field])) {
                    $errors[] = $errorMsg;
                } else {
                    $cleaned[$field] = (float) $input[$field];
                }
            } else {
                $cleaned[$field] = $default;
            }
        }

        // Boolean fields
        $booleanFields = ['is_active', 'enable_safety_checks', 'enable_logging'];
        foreach ($booleanFields as $field) {
            $cleaned[$field] = isset($input[$field]) && $input[$field] ? 1 : 0;
        }

        // Range validations
        if ($cleaned['min_allocation_pct'] >= $cleaned['max_allocation_pct']) {
            $errors[] = 'Minimum allocation percentage must be less than maximum';
        }

        if ($cleaned['power_factor'] < 0.1 || $cleaned['power_factor'] > 10.0) {
            $errors[] = 'Power factor must be between 0.1 and 10.0';
        }

        if (!empty($errors)) {
            throw new Exception(implode('; ', $errors));
        }

        return $cleaned;
    }
}