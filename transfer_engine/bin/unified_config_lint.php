<?php
declare(strict_types=1);
/** unified_config_lint.php (Phase M4)
 * Reports missing required unified config keys and warns on fallback usage.
 */
require_once __DIR__.'/_cli_bootstrap.php';
$supportDir = __DIR__.'/../src/Support';
foreach(['Env','Config','Util','Logger','Idem','Http','Validator','Pdo'] as $cls){ $p=$supportDir.'/'.$cls.'.php'; if(is_file($p)) require_once $p; }
use Unified\Support\Config; use Unified\Support\Logger;

$logger = new Logger('config_lint');
Config::setLogger($logger);
Config::prime();

$missing = Config::missing();
$out = [ 'missing_count'=>count($missing), 'missing'=>$missing ];
echo json_encode($out, JSON_UNESCAPED_SLASHES)."\n";
if ($missing) { exit(1); }
