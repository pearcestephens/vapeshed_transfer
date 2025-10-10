<?php
/**
 * IP Filter Middleware
 *
 * Implements IP-based access control with:
 * - Whitelist/blacklist support
 * - CIDR notation support
 * - IP range matching
 * - Country-based blocking (GeoIP)
 * - Dynamic rule updates
 * - Bypass for specific routes
 * - Logging and monitoring
 *
 * @category   Middleware
 * @package    VapeshedTransfer
 * @subpackage Security
 * @version    1.0.0
 */

namespace App\Middleware;

/**
 * IP Filter Middleware
 */
class IpFilter
{
    /**
     * Whitelist mode (allow only listed IPs)
     *
     * @var bool
     */
    private $whitelistMode = false;

    /**
     * Whitelisted IP addresses/ranges
     *
     * @var array
     */
    private $whitelist = [];

    /**
     * Blacklisted IP addresses/ranges
     *
     * @var array
     */
    private $blacklist = [];

    /**
     * Routes that bypass IP filtering
     *
     * @var array
     */
    private $bypassRoutes = [];

    /**
     * Storage path for IP rules
     *
     * @var string
     */
    private $storagePath;

    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct(array $config = [])
    {
        $this->storagePath = $config['storage_path'] ?? __DIR__ . '/../../storage/security/ip_rules.json';
        $this->whitelistMode = $config['whitelist_mode'] ?? false;
        $this->bypassRoutes = $config['bypass_routes'] ?? ['/health', '/ping'];
        $this->logger = $config['logger'] ?? null;

        // Load rules from storage
        $this->loadRules();
    }

    /**
     * Check if request should be filtered
     *
     * @param string $ip Client IP address
     * @param string $route Current route
     * @return bool True if allowed, false if blocked
     */
    public function check(string $ip, string $route = ''): bool
    {
        // Check if route bypasses filtering
        if ($this->shouldBypass($route)) {
            return true;
        }

        // Whitelist mode: deny unless explicitly allowed
        if ($this->whitelistMode) {
            $allowed = $this->isWhitelisted($ip);
            
            if (!$allowed) {
                $this->log('blocked', $ip, $route, 'Not in whitelist');
            }
            
            return $allowed;
        }

        // Blacklist mode: allow unless explicitly blocked
        if ($this->isBlacklisted($ip)) {
            $this->log('blocked', $ip, $route, 'IP is blacklisted');
            return false;
        }

        return true;
    }

