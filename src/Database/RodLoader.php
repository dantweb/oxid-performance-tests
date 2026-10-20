<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Database;

use Closure;
use Dantweb\OxidPerformanceTests\Catalogue\PrimaryKeyOrder;
use PDO;
use RuntimeException;

/**
 * Loads generate-tsv chunk files into the perf database and maps them to shop 1. Re-running first removes the
 * previous ROD- articles. Guards: starts only from the shop's own table definition, ends with it again, and refuses
 * chunks that are not in primary-key order (they turn appends into random page splits).
 */
final class RodLoader
{
    /** @param Closure(string): void $log */
    public function __construct(
        private readonly PDO $pdo,
        private readonly LoadSql $sql,
        private readonly PrimaryKeyOrder $order,
        private readonly string $database,
        private readonly Closure $log,
    ) {
    }

    /** @param list<string> $files */
    public function load(array $files): void
    {
        $definition = $this->tableDefinition('example');
        if (!$this->sql->sameTableDefinition($definition, $this->tableDefinition($this->database))) {
            throw new RuntimeException(
                "{$this->database}.oxarticles differs from the shop's: re-clone it (bin/db-clone.sh) first",
            );
        }
        $this->assertAppendOrder($files);
        $original = $this->currentSettings();
        $this->applySettings('bulk settings (were ' . json_encode($original) . ')', LoadSql::BULK_LOAD_SETTINGS);
        try {
            $this->loadRows($files);
        } finally {
            $this->applySettings('restore settings', $original);
        }
        if (!$this->sql->sameTableDefinition($definition, $this->tableDefinition($this->database))) {
            throw new RuntimeException("{$this->database}.oxarticles no longer matches the shop's table definition");
        }
    }

    /** @param list<string> $files */
    private function loadRows(array $files): void
    {
        foreach ($this->sql->sessionPrelude() as $statement) {
            $this->pdo->exec($statement);
        }
        [$unmap, $remove] = $this->sql->resetStatements();
        $this->step('reset: unmap ROD- articles', $unmap);
        $this->step('reset: delete ROD- articles', $remove);
        $indexes = $this->sql->secondaryIndexStatements(
            $this->pdo->query($this->sql->statisticsQuery())->fetchAll(),
            'OXMAPID',
        );
        $this->step('drop secondary indexes', $indexes['drop']);
        foreach ($files as $file) {
            $this->step('load ' . basename($file), $this->sql->loadStatement($file));
        }
        $this->step('re-add secondary indexes', $indexes['add']);
        $this->step('map ROD- articles to shop 1', $this->sql->mapToShopStatement());
        $this->step('analyze', $this->sql->analyzeStatement());
    }

    /** @param list<string> $files */
    private function assertAppendOrder(array $files): void
    {
        $last = [];
        foreach ($files as $file) {
            $oxids = array_map(
                static fn (string $line): string => substr($line, 0, 32),
                file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
            );
            if (!$this->order->isInAppendOrder([...$last, ...$oxids])) {
                throw new RuntimeException(
                    basename($file) . ' is not in primary-key order: regenerate the chunks with bin/generate-tsv.php',
                );
            }
            $last = array_slice($oxids, -1);
        }
    }

    /** Runs a statement and drains its result (ANALYZE TABLE returns rows that would block the connection). */
    private function step(string $label, string $sql): void
    {
        $start = hrtime(true);
        $this->pdo->query($sql)->closeCursor();
        ($this->log)(sprintf("%s\t%s\t%d ms", date('c'), $label, intdiv(hrtime(true) - $start, 1_000_000)));
    }

    /** @return array<string, int> */
    private function currentSettings(): array
    {
        $names = array_keys(LoadSql::BULK_LOAD_SETTINGS);
        $columns = array_map(static fn (string $n): string => "@@GLOBAL.$n AS $n", $names);
        $row = $this->pdo->query('SELECT ' . implode(', ', $columns))->fetch();

        return array_map('intval', $row);
    }

    /**
     * A buffer pool resize is asynchronous: @@innodb_buffer_pool_size shows the new value at once, and a SET issued
     * while a resize runs is ignored (warning only). Settings count as applied when the resize is idle and the values
     * match; an ignored SET is issued again.
     *
     * @param array<string, int> $settings
     */
    public function applySettings(string $label, array $settings): void
    {
        $start = hrtime(true);
        for ($attempt = 0; $attempt < 300; $attempt++) {
            if ($this->isResizeIdle()) {
                $missing = array_diff_assoc($settings, $this->currentSettings());
                if ($missing === []) {
                    ($this->log)(sprintf("%s\t%s\t%d ms", date('c'), $label, intdiv(hrtime(true) - $start, 1_000_000)));

                    return;
                }
                foreach ($this->sql->setGlobalStatements($missing) as $statement) {
                    $this->pdo->query($statement)->closeCursor();
                }
            }
            sleep(1);
        }
        throw new RuntimeException('MySQL did not reach ' . json_encode($settings));
    }

    private function isResizeIdle(): bool
    {
        $status = $this->pdo->query("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_resize_status_code'")->fetch();

        return (int) ($status['Value'] ?? 0) === 0;
    }

    private function tableDefinition(string $database): string
    {
        return (string) $this->pdo->query("SHOW CREATE TABLE $database.oxarticles")->fetch()['Create Table'];
    }
}
