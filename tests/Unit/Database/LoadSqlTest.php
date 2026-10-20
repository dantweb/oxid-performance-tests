<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Database;

use Dantweb\OxidPerformanceTests\Catalogue\ArticleRows;
use Dantweb\OxidPerformanceTests\Database\LoadSql;
use PHPUnit\Framework\TestCase;

/** Port of oxid-fe-user tests/unit/perf/loadSql.spec.ts (LOAD DATA LOCAL over PDO instead of docker cp). */
final class LoadSqlTest extends TestCase
{
    private LoadSql $sql;

    protected function setUp(): void
    {
        $this->sql = new LoadSql();
    }

    public function testLoadsAChunkFromTheClientIntoExamplePerfInRowOrder(): void
    {
        self::assertSame(
            "LOAD DATA LOCAL INFILE '/data/rods-03.tsv' INTO TABLE example_perf.oxarticles ("
                . implode(', ', ArticleRows::COLUMNS) . ')',
            $this->sql->loadStatement('/data/rods-03.tsv'),
        );
    }

    public function testResetsOnlyRodArticlesAndTheirShopMappingMappingFirst(): void
    {
        [$unmap, $remove] = $this->sql->resetStatements();

        self::assertStringContainsString('DELETE t2s FROM example_perf.oxarticles2shop', $unmap);
        self::assertStringContainsString("OXARTNUM LIKE 'ROD-%'", $unmap);
        self::assertSame("DELETE FROM example_perf.oxarticles WHERE OXARTNUM LIKE 'ROD-%'", $remove);
    }

    public function testMapsEveryRodToShopOneOrTheShopViewsWouldNotShowIt(): void
    {
        self::assertSame(
            'INSERT INTO example_perf.oxarticles2shop (OXSHOPID, OXMAPOBJECTID) '
                . "SELECT 1, OXMAPID FROM example_perf.oxarticles WHERE OXARTNUM LIKE 'ROD-%'",
            $this->sql->mapToShopStatement(),
        );
    }

    public function testSessionRelaxesStrictModeAndKeepsTheRowsOutOfTheBinlog(): void
    {
        self::assertContains("SET SESSION sql_mode = ''", $this->sql->sessionPrelude());
        self::assertContains('SET SESSION sql_log_bin = 0', $this->sql->sessionPrelude());
    }

    public function testRaisesRedoLogIoCapacityBufferPoolAndAllowsLocalInfileForTheLoadOnly(): void
    {
        self::assertSame(
            ['innodb_redo_log_capacity', 'innodb_io_capacity', 'innodb_buffer_pool_size', 'local_infile'],
            array_keys(LoadSql::BULK_LOAD_SETTINGS),
        );
        self::assertSame(
            ['SET GLOBAL innodb_io_capacity = 200', 'SET GLOBAL innodb_buffer_pool_size = 134217728'],
            $this->sql->setGlobalStatements(['innodb_io_capacity' => 200, 'innodb_buffer_pool_size' => 134217728]),
        );
    }

    public function testRefreshesTheOptimizerStatisticsOfBothTables(): void
    {
        self::assertSame(
            'ANALYZE TABLE example_perf.oxarticles, example_perf.oxarticles2shop',
            $this->sql->analyzeStatement(),
        );
    }

    public function testTargetsTheDatabaseItIsGiven(): void
    {
        self::assertStringContainsString(
            'INTO TABLE scratch.oxarticles',
            (new LoadSql('scratch'))->loadStatement('/x.tsv'),
        );
    }

    public function testNeverNamesTheDemoDatabase(): void
    {
        $all = implode("\n", [
            $this->sql->loadStatement('/x.tsv'),
            ...$this->sql->resetStatements(),
            $this->sql->mapToShopStatement(),
            $this->sql->analyzeStatement(),
            $this->sql->statisticsQuery(),
        ]);

        self::assertDoesNotMatchRegularExpression('/\bexample\./', $all);
    }

    public function testDropsEverySecondaryIndexButTheAutoIncrementOne(): void
    {
        $statements = $this->sql->secondaryIndexStatements($this->showIndex(), 'OXMAPID');

        self::assertSame(
            'ALTER TABLE example_perf.oxarticles DROP INDEX `OXSORT`, DROP INDEX `parentsort`, '
                . 'DROP INDEX `OXTITLE_PREFIX`',
            $statements['drop'],
        );
    }

    public function testReAddsThemInOrderWithColumnOrderUniquenessAndPrefixLengths(): void
    {
        self::assertSame(
            'ALTER TABLE example_perf.oxarticles ADD UNIQUE INDEX `OXMAPID` (`OXMAPID`), '
                . 'ADD INDEX `OXSORT` (`OXSORT`), '
                . 'ADD INDEX `parentsort` (`OXPARENTID`, `OXSORT`), ADD INDEX `OXTITLE_PREFIX` (`OXTITLE`(20))',
            $this->sql->secondaryIndexStatements($this->showIndex())['add'],
        );
    }

    public function testReAddsAFulltextIndexAsFulltext(): void
    {
        $rows = [$this->index('FT', 1, 1, 'OXTITLE', null, 'FULLTEXT')];

        self::assertSame(
            'ALTER TABLE example_perf.oxarticles ADD FULLTEXT INDEX `FT` (`OXTITLE`)',
            $this->sql->secondaryIndexStatements($rows)['add'],
        );
    }

    public function testComparesTableDefinitionsIgnoringTheCounterAndTheOrderOfKeyLines(): void
    {
        $before = "CREATE TABLE `t` (\n  KEY `A` (`A`),\n  KEY `B` (`B`)\n) ENGINE=InnoDB AUTO_INCREMENT=349 DEFAULT";
        $after = "CREATE TABLE `t` (\n  KEY `B` (`B`),\n  KEY `A` (`A`)\n) "
            . 'ENGINE=InnoDB AUTO_INCREMENT=5000349 DEFAULT';

        self::assertTrue($this->sql->sameTableDefinition($before, $after));
        self::assertFalse($this->sql->sameTableDefinition($before, str_replace('KEY `A`', 'KEY `C`', $after)));
    }

    /** @return list<array<string, int|string|null>> SHOW INDEX rows as PDO returns them */
    private function showIndex(): array
    {
        return [
            $this->index('PRIMARY', 0, 1, 'OXID'),
            $this->index('OXMAPID', 0, 1, 'OXMAPID'),
            $this->index('OXSORT', 1, 1, 'OXSORT'),
            $this->index('parentsort', 1, 1, 'OXPARENTID'),
            $this->index('parentsort', 1, 2, 'OXSORT'),
            $this->index('OXTITLE_PREFIX', 1, 1, 'OXTITLE', 20),
        ];
    }

    /** @return array<string, int|string|null> */
    private function index(
        string $name,
        int $nonUnique,
        int $seq,
        string $column,
        ?int $subPart = null,
        string $type = 'BTREE',
    ): array {
        return [
            'Key_name' => $name,
            'Non_unique' => $nonUnique,
            'Seq_in_index' => $seq,
            'Column_name' => $column,
            'Sub_part' => $subPart,
            'Index_type' => $type,
        ];
    }
}
