<?php

/**
 * Adds or drops the indexes under test and records their build time and size (run in the PHP container).
 *   php extensions/oxid-performance-tests/bin/migrate.php up|down btree|fulltext [--csv FILE]
 */

declare(strict_types=1);

use Dantweb\OxidPerformanceTests\Database\Connection;
use Dantweb\OxidPerformanceTests\Database\IndexMigrations;

require __DIR__ . '/../tests/bootstrap.php';

[$direction, $kind] = [$argv[1] ?? '', $argv[2] ?? ''];
if (!in_array($direction, ['up', 'down'], true) || !in_array($kind, IndexMigrations::KINDS, true)) {
    fwrite(STDERR, "usage: migrate.php up|down btree|fulltext [--csv FILE]\n");
    exit(1);
}
// getopt() stops at the first positional argument, so `up btree --csv FILE` is read by hand.
$csvAt = array_search('--csv', $argv, true);
$csv = $csvAt === false ? __DIR__ . '/../test-results/performance/raw/indexes.csv' : $argv[$csvAt + 1];
$migrations = new IndexMigrations();
$pdo = Connection::create();

$start = hrtime(true);
foreach ($direction === 'up' ? $migrations->up($kind) : $migrations->down($kind) as $statement) {
    $pdo->exec($statement);
}
$ms = intdiv(hrtime(true) - $start, 1_000_000);
echo "$direction $kind: $ms ms", PHP_EOL;

if ($direction === 'up') {
    $pdo->query('ANALYZE TABLE oxarticles')->fetchAll();
    $bytes = (int) $pdo->query($migrations->sizeQuery($kind))->fetchColumn();
    if (!is_file($csv)) {
        file_put_contents($csv, "kind,build_ms,size_bytes\n");
    }
    file_put_contents($csv, "$kind,$ms,$bytes\n", FILE_APPEND);
    printf("%s size: %d MiB\n", $kind, intdiv($bytes, 1024 ** 2));
}
