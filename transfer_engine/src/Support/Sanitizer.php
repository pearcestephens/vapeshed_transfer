<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * Sanitizer.php - Input Sanitization Utilities
 * 
 * Comprehensive input sanitization for XSS prevention,
 * SQL injection protection, and safe data handling.
 * 
 * @package Unified\Support
 * @version 1.0.0
 * @date 2025-10-07
 */
final class Sanitizer
{
    /**
     * Sanitize string for HTML output (XSS prevention)
     * 
     * @param string $value Input string
     * @param bool $doubleEncode Whether to encode existing entities
     * @return string Sanitized string
     */
    public static function html(string $value, bool $doubleEncode = true): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
    }
    
    /**
     * Sanitize string for HTML attribute context
     * 
     * @param string $value Input string
     * @return string Sanitized string
     */
    public static function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitize string for JavaScript context
     * 
     * @param string $value Input string
     * @return string Sanitized string
     */
    public static function javascript(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * Sanitize string for URL parameter
     * 
     * @param string $value Input string
     * @return string URL-encoded string
     */
    public static function url(string $value): string
    {
        return rawurlencode($value);
    }
    
    /**
     * Sanitize string for CSS context
     * 
     * @param string $value Input string
     * @return string Sanitized string
     */
    public static function css(string $value): string
    {
        // Remove any potentially dangerous CSS
        $value = preg_replace('/[^a-zA-Z0-9\s\-_#.,;:()%]/', '', $value);
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Strip all HTML tags from string
     * 
     * @param string $value Input string
     * @param array $allowedTags Allowed HTML tags (e.g., ['p', 'br'])
     * @return string Stripped string
     */
    public static function stripTags(string $value, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            return strip_tags($value);
        }
        
        $allowed = '<' . implode('><', $allowedTags) . '>';
        return strip_tags($value, $allowed);
    }
    
    /**
     * Sanitize email address
     * 
     * @param string $value Input email
     * @return string|null Sanitized email or null if invalid
     */
    public static function email(string $value): ?string
    {
        $email = filter_var($value, FILTER_SANITIZE_EMAIL);
        
        if ($email === false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        return $email;
    }
    
    /**
     * Sanitize integer value
     * 
     * @param mixed $value Input value
     * @param int|null $default Default value if invalid
     * @return int|null Sanitized integer
     */
    public static function integer($value, ?int $default = null): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        
        $int = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return $default;
        }
        
        return $int;
    }
    
    /**
     * Sanitize float value
     * 
     * @param mixed $value Input value
     * @param float|null $default Default value if invalid
     * @return float|null Sanitized float
     */
    public static function float($value, ?float $default = null): ?float
    {
        if (is_float($value)) {
            return $value;
        }
        
        $float = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        if ($float === false) {
            return $default;
        }
        
        return $float;
    }
    
    /**
     * Sanitize boolean value
     * 
     * @param mixed $value Input value
     * @param bool|null $default Default value if invalid
     * @return bool|null Sanitized boolean
     */
    public static function boolean($value, ?bool $default = null): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off', ''], true)) {
                return false;
            }
        }
        
        if (is_int($value)) {
            return $value !== 0;
        }
        
        return $default;
    }
    
    /**
     * Sanitize filename (remove directory traversal)
     * 
     * @param string $value Input filename
     * @return string Safe filename
     */
    public static function filename(string $value): string
    {
        // Remove directory separators and null bytes
        $value = str_replace(['/', '\\', "\0"], '', $value);
        
        // Remove leading dots
        $value = ltrim($value, '.');
        
        // Limit to safe characters
        $value = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $value);
        
        return $value;
    }
    
    /**
     * Sanitize file path (prevent directory traversal)
     * 
     * @param string $value Input path
     * @param string $baseDir Base directory to restrict to
     * @return string|null Safe path or null if invalid
     */
    public static function filepath(string $value, string $baseDir): ?string
    {
        // Resolve to absolute path
        $realPath = realpath($value);
        $realBase = realpath($baseDir);
        
        if ($realPath === false || $realBase === false) {
            return null;
        }
        
        // Ensure path is within base directory
        if (strpos($realPath, $realBase) !== 0) {
            return null;
        }
        
        return $realPath;
    }
    
    /**
     * Sanitize SQL LIKE pattern (escape wildcards)
     * 
     * @param string $value Input pattern
     * @param string $escapeChar Escape character (default: \)
     * @return string Escaped pattern
     */
    public static function likePattern(string $value, string $escapeChar = '\\'): string
    {
        $value = str_replace($escapeChar, $escapeChar . $escapeChar, $value);
        $value = str_replace('%', $escapeChar . '%', $value);
        $value = str_replace('_', $escapeChar . '_', $value);
        
        return $value;
    }
    
    /**
     * Sanitize array recursively
     * 
     * @param array $array Input array
     * @param callable $callback Sanitization callback for each value
     * @return array Sanitized array
     */
    public static function arrayRecursive(array $array, callable $callback): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::arrayRecursive($value, $callback);
            } else {
                $result[$key] = $callback($value);
            }
        }
        
        return $result;
    }
    
    /**
     * Remove null bytes from string
     * 
     * @param string $value Input string
     * @return string Cleaned string
     */
    public static function removeNullBytes(string $value): string
    {
        return str_replace("\0", '', $value);
    }
    
    /**
     * Truncate string to maximum length
     * 
     * @param string $value Input string
     * @param int $maxLength Maximum length
     * @param string $suffix Suffix to add if truncated (default: ...)
     * @return string Truncated string
     */
    public static function truncate(string $value, int $maxLength, string $suffix = '...'): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }
        
        $truncated = mb_substr($value, 0, $maxLength - mb_strlen($suffix));
        return $truncated . $suffix;
    }
    
    /**
     * Clean whitespace from string
     * 
     * @param string $value Input string
     * @param bool $collapse Collapse multiple spaces to single space
     * @return string Cleaned string
     */
    public static function whitespace(string $value, bool $collapse = true): string
    {
        $value = trim($value);
        
        if ($collapse) {
            $value = preg_replace('/\s+/', ' ', $value);
        }
        
        return $value;
    }
    
    /**
     * Sanitize phone number (remove formatting)
     * 
     * @param string $value Input phone number
     * @return string Digits only
     */
    public static function phone(string $value): string
    {
        return preg_replace('/[^0-9+]/', '', $value);
    }
    
    /**
     * Sanitize credit card number (remove spaces and dashes)
     * 
     * @param string $value Input card number
     * @return string Digits only
     */
    public static function creditCard(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value);
    }
    
    /**
     * Redact sensitive data (for logging)
     * 
     * @param string $value Input value
     * @param int $visibleChars Number of visible characters (default: 4)
     * @param string $mask Mask character (default: *)
     * @return string Redacted string
     */
    public static function redact(string $value, int $visibleChars = 4, string $mask = '*'): string
    {
        $length = mb_strlen($value);
        
        if ($length <= $visibleChars) {
            return str_repeat($mask, $length);
        }
        
        $visible = mb_substr($value, -$visibleChars);
        $masked = str_repeat($mask, $length - $visibleChars);
        
        return $masked . $visible;
    }
}
