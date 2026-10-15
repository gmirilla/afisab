#!/bin/sh
# AFISAB backup: database dump + private files (KYC documents, payment receipts), compressed, checksummed,
# optionally encrypted, with rotation. Run daily from cron (see crontab.txt). Works with MySQL/MariaDB and PostgreSQL.
#
# Settings come from the environment, or from the app's .env file when not set:
#   APP_DIR (default: parent of this script's folder)   BACKUP_DIR (default: $APP_DIR/../afisab-backups)
#   KEEP_DAYS (default 30)    GPG_RECIPIENT (optional: encrypt the archive for this key)
#   MYSQLDUMP / PG_DUMP (optional full paths)
#
# What is NOT in the backup: the code (it is in git) and .env (keep APP_KEY somewhere safe separately:
# without APP_KEY the encrypted BVNs in a restored database cannot be read).
set -eu

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-$APP_DIR/../afisab-backups}"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"

env_file_value() { [ -f "$APP_DIR/.env" ] && grep -E "^$1=" "$APP_DIR/.env" | head -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }
val() { eval "cur=\${$1:-}"; [ -n "$cur" ] && printf '%s' "$cur" || env_file_value "$1"; }

DB_CONNECTION="$(val DB_CONNECTION)"; DB_HOST="$(val DB_HOST)"; DB_PORT="$(val DB_PORT)"
DB_DATABASE="$(val DB_DATABASE)"; DB_USERNAME="$(val DB_USERNAME)"; DB_PASSWORD="$(val DB_PASSWORD)"
DB_HOST="${DB_HOST:-127.0.0.1}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
WORK="$BACKUP_DIR/.work-$STAMP"
mkdir -p "$WORK"
trap 'rm -rf "$WORK"' EXIT

case "$DB_CONNECTION" in
  mysql|mariadb)
    DUMP="${MYSQLDUMP:-mysqldump}"
    # On servers with GTIDs the dump would carry a SET @@GLOBAL.GTID_PURGED that makes a restore fail ("must not
    # overlap with GTID_EXECUTED"). Switch it off where the option exists (MySQL; MariaDB does not have it).
    EXTRA=""
    if "$DUMP" --help 2>/dev/null | grep -q 'set-gtid-purged'; then EXTRA="--set-gtid-purged=OFF"; fi
    # shellcheck disable=SC2086
    MYSQL_PWD="$DB_PASSWORD" "$DUMP" --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
      --single-transaction --quick --routines --no-tablespaces --default-character-set=utf8mb4 $EXTRA \
      "$DB_DATABASE" > "$WORK/database.sql" ;;
  pgsql)
    DUMP="${PG_DUMP:-pg_dump}"
    PGPASSWORD="$DB_PASSWORD" "$DUMP" --host="$DB_HOST" --port="${DB_PORT:-5432}" --username="$DB_USERNAME" \
      --no-owner --format=plain "$DB_DATABASE" > "$WORK/database.sql" ;;
  *) echo "Unsupported DB_CONNECTION '$DB_CONNECTION' (use mysql, mariadb or pgsql)" >&2; exit 2 ;;
esac

[ -s "$WORK/database.sql" ] || { echo "Database dump is empty; aborting." >&2; exit 3; }

# Private files: KYC documents and payment receipts.
if [ -d "$APP_DIR/storage/app/private" ]; then
  tar -C "$APP_DIR/storage/app" -czf "$WORK/private-files.tar.gz" private
fi

ARCHIVE="$BACKUP_DIR/afisab-$STAMP.tar.gz"
tar -C "$WORK" -czf "$ARCHIVE" .
rm -rf "$WORK"

if [ -n "${GPG_RECIPIENT:-}" ]; then
  gpg --batch --yes --trust-model always --encrypt --recipient "$GPG_RECIPIENT" --output "$ARCHIVE.gpg" "$ARCHIVE"
  rm -f "$ARCHIVE"
  ARCHIVE="$ARCHIVE.gpg"
fi

chmod 600 "$ARCHIVE"
( cd "$BACKUP_DIR" && { sha256sum "$(basename "$ARCHIVE")" 2>/dev/null || shasum -a 256 "$(basename "$ARCHIVE")"; } > "$(basename "$ARCHIVE").sha256" )

# Rotation
find "$BACKUP_DIR" -maxdepth 1 -name 'afisab-*' -type f -mtime +"$KEEP_DAYS" -delete

echo "Backup written: $ARCHIVE ($(wc -c < "$ARCHIVE") bytes)"
