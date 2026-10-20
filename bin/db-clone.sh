#!/usr/bin/env bash
# Copies the demo shop database `example` into a perf database (tables and oxv_* views) and adds the marker
# article that proves which database an entry point serves. Re-running starts over. Run on the host.
#   bin/db-clone.sh [target, default example_perf]
set -euo pipefail

TARGET="${1:-example_perf}"
[ "$TARGET" = example ] && { echo "refusing to overwrite the demo database" >&2; exit 1; }
SDK_DIR="${SDK_DIR:-$(cd "$(dirname "$0")/../../../.." && pwd)}"
MYSQL=(docker compose --project-directory "$SDK_DIR" exec -T mysql)

"${MYSQL[@]}" mysql -uroot -proot -e "DROP DATABASE IF EXISTS \`$TARGET\`; CREATE DATABASE \`$TARGET\` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci"
"${MYSQL[@]}" sh -c "mysqldump -uroot -proot --single-transaction --routines --triggers example | mysql -uroot -proot $TARGET"
# The shop's own rows carry zero dates; the shop views join oxarticles2shop on OXMAPID (unmapped articles are invisible).
"${MYSQL[@]}" mysql -uroot -proot "$TARGET" -e "
  SET SESSION sql_mode = '';
  INSERT INTO oxarticles (OXID, OXSHOPID, OXACTIVE, OXARTNUM, OXTITLE, OXSHORTDESC, OXPRICE, OXSTOCK, OXSTOCKFLAG, OXPARENTID)
  VALUES ('perf0000000000000000000000marker', 1, 1, 'PERF-MARKER', 'sprint17 perf marker', '', 1, 1, 1, '');
  INSERT INTO oxarticles2shop (OXSHOPID, OXMAPOBJECTID) SELECT 1, OXMAPID FROM oxarticles WHERE OXARTNUM = 'PERF-MARKER'"
echo "$TARGET cloned from example (+ marker)"
