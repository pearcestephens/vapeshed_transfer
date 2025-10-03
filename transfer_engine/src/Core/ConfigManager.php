<?php

/**
 * Vapeshed Transfer Engine - Configuration Manager
 * Enterprise-grade configuration management system
 * 
 * @author Ecigdis Ltd Development Team
 * @version 4.0
 * @package VapeshedTransfer\Core
 */

namespace VapeshedTransfer\Core;

use Exception;
use InvalidArgumentException;
use VapeshedTransfer\Core\SystemLogger;

class ConfigManager
{
    private $presetPath;
    private $configPath;
    private $currentConfig;
    private $logger;
    private $cache = [];
    private $validationRules;
    
    public function __construct()
    {
        $this->presetPath = __DIR__ . '/../../presets';
        $this->configPath = __DIR__ . '/../../var/config';
        $this->logger = new SystemLogger('config-manager');
        
        // Ensure directories exist
        $this->ensureDirectories();
        
        // Initialize validation rules
        $this->initValidationRules();
        
        // Load current configuration
        $this->loadCurrentConfig();
    }
    
    /**
     * Get current active configuration
     */
    public function getCurrentConfig(): array
    {
        return $this->currentConfig ?? [];
    }
    
    /**
     * Load configuration from preset
     */
    public function loadPreset(string $presetName): ?array
    {
        try {
            $presetFile = $this->presetPath . "/{$presetName}.json";
            
            if (!file_exists($presetFile)) {
                $this->logger->warning("Preset not found: {$presetName}");
                return null;
            }
            
            $content = file_get_contents($presetFile);
            $config = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON in preset {$presetName}: " . json_last_error_msg());
            }
            
            // Validate configuration
            $this->validateConfig($config);
            
            $this->logger->info("Preset loaded successfully", ['preset' => $presetName]);
            
            return $config;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to load preset: {$presetName}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Save configuration as preset
     */
    public function savePreset(string $presetName, array $config, bool $overwrite = false): bool
    {
        try {
            // Validate preset name
            if (!preg_match('/^[a-z0-9\-_]+$/i', $presetName)) {
                throw new InvalidArgumentException('Invalid preset name format');
            }
            
            $presetFile = $this->presetPath . "/{$presetName}.json";
            
            // Check if preset exists
            if (file_exists($presetFile) && !$overwrite) {
                throw new Exception("Preset '{$presetName}' already exists");
            }
            
            // Validate configuration
            $this->validateConfig($config);
            
            // Add metadata
            $config['_metadata'] = [
                'name' => $presetName,
                'created_at' => date('c'),
                'created_by' => $this->getCurrentUser(),
                'version' => '4.0'
            ];
            
            // Save preset
            $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($presetFile, $content) === false) {
                throw new Exception("Failed to write preset file");
            }
            
            $this->logger->info("Preset saved successfully", [
                'preset' => $presetName,
                'overwrite' => $overwrite
            ]);
            
            // Clear cache
            unset($this->cache['presets']);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to save preset: {$presetName}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete preset
     */
    public function deletePreset(string $presetName): bool
    {
        try {
            $presetFile = $this->presetPath . "/{$presetName}.json";
            
            if (!file_exists($presetFile)) {
                return false;
            }
            
            // Check if it's a protected preset
            if ($this->isProtectedPreset($presetName)) {
                throw new Exception("Cannot delete protected preset: {$presetName}");
            }
            
            if (unlink($presetFile)) {
                $this->logger->info("Preset deleted successfully", ['preset' => $presetName]);
                
                // Clear cache
                unset($this->cache['presets']);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to delete preset: {$presetName}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get list of available presets
     */
    public function getAvailablePresets(): array
    {
        if (isset($this->cache['presets'])) {
            return $this->cache['presets'];
        }
        
        try {
            $presets = [];
            $files = glob($this->presetPath . '/*.json');
            
            foreach ($files as $file) {
                $name = basename($file, '.json');
                $content = file_get_contents($file);
                $config = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $presets[$name] = [
                        'name' => $name,
                        'title' => $config['title'] ?? ucfirst(str_replace('-', ' ', $name)),
                        'description' => $config['description'] ?? '',
                        'created_at' => $config['_metadata']['created_at'] ?? null,
                        'created_by' => $config['_metadata']['created_by'] ?? null,
                        'version' => $config['_metadata']['version'] ?? 'unknown',
                        'protected' => $this->isProtectedPreset($name)
                    ];
                }
            }
            
            // Sort by name
            ksort($presets);
            
            $this->cache['presets'] = $presets;
            return $presets;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to get available presets", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Set current active configuration
     */
    public function setCurrentConfig(array $config): bool
    {
        try {
            // Validate configuration
            $this->validateConfig($config);
            
            // Save current config
            $configFile = $this->configPath . '/current.json';
            $configData = [
                'config' => $config,
                'updated_at' => date('c'),
                'updated_by' => $this->getCurrentUser()
            ];
            
            $content = json_encode($configData, JSON_PRETTY_PRINT);
            
            if (file_put_contents($configFile, $content) === false) {
                throw new Exception("Failed to save current configuration");
            }
            
            $this->currentConfig = $config;
            
            $this->logger->info("Current configuration updated");
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to set current configuration", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get product memory list
     */
    public function getProductMemory(): array
    {
        try {
            $memoryFile = $this->configPath . '/product_memory.json';
            
            if (!file_exists($memoryFile)) {
                return [];
            }
            
            $content = file_get_contents($memoryFile);
            $data = json_decode($content, true);
            
            return $data['products'] ?? [];
            
        } catch (Exception $e) {
            $this->logger->error("Failed to load product memory", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Save product memory list
     */
    public function saveProductMemory(array $products): bool
    {
        try {
            // Validate products
            foreach ($products as $product) {
                if (!is_string($product) || empty(trim($product))) {
                    throw new InvalidArgumentException("Invalid product in memory list");
                }
            }
            
            // Remove duplicates and empty entries
            $products = array_unique(array_filter(array_map('trim', $products)));
            
            $memoryFile = $this->configPath . '/product_memory.json';
            $data = [
                'products' => array_values($products),
                'updated_at' => date('c'),
                'updated_by' => $this->getCurrentUser(),
                'count' => count($products)
            ];
            
            $content = json_encode($data, JSON_PRETTY_PRINT);
            
            if (file_put_contents($memoryFile, $content) === false) {
                throw new Exception("Failed to save product memory");
            }
            
            $this->logger->info("Product memory saved", ['count' => count($products)]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error("Failed to save product memory", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get configuration schema for validation
     */
    public function getConfigSchema(): array
    {
        return [
            'weight_method' => [
                'type' => 'string',
                'required' => true,
                'values' => ['power', 'softmax', 'mixed']
            ],
            'reserve_percent' => [
                'type' => 'float',
                'required' => true,
                'min' => 0.0,
                'max' => 1.0
            ],
            'max_per_product' => [
                'type' => 'integer',
                'required' => true,
                'min' => 1,
                'max' => 1000
            ],
            'weight_gamma' => [
                'type' => 'float',
                'required' => false,
                'min' => 0.1,
                'max' => 10.0,
                'default' => 1.5
            ],
            'softmax_tau' => [
                'type' => 'float',
                'required' => false,
                'min' => 0.1,
                'max' => 10.0,
                'default' => 1.0
            ],
            'weight_mix_beta' => [
                'type' => 'float',
                'required' => false,
                'min' => 0.0,
                'max' => 1.0,
                'default' => 0.5
            ],
            'min_cap_per_outlet' => [
                'type' => 'integer',
                'required' => false,
                'min' => 0,
                'max' => 100,
                'default' => 1
            ],
            'live_mode' => [
                'type' => 'boolean',
                'required' => false,
                'default' => false
            ],
            'save_snapshot' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true
            ]
        ];
    }
    
    /**
     * Validate configuration against schema
     */
    public function validateConfig(array $config): bool
    {
        $schema = $this->getConfigSchema();
        $errors = [];
        
        // Check required fields
        foreach ($schema as $field => $rules) {
            if ($rules['required'] && !isset($config[$field])) {
                $errors[] = "Required field missing: {$field}";
                continue;
            }
            
            if (!isset($config[$field])) {
                continue;
            }
            
            $value = $config[$field];
            
            // Type validation
            switch ($rules['type']) {
                case 'string':
                    if (!is_string($value)) {
                        $errors[] = "Field {$field} must be a string";
                    }
                    break;
                    
                case 'integer':
                    if (!is_int($value) && !is_numeric($value)) {
                        $errors[] = "Field {$field} must be an integer";
                    }
                    break;
                    
                case 'float':
                    if (!is_numeric($value)) {
                        $errors[] = "Field {$field} must be a number";
                    }
                    break;
                    
                case 'boolean':
                    if (!is_bool($value)) {
                        $errors[] = "Field {$field} must be a boolean";
                    }
                    break;
            }
            
            // Range validation
            if (isset($rules['min']) && $value < $rules['min']) {
                $errors[] = "Field {$field} must be at least {$rules['min']}";
            }
            
            if (isset($rules['max']) && $value > $rules['max']) {
                $errors[] = "Field {$field} must be at most {$rules['max']}";
            }
            
            // Value validation
            if (isset($rules['values']) && !in_array($value, $rules['values'])) {
                $allowedValues = implode(', ', $rules['values']);
                $errors[] = "Field {$field} must be one of: {$allowedValues}";
            }
        }
        
        if (!empty($errors)) {
            throw new InvalidArgumentException("Configuration validation failed: " . implode(', ', $errors));
        }
        
        return true;
    }
    
    /**
     * Apply defaults to configuration
     */
    public function applyDefaults(array $config): array
    {
        $schema = $this->getConfigSchema();
        
        foreach ($schema as $field => $rules) {
            if (!isset($config[$field]) && isset($rules['default'])) {
                $config[$field] = $rules['default'];
            }
        }
        
        return $config;
    }
    
    /**
     * Export configuration to various formats
     */
    public function exportConfig(array $config, string $format = 'json'): string
    {
        switch (strtolower($format)) {
            case 'json':
                return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                
            case 'yaml':
                if (function_exists('yaml_emit')) {
                    return yaml_emit($config);
                }
                throw new Exception("YAML extension not available");
                
            case 'php':
                return "<?php\n\nreturn " . var_export($config, true) . ";\n";
                
            default:
                throw new InvalidArgumentException("Unsupported export format: {$format}");
        }
    }
    
    /**
     * Import configuration from various formats
     */
    public function importConfig(string $content, string $format = 'json'): array
    {
        switch (strtolower($format)) {
            case 'json':
                $config = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON: " . json_last_error_msg());
                }
                break;
                
            case 'yaml':
                if (function_exists('yaml_parse')) {
                    $config = yaml_parse($content);
                } else {
                    throw new Exception("YAML extension not available");
                }
                break;
                
            default:
                throw new InvalidArgumentException("Unsupported import format: {$format}");
        }
        
        $this->validateConfig($config);
        return $config;
    }
    
    /**
     * Initialize validation rules
     */
    private function initValidationRules(): void
    {
        $this->validationRules = $this->getConfigSchema();
    }
    
    /**
     * Load current configuration from file
     */
    private function loadCurrentConfig(): void
    {
        try {
            $configFile = $this->configPath . '/current.json';
            
            if (file_exists($configFile)) {
                $content = file_get_contents($configFile);
                $data = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && isset($data['config'])) {
                    $this->currentConfig = $data['config'];
                    return;
                }
            }
            
            // Load default configuration
            $this->currentConfig = $this->loadPreset('balanced') ?? $this->getDefaultConfig();
            
        } catch (Exception $e) {
            $this->logger->error("Failed to load current configuration", [
                'error' => $e->getMessage()
            ]);
            
            $this->currentConfig = $this->getDefaultConfig();
        }
    }
    
    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'weight_method' => 'power',
            'reserve_percent' => 0.1,
            'max_per_product' => 50,
            'weight_gamma' => 1.5,
            'softmax_tau' => 1.0,
            'weight_mix_beta' => 0.5,
            'min_cap_per_outlet' => 1,
            'live_mode' => false,
            'save_snapshot' => true
        ];
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories(): void
    {
        $directories = [$this->presetPath, $this->configPath];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Check if preset is protected
     */
    private function isProtectedPreset(string $presetName): bool
    {
        $protectedPresets = ['balanced', 'conservative', 'aggressive', 'softmax-strong'];
        return in_array($presetName, $protectedPresets);
    }
    
    /**
     * Get current user for audit trail
     */
    private function getCurrentUser(): string
    {
        // This would typically integrate with your authentication system
        return $_SESSION['user']['username'] ?? 'system';
    }
}