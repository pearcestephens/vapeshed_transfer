<?php
declare(strict_types=1);

namespace Unified\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * EgressGuard - SSRF Protection
 * 
 * Prevents Server-Side Request Forgery (SSRF) attacks by validating outbound URLs
 * against private/reserved IP ranges and enforcing host allowlists.
 * 
 * @package Unified\Security
 * @author  Autonomous Remediation Bot
 * @since   2.0.0
 */
final class EgressGuard
{
    /**
     * Private/reserved CIDR ranges that should never be accessible from external requests
     * Includes: RFC1918, link-local, loopback, cloud metadata endpoints
     */
    private const PRIVATE_CIDRS = [
        // IPv4 Private Networks (RFC1918)
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        
        // IPv4 Link-Local (RFC3927)
        '169.254.0.0/16',
        
        // IPv4 Loopback
        '127.0.0.0/8',
        
        // IPv4 Special Use
        '0.0.0.0/8',           // Current network
        '100.64.0.0/10',       // Carrier-grade NAT (RFC6598)
        '192.0.0.0/24',        // IETF Protocol Assignments
        '192.0.2.0/24',        // TEST-NET-1
        '198.18.0.0/15',       // Benchmarking
        '198.51.100.0/24',     // TEST-NET-2
        '203.0.113.0/24',      // TEST-NET-3
        '224.0.0.0/4',         // Multicast
        '240.0.0.0/4',         // Reserved
        '255.255.255.255/32',  // Broadcast
        
        // IPv6 Private Networks
        '::1/128',             // Loopback
        'fc00::/7',            // Unique Local Addresses (ULA)
        'fe80::/10',           // Link-Local
        'ff00::/8',            // Multicast
        
        // IPv6 Special Use
        '::/128',              // Unspecified
        '::ffff:0:0/96',       // IPv4-mapped IPv6
        '2001:db8::/32',       // Documentation
    ];

    /**
     * Validate and authorize an outbound URL request
     * 
     * @param string $url The URL to validate
     * @param array $allowHosts Optional allowlist of permitted hosts (case-insensitive)
     * @throws InvalidArgumentException If URL is malformed or scheme invalid
     * @throws RuntimeException If URL resolves to private/reserved address or not in allowlist
     * @return void
     */
    public static function assertUrlAllowed(string $url, array $allowHosts = []): void
    {
        // Parse URL
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL format');
        }
        
