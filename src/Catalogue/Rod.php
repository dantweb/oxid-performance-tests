<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

/** One cell of the rod matrix. An empty shape is the straight rod. */
final class Rod
{
    public function __construct(
        public readonly string $material,
        public readonly string $shape,
        public readonly int $lengthMm,
        public readonly int $diameterMm,
    ) {
    }
}
