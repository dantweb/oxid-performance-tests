<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

/** A rod as an oxarticles row and as a LOAD DATA line. Pure. */
final class ArticleRows
{
    /** The columns a generated article fills; every other oxarticles column keeps its default. */
    public const COLUMNS = [
        'OXID', 'OXSHOPID', 'OXACTIVE', 'OXARTNUM', 'OXTITLE', 'OXSHORTDESC',
        'OXPRICE', 'OXSTOCK', 'OXSTOCKFLAG', 'OXPARENTID',
    ];

    public function __construct(private readonly RodMatrix $matrix)
    {
    }

    public function oxid(int $index): string
    {
        return md5('rod-' . $index);
    }

    /** @return array<string, int|float|string> */
    public function articleRow(int $index): array
    {
        $rod = $this->matrix->combination($index);

        return [
            'OXID' => $this->oxid($index),
            'OXSHOPID' => 1,
            'OXACTIVE' => 1,
            'OXARTNUM' => sprintf('ROD-%07d', $index),
            'OXTITLE' => $this->title($rod),
            'OXSHORTDESC' => $this->shortDescription($rod),
            'OXPRICE' => $this->price($rod),
            'OXSTOCK' => 100,
            'OXSTOCKFLAG' => 1,
            'OXPARENTID' => '',
        ];
    }

    /**
     * One line for MySQL's LOAD DATA defaults (tab-separated, backslash escapes).
     *
     * @param array<string, int|float|string> $row
     */
    public function tsvLine(array $row): string
    {
        return implode("\t", array_map(
            fn (string $column): string => $this->escape((string) $row[$column]),
            self::COLUMNS,
        ));
    }

    /**
     * 1 + length × diameter / 1000, to the cent — rounded like JavaScript's Math.round (floor(x + 0.5)):
     * PHP's round() pre-rounds float noise and would give 4.66 where the TypeScript catalogue has 4.65.
     */
    private function price(Rod $rod): float
    {
        return floor((1 + ($rod->lengthMm * $rod->diameterMm) / 1000) * 100 + 0.5) / 100;
    }

    private function title(Rod $rod): string
    {
        $shape = $rod->shape === '' ? '' : $rod->shape . '-shape ';

        return sprintf(
            'metal rod %s%s %dmm length %dmm diameter',
            $shape,
            $rod->material,
            $rod->lengthMm,
            $rod->diameterMm,
        );
    }

    private function shortDescription(Rod $rod): string
    {
        $shape = $rod->shape === '' ? 'straight' : $rod->shape . '-shape';

        return sprintf(
            '%s rod, %s, %d mm long, %d mm in diameter',
            $rod->material,
            $shape,
            $rod->lengthMm,
            $rod->diameterMm,
        );
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\t", "\n"], ['\\\\', '\\t', '\\n'], $value);
    }
}
