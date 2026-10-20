<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Search;

/** The fixed measurement plan of Sprint 17. */
final class Scenarios
{
    /** Prefix terms (what beginsWith can use), an infix, a 2-letter material and a miss. */
    public const PROBE_TERMS = [
        'metal rod U-shape AlTi',
        'metal rod FeMoCo 999mm',
        'FeMoCo 999mm length 99mm',
        'Zn',
        'titanium bar',
    ];

    /**
     * What a shopper does in the quicksearch, from cheap (many matches end the scan early) to worst case.
     *
     * @return list<Scenario>
     */
    public static function quicksearch(): array
    {
        return [
            new Scenario('typing', 'me', null),
            new Scenario('typing', 'metal', null),
            new Scenario('typing', 'metal rod U', null),
            new Scenario('typing', 'metal rod U-shape AlTi', null),
            new Scenario('shape', 'U-shape AlTi', null),
            new Scenario('selective', 'FeMoCo 999mm length 99mm', null),
            new Scenario('short', 'Zn', null),
            new Scenario('short', 'Ti', null),
            new Scenario('miss', 'titanium bar', null),
            new Scenario('page-first', 'L-shape', 0),
            new Scenario('page-last', 'L-shape', Quicksearch::MAX_PAGES - 1),
        ];
    }
}
