<?php

/**
 * Turns the raw CSVs of the runs into the report's charts and tables (run in the PHP container).
 *   php extensions/oxid-performance-tests/bin/build-report.php \
 *       [--raw DIR] [--dir REPORT_DIR] [--runs none,btree,fulltext]
 * Charts go next to the report; tables.md (to paste into it) stays with the working data.
 */

declare(strict_types=1);

use Dantweb\OxidPerformanceTests\Report\BarChart;
use Dantweb\OxidPerformanceTests\Report\ReportData;

require __DIR__ . '/../tests/bootstrap.php';

$options = getopt('', ['raw:', 'dir:', 'runs:']) + [
    'raw' => __DIR__ . '/../test-results/performance/raw',
    'dir' => __DIR__ . '/../docs/20261020/reports/search-index-perf',
    'runs' => 'none,btree,fulltext',
];
$runs = explode(',', $options['runs']);
$data = new ReportData();
$rowsOf = static function (string $suffix) use ($options, $runs, $data): array {
    $rows = [];
    foreach ($runs as $run) {
        $file = "{$options['raw']}/$run$suffix.csv";
        if (is_file($file)) {
            $rows = [...$rows, ...$data->parseCsv((string) file_get_contents($file))];
        }
    }

    return $rows;
};

$charts = [
    'graphql-p50-p95' => $data->graphqlChart($rowsOf(''), $runs),
    'sql-probes-time' => $data->probeChart($rowsOf('-probes'), $runs, 'warm_ms'),
    'sql-probes-rows' => $data->probeChart($rowsOf('-probes'), $runs, 'rows_examined'),
];
@mkdir("{$options['dir']}/charts", 0775, true);
$tables = [];
foreach ($charts as $name => $chart) {
    file_put_contents("{$options['dir']}/charts/$name.svg", (new BarChart())->render($chart));
    $tables[] = "### {$chart->title} ({$chart->axisLabel})\n\n" . $data->comparisonTable($chart) . "\n";
}
if (is_file("{$options['raw']}/indexes.csv")) {
    $indexRows = $data->parseCsv((string) file_get_contents("{$options['raw']}/indexes.csv"));
    $tables[] = "### Index cost\n\n" . $data->indexCostTable($indexRows) . "\n";
}
file_put_contents("{$options['raw']}/tables.md", implode("\n", $tables));
echo "charts: {$options['dir']}/charts, tables: {$options['raw']}/tables.md", PHP_EOL;
