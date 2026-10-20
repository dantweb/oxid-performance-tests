<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use Dantweb\OxidPerformanceTests\Database\Connection;
use Dantweb\OxidPerformanceTests\Database\IndexMigrations;
use PHPUnit\Framework\TestCase;

/** The schema holds exactly the indexes of the run being measured: nothing left over from another run. */
final class IndexStateTest extends TestCase
{
    public function testExamplePerfHasExactlyTheIndexesOfThisRun(): void
    {
        $run = PerformanceEnvironment::run($this);
        $migrations = new IndexMigrations();

        $actual = Connection::create()->query($migrations->stateQuery())->fetchAll();
        $expected = $migrations->expectedIndexes($run);
        usort($actual, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);
        usort($expected, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        self::assertSame($expected, $actual);
    }
}
