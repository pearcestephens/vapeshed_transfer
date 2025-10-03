<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Config; use Unified\Support\Logger;
/** ReceiverOvershootGuardrail (Phase M4)
 * Prevents receiver DSR overshoot beyond upper buffer.
 */
final class ReceiverOvershootGuardrail extends AbstractGuardrail
{
    public function __construct() { parent::__construct('GR_RECEIVER_OVERSHOOT','Receiver DSR must not exceed max buffer'); }
    public function evaluate(array $ctx, Logger $logger): array
    {
        $post = (float)($ctx['receiver_dsr_post'] ?? 0);
        $max = (float)($ctx['receiver_max_dsr'] ?? Config::get('neuro.unified.balancer.receiver_max_dsr', 18));
        if ($post > $max) { return $this->block('Receiver DSR overshoot', ['post'=>$post,'max'=>$max]); }
        return $this->pass(['post'=>$post,'max'=>$max]);
    }
}
