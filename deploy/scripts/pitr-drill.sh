#!/usr/bin/env sh
set -eu

ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-recovery-secret}"
SOURCE="${MYSQL_SOURCE_CONTAINER:-pesantrenku-mysql-source-1}"
RECOVERY="${MYSQL_RECOVERY_CONTAINER:-pesantrenku-mysql-recovery-1}"
ARTIFACT_DIR="${PITR_ARTIFACT_DIR:-./build/pitr}"
BINLOG_IMAGE="${MYSQL_BINLOG_IMAGE:?MYSQL_BINLOG_IMAGE pinned by digest is required}"
case "$BINLOG_IMAGE" in
  *@sha256:????????????????????????????????????????????????????????????????) ;;
  *) echo "MYSQL_BINLOG_IMAGE must use an immutable sha256 digest." >&2; exit 1 ;;
esac
mkdir -p "$ARTIFACT_DIR"

write_failure_report() {
  status=$?
  trap - EXIT
  if [ "$status" -ne 0 ]; then
    printf '{"status":"failed","completed_at":"%s"}\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$ARTIFACT_DIR/report.json"
  fi
  exit "$status"
}
trap write_failure_report EXIT

MARKER="pitr-$(date -u +%Y%m%d%H%M%S)"
CONCURRENT_MARKER="${MARKER}-concurrent"
# Start a write before the consistent snapshot is taken. Depending on timing it
# will be captured either by the dump or by binlog replay; both outcomes prove
# the snapshot coordinate is handled without losing concurrent commits.
( sleep 1; docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" pesantenku -e "INSERT INTO system_settings (pesantren_id,\`key\`,value,created_at,updated_at) VALUES (1,'pitr.drill.concurrent',JSON_QUOTE('$CONCURRENT_MARKER'),UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=UTC_TIMESTAMP()" ) &
CONCURRENT_WRITER_PID=$!
docker exec "$SOURCE" mysqldump -uroot -p"$ROOT_PASSWORD" --single-transaction --source-data=2 --routines --events pesantenku > "$ARTIFACT_DIR/base.sql"
wait "$CONCURRENT_WRITER_PID"
SNAPSHOT_COORDINATE=$(grep -m 1 -E '^-- CHANGE (REPLICATION SOURCE|MASTER) TO ' "$ARTIFACT_DIR/base.sql" || true)
BINLOG_FILE=$(printf '%s\n' "$SNAPSHOT_COORDINATE" | sed -n \
    -e "s/.*SOURCE_LOG_FILE='\([^']*\)'.*/\1/p" \
    -e "s/.*MASTER_LOG_FILE='\([^']*\)'.*/\1/p" | head -n 1)
BINLOG_POSITION=$(printf '%s\n' "$SNAPSHOT_COORDINATE" | sed -n \
    -e 's/.*SOURCE_LOG_POS=\([0-9][0-9]*\).*/\1/p' \
    -e 's/.*MASTER_LOG_POS=\([0-9][0-9]*\).*/\1/p' | head -n 1)
if [ -z "$BINLOG_FILE" ] || [ -z "$BINLOG_POSITION" ]; then
    echo "Snapshot binlog coordinate was not found in mysqldump output." >&2
    exit 1
fi
docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" pesantenku -e "INSERT INTO system_settings (pesantren_id,\`key\`,value,created_at,updated_at) VALUES (1,'pitr.drill.marker',JSON_QUOTE('$MARKER'),UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=UTC_TIMESTAMP()"
docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" -e "FLUSH BINARY LOGS"
sleep 2
STOP_TIME=$(date -u '+%Y-%m-%d %H:%M:%S')

docker exec "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS pesantenku; CREATE DATABASE pesantenku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
docker exec -i "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" pesantenku < "$ARTIFACT_DIR/base.sql"
docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" -Nse "SHOW BINARY LOGS" \
    | awk -v start="$BINLOG_FILE" '$1 == start { found=1 } found { print $1 }' > "$ARTIFACT_DIR/binlogs.list"
if ! grep -Fxq "$BINLOG_FILE" "$ARTIFACT_DIR/binlogs.list"; then
    echo "Snapshot binlog $BINLOG_FILE is no longer available on source; PITR cannot be proven." >&2
    exit 1
fi
while IFS= read -r log_file; do
    [ -n "$log_file" ] || continue
    docker cp "$SOURCE:/var/lib/mysql/$log_file" "$ARTIFACT_DIR/$log_file"
done < "$ARTIFACT_DIR/binlogs.list"
BINLOG_DIR=$(cd "$ARTIFACT_DIR" && pwd)
set -- docker run --rm --entrypoint mysqlbinlog \
    --mount "type=bind,source=$BINLOG_DIR,target=/work,readonly" \
    "$BINLOG_IMAGE" \
    --start-position="$BINLOG_POSITION" \
    --stop-datetime="$STOP_TIME"
while IFS= read -r log_file; do
    [ -n "$log_file" ] || continue
    set -- "$@" "/work/$log_file"
done < "$ARTIFACT_DIR/binlogs.list"
"$@" > "$ARTIFACT_DIR/replay.raw.sql"
sed \
    -e 's/, @@session.check_constraint_checks=1//g' \
    -e 's/, @@session.system_versioning_insert_history=0//g' \
    "$ARTIFACT_DIR/replay.raw.sql" > "$ARTIFACT_DIR/replay.sql"
docker exec -i "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" pesantenku < "$ARTIFACT_DIR/replay.sql"
CONCURRENT_RESTORED=$(docker exec "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" -Nse "SELECT JSON_UNQUOTE(value) FROM pesantenku.system_settings WHERE \`key\`='pitr.drill.concurrent' ORDER BY id DESC LIMIT 1")
RESTORED=$(docker exec "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" -Nse "SELECT JSON_UNQUOTE(value) FROM pesantenku.system_settings WHERE \`key\`='pitr.drill.marker' ORDER BY id DESC LIMIT 1")

if [ "$RESTORED" != "$MARKER" ] || [ "$CONCURRENT_RESTORED" != "$CONCURRENT_MARKER" ]; then
  echo "PITR verification failed: marker=$RESTORED concurrent=$CONCURRENT_RESTORED" >&2
  exit 1
fi

BINLOG_COUNT=$(wc -l < "$ARTIFACT_DIR/binlogs.list" | tr -d ' ')
[ "$BINLOG_COUNT" -ge 2 ] || { echo "Binlog rotation was not covered by the drill." >&2; exit 1; }
printf '{"status":"passed","marker":"%s","concurrent_marker":"%s","snapshot_binlog":"%s","snapshot_position":%s,"binlog_count":%s,"stop_time":"%s","completed_at":"%s"}\n' \
  "$MARKER" "$CONCURRENT_MARKER" "$BINLOG_FILE" "$BINLOG_POSITION" "$BINLOG_COUNT" "$STOP_TIME" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$ARTIFACT_DIR/report.json"
cat "$ARTIFACT_DIR/report.json"
