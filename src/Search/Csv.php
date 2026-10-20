<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

/** The two CSV cell formats the raw files use. */
final class Csv
{
    public static function text(string $text): string
    {
        return '"' . str_replace('"', '""', $text) . '"';
    }

    /** One decimal, rounded half up like the TypeScript benchmark; "29", not "29.0". */
    public static function number(float $value): string
    {
        return (string) (floor($value * 10 + 0.5) / 10);
    }
}
