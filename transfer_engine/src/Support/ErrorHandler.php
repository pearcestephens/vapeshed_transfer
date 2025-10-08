<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * ErrorHandler.php - Global Error and Exception Handler
 * 
 * Centralized error handling with logging, alerting, and debugging.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class ErrorHandler
{
    private static ?Logger $logger = null;
    private static bool $registered = false;
    private static bool $debug = false;
    
    /**
     * Register global error and exception handlers
     * 
     * @param Logger|null $logger Logger instance
     * @param bool $debug Enable debug mode
     */
    public static function register(?Logger $logger = null, bool $debug = false): void
    {
        if (self::$registered) {
            return;
        }
        
        self::$logger = $logger ?? new Logger('errors');
        self::$debug = $debug;
        
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$registered = true;
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number where error occurred
     * @return bool
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0
    ): bool {
        // Check if error should be handled (respect error_reporting)
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];
        
        $type = $errorTypes[$errno] ?? 'UNKNOWN';
        
        $context = [
            'neuro' => [
                'namespace' => 'unified',
                'system' => 'vapeshed_transfer',
                'component' => 'error_handler',
                'error_category' => 'php_error',
            ],
            'type' => $type,
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
        ];
        
        // Determine log level
        $level = match(true) {
            in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR]) => 'ERROR',
            in_array($errno, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING]) => 'WARN',
            default => 'INFO',
        };
        
        if (self::$logger) {
            self::$logger->log($level, $errstr, $context);
        }
        
        // In debug mode, also output to screen
        if (self::$debug) {
            self::renderError($type, $errstr, $errfile, $errline);
        }
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param \Throwable $exception Exception instance
     */
    public static function handleException(\Throwable $exception): void
    {
        $context = [
            'neuro' => [
                'namespace' => 'unified',
                'system' => 'vapeshed_transfer',
                'component' => 'error_handler',
                'error_category' => 'exception',
            ],
            'class' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        
        if ($exception->getPrevious() !== null) {
            $context['previous'] = [
                'class' => get_class($exception->getPrevious()),
                'message' => $exception->getPrevious()->getMessage(),
            ];
        }
        
        if (self::$logger) {
            self::$logger->exception($exception, 'CRITICAL', $context);
        }
        
        // In debug mode, render detailed error page
        if (self::$debug) {
            self::renderException($exception);
        } else {
            // Production: generic error page
            self::renderGenericError();
        }
        
        exit(1);
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error === null) {
            return;
        }
        
        // Only handle fatal errors
        $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        
        if (!in_array($error['type'], $fatalErrors, true)) {
            return;
        }
        
        $context = [
            'neuro' => [
                'namespace' => 'unified',
                'system' => 'vapeshed_transfer',
                'component' => 'error_handler',
                'error_category' => 'fatal',
            ],
            'type' => 'FATAL',
            'file' => $error['file'] ?? 'unknown',
            'line' => $error['line'] ?? 0,
        ];
        
        if (self::$logger) {
            self::$logger->critical($error['message'] ?? 'Fatal error occurred', $context);
        }
        
        if (self::$debug) {
            self::renderError(
                'FATAL',
                $error['message'] ?? 'Fatal error occurred',
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0
            );
        } else {
            self::renderGenericError();
        }
    }
    
    /**
     * Render error for debugging
     * 
     * @param string $type Error type
     * @param string $message Error message
     * @param string $file File path
     * @param int $line Line number
     */
    private static function renderError(string $type, string $message, string $file, int $line): void
    {
        if (PHP_SAPI === 'cli') {
            echo "\n";
            echo "┌─ [$type] ─────────────────────────────────────────\n";
            echo "│ Message: $message\n";
            echo "│ File: $file\n";
            echo "│ Line: $line\n";
            echo "└───────────────────────────────────────────────────────\n";
            echo "\n";
        } else {
            http_response_code(500);
            echo "<!DOCTYPE html>\n";
            echo "<html><head><title>Error</title></head><body>\n";
            echo "<h1>$type</h1>\n";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>\n";
            echo "<p><strong>File:</strong> " . htmlspecialchars($file) . "</p>\n";
            echo "<p><strong>Line:</strong> $line</p>\n";
            echo "</body></html>\n";
        }
    }
    
    /**
     * Render exception for debugging
     * 
     * @param \Throwable $exception Exception instance
     */
    private static function renderException(\Throwable $exception): void
    {
        if (PHP_SAPI === 'cli') {
            echo "\n";
            echo "┌─ UNCAUGHT EXCEPTION ───────────────────────────────\n";
            echo "│ Class: " . get_class($exception) . "\n";
            echo "│ Message: " . $exception->getMessage() . "\n";
            echo "│ File: " . $exception->getFile() . "\n";
            echo "│ Line: " . $exception->getLine() . "\n";
            echo "├─ STACK TRACE ──────────────────────────────────────\n";
            echo $exception->getTraceAsString() . "\n";
            echo "└───────────────────────────────────────────────────────\n";
            echo "\n";
        } else {
            http_response_code(500);
            echo "<!DOCTYPE html>\n";
            echo "<html><head><title>Exception</title></head><body>\n";
            echo "<h1>Uncaught Exception: " . htmlspecialchars(get_class($exception)) . "</h1>\n";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>\n";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>\n";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>\n";
            echo "<h2>Stack Trace:</h2>\n";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>\n";
            echo "</body></html>\n";
        }
    }
    
    /**
     * Render generic error page for production
     */
    private static function renderGenericError(): void
    {
        if (PHP_SAPI === 'cli') {
            echo "\nAn error occurred. Please check the logs.\n\n";
        } else {
            http_response_code(500);
            echo "<!DOCTYPE html>\n";
            echo "<html><head><title>Error</title></head><body>\n";
            echo "<h1>An Error Occurred</h1>\n";
            echo "<p>We're sorry, but something went wrong. Please try again later.</p>\n";
            echo "<p>If the problem persists, please contact support.</p>\n";
            echo "</body></html>\n";
        }
    }
    
    /**
     * Create error handler from config
     * 
     * @return void
     */
    public static function fromConfig(): void
    {
        if (class_exists('Unified\Support\Config')) {
            $env = Config::get('neuro.unified.environment', 'production');
            $debug = $env === 'development';
            
            $logDir = Config::get('neuro.unified.log_directory', null);
            $logFile = $logDir ? rtrim($logDir, '/') . '/errors.log' : null;
            
            $logger = new Logger('errors', $logFile);
            
            self::register($logger, $debug);
        } else {
            self::register();
        }
    }
}
