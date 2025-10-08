#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Log Management CLI Tool
 * 
 * Manage application logs: tail, search, rotate, cleanup
 * 
 * Usage:
 *   php bin/logs.php tail [channel] [lines]  - Tail log file (default: 20 lines)
 *   php bin/logs.php search <pattern>        - Search logs for pattern
 *   php bin/logs.php rotate [channel]        - Rotate log file
 *   php bin/logs.php cleanup [days]          - Delete logs older than N days (default: 30)
 *   php bin/logs.php list                    - List all log files
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\Logger;
use Unified\Support\Config;

$logger = new Logger('logs_cli');

// Parse command
$command = $argv[1] ?? 'help';
$arg1 = $argv[2] ?? null;
$arg2 = $argv[3] ?? null;

$logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : sys_get_temp_dir();

if (!is_dir($logDir)) {
    echo "❌ Error: Log directory not found: $logDir\n";
    exit(1);
}

switch ($command) {
    case 'tail':
        $channel = $arg1 ?? 'api_access';
        $lines = (int) ($arg2 ?? 20);
        $logFile = $logDir . '/' . $channel . '.log';
        
        if (!is_file($logFile)) {
            echo "❌ Error: Log file not found: $logFile\n";
            exit(1);
        }
        
        echo "📄 Tailing $lines lines from $channel.log:\n\n";
        
        // Read last N lines
        $file = new \SplFileObject($logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key() + 1;
        
        $startLine = max(0, $totalLines - $lines);
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = $file->current();
            if (trim($line) !== '') {
                // Try to parse as JSON for pretty output
                $json = json_decode($line, true);
                if ($json !== null && is_array($json)) {
                    $timestamp = $json['timestamp'] ?? $json['ts'] ?? '?';
                    $level = $json['level'] ?? $json['lvl'] ?? 'INFO';
                    $message = $json['message'] ?? $json['msg'] ?? '';
                    
                    $levelColor = match($level) {
                        'ERROR', 'CRITICAL' => "\033[31m", // Red
                        'WARN' => "\033[33m",              // Yellow
                        'INFO' => "\033[32m",              // Green
                        'DEBUG' => "\033[36m",             // Cyan
                        default => "\033[0m",              // Default
                    };
                    
                    echo "{$levelColor}[{$timestamp}] [{$level}]\033[0m {$message}\n";
                } else {
                    echo $line;
                }
            }
            $file->next();
        }
        break;
        
    case 'search':
        if ($arg1 === null) {
            echo "❌ Error: Search pattern required\n";
            echo "Usage: php bin/logs.php search <pattern>\n";
            exit(1);
        }
        
        $pattern = $arg1;
        echo "🔍 Searching for: $pattern\n\n";
        
        $files = glob($logDir . '/*.log');
        $found = 0;
        
        foreach ($files as $file) {
            $matches = [];
            exec("grep -i " . escapeshellarg($pattern) . " " . escapeshellarg($file), $matches);
            
            if (!empty($matches)) {
                echo "\n📄 " . basename($file) . ":\n";
                foreach ($matches as $match) {
                    echo "  " . $match . "\n";
                    $found++;
                }
            }
        }
        
        echo "\n✅ Found $found matching entries\n";
        break;
        
    case 'rotate':
        $channel = $arg1 ?? null;
        
        if ($channel === null) {
            echo "❌ Error: Channel required\n";
            echo "Usage: php bin/logs.php rotate <channel>\n";
            exit(1);
        }
        
        $logFile = $logDir . '/' . $channel . '.log';
        
        if (!is_file($logFile)) {
            echo "❌ Error: Log file not found: $logFile\n";
            exit(1);
        }
        
        $size = filesize($logFile);
        $sizeMb = round($size / 1024 / 1024, 2);
        
        echo "🔄 Rotating $channel.log ($sizeMb MB)...\n";
        
        $archiveName = $logFile . '.' . date('Y-m-d_His') . '.gz';
        $content = file_get_contents($logFile);
        $compressed = gzencode($content, 9);
        
        file_put_contents($archiveName, $compressed);
        unlink($logFile);
        
        $logger->info("Rotated log file: $channel", ['size_mb' => $sizeMb]);
        echo "✅ Rotated to: " . basename($archiveName) . "\n";
        break;
        
    case 'cleanup':
        $days = (int) ($arg1 ?? 30);
        echo "🧹 Cleaning up logs older than $days days...\n";
        
        $cutoff = time() - ($days * 86400);
        $files = glob($logDir . '/*.log.*');
        $deleted = 0;
        $freedMb = 0;
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime < $cutoff) {
                $size = filesize($file);
                if (unlink($file)) {
                    $deleted++;
                    $freedMb += $size / 1024 / 1024;
                }
            }
        }
        
        $freedMb = round($freedMb, 2);
        $logger->info("Cleaned up old logs", ['deleted' => $deleted, 'freed_mb' => $freedMb]);
        echo "✅ Deleted $deleted files, freed $freedMb MB\n";
        break;
        
    case 'list':
        echo "📄 Log Files:\n\n";
        
        $files = glob($logDir . '/*.log*');
        
        foreach ($files as $file) {
            $size = filesize($file);
            $sizeMb = round($size / 1024 / 1024, 2);
            $mtime = date('Y-m-d H:i:s', filemtime($file));
            
            $name = basename($file);
            echo sprintf("  %-40s %8.2f MB  %s\n", $name, $sizeMb, $mtime);
        }
        
        echo "\n";
        break;
        
    case 'help':
    default:
        echo "Log Management CLI Tool\n\n";
        echo "Usage:\n";
        echo "  php bin/logs.php tail [channel] [lines]  - Tail log file (default: 20 lines)\n";
        echo "  php bin/logs.php search <pattern>        - Search logs for pattern\n";
        echo "  php bin/logs.php rotate [channel]        - Rotate log file\n";
        echo "  php bin/logs.php cleanup [days]          - Delete logs older than N days (default: 30)\n";
        echo "  php bin/logs.php list                    - List all log files\n";
        echo "  php bin/logs.php help                    - Show this help\n";
        break;
}

exit(0);
