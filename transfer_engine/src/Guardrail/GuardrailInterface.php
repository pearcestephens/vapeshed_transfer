<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Logger;
/** GuardrailInterface (Phase M3)
 * Contract for guardrail checks. Stateless objects performing a single evaluation.
 */
interface GuardrailInterface
{
    /**
     * Evaluate context and return result array.
     * Return shape: [ 'code'=>string, 'status'=>'PASS'|'WARN'|'BLOCK', 'message'=>string, 'meta'=>array ]
     */
    public function evaluate(array $ctx, Logger $logger): array;
}
