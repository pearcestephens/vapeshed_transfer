<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuration Manager
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Manages configuration loading, validation, and persistence
 */
class ConfigManager
{
    private string $configPath;
    private string $presetsPath;
    
    public function __construct()
    {
        $this->configPath = STORAGE_PATH . '/config/settings.json';
        $this->presetsPath = CONFIG_PATH . '/presets';
        
        // Ensure directories exist
        $configDir = dirname($this->configPath);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
    }
    
    public function getDefaultSettings(): array
    {
        return [
            'dry' => 1,
            'warehouse_id' => WAREHOUSE_ID,
            'warehouse_web_outlet_id' => WAREHOUSE_WEB_OUTLET_ID,
            'skip_outlets' => '020b2c2a-4671-11f0-e200-8e55f1689700',
            'min_lines' => 5,
            'max_per_product' => 40,
            'min_cap_per_outlet' => 10,
            'top_k_outlets' => 0,
            'dynamic_top_k' => 0,
            'reserve_percent' => 0.20,
            'reserve_min_units' => 2,
            'turnover_min_pct' => 3,
            'turnover_max_pct' => 10,
            'default_turnover_pct' => 7,
            'weight_method' => 'power',
            'weight_gamma' => 1.8,
            'weight_epsilon' => 1.0,
            'softmax_tau' => 6.0,
            'weight_mix_beta' => 0.8,
            'compare_mode' => 'quartiles'
        ];
    }
    
    public function loadSettings(): array
    {
        $defaults = $this->getDefaultSettings();
        
        if (!file_exists($this->configPath)) {
            return $defaults;
        }
        
        $saved = json_decode(file_get_contents($this->configPath), true);
        
        if (!is_array($saved)) {
            return $defaults;
        }
        
        return array_merge($defaults, $saved);
    }
    
    public function saveSettings(array $settings): void
    {
        // Validate settings before saving
        $validated = $this->validateSettings($settings);
        
        // Save atomically
        $tempPath = $this->configPath . '.tmp';
        $content = json_encode($validated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($tempPath, $content, LOCK_EX) === false) {
            throw new \Exception('Failed to write configuration file');
        }
        
        if (!rename($tempPath, $this->configPath)) {
            unlink($tempPath);
            throw new \Exception('Failed to save configuration file');
        }
        
        chmod($this->configPath, 0644);
    }
    
    public function validateSettings(array $settings): array
    {
        $defaults = $this->getDefaultSettings();
        $validated = [];
        
        // Validate each setting
        foreach ($defaults as $key => $defaultValue) {
            $value = $settings[$key] ?? $defaultValue;
            
            switch ($key) {
                case 'dry':
                case 'warehouse_web_outlet_id':
                case 'min_lines':
                case 'max_per_product':
                case 'min_cap_per_outlet':
                case 'top_k_outlets':
                case 'dynamic_top_k':
                case 'reserve_min_units':
                case 'turnover_min_pct':
                case 'turnover_max_pct':
                case 'default_turnover_pct':
                    $validated[$key] = (int)$value;
                    break;
                    
                case 'reserve_percent':
                case 'weight_gamma':
                case 'weight_epsilon':
                case 'softmax_tau':
                case 'weight_mix_beta':
                    $validated[$key] = (float)$value;
                    break;
                    
                case 'weight_method':
                    $validated[$key] = in_array($value, ['power', 'softmax']) ? $value : 'power';
                    break;
                    
                case 'compare_mode':
                    $validated[$key] = in_array($value, ['quartiles', 'median']) ? $value : 'quartiles';
                    break;
                    
                default:
                    $validated[$key] = (string)$value;
                    break;
            }
        }
        
        // Apply constraints
        $validated['min_lines'] = max(1, $validated['min_lines']);
    $validated['max_per_product'] = max(1, $validated['max_per_product']);
        $validated['min_cap_per_outlet'] = max(0, $validated['min_cap_per_outlet']);
    $validated['top_k_outlets'] = max(0, $validated['top_k_outlets']);
    $validated['dynamic_top_k'] = max(0, $validated['dynamic_top_k']);
        $validated['reserve_percent'] = max(0.0, min(0.90, $validated['reserve_percent']));
        $validated['weight_mix_beta'] = max(0.0, min(1.0, $validated['weight_mix_beta']));
        $validated['softmax_tau'] = max(0.1, $validated['softmax_tau']);
        
        // Ensure turnover percentages are logical
        if ($validated['turnover_min_pct'] > $validated['turnover_max_pct']) {
            [$validated['turnover_min_pct'], $validated['turnover_max_pct']] = 
                [$validated['turnover_max_pct'], $validated['turnover_min_pct']];
        }
        
        // Force dry run if kill switch is active
        if (file_exists(STORAGE_PATH . '/KILL_SWITCH')) {
            $validated['dry'] = 1;
        }
        
        return $validated;
    }
    
    public function loadPreset(string $presetName): ?array
    {
        $presetFile = $this->presetsPath . '/' . $presetName . '.json';
        
        if (!file_exists($presetFile)) {
            return null;
        }
        
        $preset = json_decode(file_get_contents($presetFile), true);
        
        if (!is_array($preset)) {
            return null;
        }
        
        return $this->validateSettings($preset);
    }
    
    public function getAvailablePresets(): array
    {
        $presets = [];
        
        if (!is_dir($this->presetsPath)) {
            return $presets;
        }
        
        $files = glob($this->presetsPath . '/*.json');
        
        foreach ($files as $file) {
            $name = basename($file, '.json');
            $presets[$name] = $this->loadPreset($name);
        }
        
        return $presets;
    }
}