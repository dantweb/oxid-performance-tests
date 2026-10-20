<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

/**
 * mulberry32, bit-identical to the JavaScript original (Math.imul, >>> 0): the PHP catalogue must pick
 * exactly the rods the TypeScript generator put into example_perf. All arithmetic is unsigned 32-bit.
 */
final class Mulberry32
{
    private const MASK = 0xFFFFFFFF;

    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed & self::MASK;
    }

    /** A float in [0, 1). */
    public function next(): float
    {
        $this->state = ($this->state + 0x6D2B79F5) & self::MASK;
        $t = $this->state;
        $t = self::imul($t ^ ($t >> 15), $t | 1);
        $t ^= ($t + self::imul($t ^ ($t >> 7), $t | 61)) & self::MASK;

        return (($t ^ ($t >> 14)) & self::MASK) / 4294967296.0;
    }

    /** Math.imul for unsigned 32-bit operands: multiplied in 16-bit halves so no product leaves 64 bits. */
    private static function imul(int $a, int $b): int
    {
        $low = ($a & 0xFFFF) * ($b & 0xFFFF);
        $middle = ((($a >> 16) * ($b & 0xFFFF)) + (($a & 0xFFFF) * ($b >> 16))) & 0xFFFF;

        return ($low + ($middle << 16)) & self::MASK;
    }
}
