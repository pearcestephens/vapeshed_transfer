<?php
declare(strict_types=1);
namespace Unified\Matching;
use Unified\Support\Logger;
/** FuzzyMatcher (Phase M15)
 * Computes a simple Jaccard similarity over token sets (placeholder for later ML similarity).
 */
final class FuzzyMatcher
{
    public function __construct(private Logger $logger) {}
    public function similarity(array $tokensA, array $tokensB): float
    {
        $setA = array_unique($tokensA); $setB = array_unique($tokensB);
        $inter = count(array_intersect($setA,$setB));
        $union = count(array_unique(array_merge($setA,$setB)));
        if ($union===0) return 0.0;
        $score = $inter / $union;
        $this->logger->info('matching.fuzzy.sim',[ 'score'=>$score ]);
        return round($score,4);
    }
}
