<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use Dantweb\OxidPerformanceTests\Catalogue\ArticleRows;
use Dantweb\OxidPerformanceTests\Catalogue\RodMatrix;
use Dantweb\OxidPerformanceTests\Catalogue\SeededSample;
use Dantweb\OxidPerformanceTests\Database\Connection;
use Dantweb\OxidPerformanceTests\Search\CurlTransport;
use Dantweb\OxidPerformanceTests\Search\Quicksearch;
use Dantweb\OxidPerformanceTests\Search\Scenario;
use PHPUnit\Framework\TestCase;

/** example_perf holds exactly the seeded 5 M sample (Sprint 17, S3). */
final class LoadedCatalogueTest extends TestCase
{
    private const SEED = 20261020;
    private const COUNT = 5_000_000;

    public function testHoldsExactlyTheFiveMillionSampledRods(): void
    {
        PerformanceEnvironment::graphqlUrl($this);
        $count = Connection::create()
            ->query("SELECT COUNT(*) FROM oxarticles WHERE OXARTNUM LIKE 'ROD-%'")
            ->fetchColumn();

        self::assertSame(self::COUNT, (int) $count);
    }

    public function testQuicksearchFindsASampledRodAndNotOneTheSampleLeftOut(): void
    {
        $url = PerformanceEnvironment::graphqlUrl($this);
        $picked = (new SeededSample())->pick(self::SEED, self::COUNT);
        $rows = new ArticleRows(new RodMatrix());
        $inShop = array_flip($picked);
        $missing = 0;
        while (isset($inShop[$missing])) {
            $missing++;
        }

        $sampled = (string) $rows->articleRow($picked[0])['OXTITLE'];
        self::assertSame([$sampled], $this->titles($url, $sampled));
        self::assertSame([], $this->titles($url, (string) $rows->articleRow($missing)['OXTITLE']));
    }

    /** @return list<string> */
    private function titles(string $url, string $term): array
    {
        $answer = (new CurlTransport($url))((new Quicksearch())->request(new Scenario('check', $term, null)));

        return array_column($answer['json']['data']['products'] ?? [], 'title');
    }
}
