<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Database;

use Dantweb\OxidPerformanceTests\Database\IndexMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Port of oxid-fe-user tests/unit/perf/indexMigrations.spec.ts. */
final class IndexMigrationsTest extends TestCase
{
    private IndexMigrations $migrations;

    protected function setUp(): void
    {
        $this->migrations = new IndexMigrations();
    }

    public function testKnowsTheTwoMeasuredKinds(): void
    {
        self::assertSame(['btree', 'fulltext'], IndexMigrations::KINDS);
    }

    /** @return iterable<array{string}> */
    public static function kinds(): iterable
    {
        yield 'btree' => ['btree'];
        yield 'fulltext' => ['fulltext'];
    }

    #[DataProvider('kinds')]
    public function testRelaxesStrictModeFirstBecauseTheShopsZeroDateDefaultsFailAnyAlter(string $kind): void
    {
        self::assertSame("SET SESSION sql_mode = ''", $this->migrations->up($kind)[0]);
        self::assertSame("SET SESSION sql_mode = ''", $this->migrations->down($kind)[0]);
    }

    public function testAddsBothBtreeIndexesOnlineInOneStatement(): void
    {
        self::assertSame(
            [
                'ALTER TABLE example_perf.oxarticles ADD INDEX OXTITLE_PERF (OXTITLE), '
                    . 'ADD INDEX OXSHORTDESC_PERF (OXSHORTDESC), ALGORITHM=INPLACE, LOCK=NONE',
            ],
            array_slice($this->migrations->up('btree'), 1),
        );
    }

    public function testAddsTheFulltextIndexesOnePerStatement(): void
    {
        self::assertSame(
            [
                'ALTER TABLE example_perf.oxarticles ADD FULLTEXT INDEX OXTITLE_FT (OXTITLE)',
                'ALTER TABLE example_perf.oxarticles ADD FULLTEXT INDEX OXSHORTDESC_FT (OXSHORTDESC)',
            ],
            array_slice($this->migrations->up('fulltext'), 1),
        );
    }

    #[DataProvider('kinds')]
    public function testDownDropsExactlyWhatUpAdded(string $kind): void
    {
        $dropped = array_map(
            static fn (array $index): string => 'DROP INDEX ' . $index['name'],
            $this->migrations->expectedIndexes($kind),
        );

        self::assertSame(
            ['ALTER TABLE example_perf.oxarticles ' . implode(', ', $dropped)],
            array_slice($this->migrations->down($kind), 1),
        );
    }

    public function testExpectsOneIndexPerColumnOfTheMeasuredKind(): void
    {
        self::assertSame(
            [
                ['name' => 'OXTITLE_PERF', 'column' => 'OXTITLE', 'type' => 'BTREE'],
                ['name' => 'OXSHORTDESC_PERF', 'column' => 'OXSHORTDESC', 'type' => 'BTREE'],
            ],
            $this->migrations->expectedIndexes('btree'),
        );
        self::assertSame([], $this->migrations->expectedIndexes('none'));
    }

    public function testReadsStateAndSizeFromExamplePerfOnly(): void
    {
        self::assertStringContainsString(
            "TABLE_SCHEMA = 'example_perf' AND TABLE_NAME = 'oxarticles'",
            $this->migrations->stateQuery(),
        );
        self::assertStringContainsString(
            "IN ('OXTITLE_PERF', 'OXSHORTDESC_PERF', 'OXTITLE_FT', 'OXSHORTDESC_FT')",
            $this->migrations->stateQuery(),
        );
        self::assertStringContainsString('mysql.innodb_index_stats', $this->migrations->sizeQuery('btree'));
        self::assertStringContainsString("NAME LIKE 'example_perf/fts\\_%'", $this->migrations->sizeQuery('fulltext'));
    }

    public function testNeverNamesTheDemoDatabase(): void
    {
        $all = [];
        foreach (IndexMigrations::KINDS as $kind) {
            $all = [
                ...$all,
                ...$this->migrations->up($kind),
                ...$this->migrations->down($kind),
                $this->migrations->sizeQuery($kind),
            ];
        }

        self::assertDoesNotMatchRegularExpression('/\bexample\./', implode("\n", $all));
    }
}
