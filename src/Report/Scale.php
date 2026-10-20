<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

/**
 * Maps a value to a bar length. Log when values span orders of magnitude: scans and index lookups both stay
 * readable.
 */
final class Scale
{
    /** @param list<float> $ticks */
    private function __construct(
        private readonly float $low,
        private readonly float $high,
        private readonly float $width,
        public readonly array $ticks,
        public readonly bool $log,
    ) {
    }

    public static function linear(float $max, float $width): self
    {
        $raw = $max / 4;
        $power = 10 ** floor(log10($raw));
        $step = $raw;
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            if ($factor * $power >= $raw) {
                $step = $factor * $power;
                break;
            }
        }
        $top = ceil($max / $step) * $step;
        $ticks = array_map(static fn (int $i): float => $i * $step, range(0, (int) round($top / $step)));

        return new self(0.0, $top, $width, $ticks, false);
    }

    public static function logarithmic(float $min, float $max, float $width): self
    {
        $low = log10($min / 2);
        $high = log10($max);
        $ticks = array_map(static fn (int $power): float => 10 ** $power, range((int) ceil($low), (int) floor($high)));

        return new self($low, $high, $width, $ticks, true);
    }

    public function length(float $value): float
    {
        if (!$this->log) {
            return $value / $this->high * $this->width;
        }

        return (log10($value) - $this->low) / ($this->high - $this->low) * $this->width;
    }
}
