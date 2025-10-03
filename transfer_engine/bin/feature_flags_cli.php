<?php
declare(strict_types=1);
/**
 * feature_flags_cli.php
 * Manage feature flags via CLI.
 * Commands:
 *   list [--json]         List all flags (optionally JSON)
 *   get <key>             Output flag value (0/1)
 *   set <key> <0|1>       Set flag value
 */

use VapeshedTransfer\App\Repositories\FeatureFlagRepository;

require_once __DIR__.'/_cli_bootstrap.php';
$mysqli = cli_db();
$repo = new FeatureFlagRepository($mysqli);

$cmd = $argv[1] ?? 'list';
$json = in_array('--json', $argv, true);

switch ($cmd) {
    case 'list':
        $all = $repo->all();
        if ($json) {
            echo json_encode(['success'=>true,'flags'=>$all,'count'=>count($all),'timestamp'=>date('c')], JSON_PRETTY_PRINT) . "\n";
        } else {
            foreach ($all as $k=>$v) { echo $k.'='.(int)$v."\n"; }
        }
        break;
    case 'get':
        $key = $argv[2] ?? null; if(!$key){ fwrite(STDERR,"Usage: get <key>\n"); exit(1);} 
        echo (int)$repo->get($key)."\n"; 
        break;
    case 'set':
        $key = $argv[2] ?? null; $val = $argv[3] ?? null; if(!$key || $val===null){ fwrite(STDERR,"Usage: set <key> <0|1>\n"); exit(1);} 
        $ok = $repo->set($key, $val==='1');
        echo $ok?"OK\n":"FAIL\n"; 
        break;
    default:
        fwrite(STDERR, "Unknown command. Use list|get|set\n");
        exit(1);
}
