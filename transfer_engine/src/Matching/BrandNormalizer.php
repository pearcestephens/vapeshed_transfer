<?php
declare(strict_types=1);
namespace Unified\Matching;
use Unified\Support\Logger;
/** BrandNormalizer (Phase M15)
 * Normalizes brand strings (case, punctuation, common synonyms) for matching pipeline.
 */
final class BrandNormalizer
{
    private array $synonyms = [ 'vape co' => 'vape company', 'tv shed' => 'the vape shed' ];
    public function __construct(private Logger $logger) {}
    public function normalize(string $brand): string
    {
        $b = strtolower(trim(preg_replace('/[^a-z0-9 ]+/i',' ', $brand)));
        $b = preg_replace('/\s+/',' ', $b);
        if (isset($this->synonyms[$b])) $b = $this->synonyms[$b];
        $this->logger->info('matching.brand.normalize',[ 'in'=>$brand,'out'=>$b ]);
        return $b;
    }
}
