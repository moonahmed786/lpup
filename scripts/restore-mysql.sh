#!/bin/sh
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.production.yml}"
BACKUP_FILE="${1:?Usage: scripts/restore-mysql.sh backups/mysql/file.sql.gz}"
DATABASE="${DB_DATABASE:?Set DB_DATABASE}"
USERNAME="${DB_USERNAME:?Set DB_USERNAME}"
PASSWORD="${DB_PASSWORD:?Set DB_PASSWORD}"

case "$BACKUP_FILE" in
    *.gz)
        gzip -dc "$BACKUP_FILE" | docker compose -f "$COMPOSE_FILE" exec -T mysql \
            mysql -u"$USERNAME" -p"$PASSWORD" "$DATABASE"
        ;;
    *)
        docker compose -f "$COMPOSE_FILE" exec -T mysql \
            mysql -u"$USERNAME" -p"$PASSWORD" "$DATABASE" < "$BACKUP_FILE"
        ;;
esac
