<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Report;

use Dantweb\OxidPerformanceTests\Search\Quicksearch;

/** Raw CSV rows → chart specs and Markdown tables. Pure. */
final class ReportData
{
    /** How each run is named for readers: legend and table headers. */
    private const RUN_LABELS = ['none' => 'no index', 'btree' => 'B-tree index', 'fulltext' => 'FULLTEXT index'];

    /** The SQL predicates in words. */
    private const PREDICATE_LABELS = [
        'titleContains' => 'title contains',
        'titleBeginsWith' => 'title starts with',
        'shortDescContains' => 'short description contains',
        'titleMatch' => 'title MATCH (FULLTEXT)',
    ];

    /** @return list<array<string, string>> */
    public function parseCsv(string $text): array
    {
        $lines = explode("\n", trim($text));
        $names = str_getcsv(array_shift($lines), ',', '"', '');

        return array_map(
            static fn (string $line): array => array_combine($names, str_getcsv($line, ',', '"', '')),
            $lines,
        );
    }

    /**
     * @param list<array<string, string>> $rows
     * @param list<string> $runs
     */
    public function graphqlChart(array $rows, array $runs): ChartSpec
    {
        $label = static function (array $row): string {
            $page = (int) $row['limit'] === Quicksearch::SUGGESTION_LIMIT
                ? ''
                : ' p' . ((int) $row['offset'] / Quicksearch::PAGE_SIZE + 1);

            return "{$row['scenario']} “{$row['term']}”$page";
        };
        $bar = fn (array $row): Bar => $row['failure'] !== ''
            ? $this->missing($row)
            : new Bar($this->runLabel($row['run']), (float) $row['p50_ms'], (float) $row['p95_ms']);

        return new ChartSpec(
            'Quicksearch over GraphQL on 5 M articles — response time',
            'ms',
            'milliseconds',
            array_map($this->runLabel(...), $runs),
            $this->grouped($rows, $runs, $label, $bar),
            'bar = median (p50), line = p95 · warm requests, one shopper',
        );
    }

    /**
     * @param list<array<string, string>> $rows
     * @param list<string> $runs
     */
    public function probeChart(array $rows, array $runs, string $metric): ChartSpec
    {
        $byTime = $metric === 'warm_ms';
        $bar = fn (array $row): Bar => $row['failure'] !== ''
            ? $this->missing($row)
            : new Bar($this->runLabel($row['run']), (float) $row[$metric]);
        $label = static fn (array $row): string => (self::PREDICATE_LABELS[$row['predicate']] ?? $row['predicate'])
            . " “{$row['term']}”";

        return new ChartSpec(
            $byTime
                ? 'The quicksearch SQL with each predicate — time inside MySQL'
                : 'The quicksearch SQL with each predicate — rows examined',
            $byTime ? 'ms' : 'rows',
            $byTime ? 'milliseconds' : 'rows examined',
            array_map($this->runLabel(...), $runs),
            $this->grouped($rows, $runs, $label, $bar),
            $byTime ? 'warm, median of repeats' : 'how many rows MySQL read to answer',
        );
    }

    /** A failed measurement in words: the MySQL errors the probes meet. */
    public function missingReason(string $failure): string
    {
        if (str_contains($failure, '1191')) {
            return 'needs a FULLTEXT index';
        }
        if (str_contains($failure, 'FTS query exceeds result cache limit')) {
            return 'failed: FTS result cache limit (error 188)';
        }

        return "failed: $failure";
    }

    /** The table view of a chart: the exact numbers behind every bar. */
    public function comparisonTable(ChartSpec $chart): string
    {
        $alignments = array_map(
            static fn (string $s): string => str_repeat('-', mb_strlen($s) - 1) . ':',
            $chart->series,
        );
        $lines = [
            '| Scenario | ' . implode(' | ', $chart->series) . ' |',
            '| -------- | ' . implode(' | ', $alignments) . ' |',
        ];
        foreach ($chart->groups as $group) {
            $cells = [];
            foreach ($chart->series as $series) {
                $found = array_values(array_filter(
                    $group->bars,
                    static fn (Bar $bar): bool => $bar->series === $series,
                ));
                $cells[] = $this->cell($found[0] ?? null);
            }
            $lines[] = "| {$group->label} | " . implode(' | ', $cells) . ' |';
        }

        return implode("\n", $lines);
    }

    /** @param list<array<string, string>> $rows */
    public function indexCostTable(array $rows): string
    {
        $lines = ['| Index | Build time | Size on disk |', '| ----- | ---------: | -----------: |'];
        foreach ($rows as $row) {
            $ms = (float) $row['build_ms'];
            $seconds = $ms >= 100_000 ? (string) floor($ms / 1000 + 0.5) : (string) (floor($ms / 100 + 0.5) / 10);
            $lines[] = sprintf(
                '| %s | %s s | %s MiB |',
                $this->runLabel($row['kind']),
                $seconds,
                NumberFormat::value((float) $row['size_bytes'] / 1024 ** 2),
            );
        }

        return implode("\n", $lines);
    }

    private function runLabel(string $run): string
    {
        return self::RUN_LABELS[$run] ?? $run;
    }

    /** @param array<string, string> $row */
    private function missing(array $row): Bar
    {
        return new Bar($this->runLabel($row['run']), null, null, $this->missingReason($row['failure']));
    }

    private function cell(?Bar $bar): string
    {
        if ($bar === null) {
            return '–';
        }
        if ($bar->value === null) {
            return $bar->note === null ? 'n/a' : "n/a ({$bar->note})";
        }

        return $bar->whisker === null
            ? NumberFormat::value($bar->value)
            : sprintf('%s (p95 %s)', NumberFormat::value($bar->value), NumberFormat::value($bar->whisker));
    }

    /**
     * Groups rows by label in first-seen order; one bar per run, in the runs' fixed order.
     *
     * @param list<array<string, string>> $rows
     * @param list<string> $runs
     * @return list<BarGroup>
     */
    private function grouped(array $rows, array $runs, callable $label, callable $bar): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$label($row)][] = $row;
        }
        $result = [];
        foreach ($groups as $name => $members) {
            $bars = [];
            foreach ($runs as $run) {
                foreach ($members as $member) {
                    if ($member['run'] === $run) {
                        $bars[] = $bar($member);
                    }
                }
            }
            $result[] = new BarGroup((string) $name, $bars);
        }

        return $result;
    }
}
