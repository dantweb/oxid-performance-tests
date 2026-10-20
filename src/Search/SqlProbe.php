<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

use InvalidArgumentException;

/**
 * The SQL behind one quicksearch request, timed inside MySQL. The statement is the one graphql-storefront sent for
 * products(filter: { title: { contains } }) (captured from performance_schema on 2026-10-20) with only the title
 * predicate swapped, so each index can be judged for contains, beginsWith, the short description and FULLTEXT.
 */
final class SqlProbe
{
    public const PREDICATES = ['titleContains', 'titleBeginsWith', 'shortDescContains', 'titleMatch'];
    public const CSV_HEADER = 'run,predicate,term,cold_ms,warm_ms,rows_examined,plan,failure';
    public const STATS_STATEMENT = 'SELECT TIMER_WAIT, ROWS_EXAMINED FROM performance_schema.events_statements_history '
        . "WHERE THREAD_ID = PS_CURRENT_THREAD_ID() AND SQL_TEXT LIKE '" . self::MARKER . "%' "
        . 'ORDER BY EVENT_ID DESC LIMIT 1';

    private const VIEW = 'oxv_oxarticles_1_de';
    private const MARKER = '/* sprint17-probe */';

    public function statement(string $predicate, string $term): string
    {
        $view = self::VIEW;
        $active = "(( $view.oxactive = 1 and $view.oxhidden = 0 "
            . "and ( $view.oxstockflag != 2 or ( $view.oxstock + $view.oxvarstock ) > 0 ) "
            . "and IF( $view.oxvarcount = 0, 1, ( select 1 from $view as art where art.oxparentid=$view.oxid "
            . 'and art.oxactive = 1 and ( art.oxstockflag != 2 or art.oxstock > 0 ) limit 1 ) ) ))';

        return sprintf(
            "%s SELECT %s.OXID FROM %s WHERE %s AND (%s) AND (%s.OXPARENTID = '') ORDER BY %s.oxsort ASC LIMIT 5",
            self::MARKER,
            $view,
            $view,
            $active,
            $this->predicate($predicate, $term),
            $view,
            $view,
        );
    }

    public function explainStatement(string $sql): string
    {
        return "EXPLAIN $sql";
    }

    /**
     * EXPLAIN rows as `table:access:key`.
     *
     * @param list<array<string, mixed>> $explain
     */
    public function plan(array $explain): string
    {
        return implode(' | ', array_map(
            static fn (array $row): string => sprintf('%s:%s:%s', $row['table'], $row['type'], $row['key'] ?? '-'),
            $explain,
        ));
    }

    public function csvRow(string $run, string $predicate, string $term, ProbeResult $result): string
    {
        return implode(',', [
            $run,
            $predicate,
            Csv::text($term),
            Csv::number($result->coldMs),
            Csv::number($result->warmMs),
            $result->rowsExamined,
            Csv::text($result->plan),
            $result->failure === null ? '' : Csv::text($result->failure),
        ]);
    }

    private function predicate(string $predicate, string $term): string
    {
        $view = self::VIEW;

        return match ($predicate) {
            'titleContains' => "$view.OXTITLE LIKE " . $this->quoted("%$term%"),
            'titleBeginsWith' => "$view.OXTITLE LIKE " . $this->quoted("$term%"),
            'shortDescContains' => "$view.OXSHORTDESC LIKE " . $this->quoted("%$term%"),
            // A phrase: the closest FULLTEXT counterpart of contains.
            'titleMatch' => "MATCH ($view.OXTITLE) AGAINST (" . $this->quoted("\"$term\"") . ' IN BOOLEAN MODE)',
            default => throw new InvalidArgumentException("unknown predicate $predicate"),
        };
    }

    private function quoted(string $text): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $text) . "'";
    }
}
