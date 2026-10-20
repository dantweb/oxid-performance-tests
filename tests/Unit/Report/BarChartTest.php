<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Report;

use Dantweb\OxidPerformanceTests\Report\Bar;
use Dantweb\OxidPerformanceTests\Report\BarChart;
use Dantweb\OxidPerformanceTests\Report\BarGroup;
use Dantweb\OxidPerformanceTests\Report\ChartSpec;
use PHPUnit\Framework\TestCase;

/** Port of oxid-fe-user tests/unit/perf/reportCharts.spec.ts. */
final class BarChartTest extends TestCase
{
    private const RUNS = ['none', 'btree', 'fulltext'];

    private string $linear;

    protected function setUp(): void
    {
        $this->linear = (new BarChart())->render($this->spec([[10, 20, 40], [5, 5, 5]]));
    }

    public function testDrawsOneGroupPerScenarioAndOneBarPerRun(): void
    {
        self::assertSame(2, substr_count($this->linear, 'data-group='));
        self::assertSame([10.0, 20.0, 40.0, 5.0, 5.0, 5.0], $this->attribute($this->linear, 'data-value'));
    }

    public function testBarLengthsAreProportionalOnALinearAxis(): void
    {
        [$ten, $twenty, $forty] = $this->attribute($this->linear, 'data-length');

        self::assertEqualsWithDelta(2.0, $twenty / $ten, 0.05);
        self::assertEqualsWithDelta(4.0, $forty / $ten, 0.05);
    }

    public function testDrawsTheP95WhiskerBeyondEachBar(): void
    {
        self::assertSame(6, substr_count($this->linear, 'data-whisker='));
    }

    public function testSwitchesToALogAxisWhenValuesSpanMoreThan100Times(): void
    {
        $wide = (new BarChart())->render($this->spec([[1, 100, 5000]]));
        [$one, $hundred, $fiveThousand] = $this->attribute($wide, 'data-length');

        self::assertStringContainsString('(log scale)', $wide);
        self::assertEqualsWithDelta(($fiveThousand - $one) * (2 / log10(5000)), $hundred - $one, 0.5);
        self::assertStringNotContainsString('(log scale)', $this->linear);
    }

    public function testLabelsEveryBarWithItsValueAndUnitAsTextAndAsTitle(): void
    {
        self::assertStringContainsString('<title>scenario 0 · btree: 20 ms (p95 40 ms)</title>', $this->linear);
        self::assertMatchesRegularExpression('/<text[^>]*data-label[^>]*>20 ms<\/text>/', $this->linear);
    }

    public function testNamesTheAxisWithItsUnit(): void
    {
        self::assertMatchesRegularExpression('/<text[^>]*data-axis-title[^>]*>milliseconds<\/text>/', $this->linear);
    }

    public function testGroupsThousandsInLabels(): void
    {
        $svg = (new BarChart())->render(new ChartSpec(
            'SQL',
            'rows',
            'rows examined',
            ['none'],
            [new BarGroup('g', [new Bar('none', 5000000.0)])],
        ));

        self::assertStringContainsString('>5 000 000 rows</text>', $svg);
    }

    public function testSaysWhyAValueIsMissing(): void
    {
        $svg = (new BarChart())->render(new ChartSpec('SQL', 'ms', 'milliseconds', ['none'], [
            new BarGroup('g', [new Bar('none', null, null, 'needs a FULLTEXT index')]),
        ]));

        self::assertMatchesRegularExpression('/<text[^>]*data-label[^>]*>n\/a – needs a FULLTEXT index<\/text>/', $svg);
    }

    public function testHasALegendInTheFixedCategoricalColours(): void
    {
        self::assertSame(['#2a78d6', '#eb6834', '#1baf7a'], BarChart::SERIES_COLORS);
        foreach (self::RUNS as $i => $run) {
            self::assertStringContainsString('fill="' . BarChart::SERIES_COLORS[$i] . '"', $this->linear);
            self::assertMatchesRegularExpression("/<text[^>]*data-legend[^>]*>$run<\\/text>/", $this->linear);
        }
    }

    public function testPaintsItsOwnLightSurface(): void
    {
        self::assertMatchesRegularExpression('/<rect width="100%" height="100%" fill="#fcfcfb"/', $this->linear);
    }

    public function testIsXmlEveryAttributeHasAValue(): void
    {
        preg_match_all('/<[a-z][^>]*>/', $this->linear, $tags);
        $bare = array_filter(
            array_map(
                static fn (string $tag): string => (string) preg_replace('/ [a-zA-Z0-9:-]+="[^"]*"/', '', $tag),
                $tags[0],
            ),
            static fn (string $tag): bool => preg_match('/^<[a-z]+\/?>$/', $tag) !== 1,
        );

        self::assertSame([], array_values($bare));
    }

    public function testEscapesXmlInLabels(): void
    {
        $svg = (new BarChart())->render(new ChartSpec(
            'a < b & "c"',
            'ms',
            'milliseconds',
            ['none'],
            [new BarGroup('<x>', [new Bar('none', 1.0)])],
        ));

        self::assertStringContainsString('a &lt; b &amp; &quot;c&quot;', $svg);
        self::assertStringNotContainsString('<x>', $svg);
    }

    /** @param list<list<int>> $values */
    private function spec(array $values): ChartSpec
    {
        $groups = [];
        foreach ($values as $i => $row) {
            $bars = [];
            foreach ($row as $j => $value) {
                $bars[] = new Bar(self::RUNS[$j], (float) $value, (float) $value * 2);
            }
            $groups[] = new BarGroup("scenario $i", $bars);
        }

        return new ChartSpec('GraphQL p50', 'ms', 'milliseconds', self::RUNS, $groups);
    }

    /** @return list<float> */
    private function attribute(string $svg, string $name): array
    {
        preg_match_all("/<path [^>]*data-bar[^>]*\\b$name=\"([\\d.]+)\"/", $svg, $matches);

        return array_map('floatval', $matches[1]);
    }
}
