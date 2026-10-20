<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

final class ChartSpec
{
    /**
     * @param string $unit printed after every value ("ms", "rows")
     * @param string $axisLabel the x-axis title ("milliseconds")
     * @param list<string> $series legend entries, in the fixed colour order
     * @param list<BarGroup> $groups
     * @param string $note what bar and whisker are, under the title
     */
    public function __construct(
        public readonly string $title,
        public readonly string $unit,
        public readonly string $axisLabel,
        public readonly array $series,
        public readonly array $groups,
        public readonly string $note = '',
    ) {
    }
}
