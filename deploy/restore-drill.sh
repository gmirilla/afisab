#!/bin/sh
# Restore drill: proves a backup can actually be restored. Restores into a SCRATCH database (never the live one),
# then checks that every table has the same number of rows as the source and that the ledger still balances.
# It FAILS CLOSED: anything it cannot read counts as a failure, never as a pass.
# Do this before launch and then every quarter.   Usage:   sh restore-drill.sh /path/to/afisab-YYYYMMDD-HHMMSS.tar.gz
#
# MySQL/MariaDB only. Needs an account allowed to create databases. Settings from the environment (or .env):
#   DB_HOST DB_PORT DB_USERNAME DB_PASSWORD DB_DATABASE (the source) and RESTORE_DB (default afisab_restore_drill)
set -eu

ARCHIVE="${1:?Give the backup archive (.tar.gz, already decrypted) as the first argument}"
APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
env_file_value() { [ -f "$APP_DIR/.env" ] && grep -E "^$1=" "$APP_DIR/.env" | head -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' || true; }
val() { eval "cur=\${$1:-}"; [ -n "$cur" ] && printf '%s' "$cur" || env_file_value "$1"; }

DB_HOST="$(val DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"; DB_PORT="$(val DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="$(val DB_USERNAME)"; DB_PASSWORD="$(val DB_PASSWORD)"; SOURCE_DB="$(val DB_DATABASE)"
RESTORE_DB="${RESTORE_DB:-afisab_restore_drill}"
MYSQL="${MYSQL:-mysql}"
export MYSQL_PWD="$DB_PASSWORD"

# q: run SQL. qs: same, but for SELECTs; it strips Windows carriage returns (octal 015) from the client output.
q() { "$MYSQL" --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --batch --skip-column-names "$@"; }
qs() { q "$@" | tr -d '\015'; }

[ "$RESTORE_DB" != "$SOURCE_DB" ] || { echo "Refusing: the restore target must not be the live database." >&2; exit 2; }

WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
tar -xzf "$ARCHIVE" -C "$WORK"
[ -s "$WORK/database.sql" ] || { echo "No database.sql in the archive." >&2; exit 3; }

echo "Restoring into scratch database $RESTORE_DB ..."
q -e "DROP DATABASE IF EXISTS \`$RESTORE_DB\`; CREATE DATABASE \`$RESTORE_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
fail=0
q "$RESTORE_DB" < "$WORK/database.sql" || { echo "  The dump did not load cleanly."; fail=1; }

tables="$(qs -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$SOURCE_DB' AND table_type='BASE TABLE' ORDER BY 1")"
[ -n "$tables" ] || { echo "  Could not list the live tables."; fail=1; }

echo "Comparing row counts with $SOURCE_DB ..."
checked=0
for t in $tables; do
  a="$(qs -e "SELECT COUNT(*) FROM \`$SOURCE_DB\`.\`$t\`" 2>/dev/null || true)"
  b="$(qs -e "SELECT COUNT(*) FROM \`$RESTORE_DB\`.\`$t\`" 2>/dev/null || true)"
  # A count that is not a number means the query failed: never let two failures compare as "equal".
  case "$a" in ''|*[!0-9]*) echo "  COULD NOT COUNT live table $t"; fail=1; continue ;; esac
  case "$b" in ''|*[!0-9]*) echo "  TABLE MISSING OR UNREADABLE after restore: $t"; fail=1; continue ;; esac
  if [ "$a" != "$b" ]; then echo "  MISMATCH $t: live=$a restored=$b"; fail=1; fi
  checked=$((checked + 1))
done
echo "  $checked tables compared."

debits="$(qs -e "SELECT COALESCE(SUM(amount),0) FROM \`$RESTORE_DB\`.journal_lines WHERE direction='debit'" 2>/dev/null || true)"
credits="$(qs -e "SELECT COALESCE(SUM(amount),0) FROM \`$RESTORE_DB\`.journal_lines WHERE direction='credit'" 2>/dev/null || true)"
echo "Restored ledger: debits=$debits credits=$credits"
case "$debits$credits" in ''|*[!0-9]*) echo "  COULD NOT READ LEDGER TOTALS"; fail=1 ;; esac
[ "$debits" = "$credits" ] || { echo "  LEDGER DOES NOT BALANCE"; fail=1; }

if [ -f "$WORK/private-files.tar.gz" ]; then echo "Private files archive present: $(tar -tzf "$WORK/private-files.tar.gz" | wc -l) entries"; else echo "  (no private files in this backup)"; fi

q -e "DROP DATABASE IF EXISTS \`$RESTORE_DB\`;"
if [ "$fail" = 0 ]; then echo "RESTORE DRILL PASSED"; else echo "RESTORE DRILL FAILED"; exit 1; fi
