<?php
declare(strict_types=1);

namespace Unified\Guardrail;

/**
 * Severity - Enum-like class for guardrail result severity levels
 * 
 * Represents the importance/impact of a guardrail result:
 * - INFO: Informational check passed
 * - WARN: Warning condition detected, but not blocking
 * - BLOCK: Critical failure, must block operation
 * 
 * @package Unified\Guardrail
 * @since Phase 2.2
 */
final class Severity
{
    public const INFO = 'INFO';
    public const WARN = 'WARN';
    public const BLOCK = 'BLOCK';

    private function __construct()
    {
        // Prevent instantiation - this is an enum-like class
    }

    /**
     * Validate severity string is one of the allowed values.
     */
    public static function isValid(string $severity): bool
    {
        return in_array($severity, [self::INFO, self::WARN, self::BLOCK], true);
    }

    /**
     * Get all valid severity levels.
     * 
     * @return string[]
     */
    public static function all(): array
    {
        return [self::INFO, self::WARN, self::BLOCK];
    }

    /**
     * Map status to severity.
     * 
     * Default mapping:
     * - PASS → INFO
     * - WARN → WARN
     * - BLOCK → BLOCK
     */
    public static function fromStatus(string $status): string
    {
        return match ($status) {
            'PASS' => self::INFO,
            'WARN' => self::WARN,
            'BLOCK' => self::BLOCK,
            default => self::INFO,
        };
    }

    /**
     * Get numeric weight for severity (for sorting/scoring).
     * Higher weight = more severe.
     */
    public static function weight(string $severity): int
    {
        return match ($severity) {
            self::BLOCK => 100,
            self::WARN => 50,
            self::INFO => 10,
            default => 0,
        };
    }
}
