<?php
declare(strict_types=1);
namespace Unified\Realtime;
/** Streams.php
 * SSE stream helper stub.
 */
final class Streams
{
    public static function sendKeepAlive(): void
    { echo "event: keepalive\n"; echo 'data: {"ts":"'.date('c').'"}'."\n\n"; @ob_flush(); @flush(); }
}
