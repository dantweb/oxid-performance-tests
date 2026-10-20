<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

use OutOfRangeException;

/**
 * Every rod of length × diameter × shape × material has one index (material, shape, length, diameter — slowest
 * to fastest), the same numbering as the oxid-fe-user TypeScript catalogue.
 */
final class RodMatrix
{
    public const LENGTHS_MM = 1000;
    public const DIAMETERS_MM = 100;
    public const SHAPES = ['', 'L', 'U', 'T', 'S'];
    public const MATERIALS = [
        'Zn', 'Fe', 'Co', 'Mn', 'Ni', 'Mg', 'Ti', 'Al',
        'ZnFe', 'FeCo', 'FeMo', 'FeMoCo', 'CoMn', 'AlTi', 'AlMg', 'AlTiMg', 'TiMg',
    ];
    public const SIZE = self::LENGTHS_MM * self::DIAMETERS_MM * 5 * 17;

    public function combination(int $index): Rod
    {
        if ($index < 0 || $index >= self::SIZE) {
            throw new OutOfRangeException(sprintf('rod index %d is outside 0..%d', $index, self::SIZE - 1));
        }
        $shapeAndMaterial = intdiv($index, self::DIAMETERS_MM * self::LENGTHS_MM);

        return new Rod(
            self::MATERIALS[intdiv($shapeAndMaterial, count(self::SHAPES))],
            self::SHAPES[$shapeAndMaterial % count(self::SHAPES)],
            intdiv($index, self::DIAMETERS_MM) % self::LENGTHS_MM + 1,
            $index % self::DIAMETERS_MM + 1,
        );
    }

    public function indexOf(Rod $rod): int
    {
        $material = (int) array_search($rod->material, self::MATERIALS, true);
        $shape = (int) array_search($rod->shape, self::SHAPES, true);
        $shapeAndMaterial = $material * count(self::SHAPES) + $shape;

        return ($shapeAndMaterial * self::LENGTHS_MM + $rod->lengthMm - 1) * self::DIAMETERS_MM + $rod->diameterMm - 1;
    }
}
