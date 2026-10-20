<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Search;

use Dantweb\OxidPerformanceTests\Search\ProbeResult;
use Dantweb\OxidPerformanceTests\Search\SqlProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Port of oxid-fe-user tests/unit/perf/sqlProbe.spec.ts. */
final class SqlProbeTest extends TestCase
{
    private SqlProbe $probe;

    protected function setUp(): void
    {
        $this->probe = new SqlProbe();
    }

    public function testIsTheShopsGraphqlProductListQueryWithThePredicateSwappedIn(): void
    {
        $sql = $this->probe->statement('titleContains', 'U-shape AlTi');

        self::assertStringStartsWith(
            '/* sprint17-probe */ SELECT oxv_oxarticles_1_de.OXID FROM oxv_oxarticles_1_de WHERE',
            $sql,
        );
        self::assertStringContainsString('oxv_oxarticles_1_de.oxactive = 1', $sql);
        self::assertStringContainsString("AND (oxv_oxarticles_1_de.OXTITLE LIKE '%U-shape AlTi%')", $sql);
        self::assertStringContainsString("AND (oxv_oxarticles_1_de.OXPARENTID = '')", $sql);
        self::assertStringEndsWith('ORDER BY oxv_oxarticles_1_de.oxsort ASC LIMIT 5', $sql);
    }

    /** @return iterable<array{string, string}> */
    public static function predicates(): iterable
    {
        yield 'beginsWith' => ['titleBeginsWith', "oxv_oxarticles_1_de.OXTITLE LIKE 'metal rod U%'"];
        yield 'short description' => ['shortDescContains', "oxv_oxarticles_1_de.OXSHORTDESC LIKE '%metal rod U%'"];
        yield 'FULLTEXT phrase' => [
            'titleMatch',
            "MATCH (oxv_oxarticles_1_de.OXTITLE) AGAINST ('\"metal rod U\"' IN BOOLEAN MODE)",
        ];
    }

    #[DataProvider('predicates')]
    public function testEachPredicateBecomesItsSql(string $predicate, string $sql): void
    {
        self::assertStringContainsString("AND ($sql)", $this->probe->statement($predicate, 'metal rod U'));
    }

    public function testEscapesQuotesAndBackslashes(): void
    {
        self::assertStringContainsString("LIKE '%it\\'s\\\\%'", $this->probe->statement('titleContains', "it's\\"));
    }

    public function testExplainsTheSameStatement(): void
    {
        self::assertSame('EXPLAIN SELECT 1', $this->probe->explainStatement('SELECT 1'));
    }

    public function testStatsAreReadFromThisSessionsStatementHistory(): void
    {
        self::assertStringContainsString('THREAD_ID = PS_CURRENT_THREAD_ID()', SqlProbe::STATS_STATEMENT);
        self::assertStringContainsString("SQL_TEXT LIKE '/* sprint17-probe */%'", SqlProbe::STATS_STATEMENT);
    }

    public function testSummarizesThePlanAsTableAccessKey(): void
    {
        $explain = [
            ['table' => 't2s', 'type' => 'ref', 'key' => 'OXMAPIDX'],
            ['table' => 'oxarticles', 'type' => 'ALL', 'key' => null],
        ];

        self::assertSame('t2s:ref:OXMAPIDX | oxarticles:ALL:-', $this->probe->plan($explain));
    }

    public function testCsvHasOneRowPerRunPredicateAndTerm(): void
    {
        self::assertSame('run,predicate,term,cold_ms,warm_ms,rows_examined,plan,failure', SqlProbe::CSV_HEADER);
        self::assertSame(
            'none,titleContains,"Zn",12000,9000.1,5000243,"oxarticles:ALL:-",',
            $this->probe->csvRow(
                'none',
                'titleContains',
                'Zn',
                new ProbeResult(12000.04, 9000.06, 5000243, 'oxarticles:ALL:-', null),
            ),
        );
    }
}
