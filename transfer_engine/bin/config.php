#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Configuration Management CLI Tool
 * 
 * View and validate application configuration
 * 
 * Usage:
 *   php bin/config.php list              - List all configuration keys
 *   php bin/config.php get <key>         - Get specific configuration value
 *   php bin/config.php validate          - Validate required configuration
 *   php bin/config.php groups            - Show rate limit groups
 *   php bin/config.php export [file]     - Export configuration to JSON
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\Config;
use Unified\Support\Logger;

$logger = new Logger('config_cli');

// Parse command
$command = $argv[1] ?? 'help';
$arg = $argv[2] ?? null;

Config::prime();

switch ($command) {
    case 'list':
        $all = Config::all();
        echo "📋 Configuration Keys (" . count($all) . " total):\n\n";
        
        ksort($all);
        
        foreach ($all as $key => $value) {
            $valueStr = is_array($value) ? json_encode($value) : var_export($value, true);
            if (strlen($valueStr) > 80) {
                $valueStr = substr($valueStr, 0, 77) . '...';
            }
            echo "  $key = $valueStr\n";
        }
        break;
        
    case 'get':
        if ($arg === null) {
            echo "❌ Error: Key required\n";
            echo "Usage: php bin/config.php get <key>\n";
            exit(1);
        }
        
        $value = Config::get($arg);
        
        if ($value === null) {
            echo "❌ Key not found: $arg\n";
            exit(1);
        }
        
        echo "✅ $arg:\n";
        if (is_array($value)) {
            echo json_encode($value, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo var_export($value, true) . "\n";
        }
        break;
        
    case 'validate':
        echo "🔍 Validating required configuration...\n\n";
        
        $required = Config::requiredKeys();
        $missing = Config::missing();
        
        echo "Required keys: " . count($required) . "\n";
        echo "Missing keys: " . count($missing) . "\n\n";
        
        if (empty($missing)) {
            echo "✅ All required configuration keys are present\n";
            exit(0);
        }
        
        echo "❌ Missing required keys:\n";
        foreach ($missing as $key) {
            echo "  - $key\n";
        }
        exit(1);
        break;
        
    case 'groups':
        echo "📊 Rate Limit Groups:\n\n";
        
        $groups = [
            'pricing', 'transfer', 'history', 'traces', 'stats', 
            'modules', 'activity', 'smoke', 'unified', 'session', 
            'diagnostics', 'health', 'metrics'
        ];
        
        echo sprintf("%-15s %10s %10s %10s %10s\n", "Group", "GET/min", "GET Burst", "POST/min", "POST Burst");
        echo str_repeat("-", 65) . "\n";
        
        foreach ($groups as $group) {
            $getLimit = Config::get("neuro.unified.security.groups.$group.get_rate_limit_per_min", 0);
            $getBurst = Config::get("neuro.unified.security.groups.$group.get_rate_burst", 0);
            $postLimit = Config::get("neuro.unified.security.groups.$group.post_rate_limit_per_min", 0);
            $postBurst = Config::get("neuro.unified.security.groups.$group.post_rate_burst", 0);
            
            echo sprintf("%-15s %10d %10d %10d %10d\n", $group, $getLimit, $getBurst, $postLimit, $postBurst);
        }
        echo "\n";
        break;
        
    case 'export':
        $file = $arg ?? 'config_export.json';
        
        echo "📤 Exporting configuration to: $file\n";
        
        $all = Config::all();
        $json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($file, $json) === false) {
            echo "❌ Error: Failed to write file\n";
            exit(1);
        }
        
        $logger->info("Exported configuration", ['file' => $file, 'keys' => count($all)]);
        echo "✅ Exported " . count($all) . " keys to $file\n";
        break;
        
    case 'env':
        echo "🌍 Environment Configuration:\n\n";
        
        $envKeys = [
            'APP_ENV' => Config::get('neuro.unified.environment'),
            'CSRF_REQUIRED' => Config::get('neuro.unified.security.csrf_required') ? 'true' : 'false',
            'GET_RL_PER_MIN' => Config::get('neuro.unified.security.get_rate_limit_per_min'),
            'GET_RL_BURST' => Config::get('neuro.unified.security.get_rate_burst'),
            'POST_RL_PER_MIN' => Config::get('neuro.unified.security.post_rate_limit_per_min'),
            'POST_RL_BURST' => Config::get('neuro.unified.security.post_rate_burst'),
        ];
        
        foreach ($envKeys as $key => $value) {
            echo "  $key = $value\n";
        }
        echo "\n";
        break;
        
    case 'help':
    default:
        echo "Configuration Management CLI Tool\n\n";
        echo "Usage:\n";
        echo "  php bin/config.php list              - List all configuration keys\n";
        echo "  php bin/config.php get <key>         - Get specific configuration value\n";
        echo "  php bin/config.php validate          - Validate required configuration\n";
        echo "  php bin/config.php groups            - Show rate limit groups\n";
        echo "  php bin/config.php env               - Show environment configuration\n";
        echo "  php bin/config.php export [file]     - Export configuration to JSON\n";
        echo "  php bin/config.php help              - Show this help\n";
        break;
}

exit(0);
