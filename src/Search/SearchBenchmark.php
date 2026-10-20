<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

use Closure;

/**
 * Times the request the quicksearch sends. Transport and clock are injected: the unit tests drive them, the
 * Performance suite passes curl and hrtime.
 */
final class SearchBenchmark
{
    private readonly Quicksearch $quicksearch;

    /**
     * @param Closure(array<string, mixed>): array{status: int, json: mixed} $transport
     * @param Closure(): float $clock milliseconds
     */
    public function __construct(private readonly Closure $transport, private readonly Closure $clock)
    {
        $this->quicksearch = new Quicksearch();
    }

    /**
     * The first request is the cold one and counts as a warm-up; only requests after the warm-up are sampled. With a
     * budget, sampling stops before a sample that would overrun it (full scans of 5 M rows take seconds), but not
     * below the minimum.
     */
    public function measure(
        Scenario $scenario,
        int $warmups,
        int $samples,
        ?float $budgetMs = null,
        int $minSamples = 1,
    ): ScenarioResult {
        $request = $this->quicksearch->request($scenario);
        $coldMs = 0.0;
        $rows = 0;
        for ($i = 0; $i < $warmups; $i++) {
            $answer = $this->send($request);
            if ($answer['failure'] !== null) {
                return new ScenarioResult($scenario, $request, $coldMs, [], $rows, $answer['failure']);
            }
            $coldMs = $i === 0 ? $answer['ms'] : $coldMs;
            $rows = $answer['rows'];
        }
        $timed = [];
        while ($this->wantsAnotherSample($timed, $samples, $budgetMs, $minSamples)) {
            $answer = $this->send($request);
            if ($answer['failure'] !== null) {
                return new ScenarioResult($scenario, $request, $coldMs, [], $rows, $answer['failure']);
            }
            $timed[] = $answer['ms'];
        }

        return new ScenarioResult($scenario, $request, $coldMs, $timed, $rows, null);
    }

    /**
     * @param array<string, mixed> $request
     * @return array{ms: float, rows: int, failure: ?string}
     */
    private function send(array $request): array
    {
        $start = ($this->clock)();
        ['status' => $status, 'json' => $json] = ($this->transport)($request);
        $ms = ($this->clock)() - $start;
        if ($status !== 200) {
            return ['ms' => $ms, 'rows' => 0, 'failure' => "HTTP $status"];
        }
        if (isset($json['errors'][0]['message'])) {
            return ['ms' => $ms, 'rows' => 0, 'failure' => (string) $json['errors'][0]['message']];
        }

        return ['ms' => $ms, 'rows' => count($json['data']['products'] ?? []), 'failure' => null];
    }

    /** @param list<float> $timed */
    private function wantsAnotherSample(array $timed, int $samples, ?float $budgetMs, int $minSamples): bool
    {
        if (count($timed) >= $samples) {
            return false;
        }
        if (count($timed) < $minSamples || $budgetMs === null) {
            return true;
        }

        return array_sum($timed) + $timed[count($timed) - 1] <= $budgetMs;
    }
}
