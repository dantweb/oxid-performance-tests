<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Report;

use Dantweb\OxidPerformanceTests\Report\Bar;
use Dantweb\OxidPerformanceTests\Report\ReportData;
use PHPUnit\Framework\TestCase;

/** Port of oxid-fe-user tests/unit/perf/reportData.spec.ts, with the run names and n/a reasons of the chart fix. */
final class ReportDataTest extends TestCase
{
    private const GRAPHQL_CSV = 'run,scenario,term,limit,offset,n,cold_ms,p50_ms,p95_ms,p99_ms,min_ms,max_ms,'
        . "rows_returned,failure\n"
        . "none,typing,\"me\",5,0,30,900,400,420,430,390,431,5,\n"
        . "btree,typing,\"me\",5,0,30,800,350,380,390,340,391,5,\n"
        . "none,page-last,\"L-shape\",13,228,5,9000,8000,8500,8600,7900,8610,13,\n";

    private const PROBE_CSV = "run,predicate,term,cold_ms,warm_ms,rows_examined,plan,failure\n"
        . "none,titleContains,\"Zn\",120,100,5000000,\"oxarticles:ALL:-\",\n"
        . "none,titleMatch,\"Zn\",0,0,0,\"\",\"ERROR 1191, no FULLTEXT index\"\n"
        . "fulltext,titleMatch,\"Zn\",3,2,0,\"oxarticles:fulltext:OXTITLE_FT\",\n";

    private ReportData $data;

    protected function setUp(): void
    {
        $this->data = new ReportData();
    }

    public function testParsesQuotedFieldsWithCommasAndDoubledQuotes(): void
    {
        self::assertSame([['a' => 'x, "y"', 'b' => '2']], $this->data->parseCsv("a,b\n\"x, \"\"y\"\"\",2\n"));
    }

    public function testGraphqlChartHasOneGroupPerScenarioAndTermInFirstSeenOrder(): void
    {
        $chart = $this->data->graphqlChart($this->data->parseCsv(self::GRAPHQL_CSV), ['none', 'btree', 'fulltext']);

        self::assertSame(
            ['typing “me”', 'page-last “L-shape” p20'],
            array_map(static fn ($group): string => $group->label, $chart->groups),
        );
        self::assertSame(['no index', 'B-tree index', 'FULLTEXT index'], $chart->series);
        self::assertSame('milliseconds', $chart->axisLabel);
    }

    public function testGraphqlBarIsP50WithAP95Whisker(): void
    {
        $chart = $this->data->graphqlChart($this->data->parseCsv(self::GRAPHQL_CSV), ['none', 'btree', 'fulltext']);

        self::assertEquals(
            [new Bar('no index', 400.0, 420.0), new Bar('B-tree index', 350.0, 380.0)],
            $chart->groups[0]->bars,
        );
    }

    public function testProbeChartUsesTheWarmTimeAndSaysWhyAValueIsMissing(): void
    {
        $chart = $this->data->probeChart(
            $this->data->parseCsv(self::PROBE_CSV),
            ['none', 'btree', 'fulltext'],
            'warm_ms',
        );
        $match = array_values(array_filter(
            $chart->groups,
            static fn ($group): bool => $group->label === 'title MATCH (FULLTEXT) “Zn”',
        ));

        self::assertEquals(
            [new Bar('no index', null, null, 'needs a FULLTEXT index'), new Bar('FULLTEXT index', 2.0)],
            $match[0]->bars,
        );
    }

    public function testProbeChartCanShowRowsExamined(): void
    {
        $chart = $this->data->probeChart($this->data->parseCsv(self::PROBE_CSV), ['none'], 'rows_examined');

        self::assertEquals(new Bar('no index', 5000000.0), $chart->groups[0]->bars[0]);
        self::assertSame('rows', $chart->unit);
        self::assertSame('rows examined', $chart->axisLabel);
        self::assertSame('title contains “Zn”', $chart->groups[0]->label);
    }

    public function testExplainsTheMysqlRefusalsInWords(): void
    {
        self::assertSame(
            'needs a FULLTEXT index',
            $this->data->missingReason("ERROR 1191 (HY000): Can't find FULLTEXT index"),
        );
        self::assertSame(
            'failed: FTS result cache limit (error 188)',
            $this->data->missingReason('SQLSTATE[HY000]: General error: 188 FTS query exceeds result cache limit'),
        );
    }

    public function testComparisonTableShowsGroupsAsRowsAndRunsAsColumns(): void
    {
        $chart = $this->data->graphqlChart($this->data->parseCsv(self::GRAPHQL_CSV), ['none', 'btree', 'fulltext']);

        self::assertSame(
            implode("\n", [
                '| Scenario | no index | B-tree index | FULLTEXT index |',
                '| -------- | -------: | -----------: | -------------: |',
                '| typing “me” | 400 (p95 420) | 350 (p95 380) | – |',
                '| page-last “L-shape” p20 | 8 000 (p95 8 500) | – | – |',
            ]),
            $this->data->comparisonTable($chart),
        );
    }

    public function testComparisonTablePrintsAMissingValueWithItsReason(): void
    {
        $chart = $this->data->probeChart($this->data->parseCsv(self::PROBE_CSV), ['none', 'fulltext'], 'warm_ms');

        self::assertSame(
            '| title MATCH (FULLTEXT) “Zn” | n/a (needs a FULLTEXT index) | 2 |',
            explode("\n", $this->data->comparisonTable($chart))[3],
        );
    }

    public function testIndexCostTableShowsSecondsAndMebibytes(): void
    {
        $rows = $this->data->parseCsv("kind,build_ms,size_bytes\nbtree,61234,734003200\nfulltext,600000,1073741824\n");

        self::assertSame(
            implode("\n", [
                '| Index | Build time | Size on disk |',
                '| ----- | ---------: | -----------: |',
                '| B-tree index | 61.2 s | 700 MiB |',
                '| FULLTEXT index | 600 s | 1 024 MiB |',
            ]),
            $this->data->indexCostTable($rows),
        );
    }
}
