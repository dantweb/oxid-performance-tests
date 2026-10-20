<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

final class BarGroup
{
    /** @param list<Bar> $bars */
    public function __construct(public readonly string $label, public readonly array $bars)
    {
    }
}
