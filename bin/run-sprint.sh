#!/usr/bin/env bash
# The three measured runs on the loaded example_perf: none → btree → fulltext (run on the host; ~1.5 h).
# Each run starts on a restarted MySQL (empty buffer pool, the SDK's own settings); the Performance suite first
# checks that the schema holds exactly the indexes of that run.
#   bin/run-sprint.sh                    all three runs
#   RUNS="btree fulltext" bin/run-sprint.sh   continue after a finished baseline
set -euo pipefail

SDK_DIR="${SDK_DIR:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
PKG="${PKG:-extensions/oxid-performance-tests}"
RESULTS=/var/www/extensions/oxid-performance-tests/test-results/performance/raw
COMPOSE=(docker compose --project-directory "$SDK_DIR")
GRAPHQL_URL="${PERF_GRAPHQL_URL:-http://apache:8094/graphql/}"
RAW="$SDK_DIR/source/extensions/oxid-performance-tests/test-results/performance/raw"
mkdir -p "$RAW"

in_php() {
  "${COMPOSE[@]}" exec -T -e PERF_GRAPHQL_URL="$GRAPHQL_URL" -e PERF_RUN="${PERF_RUN:-}" -e PERF_RESULTS_DIR="$RESULTS" \
    php sh -c "cd /var/www && $1"
}

restart_mysql() {
  "${COMPOSE[@]}" restart mysql >/dev/null
  until "${COMPOSE[@]}" exec -T mysql mysqladmin -uroot -proot ping --silent 2>/dev/null; do sleep 1; done
}

measure() {
  restart_mysql
  PERF_RUN="$1" in_php "php vendor/bin/phpunit -c $PKG/tests/phpunit.xml --testsuite Performance --exclude-filter CatalogueGolden"
}

record_environment() {
  "${COMPOSE[@]}" exec -T mysql mysql -uroot -proot -N -B example_perf -e "
    SELECT JSON_OBJECT(
      'mysql', @@version, 'innodb_buffer_pool_size', @@innodb_buffer_pool_size,
      'innodb_ft_min_token_size', @@innodb_ft_min_token_size, 'innodb_redo_log_capacity', @@innodb_redo_log_capacity,
      'oxarticles_rows', (SELECT COUNT(*) FROM oxarticles),
      'rod_rows', (SELECT COUNT(*) FROM oxarticles WHERE OXARTNUM LIKE 'ROD-%'),
      'oxarticles_bytes', (SELECT DATA_LENGTH + INDEX_LENGTH FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA = 'example_perf' AND TABLE_NAME = 'oxarticles'),
      'host_cpus', $(nproc), 'host_ram_mib', $(free -m | awk '/^Mem:/ {print $2}'),
      'host_cpu', '$(lscpu | sed -n 's/^Model name: *//p' | head -1)')" 2>/dev/null > "$RAW/environment.json"
}

for run in ${RUNS:-none btree fulltext}; do
  if [ "$run" = none ]; then
    restart_mysql
    record_environment
    measure none
    continue
  fi
  in_php "php $PKG/bin/migrate.php up $run --csv $RESULTS/indexes.csv"
  measure "$run"
  in_php "php $PKG/bin/migrate.php down $run"
done
echo "all runs written to $RAW"
