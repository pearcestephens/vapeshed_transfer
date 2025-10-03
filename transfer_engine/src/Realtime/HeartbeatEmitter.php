<?php
declare(strict_types=1);
namespace Unified\Realtime;
use Unified\Support\Config; use Unified\Support\Logger; use Unified\Support\Util;
/** HeartbeatEmitter (Phase M6)
 * Emits periodic heartbeat events (in-process loop placeholder). Real deployment may use cron or long-running worker.
 */
final class HeartbeatEmitter
{
    public function __construct(private Logger $logger) { Config::prime(); }
    public function emit(EventStream $stream, int $count=1, int $intervalMs=1000): void
    {
        for ($i=0; $i<$count; $i++) {
            $stream->send('heartbeat',[
                'ts'=>date('c'),
                'seq'=>$i+1,
                'uptime_ms'=> (int)Util::microtimeMs(),
            ]);
            if ($i+1 < $count) usleep($intervalMs*1000);
        }
    }
}
