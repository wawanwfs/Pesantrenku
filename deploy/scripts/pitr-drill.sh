#!/usr/bin/env sh
set -eu

ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-recovery-secret}"
SOURCE="${MYSQL_SOURCE_CONTAINER:-pesantrenku-mysql-source-1}"
RECOVERY="${MYSQL_RECOVERY_CONTAINER:-pesantrenku-mysql-recovery-1}"
ARTIFACT_DIR="${PITR_ARTIFACT_DIR:-./build/pitr}"
BINLOG_IMAGE="${MYSQL_BINLOG_IMAGE:-mariadb:11}"
mkdir -p "$ARTIFACT_DIR"

docker exec "$SOURCE" mysqldump -uroot -p"$ROOT_PASSWORD" --single-transaction --source-data=2 --routines --events pesantenku > "$ARTIFACT_DIR/base.sql"
STATUS=$(docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" -Nse "SHOW BINARY LOG STATUS" 2>/dev/null \
    || docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" -Nse "SHOW MASTER STATUS")
BINLOG_FILE=$(printf '%s' "$STATUS" | awk '{print $1}')
BINLOG_POSITION=$(printf '%s' "$STATUS" | awk '{print $2}')
MARKER="pitr-$(date -u +%Y%m%d%H%M%S)"
docker exec "$SOURCE" mysql -uroot -p"$ROOT_PASSWORD" pesantenku -e "INSERT INTO system_settings (pesantren_id,\`key\`,value,created_at,updated_at) VALUES (1,'pitr.drill.marker',JSON_QUOTE('$MARKER'),UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=UTC_TIMESTAMP()"
sleep 2
STOP_TIME=$(date -u '+%Y-%m-%d %H:%M:%S')

docker exec "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS pesantenku; CREATE DATABASE pesantenku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
docker exec -i "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" pesantenku < "$ARTIFACT_DIR/base.sql"
docker cp "$SOURCE:/var/lib/mysql/$BINLOG_FILE" "$ARTIFACT_DIR/$BINLOG_FILE"
BINLOG_DIR=$(cd "$ARTIFACT_DIR" && pwd)
docker run --rm --entrypoint mariadb-binlog \
    --mount "type=bind,source=$BINLOG_DIR,target=/work,readonly" \
    "$BINLOG_IMAGE" \
    --start-position="$BINLOG_POSITION" \
    --stop-datetime="$STOP_TIME" \
    "/work/$BINLOG_FILE" > "$ARTIFACT_DIR/replay.raw.sql"
sed \
    -e 's/, @@session.check_constraint_checks=1//g' \
    -e 's/, @@session.system_versioning_insert_history=0//g' \
    "$ARTIFACT_DIR/replay.raw.sql" > "$ARTIFACT_DIR/replay.sql"
docker exec -i "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" pesantenku < "$ARTIFACT_DIR/replay.sql"
RESTORED=$(docker exec "$RECOVERY" mysql -uroot -p"$ROOT_PASSWORD" -Nse "SELECT JSON_UNQUOTE(value) FROM pesantenku.system_settings WHERE \`key\`='pitr.drill.marker' ORDER BY id DESC LIMIT 1")

if [ "$RESTORED" != "$MARKER" ]; then
  echo "PITR verification failed: expected $MARKER, got $RESTORED" >&2
  exit 1
fi

printf '{"status":"passed","marker":"%s","binlog":"%s","position":%s,"stop_time":"%s"}\n' "$MARKER" "$BINLOG_FILE" "$BINLOG_POSITION" "$STOP_TIME" > "$ARTIFACT_DIR/report.json"
cat "$ARTIFACT_DIR/report.json"
