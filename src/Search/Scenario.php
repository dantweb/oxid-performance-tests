<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

/** What a shopper does in the quicksearch: type a term (page null: the suggestion dropdown) or open a results page. */
final class Scenario
{
    public function __construct(
        public readonly string $name,
        public readonly string $term,
        public readonly ?int $page,
    ) {
    }
}
