<?php
declare(strict_types=1);
namespace Unified\Guardrail;
use Unified\Support\Logger; use Unified\Support\Config;
/** GuardrailChain (Phase M3)
 * Executes registered guardrails in canonical order, short-circuiting on BLOCK.
 */
final class GuardrailChain
{
    /** @var GuardrailInterface[] */
    private array $rails = [];
    public function __construct(private Logger $logger) {}
    public function register(GuardrailInterface $r): void { $this->rails[] = $r; }

    /**
     * @return array{results:array<int,array>,final_status:string,blocked_by:?string}
     */
    public function evaluate(array $ctx): array
    {
        Config::prime();
        $results = [];
        $blockedBy = null; $final = 'PASS';
        foreach ($this->rails as $r) {
            $res = $r->evaluate($ctx, $this->logger);
            $results[] = $res;
            if ($res['status']==='BLOCK') { $blockedBy = $res['code']; $final='BLOCK'; break; }
            if ($res['status']==='WARN' && $final==='PASS') { $final='WARN'; }
        }
        $this->logger->info('guardrail.chain.result',[ 'final'=>$final,'blocked_by'=>$blockedBy ]);
        return [ 'results'=>$results,'final_status'=>$final,'blocked_by'=>$blockedBy ];
    }
}
