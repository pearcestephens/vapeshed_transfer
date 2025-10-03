<?php
declare(strict_types=1);
namespace Unified\Drift;
/** PsiCalculator (Phase M7)
 * Computes PSI between expected and observed bucket distributions.
 */
final class PsiCalculator
{
    /**
     * @param array $expected bucket=>fraction
     * @param array $observed bucket=>fraction
     * @return array{psi:float,buckets:array}
     */
    public function compute(array $expected, array $observed): array
    {
        $buckets = [];
        $psi = 0.0;
        $allKeys = array_unique(array_merge(array_keys($expected), array_keys($observed)));
        foreach ($allKeys as $k) {
            $e = max((float)($expected[$k] ?? 0.0), 1e-9);
            $o = max((float)($observed[$k] ?? 0.0), 1e-9);
            $delta = $o - $e;
            $contrib = $delta * log($o / $e);
            $psi += $contrib;
            $buckets[] = ['bucket'=>$k,'expected'=>$e,'observed'=>$o,'contrib'=>$contrib];
        }
        return ['psi'=>$psi,'buckets'=>$buckets];
    }
}
