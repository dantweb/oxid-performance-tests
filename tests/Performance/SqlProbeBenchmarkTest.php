<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use Dantweb\OxidPerformanceTests\Database\Connection;
use Dantweb\OxidPerformanceTests\Search\ProbeRunner;
use Dantweb\OxidPerformanceTests\Search\Scenarios;
use Dantweb\OxidPerformanceTests\Search\SqlProbe;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * The quicksearch SQL inside MySQL, one test per term and predicate; results go to raw/<run>-probes.csv.
 * A predicate may be refused only for a known reason (no FULLTEXT index; FTS result cache exceeded).
 * innodb_ft_result_cache_limit is lowered to 256 MiB for the probes: a phrase of words all 5 M titles share made
 * MySQL collect up to 2 GB per query, which took the 15 GB host down once (2026-10-20).
 */
final class SqlProbeBenchmarkTest extends TestCase
{
    private const FT_CACHE_LIMIT = 268_435_456;
    private const KNOWN_REFUSALS = ['/1191/', '/FTS query exceeds result cache limit/'];

    private static ?PDO $pdo = null;
    private static int $originalFtCacheLimit = 0;

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo !== null) {
            self::$pdo->exec('SET GLOBAL innodb_ft_result_cache_limit = ' . self::$originalFtCacheLimit);
            self::$pdo = null;
        }
    }

    public function testStartsTheRunsProbeCsv(): string
    {
        $run = PerformanceEnvironment::run($this);
        self::$pdo = Connection::create();
        self::$originalFtCacheLimit = (int) self::$pdo
            ->query('SELECT @@GLOBAL.innodb_ft_result_cache_limit')
            ->fetchColumn();
        self::$pdo->exec('SET GLOBAL innodb_ft_result_cache_limit = ' . self::FT_CACHE_LIMIT);
        $file = PerformanceEnvironment::rawDir() . "/$run-probes.csv";
        file_put_contents($file, SqlProbe::CSV_HEADER . "\n");

        self::assertFileExists($file);

        return $file;
    }

    /** @return iterable<array{string, string}> */
    public static function probes(): iterable
    {
        foreach (Scenarios::PROBE_TERMS as $term) {
            foreach (SqlProbe::PREDICATES as $predicate) {
                yield "$predicate \"$term\"" => [$predicate, $term];
            }
        }
    }

    #[Depends('testStartsTheRunsProbeCsv')]
    #[DataProvider('probes')]
    public function testProbesTheQuicksearchSql(string $predicate, string $term, string $file): void
    {
        $probe = new SqlProbe();
        $result = (new ProbeRunner(self::$pdo ?? Connection::create(), $probe))
            ->run($probe->statement($predicate, $term));
        $row = $probe->csvRow(PerformanceEnvironment::run($this), $predicate, $term, $result);
        file_put_contents($file, $row . "\n", FILE_APPEND);

        if ($result->failure !== null) {
            self::assertTrue($this->isKnownRefusal($result->failure), "unexpected failure: {$result->failure}");

            return;
        }
        self::assertGreaterThan(0.0, $result->coldMs);
    }

    private function isKnownRefusal(string $failure): bool
    {
        foreach (self::KNOWN_REFUSALS as $pattern) {
            if (preg_match($pattern, $failure) === 1) {
                return true;
            }
        }

        return false;
    }
}
