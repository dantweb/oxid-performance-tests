<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use Dantweb\OxidPerformanceTests\Database\PerfDatabase;
use Dantweb\OxidPerformanceTests\Search\CurlTransport;
use Dantweb\OxidPerformanceTests\Search\Quicksearch;
use Dantweb\OxidPerformanceTests\Search\Scenario;
use PHPUnit\Framework\TestCase;

/** The perf entry point serves example_perf; the demo shop does not see it (Sprint 17, S1). */
final class PerfEnvironmentTest extends TestCase
{
    public function testPerfEndpointAnswersTheQuicksearchQuery(): void
    {
        self::assertNotEmpty($this->titles(PerformanceEnvironment::graphqlUrl($this), 'a'));
    }

    public function testPerfEndpointReadsExamplePerfNotExample(): void
    {
        self::assertSame(
            [PerfDatabase::MARKER_TITLE],
            $this->titles(PerformanceEnvironment::graphqlUrl($this), PerfDatabase::MARKER_TITLE),
        );
    }

    public function testDemoEndpointDoesNotSeeThePerfMarker(): void
    {
        PerformanceEnvironment::graphqlUrl($this);

        self::assertSame([], $this->titles(PerformanceEnvironment::demoGraphqlUrl(), PerfDatabase::MARKER_TITLE));
    }

    /** @return list<string> */
    private function titles(string $url, string $term): array
    {
        $answer = (new CurlTransport($url))((new Quicksearch())->request(new Scenario('check', $term, null)));
        self::assertSame(200, $answer['status']);

        return array_column($answer['json']['data']['products'], 'title');
    }
}
