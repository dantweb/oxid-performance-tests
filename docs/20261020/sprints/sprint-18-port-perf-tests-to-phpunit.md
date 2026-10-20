# Sprint 18 — port the search-index performance tests to PHP / PHPUnit

**Date:** 2026-10-20 · **Package:** `source/extensions/oxid-performance-tests` (new) · **Origin:** Sprint 17 in `oxid-fe-user`
(`docs/dev_log/20261020/sprints/sprint-17-search-index-perf.md`, TypeScript under `scripts/perf/search-index`, branch `feature/search-index-perf`).

**Definition of Done:** the Sprint 17 measurement — 5 M seeded rods, quicksearch over GraphQL, SQL probes, runs none / B-tree / FULLTEXT,
report with charts — runs from the shop's PHP container with PHPUnit 11; working data lands in `test-results/performance/`,
the report in `docs/20261020/reports/`.

## Why PHP

The measurement is about the shop's database and GraphQL API, not the storefront. In the PHP container it talks to MySQL over PDO
(one connection: probe and `performance_schema` read share a session — no `docker exec`, no stripped comments) and to the shop at
`http://apache:8094/graphql/`. Shop developers run it next to the other module test suites.

## Scope

| Sprint 17 (TypeScript)                                | Sprint 18 (PHP)                                                                                                                             |
| ----------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `rodCatalogue.ts` — matrix, seeded sample             | `Catalogue\RodMatrix`, `Mulberry32`, `SeededSample`, `ArticleRows`, `PrimaryKeyOrder`, `ChunkWriter` — **bit-identical** sample, OXIDs, TSV |
| `generate-tsv.ts`, `load-rods.ts`, `loadSql.ts`       | `bin/generate-tsv.php`, `bin/load-rods.php`, `Database\LoadSql` — `LOAD DATA LOCAL INFILE` over PDO                                         |
| `indexMigrations.ts`, `migrate.ts`                    | `Database\IndexMigrations`, `bin/migrate.php`                                                                                               |
| `searchBenchmark.ts`, `scenarios.ts`                  | `Search\SearchBenchmark`, `Search\Scenarios` (curl transport, `hrtime`)                                                                     |
| `sqlProbe.ts`                                         | `Search\SqlProbe` (PDO, same session)                                                                                                       |
| `reportCharts.ts`, `reportData.ts`, `build-report.ts` | `Report\BarChart`, `Report\ReportData`, `bin/build-report.php`                                                                              |
| Vitest unit specs (`tests/unit/perf/*.spec.ts`)       | `tests/Unit/**` (PHPUnit)                                                                                                                   |
| env-gated perf specs + `run-benchmark.ts`             | `tests/Performance/**` (suite `Performance`, env-gated), writes the CSVs                                                                    |
| `run-sprint.sh`                                       | `bin/run-sprint.sh` (host: restarts MySQL, runs migrations and the suite per run)                                                           |

**Out of scope:** changing what is measured or how; a composer require in the shop (the suite's bootstrap autoloads the package);
CI for the package; reloading the 5 M rods (the PHP generator is proven identical, and `example_perf` already holds that sample).

## Stories

1. **Skeleton** — `composer.json`, `tests/phpunit.xml` (suites `Unit`, `Performance`), bootstrap on the shop's `vendor/autoload.php`.
2. **Catalogue** (TDD) — tests ported from `rodCatalogue.spec.ts` plus golden tests: `sample(20261020, 5)` = `[2246106, 2027997, 7333355, 4379270, 3768056]`,
   row 4242424's OXID `d2a0147a36ed0b23b669a73cb420eaf7`, first sorted chunk SHA-256 `408543d2…` (performance group).
3. **Load + migrations** (TDD) — `LoadSql`, `IndexMigrations` ported with their tests; loader smoke-loaded into a scratch clone, never into `example`.
4. **Benchmark + probes** (TDD) — `SearchBenchmark` (fake transport and clock), `SqlProbe`; the Performance suite measures one run per invocation (`PERF_RUN`).
5. **Report** (TDD) — chart and table code with the Sprint 17 fixes (run names, units, axis title, n/a reasons).
6. **Run + report** — the three runs on `example_perf`, report in `docs/20261020/reports/search-index-perf/`.

## Rules

TDD (red first), SOLID (pure modules, I/O at the edges: `bin/` and the Performance tests), DRY within the package, PSR-12,
PHPStan clean, early returns, no overengineering. The GraphQL query document is a copy of the storefront's `SEARCH_QUERY` —
the one cross-repo duplication, pinned by a test against its text.

---

## Outcome (2026-10-20)

**Done — all six stories.** Report: [`../reports/search-index-perf/report.md`](../reports/search-index-perf/report.md).

- **Port is exact:** golden unit tests and the SHA-256 of the first 5 M-sample chunk match the TypeScript generator; SQL plans and rows
  examined match Sprint 17 in every probe. Result unchanged: no index speeds up the quicksearch's `contains`; a B-tree on `OXTITLE`
  makes prefix search instant; FULLTEXT fails on shared words.
- **Gate:** Unit suite 95 tests; Performance suite 39 tests × 3 runs green; PHPStan level 6 clean; PSR-12 clean, no line > 120.

Found and fixed on the way (each with a test where the code is pure):

- `Math.round` ≠ PHP `round()`: 2 913 of 500 000 prices differed by a cent (465.49999… pre-rounded by PHP) → `floor(x + 0.5)`.
- The buffer-pool wait had a race in the TypeScript loader too: `@@innodb_buffer_pool_size` shows the new value before the
  asynchronous resize ends, and a `SET` during a resize is ignored → wait for `Innodb_buffer_pool_resize_status_code = 0`, re-issue.
- `ANALYZE TABLE` returns rows; `PDO::exec()` left them unread and blocked the connection → statements drain their results.
- `mysqlnd` rejects charset `utf8mb3` → `utf8`. `LOAD DATA LOCAL INFILE` needs `local_infile` (raised and restored with the bulk settings).
- `bin/migrate.php`: `getopt()` stops at positional arguments, so `--csv` after `up btree` was ignored → parsed by hand; this run's
  index costs were taken from the log.

Left behind: `example_perf` (5 M rods, no perf indexes). The package is not under version control yet.
