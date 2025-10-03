<?php
declare(strict_types=1);
namespace Unified\Transfer;
use Unified\Support\Logger;
use Unified\Support\Util;
/** BalancerService.php
 * Orchestrates an end-to-end balancing run (skeleton).
 */
final class BalancerService
{
    public function __construct(
        private BalancerEngine $engine,
        private TransferRepository $repo,
        private Logger $logger
    ) {}
    public function executeDryRun(): array
    {
        $t0 = Util::microtimeMs();
        $result = $this->engine->run([]); // TODO: fetch inventory slice
        $ms = Util::microtimeMs() - $t0;
        $this->logger->info('balancer.dry_run',['duration_ms'=>$ms]);
        return $result + ['duration_ms'=>$ms,'mode'=>'dry'];
    }
}
