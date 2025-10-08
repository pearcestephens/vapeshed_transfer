<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * CacheManager - Enterprise Cache Management (Phase 8)
 * 
 * Extended caching system with multiple drivers, tags, and advanced features.
 * Wraps the base Cache class with enterprise capabilities.
 * 
 * @package Unified\Support
 * @version 2.0.0
 * @date 2025-10-07
 */
final class CacheManager
{
    private Cache $cache;
    private Logger $logger;
    private array $config;
    private array $tags = [];
    
    /**
     * Track which keys belong to which tags for flush operations
     * Structure: ['tag_name' => ['key1', 'key2', ...]]
     * @var array
     */
    private array $tagIndex = [];
    
    /**
     * Initialize cache manager
     * 
     * @param Logger $logger Logger instance
     * @param array $config Configuration options
     */
    public function __construct(Logger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'driver' => 'file',
            'default_ttl' => 3600,
            'cache_dir' => sys_get_temp_dir() . '/cache',
        ], $config);
        
        // Initialize base cache
        $this->cache = new Cache(
            $this->config['cache_dir'],
            $this->config['default_ttl']
        );
        
        $this->logger->info('cache.manager.initialized', [
            'driver' => $this->config['driver'],
            'cache_dir' => $this->config['cache_dir'],
        ]);
    }
    
    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null)
    {
        $prefixedKey = $this->getPrefixedKey($key);
        $value = $this->cache->get($prefixedKey, $default);
        
        $this->logger->debug('cache.get', [
            'key' => $key,
            'hit' => $value !== $default,
        ]);
        
        return $value;
    }
    
    /**
     * Set value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $prefixedKey = $this->getPrefixedKey($key);
        $result = $this->cache->set($prefixedKey, $value, $ttl);
        
        // Track this key in tag index if tags are active
        if (!empty($this->tags)) {
            foreach ($this->tags as $tag) {
                if (!isset($this->tagIndex[$tag])) {
                    $this->tagIndex[$tag] = [];
                }
                if (!in_array($prefixedKey, $this->tagIndex[$tag])) {
                    $this->tagIndex[$tag][] = $prefixedKey;
                }
            }
        }
        
        $this->logger->debug('cache.set', [
            'key' => $key,
            'success' => $result,
            'ttl' => $ttl,
            'tags' => $this->tags,
        ]);
        
        return $result;
    }
    
    /**
     * Delete value from cache
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        $prefixedKey = $this->getPrefixedKey($key);
        $result = $this->cache->delete($prefixedKey);
        
        $this->logger->debug('cache.delete', [
            'key' => $key,
            'success' => $result,
        ]);
        
        return $result;
    }
    
    /**
     * Clear all cache
     * 
     * @return bool Success status
     */
    public function clear(): bool
    {
        $result = $this->cache->clear();
        
        $this->logger->info('cache.cleared', [
            'success' => $result,
        ]);
        
        return $result;
    }
    
    /**
     * Check if key exists
     * 
     * @param string $key Cache key
     * @return bool True if exists
     */
    public function has(string $key): bool
    {
        $prefixedKey = $this->getPrefixedKey($key);
        return $this->cache->has($prefixedKey);
    }
    
    /**
     * Increment numeric value
     * 
     * @param string $key Cache key
     * @param int $value Amount to increment
     * @return int New value
     */
    public function increment(string $key, int $value = 1): int
    {
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        
        $this->logger->debug('cache.increment', [
            'key' => $key,
            'from' => $current,
            'to' => $new,
        ]);
        
        return $new;
    }
    
    /**
     * Decrement numeric value
     * 
     * @param string $key Cache key
     * @param int $value Amount to decrement
     * @return int New value
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->increment($key, -$value);
    }
    
    /**
     * Remember value (get or set)
     * 
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @param callable $callback Callback to generate value
     * @return mixed Cached or generated value
     */
    public function remember(string $key, int $ttl, callable $callback)
    {
        // Use a unique sentinel value to detect cache misses
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel);
        
        // If value is the sentinel, cache miss - execute callback
        if ($value === $sentinel) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
    
    /**
     * Tag cache entries
     * 
     * @param string|array $tags Tag names
     * @return self Fluent interface
     */
    public function tags($tags): self
    {
        $this->tags = is_array($tags) ? $tags : [$tags];
        return $this;
    }
    
    /**
     * Flush cache entries by tags
     * 
     * @param array|string|null $tags Tags to flush
     * @return bool Success status
     */
    public function flush($tags = null): bool
    {
        if ($tags === null) {
            $tags = $this->tags;
        } else {
            $tags = is_array($tags) ? $tags : [$tags];
        }
        
        $deleted = [];
        $count = 0;
        
        // Delete all keys associated with these tags using our tag index
        foreach ($tags as $tag) {
            if (isset($this->tagIndex[$tag])) {
                foreach ($this->tagIndex[$tag] as $prefixedKey) {
                    if ($this->cache->delete($prefixedKey)) {
                        $deleted[] = $prefixedKey;
                        $count++;
                    }
                }
                // Clear this tag from index after flushing
                unset($this->tagIndex[$tag]);
            }
        }
        
        $this->logger->info('cache.flush_tags', [
            'tags' => $tags,
            'deleted_count' => $count,
            'keys' => $deleted,
        ]);
        
        // Reset tag state
        $this->tags = [];
        
        return true;
    }
    
    /**
     * Get all cache keys matching a pattern
     * 
     * @param string $pattern Pattern to match (e.g., 'prefix:*', '*:suffix', 'exact_key')
     * @return array Array of matching keys
     */
    public function keys(string $pattern = '*'): array
    {
        $keys = $this->cache->keys($pattern);
        
        $this->logger->debug('cache.keys', [
            'pattern' => $pattern,
            'count' => count($keys),
        ]);
        
        return $keys;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics
     */
    public function getStats(): array
    {
        return [
            'driver' => $this->config['driver'],
            'cache_dir' => $this->config['cache_dir'],
            'default_ttl' => $this->config['default_ttl'],
        ];
    }
    
    /**
     * Get prefixed cache key with tags
     * 
     * @param string $key Original key
     * @return string Prefixed key
     */
    private function getPrefixedKey(string $key): string
    {
        if (empty($this->tags)) {
            return $key;
        }
        
        $tagPrefix = implode(':', $this->tags) . ':';
        return $tagPrefix . $key;
    }
}
