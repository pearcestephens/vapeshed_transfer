<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * Logger.php - Enterprise Structured Logging
 * 
 * Provides structured JSON logging with multiple severity levels,
 * context enrichment, and file/stream output support.
 * 
 * @package Unified\Support
 * @version 2.0.0
 * @date 2025-10-07
 */
final class Logger
{
    private string $channel;
    private ?string $logFile = null;
    
    /**
     * Create logger instance
     * 
     * @param string $channel Logger channel identifier
     * @param string|null $logFile Optional log file path (null = stdout/stderr)
     */
    public function __construct(string $channel, ?string $logFile = null)
    {
        $this->channel = $channel;
        $this->logFile = $logFile;
        
        // Ensure log directory exists if file specified
        if ($logFile !== null) {
            $dir = dirname($logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function info(string $msg, array $ctx = []): void
    {
        $this->log('INFO', $msg, $ctx);
    }
    
    /**
     * Log warning message
     * 
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function warn(string $msg, array $ctx = []): void
    {
        $this->log('WARN', $msg, $ctx);
    }
    
    /**
     * Log warning message (alias for warn)
     * 
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function warning(string $msg, array $ctx = []): void
    {
        $this->warn($msg, $ctx);
    }
    
    /**
     * Log error message
     * 
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function error(string $msg, array $ctx = []): void
    {
        $this->log('ERROR', $msg, $ctx);
    }
    
    /**
     * Log debug message
     * 
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function debug(string $msg, array $ctx = []): void
    {
        $this->log('DEBUG', $msg, $ctx);
    }
    
    /**
     * Log critical message
     * 
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function critical(string $msg, array $ctx = []): void
    {
        $this->log('CRITICAL', $msg, $ctx);
    }
    
    /**
     * Log with custom level
     * 
     * @param string $level Severity level
     * @param string $msg Message
     * @param array $ctx Context data
     */
    public function log(string $level, string $msg, array $ctx = []): void
    {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'channel' => $this->channel,
            'message' => $msg,
            'correlation_id' => function_exists('correlationId') ? \correlationId() : null,
            'neuro' => [
                'namespace' => 'unified',
                'system' => 'vapeshed_transfer',
                'environment' => class_exists('Unified\Support\Config') ? Config::get('neuro.unified.environment', 'production') : 'production',
                'version' => class_exists('Unified\Support\Config') ? Config::get('neuro.unified.version', '2.0.0') : '2.0.0',
            ],
            'context' => $ctx,
            'server' => [
                'hostname' => gethostname(),
                'pid' => getmypid(),
            ]
        ];
        
        // Add memory usage if available
        if (function_exists('memory_get_usage')) {
            $entry['memory_mb'] = round(memory_get_usage(true) / 1024 / 1024, 2);
        }
        
        // Add request info if in web context
        if (isset($_SERVER['REQUEST_URI'])) {
            $entry['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];
        }
        
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
        
        // Write to file or stream
        if ($this->logFile !== null) {
            @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        } else {
            // Write to stdout for INFO/DEBUG/WARN, stderr for ERROR/CRITICAL
            $stream = in_array($level, ['ERROR', 'CRITICAL'], true) ? STDERR : STDOUT;
            fwrite($stream, $line);
        }
    }
    
    /**
     * Log exception with full stack trace
     * 
     * @param \Throwable $e Exception
     * @param string $level Severity level (default: ERROR)
     * @param array $ctx Additional context
     */
    public function exception(\Throwable $e, string $level = 'ERROR', array $ctx = []): void
    {
        $ctx['exception'] = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        
        if ($e->getPrevious() !== null) {
            $ctx['exception']['previous'] = [
                'class' => get_class($e->getPrevious()),
                'message' => $e->getPrevious()->getMessage(),
            ];
        }
        
        $this->log($level, 'Exception: ' . $e->getMessage(), $ctx);
    }
    
    /**
     * Create logger from config
     * 
     * @param string $channel Channel name
     * @param array|null $config Config array (optional)
     * @return self
     */
    public static function fromConfig(string $channel, ?array $config = null): self
    {
        if ($config === null && class_exists('Unified\Support\Config')) {
            $logDir = Config::get('neuro.unified.log_directory', null);
            if ($logDir && is_string($logDir)) {
                $logFile = rtrim($logDir, '/') . '/' . $channel . '.log';
                return new self($channel, $logFile);
            }
        }
        
        return new self($channel);
    }
    
    /**
     * Rotate log file (create new, archive old)
     * 
     * @param int $maxSize Maximum file size in bytes before rotation
     * @return bool True if rotated, false otherwise
     */
    public function rotate(int $maxSize = 10485760): bool // 10MB default
    {
        if ($this->logFile === null || !is_file($this->logFile)) {
            return false;
        }
        
        $size = @filesize($this->logFile);
        if ($size === false || $size < $maxSize) {
            return false;
        }
        
        // Archive current log with timestamp
        $archiveName = $this->logFile . '.' . date('Y-m-d_His') . '.gz';
        
        // Compress and move
        $content = @file_get_contents($this->logFile);
        if ($content !== false) {
            $compressed = gzencode($content, 9);
            @file_put_contents($archiveName, $compressed);
            @unlink($this->logFile);
            
            return true;
        }
        
        return false;
    }
}

