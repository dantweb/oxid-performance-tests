<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Unit\Catalogue;

use Dantweb\OxidPerformanceTests\Catalogue\ArticleRows;
use Dantweb\OxidPerformanceTests\Catalogue\ChunkWriter;
use Dantweb\OxidPerformanceTests\Catalogue\PrimaryKeyOrder;
use Dantweb\OxidPerformanceTests\Catalogue\RodMatrix;
use Dantweb\OxidPerformanceTests\Catalogue\SeededSample;
use PHPUnit\Framework\TestCase;

final class ChunkWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/chunk-writer-' . uniqid();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function testWritesTheSampleInPrimaryKeyOrderAsNumberedChunks(): void
    {
        $files = ChunkWriter::create()->write($this->dir, 20261020, 25, 10);

        self::assertSame(['rods-00.tsv', 'rods-01.tsv', 'rods-02.tsv'], array_map('basename', $files));
        $lines = array_merge(...array_map(
            static fn (string $file): array => file($file, FILE_IGNORE_NEW_LINES) ?: [],
            $files,
        ));
        self::assertCount(25, $lines);
        $order = new PrimaryKeyOrder(new ArticleRows(new RodMatrix()));
        self::assertTrue($order->isInAppendOrder(
            array_map(static fn (string $line): string => substr($line, 0, 32), $lines),
        ));
    }

    public function testSameSeedWritesIdenticalFiles(): void
    {
        $first = ChunkWriter::create()->write($this->dir . '/a', 3, 12, 5);
        $second = ChunkWriter::create()->write($this->dir . '/b', 3, 12, 5);

        self::assertSame(array_map('md5_file', $first), array_map('md5_file', $second));
        array_map('unlink', [...$first, ...$second]);
        rmdir($this->dir . '/a');
        rmdir($this->dir . '/b');
    }

    public function testWriterIsBuiltFromTheCatalogueParts(): void
    {
        $matrix = new RodMatrix();
        $rows = new ArticleRows($matrix);

        self::assertEquals(
            new ChunkWriter(new SeededSample(), new PrimaryKeyOrder($rows), $rows),
            ChunkWriter::create(),
        );
    }
}
