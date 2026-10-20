<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

final class Bar
{
    /** A null value is no measurement: drawn as "n/a – <note>". */
    public function __construct(
        public readonly string $series,
        public readonly ?float $value,
        public readonly ?float $whisker = null,
        public readonly ?string $note = null,
    ) {
    }
}
