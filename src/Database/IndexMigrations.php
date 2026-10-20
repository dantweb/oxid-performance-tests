<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Database;

/** The indexes under test, added and removed on the perf database only. Statements are for one session. */
final class IndexMigrations
{
    public const KINDS = ['btree', 'fulltext'];

    private const INDEXES = [
        'btree' => [
            ['name' => 'OXTITLE_PERF', 'column' => 'OXTITLE', 'type' => 'BTREE'],
            ['name' => 'OXSHORTDESC_PERF', 'column' => 'OXSHORTDESC', 'type' => 'BTREE'],
        ],
        'fulltext' => [
            ['name' => 'OXTITLE_FT', 'column' => 'OXTITLE', 'type' => 'FULLTEXT'],
            ['name' => 'OXSHORTDESC_FT', 'column' => 'OXSHORTDESC', 'type' => 'FULLTEXT'],
        ],
    ];

    private readonly string $articles;

    public function __construct(private readonly string $database = PerfDatabase::NAME)
    {
        $this->articles = $database . '.oxarticles';
    }

    /**
     * The indexes a run measures; `none` has none.
     *
     * @return list<array{name: string, column: string, type: string}>
     */
    public function expectedIndexes(string $run): array
    {
        return self::INDEXES[$run] ?? [];
    }

    /**
     * InnoDB builds one FULLTEXT index per statement; both B-tree indexes are added online in one.
     *
     * @return list<string>
     */
    public function up(string $kind): array
    {
        if ($kind === 'fulltext') {
            return [
                PerfDatabase::RELAXED_MODE,
                ...array_map(
                    fn (array $index): string => "ALTER TABLE {$this->articles} "
                        . "ADD FULLTEXT INDEX {$index['name']} ({$index['column']})",
                    self::INDEXES['fulltext'],
                ),
            ];
        }
        $added = array_map(
            static fn (array $index): string => "ADD INDEX {$index['name']} ({$index['column']})",
            self::INDEXES['btree'],
        );

        return [
            PerfDatabase::RELAXED_MODE,
            "ALTER TABLE {$this->articles} " . implode(', ', $added) . ', ALGORITHM=INPLACE, LOCK=NONE',
        ];
    }

    /** @return list<string> */
    public function down(string $kind): array
    {
        $dropped = array_map(
            static fn (array $index): string => 'DROP INDEX ' . $index['name'],
            $this->expectedIndexes($kind),
        );

        return [PerfDatabase::RELAXED_MODE, "ALTER TABLE {$this->articles} " . implode(', ', $dropped)];
    }

    public function stateQuery(): string
    {
        $names = [];
        foreach (self::INDEXES as $indexes) {
            foreach ($indexes as $index) {
                $names[] = "'{$index['name']}'";
            }
        }

        return 'SELECT DISTINCT INDEX_NAME AS name, COLUMN_NAME AS `column`, INDEX_TYPE AS type '
            . 'FROM information_schema.STATISTICS '
            . "WHERE TABLE_SCHEMA = '{$this->database}' AND TABLE_NAME = 'oxarticles' "
            . 'AND INDEX_NAME IN (' . implode(', ', $names) . ')';
    }

    /** Bytes on disk. A FULLTEXT index lives in its own fts_* tablespaces, not in innodb_index_stats. */
    public function sizeQuery(string $kind): string
    {
        if ($kind === 'fulltext') {
            return 'SELECT SUM(FILE_SIZE) FROM information_schema.INNODB_TABLESPACES '
                . "WHERE NAME LIKE '{$this->database}/fts\\_%'";
        }
        $names = implode(', ', array_map(
            static fn (array $index): string => "'{$index['name']}'",
            self::INDEXES['btree'],
        ));

        return 'SELECT SUM(stat_value) * @@innodb_page_size FROM mysql.innodb_index_stats '
            . "WHERE database_name = '{$this->database}' AND table_name = 'oxarticles' AND stat_name = 'size' "
            . "AND index_name IN ($names)";
    }
}
