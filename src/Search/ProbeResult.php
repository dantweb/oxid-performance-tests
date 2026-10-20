<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

final class ProbeResult
{
    public function __construct(
        public readonly float $coldMs,
        public readonly float $warmMs,
        public readonly int $rowsExamined,
        public readonly string $plan,
        public readonly ?string $failure,
    ) {
    }
}
