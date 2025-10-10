<?php
declare(strict_types=1);

namespace Unified\Persistence;

use Unified\Support\Logger;
use Unified\Guardrail\Result;

/**
 * GuardrailTraceRepository - Persists guardrail chain results
 * 
 * Phase 2.2 enhancements:
 * - Support for Result objects
 * - Stores duration_ms, severity, reason fields
 * - Compact JSON serialization
 * - Backward compatible with legacy array format
 * 
 * @package Unified\Persistence
 * @since Phase M12, enhanced Phase 2.2
 */
final class GuardrailTraceRepository
{
    public function __construct(private Logger $logger)
    {
    }

    /**
     * Insert batch of guardrail results for a proposal.
     * 
     * @param int $proposalId
     * @param string $runId Unique run identifier
     * @param array<int, Result|array> $results Array of Result objects or legacy arrays
     */
    public function insertBatch(int $proposalId, string $runId, array $results): void
    {
        if (!$results) {
            return;
        }

        $pdo = Db::pdo();
        $sql = "INSERT INTO guardrail_traces 
                (proposal_id, run_id, sequence, code, status, severity, reason, message, meta, duration_ms) 
                VALUES (?,?,?,?,?,?,?,?,?,?)";
        
        $stmt = $pdo->prepare($sql);
        $sequence = 0;

        foreach ($results as $r) {
            $sequence++;

            // Handle both Result objects and legacy arrays
            if ($r instanceof Result) {
                $data = $this->extractFromResult($r, $sequence);
            } else {
                $data = $this->extractFromLegacyArray($r, $sequence);
            }

            $stmt->execute([
                $proposalId,
                $runId,
                $data['sequence'],
                $data['code'],
                $data['status'],
                $data['severity'],
                $data['reason'],
                $data['message'],
                $data['meta_json'],
                $data['duration_ms'],
            ]);
        }

        $this->logger->info('guardrail.trace.insert', [
            'proposal_id' => $proposalId,
            'run_id' => $runId,
            'count' => count($results),
        ]);
    }

    /**
     * Extract data from Result object.
     * 
     * @return array<string, mixed>
     */
    private function extractFromResult(Result $result, int $sequence): array
    {
        return [
            'sequence' => $sequence,
            'code' => $result->code,
            'status' => $result->status,
            'severity' => $result->severity,
            'reason' => $result->reason,
            'message' => $result->message,
            'meta_json' => $result->meta ? json_encode($result->meta, JSON_UNESCAPED_SLASHES) : null,
            'duration_ms' => round($result->duration_ms, 2),
        ];
    }

    /**
     * Extract data from legacy array format (backward compatibility).
     * 
     * @param array<string, mixed> $legacy
     * @return array<string, mixed>
     */
    private function extractFromLegacyArray(array $legacy, int $sequence): array
    {
        $code = (string)($legacy['code'] ?? 'UNKNOWN');
        $status = (string)($legacy['status'] ?? 'PASS');
        $message = (string)($legacy['message'] ?? '');
        $meta = (array)($legacy['meta'] ?? []);

        // Derive severity from status if not provided
        $severity = (string)($legacy['severity'] ?? $this->deriveS everityFromStatus($status));

        // Derive reason from message if not provided
        $reason = (string)($legacy['reason'] ?? $this->deriveReasonFromMessage($message));

        // Extract duration if provided
        $duration = (float)($legacy['duration_ms'] ?? 0.0);

        return [
            'sequence' => $sequence,
            'code' => $code,
            'status' => $status,
            'severity' => $severity,
            'reason' => $reason,
            'message' => $message,
            'meta_json' => $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null,
            'duration_ms' => round($duration, 2),
        ];
    }

    /**
     * Map status to severity (default mapping).
     */
    private function deriveSeverityFromStatus(string $status): string
    {
        return match ($status) {
            'PASS' => 'INFO',
            'WARN' => 'WARN',
            'BLOCK' => 'BLOCK',
            default => 'INFO',
        };
    }

    /**
     * Derive machine-friendly reason from human message.
     */
    private function deriveReasonFromMessage(string $message): string
    {
        if ($message === '') {
            return 'passed';
        }

        $reason = strtolower($message);
        $reason = preg_replace('/[^a-z0-9]+/', '_', $reason);
        $reason = trim($reason, '_');

        return $reason ?: 'check_failed';
    }
}
