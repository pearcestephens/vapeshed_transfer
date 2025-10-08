# Issue #14: Missing Cache::keys() and CacheManager::keys() Methods

## Error
```
Fatal error: Call to undefined method Unified\Support\CacheManager::keys() 
in NotificationScheduler.php on line 439
```

## Root Cause
`NotificationScheduler::getAllSchedules()` calls `$this->cache->keys($pattern)` to find all notification schedules, but neither `CacheManager` nor the underlying `Cache` class had a `keys()` method implemented.

## Usage Pattern
```php
// NotificationScheduler.php line 439
$pattern = 'notification_schedule:*';
$keys = $this->cache->keys($pattern);  // ❌ Method doesn't exist
```

## Solution

### 1. Modified `Cache::set()` to Store Original Key
**File**: `src/Support/Cache.php` line ~80

**Added** `'key' => $key` to stored data array:
```php
$data = [
    'key' => $key,  // ✅ Store original key for pattern matching
    'value' => $value,
    'expires_at' => time() + $ttl,
    'created_at' => time(),
];
```

**Reason**: The cache uses hashed filenames (`cache_{sha256}.dat`), so we need to store the original key inside the file to enable pattern matching.

### 2. Added `Cache::keys()` Method
**File**: `src/Support/Cache.php` after line ~227

**Implementation**:
```php
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
```

**Features**:
- ✅ Supports wildcard patterns: `prefix:*`, `*:suffix`, `*middle*`, `exact_key`
- ✅ Skips expired entries automatically
- ✅ Handles corrupt/missing files gracefully
- ✅ Returns array of matching keys

### 3. Added `CacheManager::keys()` Wrapper
**File**: `src/Support/CacheManager.php` after line ~272

**Implementation**:
```php
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
```

**Features**:
- ✅ Delegates to underlying `Cache::keys()`
- ✅ Logs pattern and result count for debugging
- ✅ Maintains consistency with CacheManager logging patterns

## Testing
```php
// Example usage
$cache = new CacheManager($logger);

$cache->set('notification_schedule:1', ['data' => 'test1']);
$cache->set('notification_schedule:2', ['data' => 'test2']);
$cache->set('other_key:1', ['data' => 'test3']);

$keys = $cache->keys('notification_schedule:*');
// Returns: ['notification_schedule:1', 'notification_schedule:2']

$allKeys = $cache->keys('*');
// Returns: ['notification_schedule:1', 'notification_schedule:2', 'other_key:1']
```

## Files Modified
1. ✅ `src/Support/Cache.php` - Added `keys()` and `matchesPattern()` methods, modified `set()` to store key
2. ✅ `src/Support/CacheManager.php` - Added `keys()` wrapper method
3. ✅ `clear_all_and_test.sh` - Added Cache.php and NotificationScheduler.php to touch list

## Impact
- **Backwards Compatible**: ✅ All existing cache operations unchanged
- **Performance**: ⚠️ `keys()` scans all cache files (O(n)) - acceptable for moderate cache sizes
- **Storage**: +~20 bytes per cache entry (storing original key)

## Status
✅ **RESOLVED** - Ready for testing

## Next Steps
1. Run `bash clear_all_and_test.sh` to continue tests
2. Verify NotificationScheduler tests pass
3. Continue with ApiDocumentationGenerator tests
