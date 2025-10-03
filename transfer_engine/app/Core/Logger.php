<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logger Class
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Simple file-based logger with structured logging
 */
class Logger
{
    private string $logPath;
    private string $logLevel;
    
    const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    public function __construct()
    {
        $this->logPath = LOG_PATH;
        $this->logLevel = LOG_LEVEL;
        
        // Ensure log directory exists
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }
    
    private function log(string $level, string $message, array $context = []): void
    {
        // Check if level should be logged
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }
        
        $logEntry = [
            'timestamp' => date('c'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        
        // Write to log file - suppress errors in web context
        @file_put_contents($this->logPath, $logLine, FILE_APPEND | LOCK_EX);
    }
}