<?php
declare(strict_types=1);

namespace Unified\Guardrail;

/**
 * Result - Value object for guardrail evaluation results
 * 
 * Immutable value object containing all information about a guardrail check:
 * - code: Unique identifier (e.g., GR_COST_FLOOR)
 * - status: PASS | WARN | BLOCK
 * - severity: INFO | WARN | BLOCK (from Severity class)
 * - reason: Machine-friendly reason code (e.g., below_cost_floor)
 * - message: Human-readable message
 * - meta: Additional structured data (array)
 * - duration_ms: Execution time in milliseconds
 * 
 * @package Unified\Guardrail
 * @since Phase 2.2
 */
final class Result
{
    /**
     * @param string $code Guardrail code (e.g., GR_COST_FLOOR)
     * @param string $status PASS | WARN | BLOCK
     * @param string $severity INFO | WARN | BLOCK
     * @param string $reason Machine-friendly reason (e.g., below_cost_floor)
     * @param string $message Human-readable message
     * @param array<string, mixed> $meta Additional structured data
     * @param float $duration_ms Execution time in milliseconds
     */
    public function __construct(
        public readonly string $code,
        public readonly string $status,
        public readonly string $severity,
        public readonly string $reason,
        public readonly string $message,
        public readonly array $meta,
        public readonly float $duration_ms
    ) {
        // Validate status
        if (!in_array($status, ['PASS', 'WARN', 'BLOCK'], true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        // Validate severity
        if (!Severity::isValid($severity)) {
            throw new \InvalidArgumentException("Invalid severity: {$severity}");
        }

        // Validate duration is non-negative
        if ($duration_ms < 0) {
            throw new \InvalidArgumentException("Duration must be non-negative: {$duration_ms}");
        }

        // Ensure meta doesn't contain resources or closures
        $this->validateMeta($meta);
    }

    /**
     * Create Result from legacy array format.
     * 
     * Legacy format: ['code' => '...', 'status' => '...', 'message' => '...', 'meta' => [...]]
     * 
     * @param array<string, mixed> $legacy
     * @param float $duration_ms
     */
    public static function fromLegacy(array $legacy, float $duration_ms = 0.0): self
    {
        $code = (string)($legacy['code'] ?? 'UNKNOWN');
        $status = (string)($legacy['status'] ?? 'PASS');
        $message = (string)($legacy['message'] ?? '');
        $meta = (array)($legacy['meta'] ?? []);

        // Derive severity from status if not provided
        $severity = isset($legacy['severity']) && Severity::isValid($legacy['severity'])
            ? $legacy['severity']
            : Severity::fromStatus($status);

        // Derive reason from message or code
        $reason = (string)($legacy['reason'] ?? self::deriveReason($message, $code));

        return new self($code, $status, $severity, $reason, $message, $meta, $duration_ms);
    }

    /**
     * Convert Result to array format (for JSON serialization).
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'severity' => $this->severity,
            'reason' => $this->reason,
            'message' => $this->message,
            'meta' => $this->meta,
            'duration_ms' => $this->duration_ms,
        ];
    }

    /**
     * Check if result is passing.
     */
    public function isPassing(): bool
    {
        return $this->status === 'PASS';
    }

    /**
     * Check if result is warning.
     */
    public function isWarning(): bool
    {
        return $this->status === 'WARN';
    }

    /**
     * Check if result is blocking.
     */
    public function isBlocking(): bool
    {
        return $this->status === 'BLOCK';
    }

    /**
     * Get severity weight (for scoring/sorting).
     */
    public function severityWeight(): int
    {
        return Severity::weight($this->severity);
    }

    /**
     * JSON serialize.
     * 
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Validate meta array doesn't contain unsupported types.
     * 
     * @throws \InvalidArgumentException if meta contains resources/closures
     */
    private function validateMeta(array $meta): void
    {
        array_walk_recursive($meta, function ($value) {
            if (is_resource($value)) {
                throw new \InvalidArgumentException('Meta cannot contain resources');
            }
            if ($value instanceof \Closure) {
                throw new \InvalidArgumentException('Meta cannot contain closures');
            }
        });
    }

    /**
     * Derive machine-friendly reason from human message.
     */
    private static function deriveReason(string $message, string $code): string
    {
        if ($message === '') {
            return 'passed';
        }

        // Convert message to snake_case reason
        $reason = strtolower($message);
        $reason = preg_replace('/[^a-z0-9]+/', '_', $reason);
        $reason = trim($reason, '_');

        return $reason ?: 'check_failed';
    }
}
