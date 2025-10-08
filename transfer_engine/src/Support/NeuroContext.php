<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * NeuroContext.php - Neuro Context Provider
 * 
 * Provides standardized "neuro" context for all logging operations.
 * Ensures consistent namespace, system, and component identification.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class NeuroContext
{
    /**
     * Get base neuro context
     * 
     * @param string $component Component identifier
     * @param array $additional Additional context fields
     * @return array Neuro context
     */
    public static function get(string $component, array $additional = []): array
    {
        return array_merge([
            'namespace' => 'unified',
            'system' => 'vapeshed_transfer',
            'component' => $component,
            'environment' => self::getEnvironment(),
            'version' => self::getVersion(),
        ], $additional);
    }
    
    /**
     * Get full logging context with neuro wrapper
     * 
     * @param string $component Component identifier
     * @param array $context Application context data
     * @param array $neuroExtra Additional neuro fields
     * @return array Full context with neuro section
     */
    public static function wrap(string $component, array $context = [], array $neuroExtra = []): array
    {
        return array_merge([
            'neuro' => self::get($component, $neuroExtra),
        ], $context);
    }
    
    /**
     * Get API request context
     * 
     * @param string $endpoint Endpoint identifier
     * @param array $additional Additional context
     * @return array API context
     */
    public static function api(string $endpoint, array $additional = []): array
    {
        return self::wrap('api', array_merge([
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'correlation_id' => function_exists('correlationId') ? \correlationId() : null,
        ], $additional), [
            'request_type' => 'http',
        ]);
    }
    
    /**
     * Get database context
     * 
     * @param string $operation Operation type (query, insert, update, delete)
     * @param array $additional Additional context
     * @return array Database context
     */
    public static function database(string $operation, array $additional = []): array
    {
        return self::wrap('database', array_merge([
            'operation' => $operation,
        ], $additional), [
            'subsystem' => 'pdo',
        ]);
    }
    
    /**
     * Get monitoring context
     * 
     * @param string $checkType Check type (health, metric, alert)
     * @param array $additional Additional context
     * @return array Monitoring context
     */
    public static function monitoring(string $checkType, array $additional = []): array
    {
        return self::wrap('monitor', array_merge([
            'check_type' => $checkType,
        ], $additional), [
            'subsystem' => 'health',
        ]);
    }
    
    /**
     * Get security context
     * 
     * @param string $eventType Security event type
     * @param array $additional Additional context
     * @return array Security context
     */
    public static function security(string $eventType, array $additional = []): array
    {
        return self::wrap('security', array_merge([
            'event_type' => $eventType,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ], $additional), [
            'subsystem' => 'access_control',
        ]);
    }
    
    /**
     * Get CLI context
     * 
     * @param string $script Script name
     * @param array $additional Additional context
     * @return array CLI context
     */
    public static function cli(string $script, array $additional = []): array
    {
        global $argv;
        
        return self::wrap('cli', array_merge([
            'script' => $script,
            'args' => $argv ?? [],
            'user' => get_current_user(),
            'pid' => getmypid(),
        ], $additional), [
            'interface' => 'command_line',
        ]);
    }
    
    /**
     * Get cron context
     * 
     * @param string $job Job identifier
     * @param array $additional Additional context
     * @return array Cron context
     */
    public static function cron(string $job, array $additional = []): array
    {
        return self::wrap('cron', array_merge([
            'job' => $job,
            'started_at' => date('c'),
            'pid' => getmypid(),
        ], $additional), [
            'interface' => 'scheduled_task',
        ]);
    }
    
    /**
     * Get environment from config
     * 
     * @return string Environment
     */
    private static function getEnvironment(): string
    {
        if (class_exists('Unified\Support\Config')) {
            return Config::get('neuro.unified.environment', 'production');
        }
        
        return 'production';
    }
    
    /**
     * Get version from config
     * 
     * @return string Version
     */
    private static function getVersion(): string
    {
        if (class_exists('Unified\Support\Config')) {
            return Config::get('neuro.unified.version', '2.0.0');
        }
        
        return '2.0.0';
    }
    
    /**
     * Add performance metrics to context
     * 
     * @param array $context Existing context
     * @return array Context with performance data
     */
    public static function withPerformance(array $context): array
    {
        $context['performance'] = [
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
        
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $context['performance']['load_avg'] = [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2),
            ];
        }
        
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $duration = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
            $context['performance']['duration_ms'] = round($duration * 1000, 2);
        }
        
        return $context;
    }
    
    /**
     * Add trace information to context
     * 
     * @param array $context Existing context
     * @param int $limit Backtrace limit
     * @return array Context with trace data
     */
    public static function withTrace(array $context, int $limit = 5): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        
        $context['trace'] = array_map(function($frame) {
            return sprintf(
                '%s:%d %s%s%s()',
                basename($frame['file'] ?? 'unknown'),
                $frame['line'] ?? 0,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown'
            );
        }, $trace);
        
        return $context;
    }
}
