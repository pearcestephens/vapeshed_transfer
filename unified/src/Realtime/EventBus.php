<?php
declare(strict_types=1);
namespace Unified\Realtime;
use Unified\Support\Logger;
/** EventBus.php
 * Simple in-process publish (future: SSE / Redis pub-sub).
 */
final class EventBus
{
    private array $subscribers = [];
    public function __construct(private Logger $logger) {}
    public function subscribe(string $topic, callable $cb): void { $this->subscribers[$topic][]=$cb; }
    public function publish(string $topic, array $event): void
    { foreach($this->subscribers[$topic]??[] as $cb){ $cb($event); } $this->logger->info('event.publish',['topic'=>$topic]); }
}
