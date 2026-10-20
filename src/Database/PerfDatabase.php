<?php

declare(strict_types=1);

namespace Dantweb\OxidPerformanceTests\Database;

/** The cloned database every perf step reads and writes; the demo shop's `example` is never touched. */
final class PerfDatabase
{
    public const NAME = 'example_perf';

    /** A title that exists only in the perf database: proves which database an entry point serves. */
    public const MARKER_TITLE = 'sprint17 perf marker';

    /**
     * The shop's columns default to zero dates, which strict mode rejects in LOAD DATA and in any ALTER of the
     * table.
     */
    public const RELAXED_MODE = "SET SESSION sql_mode = ''";
}
