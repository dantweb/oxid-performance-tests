# oxid-performance-tests

Performance tests for OXID eShop 7.4 with PHPUnit 11: a seeded catalogue of millions of near-identical articles,
the storefront's quicksearch measured over GraphQL, and the SQL behind it measured inside MySQL — with and without
indexes on `oxarticles.OXTITLE` / `OXSHORTDESC`.

Ported from Sprint 17 of `oxid-fe-user` (TypeScript); see `docs/20261020/sprints/` and the report in `docs/20261020/reports/`.

## Layout

| Path                        | What                                                                                                                 |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `src/Catalogue`             | the rod matrix, the seeded 5 M sample (bit-identical to the TypeScript one), TSV chunks                              |
| `src/Database`              | bulk load into the perf database, index migrations, PDO connection                                                   |
| `src/Search`                | the quicksearch request, benchmark, SQL probes, CSV rows                                                             |
| `src/Report`                | SVG bar charts and Markdown tables from the raw CSVs                                                                 |
| `tests/Unit`                | pure logic — runs anywhere in seconds                                                                                |
| `tests/Performance`         | against the perf shop; skips itself without `PERF_GRAPHQL_URL` / `PERF_RUN`                                          |
| `bin/`                      | `db-clone.sh` (host), `generate-tsv.php`, `load-rods.php`, `migrate.php`, `build-report.php`, `run-sprint.sh` (host) |
| `test-results/performance/` | working data: TSV chunks, raw CSVs, logs (gitignored)                                                                |

## Run

From the SDK root (`docker compose` project), the package is mounted at `/var/www/extensions/oxid-performance-tests`.

```bash
# unit tests
docker compose exec php php vendor/bin/phpunit -c extensions/oxid-performance-tests/tests/phpunit.xml --testsuite Unit

# one-time setup of the perf shop (see Sprint 17, S1: :8094 vhost with OXID_DB_NAME=example_perf)
source/extensions/oxid-performance-tests/bin/db-clone.sh                     # example → example_perf (+ marker)
docker compose exec php php extensions/oxid-performance-tests/bin/generate-tsv.php   # 5 M rows, ~1 min
docker compose exec php php extensions/oxid-performance-tests/bin/load-rods.php      # ~10–20 min

# the three measured runs (none → B-tree → FULLTEXT), ~1.5 h, then the charts
source/extensions/oxid-performance-tests/bin/run-sprint.sh
docker compose exec php php extensions/oxid-performance-tests/bin/build-report.php

# a single run by hand
docker compose exec -e PERF_GRAPHQL_URL=http://apache:8094/graphql/ -e PERF_RUN=none php \
  php vendor/bin/phpunit -c extensions/oxid-performance-tests/tests/phpunit.xml --testsuite Performance
```

## Safety

- Every statement targets `example_perf` (unit tests assert the demo database `example` is never named in a write).
- MySQL settings raised for the bulk load (redo log, IO capacity, buffer pool, `local_infile`) are restored in `finally`,
  waiting for the asynchronous buffer-pool resize; the SQL probes cap `innodb_ft_result_cache_limit` at 256 MiB and restore it.
- Clean up: `docker compose exec mysql mysql -uroot -proot -e 'DROP DATABASE example_perf'`; `rm -r test-results/performance`.
