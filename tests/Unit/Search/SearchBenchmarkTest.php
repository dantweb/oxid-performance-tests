<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Search;

use Dantweb\OxidPerformanceTests\Search\Quicksearch;
use Dantweb\OxidPerformanceTests\Search\ResultCsv;
use Dantweb\OxidPerformanceTests\Search\Scenario;
use Dantweb\OxidPerformanceTests\Search\SearchBenchmark;
use Dantweb\OxidPerformanceTests\Search\Statistics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Port of oxid-fe-user tests/unit/perf/searchBenchmark.spec.ts. */
final class SearchBenchmarkTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $sent = [];
    private float $now = 0.0;

    public function testQueryIsTheStorefrontsSearchQueryWordForWord(): void
    {
        self::assertSame(
            "query search(\$q: String!, \$limit: Int!, \$offset: Int!) {\n"
                . '  products(filter: { title: { contains: $q } }, pagination: { offset: $offset, limit: $limit }) '
                . "{ id title shortDescription price { price currency { name sign } } imageGallery { thumb } }\n}",
            Quicksearch::QUERY,
        );
    }

    public function testSuggestionRequestUsesTheSuggestionLimit(): void
    {
        self::assertSame(
            ['query' => Quicksearch::QUERY, 'variables' => ['q' => 'U-shape AlTi', 'limit' => 5, 'offset' => 0]],
            (new Quicksearch())->request(new Scenario('shape', 'U-shape AlTi', null)),
        );
    }

    public function testResultsPageAsksPageSizePlusOneAtItsOffset(): void
    {
        $request = (new Quicksearch())->request(new Scenario('page-last', 'L-shape', Quicksearch::MAX_PAGES - 1));

        self::assertSame(['q' => 'L-shape', 'limit' => 13, 'offset' => 228], $request['variables']);
    }

    public function testTermIsNormalizedLikeTheStorefrontDoes(): void
    {
        self::assertSame('metal rod', (new Quicksearch())->normalizeTerm('  %metal   rod% '));
    }

    /** @return iterable<array{list<float>, float, float}> */
    public static function percentiles(): iterable
    {
        yield 'single' => [[5.0], 50.0, 5.0];
        yield 'odd median' => [[1.0, 2.0, 3.0], 50.0, 2.0];
        yield 'even median' => [[1.0, 2.0, 3.0, 4.0], 50.0, 2.5];
        yield 'p95 interpolated' => [[10.0, 20.0, 30.0, 40.0, 50.0], 95.0, 48.0];
        yield 'max, unsorted' => [[4.0, 1.0, 3.0, 2.0], 100.0, 4.0];
        yield 'min, unsorted' => [[4.0, 1.0, 3.0, 2.0], 0.0, 1.0];
    }

    /** @param list<float> $samples */
    #[DataProvider('percentiles')]
    public function testPercentileInterpolatesLinearly(array $samples, float $p, float $expected): void
    {
        self::assertEqualsWithDelta($expected, (new Statistics())->percentile($samples, $p), 1e-9);
    }

    public function testFirstRequestIsColdAndOnlyRequestsAfterTheWarmUpAreTimed(): void
    {
        $result = $this->benchmark([900, 50, 50, 10, 20, 30])->measure($this->suggest(), 3, 3);

        self::assertCount(6, $this->sent);
        self::assertSame(900.0, $result->coldMs);
        self::assertSame([10.0, 20.0, 30.0], $result->samplesMs);
        self::assertSame(2, $result->rowsReturned);
        self::assertNull($result->failure);
    }

    public function testStopsWhenTheBudgetIsSpentButKeepsTheMinimum(): void
    {
        $result = $this->benchmark([100, 100, 100, 1000])->measure($this->suggest(), 1, 30, 2500.0, 3);

        self::assertSame([100.0, 100.0, 1000.0, 1000.0], $result->samplesMs);
    }

    public function testGraphqlErrorIsAFailureNotATiming(): void
    {
        $benchmark = new SearchBenchmark(
            static fn (array $body): array => ['status' => 200, 'json' => ['errors' => [['message' => 'boom']]]],
            static fn (): float => 0.0,
        );
        $result = $benchmark->measure($this->suggest(), 1, 3);

        self::assertSame('boom', $result->failure);
        self::assertSame([], $result->samplesMs);
    }

    public function testNon200IsAFailure(): void
    {
        $benchmark = new SearchBenchmark(
            static fn (array $body): array => ['status' => 502, 'json' => null],
            static fn (): float => 0.0,
        );

        self::assertSame('HTTP 502', $benchmark->measure($this->suggest(), 1, 3)->failure);
    }

    public function testCsvRowHasQuotedTermAndRoundedTimings(): void
    {
        $result = $this->benchmark([900, 10, 20, 30])->measure($this->suggest(), 1, 3);

        self::assertSame(
            'run,scenario,term,limit,offset,n,cold_ms,p50_ms,p95_ms,p99_ms,min_ms,max_ms,rows_returned,failure',
            ResultCsv::HEADER,
        );
        self::assertSame(
            'btree,shape,"U-shape AlTi",5,0,3,900,20,29,29.8,10,30,2,',
            (new ResultCsv(new Statistics()))->row('btree', $result),
        );
    }

    private function suggest(): Scenario
    {
        return new Scenario('shape', 'U-shape AlTi', null);
    }

    /**
     * A clock that advances by the given steps, one per request.
     *
     * @param list<int> $stepsMs
     */
    private function benchmark(array $stepsMs): SearchBenchmark
    {
        $call = 0;

        return new SearchBenchmark(
            function (array $body) use ($stepsMs, &$call): array {
                $this->sent[] = $body;
                $this->now += $stepsMs[min($call, count($stepsMs) - 1)];
                $call++;

                return ['status' => 200, 'json' => ['data' => ['products' => [['id' => 'a'], ['id' => 'b']]]]];
            },
            fn (): float => $this->now,
        );
    }
}
