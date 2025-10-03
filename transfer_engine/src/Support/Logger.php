<?php
declare(strict_types=1);
namespace Unified\Support;
/** Logger.php (Phase M1)
 * Structured line logger (JSON). Future: central aggregator + rotation.
 */
final class Logger
{
    public function __construct(private string $channel) {}
    public function info(string $msg, array $ctx=[]): void { $this->log('INFO',$msg,$ctx); }
    public function warn(string $msg, array $ctx=[]): void { $this->log('WARN',$msg,$ctx); }
    public function error(string $msg, array $ctx=[]): void { $this->log('ERROR',$msg,$ctx); }
    private function log(string $lvl, string $msg, array $ctx): void
    {
        $line = json_encode([
            'ts'=>date('c'), 'lvl'=>$lvl, 'chan'=>$this->channel, 'msg'=>$msg, 'ctx'=>$ctx
        ], JSON_UNESCAPED_SLASHES);
        fwrite(($lvl==='ERROR'?STDERR:STDOUT), $line."\n");
    }
}
