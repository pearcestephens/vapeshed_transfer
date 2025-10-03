<?php
declare(strict_types=1);
/** unified_drift_smoke.php (Phase M7)
 * Demonstrates PSI computation + materialization run (dry).
 */
require_once __DIR__.'/_cli_bootstrap.php';
$base = __DIR__.'/../src';
foreach ([
  'Support/Env','Support/Config','Support/Util','Support/Logger','Support/Pdo',
  'Drift/PsiCalculator','Views/ViewMaterializer'
] as $rel){ $p=$base.'/'.$rel.'.php'; if(is_file($p)) require_once $p; }
use Unified\Support\Logger; use Unified\Drift\PsiCalculator; use Unified\Views\ViewMaterializer; use Unified\Support\Config;

$logger = new Logger('drift_smoke');
Config::prime();
$psiCalc = new PsiCalculator();
$expected = ['low'=>0.30,'mid'=>0.50,'high'=>0.20];
$observed = ['low'=>0.25,'mid'=>0.55,'high'=>0.20];
$psi = $psiCalc->compute($expected,$observed);
$warn = (float)Config::get('neuro.unified.drift.psi_warn',0.15);
$crit = (float)Config::get('neuro.unified.drift.psi_critical',0.25);
$status = 'normal';
if ($psi['psi'] >= $crit) $status='critical'; elseif ($psi['psi'] >= $warn) $status='warn';

$vm = new ViewMaterializer($logger);
$viewsResult = $vm->run(['v_sales_daily','v_inventory_daily']);

echo json_encode([
  'psi'=>$psi,
  'psi_status'=>$status,
  'views_materialization'=>$viewsResult
], JSON_UNESCAPED_SLASHES)."\n";
