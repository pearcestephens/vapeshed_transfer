<?php
declare(strict_types=1);
/** sse.php (Phase M6)
 * Simple SSE endpoint providing heartbeat events (development scaffold).
 * Usage: curl -H 'Accept: text/event-stream' http://host/path/to/sse.php
 */
require_once __DIR__.'/../bin/_cli_bootstrap.php';

// Load Support + Realtime classes manually (temporary until autoloader refactor)
$base = __DIR__.'/../src';
foreach ([
  'Support/Env','Support/Config','Support/Util','Support/Logger',
  'Realtime/EventStream','Realtime/HeartbeatEmitter'
] as $rel){ $p=$base.'/'.$rel.'.php'; if(is_file($p)) require_once $p; }

use Unified\Support\Logger; use Unified\Realtime\EventStream; use Unified\Realtime\HeartbeatEmitter;

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$logger = new Logger('sse');
$stream = new EventStream($logger);
$hb = new HeartbeatEmitter($logger);
$hb->emit($stream, 3, 1000); // emit 3 heartbeats then exit (dev mode)
