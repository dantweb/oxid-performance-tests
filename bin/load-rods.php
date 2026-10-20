<?php

/**
 * Loads the chunk files of generate-tsv.php into the perf database and maps them to shop 1 (run in the PHP container).
 *   php extensions/oxid-performance-tests/bin/load-rods.php [--tsv-dir DIR] [--database example_perf] [--log FILE]
 */

declare(strict_types=1);

use Dantweb\OxidPerformanceTests\Catalogue\ArticleRows;
use Dantweb\OxidPerformanceTests\Catalogue\PrimaryKeyOrder;
use Dantweb\OxidPerformanceTests\Catalogue\RodMatrix;
use Dantweb\OxidPerformanceTests\Database\Connection;
use Dantweb\OxidPerformanceTests\Database\LoadSql;
use Dantweb\OxidPerformanceTests\Database\PerfDatabase;
use Dantweb\OxidPerformanceTests\Database\RodLoader;

require __DIR__ . '/../tests/bootstrap.php';

$results = __DIR__ . '/../test-results/performance';
$options = getopt('', ['tsv-dir:', 'database:', 'log:']) + [
    'tsv-dir' => "$results/rods",
    'database' => PerfDatabase::NAME,
    'log' => "$results/load.log",
];
$log = static function (string $line) use ($options): void {
    echo $line, PHP_EOL;
    file_put_contents($options['log'], $line . PHP_EOL, FILE_APPEND);
};
$files = glob($options['tsv-dir'] . '/*.tsv') ?: [];
sort($files);
$pdo = Connection::create($options['database']);
(new RodLoader(
    $pdo,
    new LoadSql($options['database']),
    new PrimaryKeyOrder(new ArticleRows(new RodMatrix())),
    $options['database'],
    $log(...),
))
    ->load($files);
$rods = $pdo->query("SELECT COUNT(*) FROM oxarticles WHERE OXARTNUM LIKE 'ROD-%'")->fetchColumn();
$log("rods=$rods");
