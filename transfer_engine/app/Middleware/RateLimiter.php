<?php
/**
 * Rate Limiter Middleware
 *
 * Implements token bucket algorithm for rate limiting with:
 * - Per-IP rate limiting
 * - Per-user rate limiting
 * - Per-endpoint rate limiting
 * - Redis-based storage with fallback to file-based
 * - Configurable limits per route
 * - Automatic cleanup of expired entries
 * - Rate limit headers in responses
 *
 * @category   Middleware
 * @package    VapeshedTransfer
 * @subpackage Security
 * @version    1.0.0
 */

namespace App\Middleware;

use App\Support\Db;
use Exception;

/**
 * Rate Limiter Middleware
 */
class RateLimiter
{
    /**
     * Storage backend (redis or file)
     *
     * @var string
     */
    private $backend = 'file';

    /**
     * Storage path for file-based backend
     *
     * @var string
     */
    private $storagePath;

    /**
     * Redis connection
     *
     * @var \Redis|null
     */
    private $redis = null;

    /**
     * Default rate limits (requests per window)
     *
     * @var array
     */
    private $defaultLimits = [
        'global' => ['limit' => 1000, 'window' => 3600],      // 1000 req/hour
        'api' => ['limit' => 100, 'window' => 60],            // 100 req/min
        'auth' => ['limit' => 5, 'window' => 300],            // 5 req/5min
        'transfer' => ['limit' => 50, 'window' => 60],        // 50 req/min
        'analytics' => ['limit' => 100, 'window' => 60],      // 100 req/min
        'export' => ['limit' => 10, 'window' => 300]          // 10 req/5min
    ];

    /**
     * Custom limits per route
     *
     * @var array
     */
    private $routeLimits = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->storagePath = dirname(__DIR__, 2) . '/storage/rate_limits';
        
        // Ensure storage directory exists
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        // Try to connect to Redis if available
        if (extension_loaded('redis')) {
            try {
                $this->redis = new \Redis();
                $this->redis->connect('127.0.0.1', 6379, 1);
                $this->backend = 'redis';
            } catch (Exception $e) {
                error_log('Redis connection failed, using file backend: ' . $e->getMessage());
                $this->backend = 'file';
            }
        }

