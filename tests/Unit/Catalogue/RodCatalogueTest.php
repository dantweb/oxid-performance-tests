<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Catalogue;

use Dantweb\OxidPerformanceTests\Catalogue\ArticleRows;
use Dantweb\OxidPerformanceTests\Catalogue\PrimaryKeyOrder;
use Dantweb\OxidPerformanceTests\Catalogue\Rod;
use Dantweb\OxidPerformanceTests\Catalogue\RodMatrix;
use Dantweb\OxidPerformanceTests\Catalogue\SeededSample;
use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Port of oxid-fe-user tests/unit/perf/rodCatalogue.spec.ts. The golden values were produced by the TypeScript
 * implementation on 2026-10-20: the PHP catalogue must be bit-identical, so example_perf's rows stay valid.
 */
final class RodCatalogueTest extends TestCase
{
    private RodMatrix $matrix;
    private ArticleRows $rows;

    protected function setUp(): void
    {
        $this->matrix = new RodMatrix();
        $this->rows = new ArticleRows($this->matrix);
    }

    public function testMatrixIs1000Lengths100Diameters5Shapes17Materials(): void
    {
        self::assertSame(1000, RodMatrix::LENGTHS_MM);
        self::assertSame(100, RodMatrix::DIAMETERS_MM);
        self::assertSame(['', 'L', 'U', 'T', 'S'], RodMatrix::SHAPES);
        self::assertSame(
            [
                'Zn', 'Fe', 'Co', 'Mn', 'Ni', 'Mg', 'Ti', 'Al',
                'ZnFe', 'FeCo', 'FeMo', 'FeMoCo', 'CoMn', 'AlTi', 'AlMg', 'AlTiMg', 'TiMg',
            ],
            RodMatrix::MATERIALS,
        );
        self::assertSame(8_500_000, RodMatrix::SIZE);
    }

    /** @return iterable<array{int}> */
    public static function indexes(): iterable
    {
        foreach ([0, 1, 99, 100, 4_242_424, RodMatrix::SIZE - 1] as $index) {
            yield "index $index" => [$index];
        }
    }

    #[DataProvider('indexes')]
    public function testCombinationAndIndexOfRoundTrip(int $index): void
    {
        self::assertSame($index, $this->matrix->indexOf($this->matrix->combination($index)));
    }

    public function testFirstAndLastIndexAreTheMatrixCorners(): void
    {
        self::assertEquals(new Rod('Zn', '', 1, 1), $this->matrix->combination(0));
        self::assertEquals(new Rod('TiMg', 'S', 1000, 100), $this->matrix->combination(RodMatrix::SIZE - 1));
        self::assertEquals(new Rod('ZnFe', 'U', 425, 25), $this->matrix->combination(4_242_424));
    }

    public function testIndexOutsideTheMatrixIsRejected(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->matrix->combination(RodMatrix::SIZE);
    }

    public function testSampleYieldsDistinctIndexesInsideThePopulation(): void
    {
        $picked = (new SeededSample())->pick(7, 50, 85);

        self::assertCount(50, $picked);
        self::assertCount(50, array_unique($picked));
        self::assertLessThan(85, max($picked));
    }

    public function testSampleIsReproducibleAndDependsOnTheSeed(): void
    {
        $sample = new SeededSample();

        self::assertSame($sample->pick(20261020, 20, 85), $sample->pick(20261020, 20, 85));
        self::assertNotSame($sample->pick(20261020, 20, 85), $sample->pick(20261021, 20, 85));
    }

    public function testSampleMatchesTheTypeScriptImplementation(): void
    {
        $sample = new SeededSample();

        self::assertSame([0, 6, 83, 60, 46], $sample->pick(7, 5, 85));
        self::assertSame([2246106, 2027997, 7333355, 4379270, 3768056], $sample->pick(20261020, 5));
    }

    public function testSampleOfTheWholePopulationIsAPermutation(): void
    {
        $picked = (new SeededSample())->pick(1, 85, 85);
        sort($picked);

        self::assertSame(range(0, 84), $picked);
    }

    public function testAskingForMoreThanThePopulationIsRejected(): void
    {
        $this->expectException(OutOfRangeException::class);
        (new SeededSample())->pick(1, 86, 85);
    }

    public function testStraightRodIsTitledWithoutAShapeWord(): void
    {
        $row = $this->rows->articleRow($this->matrix->indexOf(new Rod('Fe', '', 120, 8)));

        self::assertSame('metal rod Fe 120mm length 8mm diameter', $row['OXTITLE']);
    }

