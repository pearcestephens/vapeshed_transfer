<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Logger;
/** RoiViabilityGuardrail (Phase M4)
 * Blocks negative projected ROI.
 */
final class RoiViabilityGuardrail extends AbstractGuardrail
{
    public function __construct() { parent::__construct('GR_ROI_VIABILITY','Projected ROI must be non-negative'); }
    public function evaluate(array $ctx, Logger $logger): array
    {
        $roi = (float)($ctx['projected_roi'] ?? 0.0);
        if ($roi < 0) { return $this->block('Negative ROI', ['roi'=>$roi]); }
        return $this->pass(['roi'=>$roi]);
    }
}
