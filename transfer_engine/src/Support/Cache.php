<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * Cache.php - Lightweight Caching System
 * 
 * Simple file-based cache with TTL support for configuration,
 * query results, and computed data.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class Cache
{
    private string $cacheDir;
    private int $defaultTtl;
    
    /**
     * Create cache instance
     * 
     * @param string|null $cacheDir Cache directory (null = temp dir)
     * @param int $defaultTtl Default TTL in seconds (default: 3600)
     */
    public function __construct(?string $cacheDir = null, int $defaultTtl = 3600)
    {
        $this->cacheDir = $cacheDir ?? (sys_get_temp_dir() . '/cache');
        $this->defaultTtl = $defaultTtl;
        
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }
    
    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found or expired
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null)
    {
        $file = $this->getFilePath($key);
        
        if (!is_file($file)) {
            return $default;
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return $default;
        }
        
        $data = @unserialize($content);
        if ($data === false || !is_array($data)) {
            return $default;
        }
        
        // Check expiration
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            @unlink($file);
            return $default;
        }
        
        return $data['value'] ?? $default;
    }
    
    /**
     * Set value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds (null = default TTL)
     * @return bool True on success
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $file = $this->getFilePath($key);
        $ttl = $ttl ?? $this->defaultTtl;
        
        $data = [
            'key' => $key,  // Store original key for pattern matching
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time(),
        ];
        
        $serialized = serialize($data);
        $result = @file_put_contents($file, $serialized, LOCK_EX);
        
        return $result !== false;
    }
    
    /**
     * Check if key exists and is not expired
     * 
     * @param string $key Cache key
     * @return bool True if exists and valid
     */
    public function has(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (!is_file($file)) {
            return false;
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return false;
        }
        
        $data = @unserialize($content);
        if ($data === false || !is_array($data)) {
            return false;
        }
        
        // Check expiration
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            @unlink($file);
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete key from cache
     * 
     * @param string $key Cache key
     * @return bool True if deleted
     */
    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (is_file($file)) {
            return @unlink($file);
        }
        
        return false;
    }
    
    /**
     * Clear all cache entries
     * 
     * @return int Number of entries cleared
     */
    public function clear(): int
    {
        $count = 0;
        
        $files = glob($this->cacheDir . '/cache_*.dat');
        if ($files === false) {
            return 0;
        }
        
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get value from cache or compute and store it
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to compute value if not cached
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Clean up expired cache entries
     * 
     * @return int Number of entries cleaned up
     */
    public function cleanup(): int
    {
        $count = 0;
        $now = time();
        
        $files = glob($this->cacheDir . '/cache_*.dat');
        if ($files === false) {
            return 0;
        }
        
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = @unserialize($content);
            if ($data === false || !is_array($data)) {
                // Corrupt file, delete it
                @unlink($file);
                $count++;
                continue;
            }
            
            if (isset($data['expires_at']) && $now > $data['expires_at']) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get all cache keys matching a pattern
     * 
     * @param string $pattern Pattern to match (e.g., 'prefix:*', '*:suffix', 'exact_key')
     * @return array Array of matching keys
     */
    public function keys(string $pattern = '*'): array
    {
        $files = glob($this->cacheDir . '/cache_*.dat');
        if ($files === false) {
            return [];
        }
        
        $keys = [];
        $now = time();
        
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = @unserialize($content);
            if ($data === false || !is_array($data) || !isset($data['key'])) {
                continue;
            }
            
            // Skip expired entries
            if (isset($data['expires_at']) && $now > $data['expires_at']) {
                continue;
            }
            
            $key = $data['key'];
            
            // Match pattern
            if ($this->matchesPattern($key, $pattern)) {
                $keys[] = $key;
            }
        }
        
        return $keys;
    }
    
    /**
     * Check if a key matches a wildcard pattern
     * 
     * @param string $key The key to check
     * @param string $pattern The pattern (supports * wildcard)
     * @return bool True if matches
     */
    private function matchesPattern(string $key, string $pattern): bool
    {
        // Exact match
        if ($pattern === '*' || $pattern === $key) {
            return true;
        }
        
        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '$/';
        
        return (bool) preg_match($regex, $key);
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics
     */
    public function stats(): array
    {
        $files = glob($this->cacheDir . '/cache_*.dat');
        if ($files === false) {
            return [
                'total_entries' => 0,
                'valid_entries' => 0,
                'expired_entries' => 0,
                'total_size_bytes' => 0,
            ];
        }
        
        $total = count($files);
        $valid = 0;
        $expired = 0;
        $totalSize = 0;
        $now = time();
        
        foreach ($files as $file) {
            $size = @filesize($file);
            if ($size !== false) {
                $totalSize += $size;
            }
            
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $data = @unserialize($content);
            if ($data === false || !is_array($data)) {
                continue;
            }
            
            if (isset($data['expires_at'])) {
                if ($now > $data['expires_at']) {
                    $expired++;
                } else {
                    $valid++;
                }
            }
        }
        
        return [
            'total_entries' => $total,
            'valid_entries' => $valid,
            'expired_entries' => $expired,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
        ];
    }
    
    /**
     * Get file path for cache key
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->cacheDir . '/cache_' . $hash . '.dat';
    }
    
    /**
     * Create cache instance from config
     * 
     * @return self
     */
    public static function fromConfig(): self
    {
        $cacheDir = null;
        $ttl = 3600;
        
        if (class_exists('Unified\Support\Config')) {
            $cacheDir = Config::get('neuro.unified.cache_directory', null);
            $ttl = (int) Config::get('neuro.unified.cache_ttl_seconds', 3600);
        }
        
        if ($cacheDir === null && defined('STORAGE_PATH')) {
            $cacheDir = STORAGE_PATH . '/cache';
        }
        
        return new self($cacheDir, $ttl);
    }
}
