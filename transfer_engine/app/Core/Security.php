<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Security Class
 * 
 * @author Pearce Stephens <pearce.stephens@ecigdis.co.nz>
 * @company Ecigdis Ltd (The Vape Shed)
 * @description Handles security headers, CSRF protection, and input validation
 */
class Security
{
    public static function applyHeaders(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
              || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        
        // Generate CSP nonce
        $nonce = base64_encode(random_bytes(16));
        $_SESSION['csp_nonce'] = $nonce;
        
        // Security headers
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer-when-downgrade');
        
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy (relaxed for external CDNs per user request)
        // Allow jsDelivr and common CDNs for Bootstrap, jQuery, and Bootstrap Icons.
        $cdn = "https://cdn.jsdelivr.net";
        $csp = implode('; ', [
            "default-src 'self' $cdn",
            "img-src 'self' data: $cdn",
            "style-src 'self' 'unsafe-inline' $cdn",
            "font-src 'self' data: $cdn",
            // Temporarily allow inline scripts while migrating; keep 'self' and CDN
            "script-src 'self' 'unsafe-inline' $cdn",
            "connect-src 'self' $cdn",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'"
        ]);

        header("Content-Security-Policy: {$csp}");
    }
    
    public static function generateCSRFToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function verifyCSRFToken(string $token): bool
    {
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Retrieve CSRF token from POST/GET or common headers
     */
    public static function getRequestCSRFToken(): string
    {
        // Prefer explicit POST/GET field first
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_GET[CSRF_TOKEN_NAME] ?? '';
        if (!empty($token)) {
            return (string)$token;
        }

        // Then try common header names (normalized by PHP to HTTP_*)
        $headers = [
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X_CSRF',
            'HTTP_X_XSRF_TOKEN',
            'HTTP_CSRF_TOKEN',
        ];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                return (string)$_SERVER[$h];
            }
        }
        // Fallback: try to read from Authorization style if someone sends Bearer <token>
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return '';
    }

    public static function requireCSRF(): void
    {
        $token = self::getRequestCSRFToken();
        
        if (!self::verifyCSRFToken($token)) {
            http_response_code(403);
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            } else {
                include APP_ROOT . '/resources/views/errors/403.php';
            }
            exit;
        }
    }
    
    public static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Sanitize input to prevent XSS, SQL injection, path traversal, command injection
     * This is defensive - proper parameterized queries still required for SQL
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if (!is_string($input)) {
            return $input;
        }
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Strip dangerous HTML attributes BEFORE encoding (more aggressive)
        $dangerousAttrs = ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur'];
        foreach ($dangerousAttrs as $attr) {
            $input = preg_replace('/' . $attr . '\s*=/i', '', $input);
        }
        
        // Remove script tags completely
        $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
        
        // HTML escape to prevent XSS
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        
        // Remove common SQL injection patterns (defensive layer)
        $sqlPatterns = [
            '/;\s*DROP\s+TABLE/i',
            '/;\s*DELETE\s+FROM/i',
            '/;\s*TRUNCATE/i',
            '/;\s*UPDATE\s+.*SET/i',
            '/UNION\s+SELECT/i',
            '/\/\*.*\*\//s', // SQL comments
            '/--\s*$/m',     // SQL line comments
        ];
        foreach ($sqlPatterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }
        
        // Remove path traversal attempts
        $input = str_replace(['../', '..\\'], '', $input);
        
        // Remove command injection attempts (semicolons, pipes, spaces before rm/etc)
        $input = preg_replace('/;\s*(rm|del|format|fdisk)/i', '', $input);
        $input = str_replace(['|', '&', '$', '`', '>', '<', '\n', '\r'], '', $input);
        
        return $input;
    }
    
    public static function escapeHtml(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateUUID(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Kill switch helpers
     */
    public static function isKillSwitchActive(): bool
    {
        $paths = [
            defined('APP_ROOT') ? APP_ROOT . '/KILL_SWITCH' : null,
            defined('STORAGE_PATH') ? STORAGE_PATH . '/KILL_SWITCH' : null,
        ];
        foreach ($paths as $p) {
            if ($p && is_file($p)) { return true; }
        }
        return false;
    }

    /**
     * Write window helpers – mirrors control-panel/api.php behavior
     */
    public static function getWriteWindowState(): array
    {
        $path = (defined('APP_ROOT') ? APP_ROOT : __DIR__ . '/..') . '/var/tmp/write_window.json';
        if (!is_file($path)) {
            return ['active' => false];
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return ['active' => false];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['active' => false];
        }
        // Calculate remaining if expires_at present
        $now = time();
        if (!empty($data['expires_at'])) {
            $remaining = max(0, (int)$data['expires_at'] - $now);
            $data['remaining_sec'] = $remaining;
            $data['active'] = $remaining > 0;
        }
        return $data;
    }

    public static function isWriteWindowActive(): bool
    {
        $win = self::getWriteWindowState();
        return (bool)($win['active'] ?? false);
    }

    /**
     * Determine if write operations are allowed. Rules:
     * - If KILL_SWITCH is active => block
     * - If write window is active => allow
     * - Else require BROWSE_MODE=false AND CONTROL_PANEL_WRITE_ENABLED=true
     */
    public static function isWriteAllowed(): bool
    {
        if (self::isKillSwitchActive()) {
            return false;
        }
        if (self::isWriteWindowActive()) {
            return true;
        }
        $browse = filter_var($_ENV['BROWSE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $writeEnabled = filter_var($_ENV['CONTROL_PANEL_WRITE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $browse = $browse === null ? false : $browse; // default to false (live) if unset
        $writeEnabled = $writeEnabled === null ? false : $writeEnabled;
        return ($browse === false) && ($writeEnabled === true);
    }

    /**
     * Enforce write policy with a 403 JSON/HTML response
     */
    public static function ensureWriteAllowed(string $action = 'write'): void
    {
        if (self::isWriteAllowed()) {
            return;
        }
        http_response_code(403);
        $payload = [
            'ok' => false,
            'error' => 'Write operations are currently blocked',
            'reason' => self::isKillSwitchActive() ? 'kill_switch' : (self::isWriteWindowActive() ? 'unknown' : 'browse_mode'),
            'security' => [
                'browse_mode' => filter_var($_ENV['BROWSE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'write_enabled' => filter_var($_ENV['CONTROL_PANEL_WRITE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'write_window' => self::getWriteWindowState(),
                'kill_switch' => self::isKillSwitchActive(),
            ],
            'action' => $action
        ];
        if (self::isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode($payload);
        } else {
            include APP_ROOT . '/resources/views/errors/403.php';
        }
        exit;
    }
}