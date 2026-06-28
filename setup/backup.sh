#!/usr/bin/env bash
# =============================================================
# backup.sh — Backup MySQL eskristal harian
# Pasang via: sudo cp setup/backup.cron /etc/cron.d/eskristal-backup
# =============================================================

set -euo pipefail

# ── Muat variabel dari config/.env ────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/../config/.env"

if [[ -f "$ENV_FILE" ]]; then
    # Export hanya baris KEY=VALUE (abaikan komentar & baris kosong)
    set -a
    # shellcheck disable=SC1090
    source <(grep -E '^[A-Z_]+=.+' "$ENV_FILE")
    set +a
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-eskristal}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"

BACKUP_DIR="/var/backups/eskristal"
TANGGAL="$(date +%Y-%m-%d)"
FILE="$BACKUP_DIR/eskristal-$TANGGAL.sql.gz"
RETENSI_HARI=7

# ── Buat direktori backup ──────────────────────────────────────
mkdir -p "$BACKUP_DIR"

# ── Dump & kompres ────────────────────────────────────────────
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Mulai backup → $FILE"

MYSQL_PWD="$DB_PASS" mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_NAME" \
    | gzip -9 > "$FILE"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup selesai ($(du -h "$FILE" | cut -f1))"

# ── Hapus backup lebih dari 7 hari ────────────────────────────
find "$BACKUP_DIR" -name "eskristal-*.sql.gz" -mtime "+$RETENSI_HARI" -delete
echo "[$(date '+%Y-%m-%d %H:%M:%S')] File lama (>$RETENSI_HARI hari) dibersihkan"
