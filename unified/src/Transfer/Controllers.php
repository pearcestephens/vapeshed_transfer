<?php
declare(strict_types=1);
namespace Unified\Transfer;
use Unified\Support\Http;
/** Controllers.php
 * HTTP handlers for transfer endpoints (skeleton).
 */
final class Controllers
{
    public function __construct(private BalancerService $service) {}
    public function run(): void
    { Http::json($this->service->executeDryRun()); }
    public function list(): void
    { Http::json(['allocations'=>[]]); }
    public function export(): void
    { header('Content-Type: text/csv'); echo "sku,qty\n"; }
}
