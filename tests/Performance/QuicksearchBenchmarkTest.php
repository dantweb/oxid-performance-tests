<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use Dantweb\OxidPerformanceTests\Search\CurlTransport;
use Dantweb\OxidPerformanceTests\Search\ResultCsv;
use Dantweb\OxidPerformanceTests\Search\Scenario;
use Dantweb\OxidPerformanceTests\Search\Scenarios;
use Dantweb\OxidPerformanceTests\Search\SearchBenchmark;
use Dantweb\OxidPerformanceTests\Search\Statistics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * The quicksearch over GraphQL, one test per scenario: 3 warm-up requests (the first reported as cold), then up to
 * 30 timed ones within a 60 s budget (minimum 5). Each result is appended to raw/<run>.csv.
 */
final class QuicksearchBenchmarkTest extends TestCase
{
    private const WARMUPS = 3;
    private const SAMPLES = 30;
    private const BUDGET_MS = 60_000.0;
    private const MIN_SAMPLES = 5;

    public function testStartsTheRunsCsv(): string
    {
        $file = PerformanceEnvironment::rawDir() . '/' . PerformanceEnvironment::run($this) . '.csv';
        file_put_contents($file, ResultCsv::HEADER . "\n");

        self::assertFileExists($file);

        return $file;
    }

    /** @return iterable<array{Scenario}> */
    public static function scenarios(): iterable
    {
        foreach (Scenarios::quicksearch() as $scenario) {
            $page = $scenario->page === null ? '' : " p{$scenario->page}";
            yield "{$scenario->name} \"{$scenario->term}\"" . $page => [$scenario];
        }
    }

    #[Depends('testStartsTheRunsCsv')]
    #[DataProvider('scenarios')]
    public function testMeasuresTheQuicksearch(Scenario $scenario, string $file): void
    {
        $benchmark = new SearchBenchmark(
            (new CurlTransport(PerformanceEnvironment::graphqlUrl($this)))(...),
            static fn (): float => hrtime(true) / 1e6,
        );
        $result = $benchmark->measure($scenario, self::WARMUPS, self::SAMPLES, self::BUDGET_MS, self::MIN_SAMPLES);
        $row = (new ResultCsv(new Statistics()))->row(PerformanceEnvironment::run($this), $result);
        file_put_contents($file, $row . "\n", FILE_APPEND);

        self::assertNull($result->failure);
        self::assertGreaterThanOrEqual(self::MIN_SAMPLES, count($result->samplesMs));
    }
}