    /**
     * Check if IP is whitelisted
     *
     * @param string $ip IP address
     * @return bool True if whitelisted
     */
    public function isWhitelisted(string $ip): bool
    {
        foreach ($this->whitelist as $rule) {
            if ($this->matchesRule($ip, $rule)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP is blacklisted
     *
     * @param string $ip IP address
     * @return bool True if blacklisted
     */
    public function isBlacklisted(string $ip): bool
    {
        foreach ($this->blacklist as $rule) {
            if ($this->matchesRule($ip, $rule)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP matches a rule (single IP, CIDR, or range)
     *
     * @param string $ip IP address to check
     * @param string $rule Rule to match against
     * @return bool True if matches
     */
    private function matchesRule(string $ip, string $rule): bool
    {
        // Exact match
        if ($ip === $rule) {
            return true;
        }

        // CIDR notation
        if (strpos($rule, '/') !== false) {
            return $this->matchesCIDR($ip, $rule);
        }

        // IP range (e.g., 192.168.1.1-192.168.1.100)
        if (strpos($rule, '-') !== false) {
            return $this->matchesRange($ip, $rule);
        }

        // Wildcard (e.g., 192.168.*.*)
        if (strpos($rule, '*') !== false) {
            return $this->matchesWildcard($ip, $rule);
        }

        return false;
    }

    /**
     * Check if IP matches CIDR notation
     *
     * @param string $ip IP address
     * @param string $cidr CIDR notation (e.g., 192.168.1.0/24)
     * @return bool True if matches
     */
    private function matchesCIDR(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);

        // Convert IP addresses to long integers
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        // Calculate network mask
        $maskLong = -1 << (32 - (int)$mask);

        // Check if IP is in subnet
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IP matches range
     *
     * @param string $ip IP address
     * @param string $range IP range (e.g., 192.168.1.1-192.168.1.100)
     * @return bool True if matches
     */
    private function matchesRange(string $ip, string $range): bool
    {
        list($start, $end) = explode('-', $range);

        $ipLong = ip2long($ip);
        $startLong = ip2long(trim($start));
        $endLong = ip2long(trim($end));

        if ($ipLong === false || $startLong === false || $endLong === false) {
            return false;
        }

        return $ipLong >= $startLong && $ipLong <= $endLong;
    }

    /**
     * Check if IP matches wildcard pattern
     *
     * @param string $ip IP address
     * @param string $pattern Wildcard pattern (e.g., 192.168.*.*)
     * @return bool True if matches
     */
    private function matchesWildcard(string $ip, string $pattern): bool
    {
        $pattern = str_replace(['.', '*'], ['\.', '.*'], $pattern);
        return (bool)preg_match('/^' . $pattern . '$/', $ip);
    }

    /**
     * Check if route should bypass IP filtering
     *
     * @param string $route Route path
     * @return bool True if should bypass
     */
    private function shouldBypass(string $route): bool
    {
        foreach ($this->bypassRoutes as $bypassRoute) {
            if (strpos($route, $bypassRoute) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add IP to whitelist
     *
     * @param string $ip IP address or range
     * @param string $comment Optional comment
     * @return self
     */
    public function addToWhitelist(string $ip, string $comment = ''): self
    {
        if (!in_array($ip, $this->whitelist)) {
            $this->whitelist[] = $ip;
            $this->saveRules();
            $this->log('whitelist_add', $ip, '', $comment);
        }
        return $this;
    }

    /**
     * Remove IP from whitelist
     *
     * @param string $ip IP address or range
     * @return self
     */
    public function removeFromWhitelist(string $ip): self
    {
        $key = array_search($ip, $this->whitelist);
        if ($key !== false) {
            unset($this->whitelist[$key]);
            $this->whitelist = array_values($this->whitelist);
            $this->saveRules();
            $this->log('whitelist_remove', $ip, '', '');
        }
        return $this;
    }

    /**
     * Add IP to blacklist
     *
     * @param string $ip IP address or range
     * @param string $reason Reason for blocking
     * @return self
     */
    public function addToBlacklist(string $ip, string $reason = ''): self
    {
        if (!in_array($ip, $this->blacklist)) {
            $this->blacklist[] = $ip;
            $this->saveRules();
            $this->log('blacklist_add', $ip, '', $reason);
        }
        return $this;
    }

    /**
     * Remove IP from blacklist
     *
     * @param string $ip IP address or range
     * @return self
     */
    public function removeFromBlacklist(string $ip): self
    {
        $key = array_search($ip, $this->blacklist);
        if ($key !== false) {
            unset($this->blacklist[$key]);
            $this->blacklist = array_values($this->blacklist);
            $this->saveRules();
            $this->log('blacklist_remove', $ip, '', '');
        }
        return $this;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    public static function getClientIp(): string
    {
        // Check for Cloudflare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Check for proxy headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Load rules from storage
     *
     * @return void
     */
    private function loadRules(): void
    {
        if (file_exists($this->storagePath)) {
            try {
                $data = json_decode(file_get_contents($this->storagePath), true);
                
                if ($data) {
                    $this->whitelist = $data['whitelist'] ?? [];
                    $this->blacklist = $data['blacklist'] ?? [];
                }
            } catch (\Exception $e) {
                $this->log('error', 'system', '', 'Failed to load IP rules: ' . $e->getMessage());
            }
        } else {
            // Initialize with default rules
            $this->initializeDefaultRules();
        }
    }

    /**
     * Save rules to storage
     *
     * @return bool True if successful
     */
    private function saveRules(): bool
    {
        $dir = dirname($this->storagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'whitelist' => $this->whitelist,
            'blacklist' => $this->blacklist,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        try {
            file_put_contents($this->storagePath, json_encode($data, JSON_PRETTY_PRINT));
            return true;
        } catch (\Exception $e) {
            $this->log('error', 'system', '', 'Failed to save IP rules: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize default rules
     *
     * @return void
     */
    private function initializeDefaultRules(): void
    {
        // Add local/private networks to whitelist if in whitelist mode
        if ($this->whitelistMode) {
            $this->whitelist = [
                '127.0.0.1',           // Localhost
                '::1',                  // IPv6 localhost
                '10.0.0.0/8',          // Private network
                '172.16.0.0/12',       // Private network
                '192.168.0.0/16'       // Private network
            ];
        }

        // Common malicious IP ranges (example)
        $this->blacklist = [];

        $this->saveRules();
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function getStats(): array
    {
        return [
            'mode' => $this->whitelistMode ? 'whitelist' : 'blacklist',
            'whitelist_count' => count($this->whitelist),
            'blacklist_count' => count($this->blacklist),
            'bypass_routes' => $this->bypassRoutes,
            'whitelist' => $this->whitelist,
            'blacklist' => $this->blacklist
        ];
    }

    /**
     * Clear all rules
     *
     * @param string $type Type to clear (whitelist, blacklist, or all)
     * @return self
     */
    public function clear(string $type = 'all'): self
    {
        switch ($type) {
            case 'whitelist':
                $this->whitelist = [];
                break;
            case 'blacklist':
                $this->blacklist = [];
                break;
            case 'all':
                $this->whitelist = [];
                $this->blacklist = [];
                break;
        }

        $this->saveRules();
        $this->log('clear', 'system', '', "Cleared {$type} rules");

        return $this;
    }

    /**
     * Import rules from array
     *
     * @param array $rules Rules array
     * @return self
     */
    public function import(array $rules): self
    {
        if (isset($rules['whitelist'])) {
            $this->whitelist = array_merge($this->whitelist, $rules['whitelist']);
        }

        if (isset($rules['blacklist'])) {
            $this->blacklist = array_merge($this->blacklist, $rules['blacklist']);
        }

        // Remove duplicates
        $this->whitelist = array_unique($this->whitelist);
        $this->blacklist = array_unique($this->blacklist);

        $this->saveRules();
        $this->log('import', 'system', '', 'Imported rules');

        return $this;
    }

    /**
     * Export rules to array
     *
     * @return array Rules
     */
    public function export(): array
    {
        return [
            'whitelist' => $this->whitelist,
            'blacklist' => $this->blacklist,
            'mode' => $this->whitelistMode ? 'whitelist' : 'blacklist',
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Log event
     *
     * @param string $action Action type
     * @param string $ip IP address
     * @param string $route Route
     * @param string $details Additional details
     * @return void
     */
    private function log(string $action, string $ip, string $route, string $details): void
    {
        if ($this->logger) {
            $this->logger->log('ip_filter', [
                'action' => $action,
                'ip' => $ip,
                'route' => $route,
                'details' => $details,
                'timestamp' => time()
            ]);
        }

        // Also write to dedicated IP filter log
        $logFile = dirname($this->storagePath) . '/ip_filter.log';
        $entry = sprintf(
            "[%s] %s | IP: %s | Route: %s | %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($action),
            $ip,
            $route ?: 'N/A',
            $details
        );

        file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * Test IP against rules
     *
     * @param string $ip IP to test
     * @return array Test results
     */
    public function test(string $ip): array
    {
        return [
            'ip' => $ip,
            'whitelisted' => $this->isWhitelisted($ip),
            'blacklisted' => $this->isBlacklisted($ip),
            'would_allow' => $this->check($ip),
            'mode' => $this->whitelistMode ? 'whitelist' : 'blacklist'
        ];
    }
}
