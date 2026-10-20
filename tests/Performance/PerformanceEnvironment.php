<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Tests\Performance;

use PHPUnit\Framework\TestCase;

/**
 * What the Performance suite needs from its environment. Without it the tests skip, so `phpunit` without
 * env vars never touches a database or the shop.
 *   PERF_GRAPHQL_URL  the perf shop's GraphQL endpoint (from the PHP container: http://apache:8094/graphql/)
 *   PERF_RUN          the index state being measured: none | btree | fulltext
 *   DEMO_GRAPHQL_URL  the demo shop (default http://apache/graphql/)
 */
final class PerformanceEnvironment
{
    public static function graphqlUrl(TestCase $test): string
    {
        return self::required($test, 'PERF_GRAPHQL_URL');
    }

    public static function run(TestCase $test): string
    {
        return self::required($test, 'PERF_RUN');
    }

    public static function demoGraphqlUrl(): string
    {
        return getenv('DEMO_GRAPHQL_URL') ?: 'http://apache/graphql/';
    }

    /** Working data: raw CSVs, logs (gitignored). */
    public static function rawDir(): string
    {
        $dir = getenv('PERF_RESULTS_DIR') ?: __DIR__ . '/../../test-results/performance/raw';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function required(TestCase $test, string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            $test->markTestSkipped("$name is not set: performance tests run only against the perf shop");
        }

        return (string) $value;
    }
}