    public function testShapedRodIsTitledWithItsShapeBeforeTheMaterial(): void
    {
        $row = $this->rows->articleRow($this->matrix->indexOf(new Rod('AlTiMg', 'U', 3, 1)));

        self::assertSame('metal rod U-shape AlTiMg 3mm length 1mm diameter', $row['OXTITLE']);
    }

    public function testShortDescriptionNamesMaterialShapeLengthAndDiameter(): void
    {
        $shaped = $this->rows->articleRow($this->matrix->indexOf(new Rod('FeCo', 'T', 450, 12)));
        $straight = $this->rows->articleRow($this->matrix->indexOf(new Rod('Ni', '', 7, 100)));

        self::assertSame('FeCo rod, T-shape, 450 mm long, 12 mm in diameter', $shaped['OXSHORTDESC']);
        self::assertSame('Ni rod, straight, 7 mm long, 100 mm in diameter', $straight['OXSHORTDESC']);
    }

    public function testRowMatchesTheTypeScriptImplementation(): void
    {
        self::assertSame(
            [
                'OXID' => 'd2a0147a36ed0b23b669a73cb420eaf7',
                'OXSHOPID' => 1,
                'OXACTIVE' => 1,
                'OXARTNUM' => 'ROD-4242424',
                'OXTITLE' => 'metal rod U-shape ZnFe 425mm length 25mm diameter',
                'OXSHORTDESC' => 'ZnFe rod, U-shape, 425 mm long, 25 mm in diameter',
                'OXPRICE' => 11.63,
                'OXSTOCK' => 100,
                'OXSTOCKFLAG' => 1,
                'OXPARENTID' => '',
            ],
            $this->rows->articleRow(4_242_424),
        );
    }

    public function testPriceRoundsLikeJavaScriptsMathRoundNotPhpsRound(): void
    {
        // 1 + 215 × 17 / 1000 = 4.655 → × 100 = 465.49999999999994: Math.round gives 465, PHP's round() 466.
        self::assertSame(4.65, $this->rows->articleRow(7_921_416)['OXPRICE']);
        self::assertSame(40.35, $this->rows->articleRow(5_646_284)['OXPRICE']);
    }

    public function testArticleNumberIsZeroPadded(): void
    {
        self::assertSame('ROD-0000005', $this->rows->articleRow(5)['OXARTNUM']);
    }

    public function testRowListsItsFieldsInColumnOrder(): void
    {
        self::assertSame(ArticleRows::COLUMNS, array_keys($this->rows->articleRow(0)));
    }

    public function testTsvLineMatchesTheTypeScriptImplementation(): void
    {
        self::assertSame(
            "feddbf61efc36303281c4b846e155fb4\t1\t1\tROD-0000000\tmetal rod Zn 1mm length 1mm diameter\t"
                . "Zn rod, straight, 1 mm long, 1 mm in diameter\t1\t100\t1\t",
            $this->rows->tsvLine($this->rows->articleRow(0)),
        );
    }

    public function testTsvEscapesBackslashTabAndNewlineTheWayLoadDataReadsThem(): void
    {
        $row = ['OXSHORTDESC' => "a\\b\tc\nd"] + $this->rows->articleRow(0);

        self::assertStringContainsString('a\\\\b\\tc\\nd', $this->rows->tsvLine($row));
    }

    public function testPrimaryKeyOrderSortsByOxidAndMatchesTheTypeScriptImplementation(): void
    {
        $ordered = (new PrimaryKeyOrder($this->rows))->order((new SeededSample())->pick(5, 2000));
        $oxids = array_map(fn (int $index): string => $this->rows->oxid($index), $ordered);
        $sorted = $oxids;
        sort($sorted, SORT_STRING);

        self::assertSame([1039061, 6825911, 7846970, 1148680, 3239600], array_slice($ordered, 0, 5));
        self::assertSame($sorted, $oxids);
    }

    public function testAppendOrderAllowsTiesWithinFortyBitsButNoStepBack(): void
    {
        $order = new PrimaryKeyOrder($this->rows);

        self::assertTrue($order->isInAppendOrder(['0a', '1bd6a2d9489a', '1bd6a2d94832', 'ff']));
        self::assertFalse($order->isInAppendOrder(['42fd2788', '4b798a9c', '1a000000']));
    }
}
