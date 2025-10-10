<?php
declare(strict_types=1);

namespace Unified\Guardrail;

use Unified\Support\Logger;
use Unified\Support\Config;

/**
 * GuardrailChain - Executes guardrails in deterministic order
 * 
 * Phase 2.2 Improvements:
 * - Deterministic execution order (alphabetical by code)
 * - Rich Result objects with timing and severity
 * - Short-circuit on BLOCK (preserves already-executed results)
 * - Score hint calculation (0..1 range)
 * - Comprehensive tracing support
 * 
 * @package Unified\Guardrail
 * @since Phase M3, enhanced Phase 2.2
 */
final class GuardrailChain
{
    /** @var GuardrailInterface[] */
    private array $rails = [];

    public function __construct(private Logger $logger)
    {
    }

    /**
     * Register a guardrail.
     */
    public function register(GuardrailInterface $r): void
    {
        $this->rails[] = $r;
    }

    /**
     * Evaluate all registered guardrails in deterministic order.
     * 
     * Execution rules:
     * - Rails executed alphabetically by code for determinism
     * - Short-circuit on first BLOCK (preserves prior results)
     * - Collect timing for each rail
     * - Calculate score_hint (0..1, lower = worse)
     * 
     * @param array<string, mixed> $ctx Evaluation context
     * @return array{results:Result[],final_status:string,blocked_by:?string,total_duration_ms:float,score_hint:float}
     */
    public function evaluate(array $ctx): array
    {
        Config::prime();

        $startTime = microtime(true);

        // Sort rails alphabetically by code for deterministic ordering
        $sortedRails = $this->sortRailsByCode();

        $results = [];
        $blockedBy = null;
        $finalStatus = 'PASS';

        foreach ($sortedRails as $rail) {
            $railStart = microtime(true);

            // Execute guardrail
            $legacyResult = $rail->evaluate($ctx, $this->logger);
            $railDuration = (microtime(true) - $railStart) * 1000;

            // Convert to Result object
            $result = Result::fromLegacy($legacyResult, $railDuration);
            $results[] = $result;

            // Update final status
            if ($result->isBlocking()) {
                $blockedBy = $result->code;
                $finalStatus = 'BLOCK';
                
                $this->logger->warning('guardrail.chain.blocked', [
                    'code' => $result->code,
                    'reason' => $result->reason,
                    'message' => $result->message,
                ]);

                // Short-circuit: stop executing remaining rails
                break;
            }

            if ($result->isWarning() && $finalStatus === 'PASS') {
                $finalStatus = 'WARN';
            }
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;

        // Calculate score hint (0..1, where 1 = all passed, 0 = blocked)
        $scoreHint = $this->calculateScoreHint($results, $finalStatus);

        $this->logger->info('guardrail.chain.result', [
            'final_status' => $finalStatus,
            'blocked_by' => $blockedBy,
            'total_rails' => count($sortedRails),
            'executed_rails' => count($results),
            'total_duration_ms' => round($totalDuration, 2),
            'score_hint' => $scoreHint,
        ]);

        return [
            'results' => $results,
            'final_status' => $finalStatus,
            'blocked_by' => $blockedBy,
            'total_duration_ms' => round($totalDuration, 2),
            'score_hint' => $scoreHint,
        ];
    }

    /**
     * Sort rails alphabetically by code for deterministic execution.
     * 
     * @return GuardrailInterface[]
     */
    private function sortRailsByCode(): array
    {
        $rails = $this->rails;

        usort($rails, function (GuardrailInterface $a, GuardrailInterface $b): int {
            // Extract code from each rail (assumes AbstractGuardrail with $code property)
            $codeA = $this->extractCode($a);
            $codeB = $this->extractCode($b);

            return strcmp($codeA, $codeB);
        });

        return $rails;
    }

    /**
     * Extract code from guardrail (reflection fallback if not publicly accessible).
     */
    private function extractCode(GuardrailInterface $rail): string
    {
        // Try to get code via reflection (AbstractGuardrail has protected $code)
        try {
            $reflection = new \ReflectionClass($rail);
            $property = $reflection->getProperty('code');
            $property->setAccessible(true);
            return (string)$property->getValue($rail);
        } catch (\ReflectionException $e) {
            // Fallback: use class name
            return get_class($rail);
        }
    }

    /**
     * Calculate score hint (0..1) based on results.
     * 
     * Algorithm:
     * - BLOCK: 0.0
     * - WARN: 0.5 - (warn_count * 0.1)
     * - PASS: 1.0 - (total_duration_penalty)
     * 
     * @param Result[] $results
     */
    private function calculateScoreHint(array $results, string $finalStatus): float
    {
        if ($finalStatus === 'BLOCK') {
            return 0.0;
        }

        if ($finalStatus === 'WARN') {
            $warnCount = count(array_filter($results, fn($r) => $r->isWarning()));
            return max(0.0, 0.5 - ($warnCount * 0.1));
        }

        // PASS: slight penalty for slow execution
        $totalDuration = array_sum(array_map(fn($r) => $r->duration_ms, $results));
        $durationPenalty = min(0.2, $totalDuration / 10000); // Max 0.2 penalty for 2000ms

        return max(0.0, min(1.0, 1.0 - $durationPenalty));
    }
}
