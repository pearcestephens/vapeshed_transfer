<?php
declare(strict_types=1);
namespace Unified\Matching;
use Unified\Support\Logger;
/** TokenExtractor (Phase M15)
 * Extracts normalized tokens from product titles for matching & similarity.
 */
final class TokenExtractor
{
    public function __construct(private Logger $logger) {}
    /** @return string[] */
    public function extract(string $title): array
    {
        $t = strtolower($title);
        $t = preg_replace('/[^a-z0-9 ]+/',' ', $t);
        $t = preg_replace('/\s+/',' ', $t);
        $parts = array_values(array_filter(explode(' ', $t), fn($p)=>strlen($p)>1));
        $this->logger->info('matching.token.extract',[ 'count'=>count($parts) ]);
        return $parts;
    }
}
