#!/usr/bin/env bash
set -euo pipefail

BACKUP_ROOT="/mnt/backup-usb/app-linn-games"
DATE=$(date +%Y-%m-%d_%H%M)
BACKUP_DIR="${BACKUP_ROOT}/${DATE}"
APP_DIR="/home/nileneb/app.linn.games"
KEEP_DAYS=7

if ! mountpoint -q /mnt/backup-usb; then
    echo "ERROR: /mnt/backup-usb ist nicht gemountet!" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
echo "=== Backup gestartet: $DATE ==="

# 1. PostgreSQL Dump
echo "→ PostgreSQL Dump..."
docker compose -f "$APP_DIR/docker-compose.yml" exec -T postgres \
    sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "$BACKUP_DIR/postgres.sql.gz"

# 2. .env (Secrets — nicht im Git)
echo "→ .env sichern..."
cp "$APP_DIR/.env" "$BACKUP_DIR/dot-env.bak"

# 3. Laravel Storage (Uploads, Artifacts)
echo "→ Laravel Storage Volume..."
docker run --rm \
    -v applinngames_linn-storage-production:/data:ro \
    -v "$BACKUP_DIR":/backup \
    alpine tar czf /backup/storage.tar.gz -C /data .

# 4. Mayring Cache (ChromaDB)
echo "→ Mayring Cache Volume..."
docker run --rm \
    -v applinngames_linn-mayring-cache:/data:ro \
    -v "$BACKUP_DIR":/backup \
    alpine tar czf /backup/mayring-cache.tar.gz -C /data .

# 5. Papers Data (heruntergeladene PDFs)
echo "→ Papers Data Volume..."
docker run --rm \
    -v applinngames_linn-papers-data:/data:ro \
    -v "$BACKUP_DIR":/backup \
    alpine tar czf /backup/papers-data.tar.gz -C /data .

# 6. Rotation — lösche Backups älter als KEEP_DAYS
echo "→ Alte Backups aufräumen (>${KEEP_DAYS} Tage)..."
DELETED=$(find "$BACKUP_ROOT" -maxdepth 1 -type d -mtime +${KEEP_DAYS} \
    -not -path "$BACKUP_ROOT" -print -exec rm -rf {} + | wc -l)
echo "  ${DELETED} alte Backups gelöscht."

echo "=== Backup fertig ==="
ls -lh "$BACKUP_DIR"
du -sh "$BACKUP_DIR"
