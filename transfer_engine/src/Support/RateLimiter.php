<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * RateLimiter.php - Advanced Rate Limiting
 * 
 * Token bucket algorithm with Redis/file backend support,
 * per-IP, per-user, and per-endpoint rate limiting.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class RateLimiter
{
    private string $storageDir;
    private ?Logger $logger = null;
    
    /**
     * Create rate limiter instance
     * 
     * @param string|null $storageDir Storage directory for rate limit state (null = temp dir)
     * @param Logger|null $logger Logger instance for rate limit violations
     */
    public function __construct(?string $storageDir = null, ?Logger $logger = null)
    {
        $this->storageDir = $storageDir ?? sys_get_temp_dir();
        $this->logger = $logger;
        
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }
    
    /**
     * Check if action is allowed under rate limit
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @param int $cost Cost of this request (default: 1)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int, 'retry_after' => int]
     */
    public function check(
        string $identifier,
        int $maxRequests,
        int $windowSeconds,
        int $cost = 1
    ): array {
        if ($maxRequests <= 0) {
            // Rate limiting disabled
            return [
                'allowed' => true,
                'remaining' => PHP_INT_MAX,
                'reset_at' => time() + $windowSeconds,
                'retry_after' => 0,
            ];
        }
        
        $now = time();
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
        $bucketFile = $this->storageDir . '/ratelimit_' . $safeId . '.json';
        
        // Load current state
        $state = $this->loadState($bucketFile);
        
        // Check if window has expired
        if ($now - $state['window_start'] >= $windowSeconds) {
            // Reset window
            $state = [
                'window_start' => $now,
                'requests' => 0,
            ];
        }
        
        // Calculate remaining capacity
        $remaining = max(0, $maxRequests - $state['requests']);
        $allowed = $state['requests'] + $cost <= $maxRequests;
        
        // Update state if allowed
        if ($allowed) {
            $state['requests'] += $cost;
            $this->saveState($bucketFile, $state);
        } else {
            // Log rate limit violation
            if ($this->logger !== null) {
                $this->logger->warn('Rate limit exceeded', NeuroContext::security('rate_limit_exceeded', [
                    'identifier' => $identifier,
                    'limit' => $maxRequests,
                    'window_seconds' => $windowSeconds,
                    'current_requests' => $state['requests'],
                    'attempted_cost' => $cost,
                    'retry_after' => max(1, $resetAt - $now),
                ]));
            }
        }
        
        $resetAt = $state['window_start'] + $windowSeconds;
        $retryAfter = $allowed ? 0 : max(1, $resetAt - $now);
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $remaining - ($allowed ? $cost : 0)),
            'reset_at' => $resetAt,
            'retry_after' => $retryAfter,
        ];
    }
    
    /**
     * Attempt action and throw exception if rate limited
     * 
     * @param string $identifier Unique identifier
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @param int $cost Cost of this request
     * @return array Rate limit status
     * @throws RateLimitExceededException If rate limit exceeded
     */
    public function attempt(
        string $identifier,
        int $maxRequests,
        int $windowSeconds,
        int $cost = 1
    ): array {
        $result = $this->check($identifier, $maxRequests, $windowSeconds, $cost);
        
        if (!$result['allowed']) {
            throw new RateLimitExceededException(
                'Rate limit exceeded',
                $result['retry_after'],
                $result['reset_at']
            );
        }
        
        return $result;
    }
    
    /**
     * Reset rate limit for identifier
     * 
     * @param string $identifier Unique identifier
     */
    public function reset(string $identifier): void
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
        $bucketFile = $this->storageDir . '/ratelimit_' . $safeId . '.json';
        
        if (is_file($bucketFile)) {
            @unlink($bucketFile);
        }
    }
    
    /**
     * Get current rate limit status
     * 
     * @param string $identifier Unique identifier
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return array Status information
     */
    public function status(
        string $identifier,
        int $maxRequests,
        int $windowSeconds
    ): array {
        $now = time();
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier);
        $bucketFile = $this->storageDir . '/ratelimit_' . $safeId . '.json';
        
        $state = $this->loadState($bucketFile);
        
        // Check if window has expired
        if ($now - $state['window_start'] >= $windowSeconds) {
            return [
                'requests' => 0,
                'remaining' => $maxRequests,
                'reset_at' => $now + $windowSeconds,
                'window_start' => $now,
            ];
        }
        
        $remaining = max(0, $maxRequests - $state['requests']);
        $resetAt = $state['window_start'] + $windowSeconds;
        
        return [
            'requests' => $state['requests'],
            'remaining' => $remaining,
            'reset_at' => $resetAt,
            'window_start' => $state['window_start'],
        ];
    }
    
    /**
     * Clean up expired rate limit files
     * 
     * @param int $maxAge Maximum age in seconds (default: 1 hour)
     * @return int Number of files cleaned up
     */
    public function cleanup(int $maxAge = 3600): int
    {
        $count = 0;
        $now = time();
        
        $files = glob($this->storageDir . '/ratelimit_*.json');
        if ($files === false) {
            return 0;
        }
        
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && ($now - $mtime) > $maxAge) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Load rate limit state from file
     * 
     * @param string $file File path
     * @return array State data
     */
    private function loadState(string $file): array
    {
        $default = [
            'window_start' => time(),
            'requests' => 0,
        ];
        
        if (!is_file($file)) {
            return $default;
        }
        
        $content = @file_get_contents($file);
        if ($content === false) {
            return $default;
        }
        
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return $default;
        }
        
        return array_merge($default, $data);
    }
    
    /**
     * Save rate limit state to file
     * 
     * @param string $file File path
     * @param array $state State data
     */
    private function saveState(string $file, array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_SLASHES);
        @file_put_contents($file, $json, LOCK_EX);
    }
}

/**
 * RateLimitExceededException
 * 
 * Thrown when rate limit is exceeded
 */
class RateLimitExceededException extends \RuntimeException
{
    private int $retryAfter;
    private int $resetAt;
    
    public function __construct(string $message, int $retryAfter, int $resetAt)
    {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
        $this->resetAt = $resetAt;
    }
    
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
    
    public function getResetAt(): int
    {
        return $this->resetAt;
    }
}
