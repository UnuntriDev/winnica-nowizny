#!/bin/sh
# Encrypted off-site backup. Run from the repository root on the production host.

set -eu

: "${BACKUP_AGE_RECIPIENT:?set BACKUP_AGE_RECIPIENT}"
: "${BACKUP_REMOTE:?set BACKUP_REMOTE, for example remote:winnica-backups}"

command -v age >/dev/null 2>&1 || { echo "age is required" >&2; exit 1; }
command -v rclone >/dev/null 2>&1 || { echo "rclone is required" >&2; exit 1; }

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT INT TERM

COMPOSE="docker compose -f docker-compose.production.yml"

$COMPOSE exec -T db sh -c \
  'exec mariadb-dump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  >"$WORK_DIR/database.sql"

$COMPOSE exec -T wordpress \
  tar -C /var/www/html/wp-content -czf - uploads \
  >"$WORK_DIR/uploads.tar.gz"

age -r "$BACKUP_AGE_RECIPIENT" -o "$WORK_DIR/database-$STAMP.sql.age" "$WORK_DIR/database.sql"
age -r "$BACKUP_AGE_RECIPIENT" -o "$WORK_DIR/uploads-$STAMP.tar.gz.age" "$WORK_DIR/uploads.tar.gz"

rclone copy "$WORK_DIR/database-$STAMP.sql.age" "$BACKUP_REMOTE/$STAMP/"
rclone copy "$WORK_DIR/uploads-$STAMP.tar.gz.age" "$BACKUP_REMOTE/$STAMP/"

echo "Encrypted backup uploaded to $BACKUP_REMOTE/$STAMP/"
