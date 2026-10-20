<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Database;

use Dantweb\OxidPerformanceTests\Catalogue\ArticleRows;

/** The statements that put the rod sample into the perf database. Pure strings. */
final class LoadSql
{
    /**
     * The SDK's MySQL defaults (100 MB redo log, IO capacity 200, 128 MB buffer pool, no LOCAL INFILE) make a
     * 5 M row load take hours or impossible. All are dynamic: raised for the load, restored before anything is
     * measured.
     */
    public const BULK_LOAD_SETTINGS = [
        'innodb_redo_log_capacity' => 2 * 1024 ** 3,
        'innodb_io_capacity' => 2000,
        'innodb_buffer_pool_size' => 1024 ** 3,
        'local_infile' => 1,
    ];

    private const ROD_ARTICLES = "OXARTNUM LIKE 'ROD-%'";

    private readonly string $articles;
    private readonly string $articlesToShop;

    public function __construct(string $database = PerfDatabase::NAME)
    {
        $this->articles = $database . '.oxarticles';
        $this->articlesToShop = $database . '.oxarticles2shop';
    }

    /**
     * Generated rows are reproducible from the seed, so they stay out of the binary log.
     *
     * @return list<string>
     */
    public function sessionPrelude(): array
    {
        return [
            PerfDatabase::RELAXED_MODE,
            'SET SESSION sql_log_bin = 0',
            'SET SESSION unique_checks = 0',
            'SET SESSION foreign_key_checks = 0',
        ];
    }

    /**
     * @param array<string, int> $settings
     * @return list<string>
     */
    public function setGlobalStatements(array $settings): array
    {
        return array_map(
            static fn (string $name, int $value): string => "SET GLOBAL $name = $value",
            array_keys($settings),
            $settings,
        );
    }

    /**
     * Mapping rows first: they are found through the articles about to be deleted.
     *
     * @return array{string, string}
     */
    public function resetStatements(): array
    {
        return [
            "DELETE t2s FROM {$this->articlesToShop} t2s JOIN {$this->articles} a "
                . 'ON a.OXMAPID = t2s.OXMAPOBJECTID WHERE a.'
                . self::ROD_ARTICLES,
            "DELETE FROM {$this->articles} WHERE " . self::ROD_ARTICLES,
        ];
    }

    public function loadStatement(string $file): string
    {
        return sprintf(
            "LOAD DATA LOCAL INFILE '%s' INTO TABLE %s (%s)",
            $file,
            $this->articles,
            implode(', ', ArticleRows::COLUMNS),
        );
    }

    /** The shop views (oxv_oxarticles_1_*) join oxarticles2shop on OXMAPID: unmapped articles are invisible. */
    public function mapToShopStatement(): string
    {
        return "INSERT INTO {$this->articlesToShop} (OXSHOPID, OXMAPOBJECTID) "
            . "SELECT 1, OXMAPID FROM {$this->articles} WHERE "
            . self::ROD_ARTICLES;
    }

    public function analyzeStatement(): string
    {
        return "ANALYZE TABLE {$this->articles}, {$this->articlesToShop}";
    }

    /**
     * Inserting 5 M rows while 16 secondary indexes are maintained runs at ~1 000 rows/s once the table outgrows the
     * buffer pool; without them it appends, and InnoDB then builds each index by sorting.
     */
    public function statisticsQuery(): string
    {
        return "SHOW INDEX FROM {$this->articles}";
    }

    /**
     * Drop and re-add the secondary indexes from their own SHOW INDEX rows, in data-dictionary order. An index led by
     * the AUTO_INCREMENT column stays: MySQL refuses to drop it (and it is appended in order anyway).
     *
     * @param list<array<string, int|string|null>> $showIndex
     * @return array{drop: string, add: string}
     */
    public function secondaryIndexStatements(array $showIndex, string $autoIncrementColumn = ''): array
    {
        $indexes = $this->groupIndexes($showIndex, $autoIncrementColumn);
        $drop = array_map(static fn (string $name): string => "DROP INDEX `$name`", array_keys($indexes));
        $add = array_map($this->addClause(...), array_keys($indexes), $indexes);

        return [
            'drop' => "ALTER TABLE {$this->articles} " . implode(', ', $drop),
            'add' => "ALTER TABLE {$this->articles} " . implode(', ', $add),
        ];
    }

    /** Same columns and indexes; ignored: the AUTO_INCREMENT counter and the order of KEY lines. */
    public function sameTableDefinition(string $before, string $after): bool
    {
        return $this->normalized($before) === $this->normalized($after);
    }

    /**
     * @param list<array<string, int|string|null>> $showIndex
     * @return array<string, array{kind: string, columns: array<int, string>}>
     */
    private function groupIndexes(array $showIndex, string $autoIncrementColumn): array
    {
        $indexes = [];
        foreach ($showIndex as $row) {
            $name = (string) $row['Key_name'];
            $seq = (int) $row['Seq_in_index'];
            if ($name === 'PRIMARY' || ($seq === 1 && $row['Column_name'] === $autoIncrementColumn)) {
                continue;
            }
            $indexes[$name] ??= ['kind' => $this->kind($row), 'columns' => []];
            $prefix = $row['Sub_part'] === null ? '' : '(' . $row['Sub_part'] . ')';
            $indexes[$name]['columns'][$seq] = '`' . $row['Column_name'] . '`' . $prefix;
        }

        return $indexes;
    }

    /** @param array<string, int|string|null> $row */
    private function kind(array $row): string
    {
        if ($row['Index_type'] === 'FULLTEXT') {
            return 'FULLTEXT INDEX';
        }

        return (int) $row['Non_unique'] === 0 ? 'UNIQUE INDEX' : 'INDEX';
    }

    /** @param array{kind: string, columns: array<int, string>} $index */
    private function addClause(string $name, array $index): string
    {
        $columns = $index['columns'];
        ksort($columns);

        return sprintf('ADD %s `%s` (%s)', $index['kind'], $name, implode(', ', $columns));
    }

    private function normalized(string $definition): string
    {
        $lines = array_map(
            static fn (string $line): string => rtrim($line, ','),
            explode("\n", (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', $definition)),
        );
        sort($lines);

        return implode("\n", $lines);
    }
}
