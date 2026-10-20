<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

final class Statistics
{
    /**
     * Linear interpolation between closest ranks (numpy's default).
     *
     * @param list<float> $samples
     */
    public function percentile(array $samples, float $p): float
    {
        sort($samples);
        $rank = (count($samples) - 1) * $p / 100;
        $lower = (int) floor($rank);
        $upper = (int) ceil($rank);

        return $samples[$lower] + ($samples[$upper] - $samples[$lower]) * ($rank - $lower);
    }
}
