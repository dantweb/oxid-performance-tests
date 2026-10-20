<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

final class NumberFormat
{
    /** Whole numbers from 100 up, grouped by thousands ("5 000 301"); one decimal below. */
    public static function value(float $value): string
    {
        if ($value < 100) {
            return (string) (floor($value * 10 + 0.5) / 10);
        }

        return number_format(floor($value + 0.5), 0, '.', ' ');
    }
}
