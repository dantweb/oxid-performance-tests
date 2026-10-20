<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use Dantweb\OxidPerformanceTests\Catalogue\ChunkWriter;
use PHPUnit\Framework\TestCase;

/**
 * The full 5 M sample, generated in PHP, equals the TypeScript generator's chunk byte for byte — the rows loaded
 * into example_perf by Sprint 17 are the PHP suite's rows too. Heavy (~1 min, ~0.5 GB RAM): Performance suite.
 */
final class CatalogueGoldenTest extends TestCase
{
    /** SHA-256 of oxid-fe-user test-results/performance/rods/rods-00.tsv (seed 20261020, 5 M, 500 000 per chunk). */
    private const FIRST_CHUNK_SHA256 = '408543d268094b8ceae6f9e0162128c90c826fcb94c5ee84536baa7e7406d1ac';

    public function testFirstChunkOfTheFiveMillionSampleMatchesTheTypeScriptGenerator(): void
    {
        $dir = sys_get_temp_dir() . '/golden-' . uniqid();
        $files = ChunkWriter::create()->write($dir, 20261020, 5_000_000, 500_000);

        $sha = hash_file('sha256', $files[0]);
        array_map('unlink', $files);
        rmdir($dir);

        self::assertCount(10, $files);
        self::assertSame(self::FIRST_CHUNK_SHA256, $sha);
    }
}