        // Load custom route limits from config
        $this->loadRouteLimits();
    }

    /**
     * Handle incoming request
     *
     * @param array $request Request data
     * @return array Response with status and headers
     */
    public function handle(array $request): array
    {
        $ip = $this->getClientIp($request);
        $userId = $request['user_id'] ?? null;
        $route = $request['route'] ?? 'global';
        $endpoint = $request['endpoint'] ?? '/';

        // Get rate limit configuration for this route
        $limitConfig = $this->getLimitConfig($route, $endpoint);

        // Check rate limit
        $ipCheck = $this->checkLimit("ip:{$ip}", $limitConfig);
        
        if ($userId) {
            $userCheck = $this->checkLimit("user:{$userId}", $limitConfig);
            if (!$userCheck['allowed']) {
                return $this->rateLimitResponse($userCheck, 'user');
            }
        }

        if (!$ipCheck['allowed']) {
            return $this->rateLimitResponse($ipCheck, 'ip');
        }

        // Increment counters
        $this->increment("ip:{$ip}", $limitConfig);
        if ($userId) {
            $this->increment("user:{$userId}", $limitConfig);
        }

        // Return success with rate limit headers
        return [
            'allowed' => true,
            'headers' => [
                'X-RateLimit-Limit' => $limitConfig['limit'],
                'X-RateLimit-Remaining' => $ipCheck['remaining'],
                'X-RateLimit-Reset' => $ipCheck['reset_at']
            ]
        ];
    }

    /**
     * Check rate limit for a key
     *
     * @param string $key Unique identifier
     * @param array $config Limit configuration
     * @return array Status with allowed, remaining, reset_at
     */
    private function checkLimit(string $key, array $config): array
    {
        $data = $this->get($key);
        $now = time();

        if (!$data) {
            // First request
            return [
                'allowed' => true,
                'remaining' => $config['limit'] - 1,
                'reset_at' => $now + $config['window']
            ];
        }

        // Check if window has expired
        if ($now >= $data['reset_at']) {
            // Reset window
            return [
                'allowed' => true,
                'remaining' => $config['limit'] - 1,
                'reset_at' => $now + $config['window']
            ];
        }

        // Check if limit exceeded
        if ($data['count'] >= $config['limit']) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_at' => $data['reset_at'],
                'retry_after' => $data['reset_at'] - $now
            ];
        }

        // Within limits
        return [
            'allowed' => true,
            'remaining' => $config['limit'] - $data['count'] - 1,
            'reset_at' => $data['reset_at']
        ];
    }

    /**
     * Increment counter for a key
     *
     * @param string $key Unique identifier
     * @param array $config Limit configuration
     * @return void
     */
    private function increment(string $key, array $config): void
    {
        $data = $this->get($key);
        $now = time();

        if (!$data || $now >= $data['reset_at']) {
            // Start new window
            $data = [
                'count' => 1,
                'reset_at' => $now + $config['window'],
                'first_request' => $now
            ];
        } else {
            // Increment within window
            $data['count']++;
        }

        $this->set($key, $data, $config['window']);
    }

    /**
     * Get rate limit data
     *
     * @param string $key Storage key
     * @return array|null
     */
    private function get(string $key): ?array
    {
        if ($this->backend === 'redis' && $this->redis) {
            $data = $this->redis->get("ratelimit:{$key}");
            return $data ? json_decode($data, true) : null;
        }

        // File backend
        $file = $this->storagePath . '/' . md5($key) . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $data = file_get_contents($file);
        return json_decode($data, true);
    }

    /**
     * Set rate limit data
     *
     * @param string $key Storage key
     * @param array $data Rate limit data
     * @param int $ttl Time to live in seconds
     * @return void
     */
    private function set(string $key, array $data, int $ttl): void
    {
        if ($this->backend === 'redis' && $this->redis) {
            $this->redis->setex("ratelimit:{$key}", $ttl, json_encode($data));
            return;
        }

        // File backend
        $file = $this->storagePath . '/' . md5($key) . '.json';
        file_put_contents($file, json_encode($data));
    }

    /**
     * Get rate limit configuration for route
     *
     * @param string $route Route name
     * @param string $endpoint Endpoint path
     * @return array Limit configuration
     */
    private function getLimitConfig(string $route, string $endpoint): array
    {
        // Check for custom route limit
        if (isset($this->routeLimits[$route])) {
            return $this->routeLimits[$route];
        }

        // Check for endpoint-specific limit
        foreach ($this->routeLimits as $pattern => $config) {
            if (strpos($endpoint, $pattern) !== false) {
                return $config;
            }
        }

        // Check default limits by route type
        if (strpos($route, 'auth') !== false) {
            return $this->defaultLimits['auth'];
        } elseif (strpos($route, 'transfer') !== false) {
            return $this->defaultLimits['transfer'];
        } elseif (strpos($route, 'analytics') !== false) {
            return $this->defaultLimits['analytics'];
        } elseif (strpos($route, 'export') !== false) {
            return $this->defaultLimits['export'];
        } elseif (strpos($route, 'api') !== false) {
            return $this->defaultLimits['api'];
        }

        // Global default
        return $this->defaultLimits['global'];
    }

    /**
     * Get client IP address
     *
     * @param array $request Request data
     * @return string IP address
     */
    private function getClientIp(array $request): string
    {
        // Check for IP in request array
        if (isset($request['ip'])) {
            return $request['ip'];
        }

        // Check common headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // Cloudflare
            'HTTP_X_FORWARDED_FOR',     // Proxy
            'HTTP_X_REAL_IP',           // Nginx
            'REMOTE_ADDR'               // Direct
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (take first)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Generate rate limit exceeded response
     *
     * @param array $check Rate limit check result
     * @param string $type Limit type (ip or user)
     * @return array Response
     */
    private function rateLimitResponse(array $check, string $type): array
    {
        return [
            'allowed' => false,
            'status' => 429,
            'headers' => [
                'X-RateLimit-Limit' => $check['limit'] ?? 0,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $check['reset_at'] ?? time(),
                'Retry-After' => $check['retry_after'] ?? 60
            ],
            'body' => [
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                    'type' => $type,
                    'retry_after' => $check['retry_after'] ?? 60
                ]
            ]
        ];
    }

    /**
     * Load custom route limits from configuration
     *
     * @return void
     */
    private function loadRouteLimits(): void
    {
        $configFile = dirname(__DIR__, 2) . '/config/rate_limits.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            if (is_array($config)) {
                $this->routeLimits = array_merge($this->routeLimits, $config);
            }
        }
    }

    /**
     * Set custom route limit
     *
     * @param string $route Route name or pattern
     * @param int $limit Request limit
     * @param int $window Time window in seconds
     * @return void
     */
    public function setRouteLimit(string $route, int $limit, int $window): void
    {
        $this->routeLimits[$route] = [
            'limit' => $limit,
            'window' => $window
        ];
    }

    /**
     * Clean up expired entries (maintenance)
     *
     * @return int Number of cleaned entries
     */
    public function cleanup(): int
    {
        if ($this->backend === 'redis') {
            // Redis handles TTL automatically
            return 0;
        }

        // File backend cleanup
        $cleaned = 0;
        $now = time();
        $files = glob($this->storagePath . '/*.json');

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['reset_at']) && $now >= $data['reset_at']) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Get statistics for monitoring
     *
     * @return array Statistics
     */
    public function getStats(): array
    {
        if ($this->backend === 'redis' && $this->redis) {
            $keys = $this->redis->keys('ratelimit:*');
            return [
                'backend' => 'redis',
                'active_limits' => count($keys),
                'storage_size' => 'N/A'
            ];
        }

        // File backend stats
        $files = glob($this->storagePath . '/*.json');
        $size = 0;
        foreach ($files as $file) {
            $size += filesize($file);
        }

        return [
            'backend' => 'file',
            'active_limits' => count($files),
            'storage_size' => $this->formatBytes($size)
        ];
    }

    /**
     * Format bytes to human-readable size
     *
     * @param int $bytes Bytes
     * @return string Formatted size
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Reset rate limit for a key
     *
     * @param string $key Key to reset (ip:X.X.X.X or user:ID)
     * @return bool Success
     */
    public function reset(string $key): bool
    {
        if ($this->backend === 'redis' && $this->redis) {
            return $this->redis->del("ratelimit:{$key}") > 0;
        }

        // File backend
        $file = $this->storagePath . '/' . md5($key) . '.json';
        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }
}
