<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

/** One CSV row per measured scenario (same columns as the Sprint 17 TypeScript benchmark). */
final class ResultCsv
{
    public const HEADER = 'run,scenario,term,limit,offset,n,cold_ms,p50_ms,p95_ms,p99_ms,min_ms,max_ms,'
        . 'rows_returned,failure';

    public function __construct(private readonly Statistics $statistics)
    {
    }

    public function row(string $run, ScenarioResult $result): string
    {
        $samples = $result->samplesMs;
        $stats = $samples === []
            ? ['', '', '', '', '']
            : array_map(
                fn (float $p): string => Csv::number($this->statistics->percentile($samples, $p)),
                [50.0, 95.0, 99.0, 0.0, 100.0],
            );
        $variables = $result->request['variables'];

        return implode(',', [
            $run,
            $result->scenario->name,
            Csv::text($variables['q']),
            $variables['limit'],
            $variables['offset'],
            count($samples),
            Csv::number($result->coldMs),
            ...$stats,
            $result->rowsReturned,
            $result->failure === null ? '' : Csv::text($result->failure),
        ]);
    }
}
