<?php
declare(strict_types=1);
namespace Unified\Support;

/**
 * Validator.php - Enterprise Input Validation
 * 
 * Comprehensive validation utilities for API inputs, configurations,
 * and user-supplied data with security hardening.
 * 
 * @package Unified\Support
 * @version 2.0.0
 * @date 2025-10-07
 */
final class Validator
{
    /**
     * Validate integer is within range
     * 
     * @param int $v Value to validate
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @param string $field Field name for error messages
     * @throws \InvalidArgumentException If value out of range
     */
    public static function intRange(int $v, int $min, int $max, string $field): void
    {
        if ($v < $min || $v > $max) {
            throw new \InvalidArgumentException(
                sprintf('%s must be between %d and %d, got %d', $field, $min, $max, $v)
            );
        }
    }

    /**
     * Validate required string field
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param int $minLength Minimum length (default: 1)
     * @param int $maxLength Maximum length (default: 255)
     * @throws \InvalidArgumentException If validation fails
     */
    public static function requiredString($value, string $field, int $minLength = 1, int $maxLength = 255): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("$field is required and must be a non-empty string");
        }
        
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);
        
        if ($length < $minLength || $length > $maxLength) {
            throw new \InvalidArgumentException(
                sprintf('%s length must be between %d and %d characters, got %d', $field, $minLength, $maxLength, $length)
            );
        }
        
        return $trimmed;
    }

    /**
     * Validate optional string field
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param int $maxLength Maximum length (default: 255)
     * @return string|null Trimmed string or null
     * @throws \InvalidArgumentException If validation fails
     */
    public static function optionalString($value, string $field, int $maxLength = 255): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        if (!is_string($value)) {
            throw new \InvalidArgumentException("$field must be a string or null");
        }
        
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        
        if (mb_strlen($trimmed) > $maxLength) {
            throw new \InvalidArgumentException("$field exceeds maximum length of $maxLength characters");
        }
        
        return $trimmed;
    }

    /**
     * Validate integer with optional bounds
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param int|null $min Minimum value (optional)
     * @param int|null $max Maximum value (optional)
     * @return int Validated integer
     * @throws \InvalidArgumentException If validation fails
     */
    public static function integer($value, string $field, ?int $min = null, ?int $max = null): int
    {
        if (!is_int($value) && !is_numeric($value)) {
            throw new \InvalidArgumentException("$field must be an integer");
        }
        
        $int = (int)$value;
        
        if ($min !== null && $int < $min) {
            throw new \InvalidArgumentException("$field must be at least $min, got $int");
        }
        
        if ($max !== null && $int > $max) {
            throw new \InvalidArgumentException("$field must be at most $max, got $int");
        }
        
        return $int;
    }

    /**
     * Validate float with optional bounds
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param float|null $min Minimum value (optional)
     * @param float|null $max Maximum value (optional)
     * @return float Validated float
     * @throws \InvalidArgumentException If validation fails
     */
    public static function float($value, string $field, ?float $min = null, ?float $max = null): float
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("$field must be a number");
        }
        
        $float = (float)$value;
        
        if ($min !== null && $float < $min) {
            throw new \InvalidArgumentException("$field must be at least $min, got $float");
        }
        
        if ($max !== null && $float > $max) {
            throw new \InvalidArgumentException("$field must be at most $max, got $float");
        }
        
        return $float;
    }

    /**
     * Validate boolean value
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @return bool Validated boolean
     * @throws \InvalidArgumentException If validation fails
     */
    public static function boolean($value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
                return false;
            }
        }
        
        if (is_int($value)) {
            return $value !== 0;
        }
        
        throw new \InvalidArgumentException("$field must be a boolean value");
    }

    /**
     * Validate value is in allowed list
     * 
     * @param mixed $value Value to validate
     * @param array $allowed Allowed values
     * @param string $field Field name
     * @return mixed Validated value
     * @throws \InvalidArgumentException If value not in allowed list
     */
    public static function enum($value, array $allowed, string $field)
    {
        if (!in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', array_map(fn($v) => var_export($v, true), $allowed));
            throw new \InvalidArgumentException(
                sprintf('%s must be one of [%s], got %s', $field, $allowedStr, var_export($value, true))
            );
        }
        
        return $value;
    }

    /**
     * Validate array structure
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param bool $allowEmpty Whether to allow empty arrays
     * @return array Validated array
     * @throws \InvalidArgumentException If validation fails
     */
    public static function array($value, string $field, bool $allowEmpty = true): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("$field must be an array");
        }
        
        if (!$allowEmpty && empty($value)) {
            throw new \InvalidArgumentException("$field cannot be empty");
        }
        
        return $value;
    }

    /**
     * Validate email address
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @return string Validated email
     * @throws \InvalidArgumentException If invalid email
     */
    public static function email($value, string $field): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("$field must be a string");
        }
        
        $email = trim($value);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("$field must be a valid email address");
        }
        
        return $email;
    }

    /**
     * Validate URL
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param array $allowedSchemes Allowed URL schemes (default: ['http', 'https'])
     * @return string Validated URL
     * @throws \InvalidArgumentException If invalid URL
     */
    public static function url($value, string $field, array $allowedSchemes = ['http', 'https']): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("$field must be a string");
        }
        
        $url = trim($value);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("$field must be a valid URL");
        }
        
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || !in_array(strtolower($scheme), $allowedSchemes, true)) {
            $allowed = implode(', ', $allowedSchemes);
            throw new \InvalidArgumentException("$field must use one of these schemes: $allowed");
        }
        
        return $url;
    }

    /**
     * Validate IP address
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param bool $allowIPv6 Whether to allow IPv6 addresses
     * @return string Validated IP address
     * @throws \InvalidArgumentException If invalid IP
     */
    public static function ip($value, string $field, bool $allowIPv6 = true): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("$field must be a string");
        }
        
        $ip = trim($value);
        $flags = FILTER_FLAG_IPV4;
        
        if ($allowIPv6) {
            $flags = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        }
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
            throw new \InvalidArgumentException("$field must be a valid IP address");
        }
        
        return $ip;
    }

    /**
     * Validate date string
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @param string $format Expected date format (default: 'Y-m-d')
     * @return string Validated date string
     * @throws \InvalidArgumentException If invalid date
     */
    public static function date($value, string $field, string $format = 'Y-m-d'): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("$field must be a string");
        }
        
        $date = trim($value);
        $d = \DateTime::createFromFormat($format, $date);
        
        if (!$d || $d->format($format) !== $date) {
            throw new \InvalidArgumentException("$field must be a valid date in format $format");
        }
        
        return $date;
    }

    /**
     * Sanitize string for safe output (XSS prevention)
     * 
     * @param string $value Value to sanitize
     * @param bool $allowHtml Whether to allow HTML (default: false)
     * @return string Sanitized string
     */
    public static function sanitize(string $value, bool $allowHtml = false): string
    {
        if (!$allowHtml) {
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        // If HTML allowed, use strip_tags with allowed tags
        return strip_tags($value, '<p><br><strong><em><ul><ol><li><a><span>');
    }

    /**
     * Validate JSON string
     * 
     * @param mixed $value Value to validate
     * @param string $field Field name
     * @return array Parsed JSON as array
     * @throws \InvalidArgumentException If invalid JSON
     */
    public static function json($value, string $field): array
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("$field must be a JSON string");
        }
        
        $decoded = json_decode($value, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("$field must be valid JSON: " . json_last_error_msg());
        }
        
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException("$field must decode to an array");
        }
        
        return $decoded;
    }
}
