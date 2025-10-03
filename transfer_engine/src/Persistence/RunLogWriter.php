<?php
declare(strict_types=1);
namespace Unified\Persistence;
use Unified\Support\Logger;
/** RunLogWriter (Phase M9)
 * Writes run-level metadata (stdout logger now; future table insert).
 */
final class RunLogWriter
{
    public function __construct(private Logger $logger) {}
    public function write(array $meta): void
    { $this->logger->info('run.log',$meta); }
}
