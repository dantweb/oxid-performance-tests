<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

final class ScenarioResult
{
    /**
     * @param array{query: string, variables: array{q: string, limit: int, offset: int}} $request
     * @param list<float> $samplesMs
     */
    public function __construct(
        public readonly Scenario $scenario,
        public readonly array $request,
        public readonly float $coldMs,
        public readonly array $samplesMs,
        public readonly int $rowsReturned,
        public readonly ?string $failure,
    ) {
    }
}
