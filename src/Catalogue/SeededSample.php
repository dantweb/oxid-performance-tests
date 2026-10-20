<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

use OutOfRangeException;
use SplFixedArray;

/** n distinct indexes of 0..population-1: a partial Fisher–Yates shuffle driven by mulberry32. */
final class SeededSample
{
    /** @return list<int> */
    public function pick(int $seed, int $count, int $population = RodMatrix::SIZE): array
    {
        if ($count > $population) {
            throw new OutOfRangeException(sprintf('cannot sample %d of %d', $count, $population));
        }
        $random = new Mulberry32($seed);
        $pool = new SplFixedArray($population);
        for ($i = 0; $i < $population; $i++) {
            $pool[$i] = $i;
        }
        for ($i = 0; $i < $count; $i++) {
            $j = $i + (int) floor($random->next() * ($population - $i));
            $picked = $pool[$j];
            $pool[$j] = $pool[$i];
            $pool[$i] = $picked;
        }

        return array_slice($pool->toArray(), 0, $count);
    }
}