        // Validate scheme (only HTTP/HTTPS allowed)
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('URL scheme must be http or https');
        }
        
        $host = $parts['host'];
        
        // Normalize host for comparison
        $normalizedHost = strtolower($host);
        
        // Check allowlist if provided
        if (!empty($allowHosts)) {
            $normalizedAllowHosts = array_map('strtolower', $allowHosts);
            if (!in_array($normalizedHost, $normalizedAllowHosts, true)) {
                throw new RuntimeException(sprintf(
                    'Host "%s" not in allowlist. Permitted hosts: %s',
                    $host,
                    implode(', ', $allowHosts)
                ));
            }
        }
        
        // Resolve DNS (both A and AAAA records)
        $ipv4Records = @dns_get_record($host, DNS_A) ?: [];
        $ipv6Records = @dns_get_record($host, DNS_AAAA) ?: [];
        $allRecords = array_merge($ipv4Records, $ipv6Records);
        
        if (empty($allRecords)) {
            throw new RuntimeException(sprintf('DNS resolution failed for host: %s', $host));
        }
        
        // Check each resolved IP against private/reserved ranges
        foreach ($allRecords as $record) {
            $ip = $record['ip'] ?? ($record['ipv6'] ?? null);
            
            if ($ip === null) {
                continue;
            }
            
            if (self::isPrivateOrReservedIp($ip)) {
                throw new RuntimeException(sprintf(
                    'Host "%s" resolves to private/reserved address: %s',
                    $host,
                    $ip
                ));
            }
        }
    }

    /**
     * Check if URL is safe (non-throwing version)
     * 
     * @param string $url The URL to check
     * @param array $allowHosts Optional allowlist of permitted hosts
     * @return bool True if URL is safe, false otherwise
     */
    public static function isUrlAllowed(string $url, array $allowHosts = []): bool
    {
        try {
            self::assertUrlAllowed($url, $allowHosts);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get detailed validation result with reason
     * 
     * @param string $url The URL to check
     * @param array $allowHosts Optional allowlist
     * @return array { allowed:bool, reason:?string }
     */
    public static function checkUrl(string $url, array $allowHosts = []): array
    {
        try {
            self::assertUrlAllowed($url, $allowHosts);
            return ['allowed' => true, 'reason' => null];
        } catch (\Exception $e) {
            return ['allowed' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Check if an IP address is in private/reserved ranges
     * 
     * @param string $ip IP address (IPv4 or IPv6)
     * @return bool True if private/reserved
     */
    private static function isPrivateOrReservedIp(string $ip): bool
    {
        // Validate IP format
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isIpInCidrs($ip, self::PRIVATE_CIDRS, 4);
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isIpInCidrs($ip, self::PRIVATE_CIDRS, 6);
        }
        
        // If not valid IP, treat as private (fail-safe)
        return true;
    }

    /**
     * Check if IP address is within any of the CIDR ranges
     * 
     * @param string $ip IP address
     * @param array $cidrs Array of CIDR notations
     * @param int $ipVersion 4 for IPv4, 6 for IPv6
     * @return bool True if IP is in any CIDR range
     */
    private static function isIpInCidrs(string $ip, array $cidrs, int $ipVersion): bool
    {
        foreach ($cidrs as $cidr) {
            if (strpos($cidr, '/') === false) {
                continue;
            }
            
            [$network, $maskBits] = explode('/', $cidr, 2);
            
            // Skip if CIDR doesn't match IP version
            if ($ipVersion === 4 && strpos($network, ':') !== false) {
                continue;
            }
            if ($ipVersion === 6 && strpos($network, ':') === false) {
                continue;
            }
            
            if ($ipVersion === 4) {
                if (self::isIpv4InCidr($ip, $network, (int)$maskBits)) {
                    return true;
                }
            } else {
                if (self::isIpv6InCidr($ip, $network, (int)$maskBits)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if IPv4 address is within CIDR range
     * 
     * @param string $ip IPv4 address
     * @param string $network Network address
     * @param int $maskBits Subnet mask bits
     * @return bool True if IP is in range
     */
    private static function isIpv4InCidr(string $ip, string $network, int $maskBits): bool
    {
        $ipLong = ip2long($ip);
        $networkLong = ip2long($network);
        
        if ($ipLong === false || $networkLong === false) {
            return false;
        }
        
        $mask = -1 << (32 - $maskBits);
        
        return ($ipLong & $mask) === ($networkLong & $mask);
    }

    /**
     * Check if IPv6 address is within CIDR range
     * 
     * @param string $ip IPv6 address
     * @param string $network Network address
     * @param int $maskBits Subnet mask bits
     * @return bool True if IP is in range
     */
    private static function isIpv6InCidr(string $ip, string $network, int $maskBits): bool
    {
        $ipBin = @inet_pton($ip);
        $networkBin = @inet_pton($network);
        
        if ($ipBin === false || $networkBin === false) {
            return false;
        }
        
        // Compare byte by byte
        $bytesToCompare = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;
        
        // Compare full bytes
        if ($bytesToCompare > 0) {
            if (strncmp($ipBin, $networkBin, $bytesToCompare) !== 0) {
                return false;
            }
        }
        
        // Compare remaining bits in partial byte
        if ($remainingBits > 0) {
            $maskByte = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;
            $ipByte = ord($ipBin[$bytesToCompare]);
            $networkByte = ord($networkBin[$bytesToCompare]);
            
            if (($ipByte & $maskByte) !== ($networkByte & $maskByte)) {
                return false;
            }
        }
        
        return true;
    }
}
