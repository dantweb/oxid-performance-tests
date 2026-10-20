<?php

/**
 * Writes the seeded rod sample as LOAD DATA chunk files (run in the shop's PHP container).
 *   php extensions/oxid-performance-tests/bin/generate-tsv.php \
 *       [--out DIR] [--seed 20261020] [--count 5000000] [--chunk 500000]
 */

declare(strict_types=1);

use Dantweb\OxidPerformanceTests\Catalogue\ChunkWriter;

require __DIR__ . '/../tests/bootstrap.php';

$options = getopt('', ['out:', 'seed:', 'count:', 'chunk:']) + [
    'out' => __DIR__ . '/../test-results/performance/rods',
    'seed' => '20261020',
    'count' => '5000000',
    'chunk' => '500000',
];
$files = ChunkWriter::create()->write(
    $options['out'],
    (int) $options['seed'],
    (int) $options['count'],
    (int) $options['chunk'],
);
foreach ($files as $file) {
    echo $file, PHP_EOL;
}
