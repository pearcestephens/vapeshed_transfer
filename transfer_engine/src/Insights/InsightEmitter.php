<?php
declare(strict_types=1);
namespace Unified\Insights;
use Unified\Support\Logger;
/** InsightEmitter (Phase M5)
 * Emits structured insights (stdout/log placeholder). Future: persist to table.
 */
final class InsightEmitter
{
    public function __construct(private Logger $logger) {}
    public function emit(string $type, string $message, array $meta=[]): void
    {
        $payload = [ 'type'=>$type,'message'=>$message,'meta'=>$meta,'ts'=>date('c') ];
        $this->logger->info('insight.emit', $payload);
    }
}
