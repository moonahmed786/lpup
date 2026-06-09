#!/bin/sh
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.production.yml}"
BACKUP_DIR="${BACKUP_DIR:-backups/mysql}"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DATABASE="${DB_DATABASE:?Set DB_DATABASE}"
USERNAME="${DB_USERNAME:?Set DB_USERNAME}"
PASSWORD="${DB_PASSWORD:?Set DB_PASSWORD}"
OUTPUT="${BACKUP_DIR}/${DATABASE}-${TIMESTAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

docker compose -f "$COMPOSE_FILE" exec -T mysql \
    mysqldump --single-transaction --quick --routines --triggers \
    -u"$USERNAME" -p"$PASSWORD" "$DATABASE" \
    | gzip > "$OUTPUT"

printf '%s\n' "$OUTPUT"
