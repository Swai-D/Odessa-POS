#!/bin/sh
# Logical backup of the Odessa POS PostgreSQL database.
#
#   DATABASE_URL="postgresql://user:pass@host:5432/db" scripts/backup-postgres.sh [output-dir] [keep-days]
#
# Writes odessa-pos-YYYYmmdd-HHMMSS.sql.gz into output-dir (default ./backups) and deletes dumps older than
# keep-days (default 14). Needs pg_dump of version 16 or newer (the same major version as the server).
# Store the files somewhere other than the server that runs the database. The dump holds every shop's data:
# treat it like a password.
set -eu

: "${DATABASE_URL:?Set DATABASE_URL to the PostgreSQL connection URL}"

dir="${1:-./backups}"
keep="${2:-14}"
stamp="$(date -u +%Y%m%d-%H%M%S)"
file="${dir}/odessa-pos-${stamp}.sql.gz"

mkdir -p "$dir"
umask 077

# --no-owner/--no-privileges so the dump restores into a database with a different role name.
pg_dump --no-owner --no-privileges --format=plain "$DATABASE_URL" | gzip -9 > "${file}.partial"
mv "${file}.partial" "$file"

# A dump of a few bytes means something went wrong even though pg_dump exited cleanly.
size="$(wc -c < "$file")"
if [ "$size" -lt 1024 ]; then
  echo "Backup ${file} is only ${size} bytes: treating it as failed" >&2
  exit 1
fi

find "$dir" -name 'odessa-pos-*.sql.gz' -type f -mtime "+${keep}" -delete

echo "Backup written: ${file} (${size} bytes)"
