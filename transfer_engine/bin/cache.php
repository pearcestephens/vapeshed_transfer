#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Cache Management CLI Tool
 * 
 * Manage application cache: clear, stats, cleanup
 * 
 * Usage:
 *   php bin/cache.php clear          - Clear all cache entries
 *   php bin/cache.php cleanup        - Remove expired entries
 *   php bin/cache.php stats          - Show cache statistics
 *   php bin/cache.php get <key>      - Get cache value
 *   php bin/cache.php delete <key>   - Delete cache key
 * 
 * @version 1.0.0
 * @date 2025-10-07
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Unified\Support\Cache;
use Unified\Support\Logger;

$logger = new Logger('cache_cli');

// Parse command
$command = $argv[1] ?? 'help';
$arg = $argv[2] ?? null;

$cache = Cache::fromConfig();

switch ($command) {
    case 'clear':
        $logger->info('Clearing all cache entries...');
        $count = $cache->clear();
        $logger->info("Cleared $count cache entries");
        echo "✅ Cleared $count cache entries\n";
        break;
        
    case 'cleanup':
        $logger->info('Cleaning up expired cache entries...');
        $count = $cache->cleanup();
        $logger->info("Cleaned up $count expired entries");
        echo "✅ Cleaned up $count expired entries\n";
        break;
        
    case 'stats':
        $stats = $cache->stats();
        echo "📊 Cache Statistics:\n";
        echo "  Total entries: {$stats['total_entries']}\n";
        echo "  Valid entries: {$stats['valid_entries']}\n";
        echo "  Expired entries: {$stats['expired_entries']}\n";
        echo "  Total size: {$stats['total_size_mb']} MB\n";
        break;
        
    case 'get':
        if ($arg === null) {
            echo "❌ Error: Key required\n";
            echo "Usage: php bin/cache.php get <key>\n";
            exit(1);
        }
        
        $value = $cache->get($arg);
        if ($value === null) {
            echo "❌ Key not found or expired: $arg\n";
            exit(1);
        }
        
        echo "✅ Value for key '$arg':\n";
        echo json_encode($value, JSON_PRETTY_PRINT) . "\n";
        break;
        
    case 'delete':
        if ($arg === null) {
            echo "❌ Error: Key required\n";
            echo "Usage: php bin/cache.php delete <key>\n";
            exit(1);
        }
        
        $deleted = $cache->delete($arg);
        if ($deleted) {
            $logger->info("Deleted cache key: $arg");
            echo "✅ Deleted key: $arg\n";
        } else {
            echo "❌ Key not found: $arg\n";
            exit(1);
        }
        break;
        
    case 'help':
    default:
        echo "Cache Management CLI Tool\n\n";
        echo "Usage:\n";
        echo "  php bin/cache.php clear          - Clear all cache entries\n";
        echo "  php bin/cache.php cleanup        - Remove expired entries\n";
        echo "  php bin/cache.php stats          - Show cache statistics\n";
        echo "  php bin/cache.php get <key>      - Get cache value\n";
        echo "  php bin/cache.php delete <key>   - Delete cache key\n";
        echo "  php bin/cache.php help           - Show this help\n";
        break;
}

exit(0);
