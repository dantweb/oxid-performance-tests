<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Catalogue;

use RuntimeException;

/** Writes the seeded rod sample, in primary-key order, as numbered LOAD DATA chunk files. */
final class ChunkWriter
{
    public function __construct(
        private readonly SeededSample $sample,
        private readonly PrimaryKeyOrder $order,
        private readonly ArticleRows $rows,
    ) {
    }

    public static function create(): self
    {
        $rows = new ArticleRows(new RodMatrix());

        return new self(new SeededSample(), new PrimaryKeyOrder($rows), $rows);
    }

    /** @return list<string> the written files, in order */
    public function write(string $dir, int $seed, int $count, int $chunkSize): array
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new RuntimeException("cannot create $dir");
        }
        $files = [];
        $chunks = array_chunk($this->order->order($this->sample->pick($seed, $count)), $chunkSize);
        foreach ($chunks as $number => $indexes) {
            $file = sprintf('%s/rods-%02d.tsv', $dir, $number);
            $lines = array_map(
                fn (int $index): string => $this->rows->tsvLine($this->rows->articleRow($index)),
                $indexes,
            );
            file_put_contents($file, implode("\n", $lines) . "\n");
            $files[] = $file;
        }

        return $files;
    }
}
