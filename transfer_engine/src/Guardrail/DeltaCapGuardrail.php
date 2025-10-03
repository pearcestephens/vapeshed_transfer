<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Config; use Unified\Support\Logger;
/** DeltaCapGuardrail (Phase M3)
 * Enforces max percentage delta vs current price.
 */
final class DeltaCapGuardrail extends AbstractGuardrail
{
    public function __construct() { parent::__construct('GR_DELTA_CAP','Price delta must not exceed cap'); }
    public function evaluate(array $ctx, Logger $logger): array
    {
        $current = (float)($ctx['current_price'] ?? 0);
        $cand = (float)($ctx['candidate_price'] ?? 0);
        if ($current <= 0 || $cand <= 0) { return $this->block('Invalid current or candidate price'); }
        $cap = (float)Config::get('neuro.unified.pricing.delta_cap_pct', 0.07); // 7%
        $deltaPct = ($cand - $current) / $current;
        if (abs($deltaPct) - 1e-9 > $cap) {
            return $this->block('Delta exceeds cap', ['delta_pct'=>$deltaPct,'cap'=>$cap]);
        }
        return $this->pass(['delta_pct'=>$deltaPct,'cap'=>$cap]);
    }
}
