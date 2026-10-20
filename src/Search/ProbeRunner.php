<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

use PDO;
use PDOException;

/**
 * Runs a probe on one PDO session: the probe and the performance_schema read share the session, so the statement
 * history holds exactly this probe's time and rows examined. 1 cold + (repeats - 1) warm executions.
 */
final class ProbeRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SqlProbe $probe,
        private readonly int $repeats = 3,
    ) {
    }

    public function run(string $sql): ProbeResult
    {
        try {
            $plan = $this->probe->plan($this->pdo->query($this->probe->explainStatement($sql))->fetchAll());
            $timings = [];
            for ($i = 0; $i < $this->repeats; $i++) {
                $this->pdo->query($sql)->fetchAll();
                $timings[] = $this->pdo->query(SqlProbe::STATS_STATEMENT)->fetch();
            }
        } catch (PDOException $exception) {
            return new ProbeResult(0.0, 0.0, 0, '', $exception->getMessage());
        }
        $warm = array_map(static fn (array $row): float => $row['TIMER_WAIT'] / 1e9, array_slice($timings, 1));
        sort($warm);

        return new ProbeResult(
            $timings[0]['TIMER_WAIT'] / 1e9,
            $warm[intdiv(count($warm), 2)] ?? 0.0,
            (int) $timings[count($timings) - 1]['ROWS_EXAMINED'],
            $plan,
            null,
        );
    }
}
