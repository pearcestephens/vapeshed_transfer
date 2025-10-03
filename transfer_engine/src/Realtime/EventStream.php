<?php
declare(strict_types=1);
namespace Unified\Realtime;
use Unified\Support\Logger;
/** EventStream (Phase M6)
 * Basic SSE helper (no persistence). Writes formatted events to output.
 */
final class EventStream
{
    public function __construct(private Logger $logger) {}
    public function send(string $event, array $data): void
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        echo "event: $event\n";
        echo 'data: '.$payload."\n\n";
        @ob_flush(); @flush();
        $this->logger->info('sse.dispatch',[ 'event'=>$event ]);
    }
}
