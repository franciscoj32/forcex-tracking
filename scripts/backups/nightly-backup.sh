#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "$APP_ROOT"

if [[ ! -f ".env" ]]; then
  echo "No se encontro .env en $APP_ROOT"
  exit 1
fi

eval "$(
php -r '
$keys = ["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD", "APP_NAME"];
$env = [];
foreach (file(".env", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $trim = trim($line);
    if ($trim === "" || str_starts_with($trim, "#") || !str_contains($line, "=")) {
        continue;
    }
    [$key, $value] = explode("=", $line, 2);
    $key = trim($key);
    $value = trim($value);
    if ((str_starts_with($value, "\"") && str_ends_with($value, "\"")) || (str_starts_with($value, "'\''") && str_ends_with($value, "'\''"))) {
        $value = substr($value, 1, -1);
    }
    $env[$key] = $value;
}
foreach ($keys as $key) {
    echo $key . "=" . escapeshellarg($env[$key] ?? "") . PHP_EOL;
}
'
)"

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_ROOT="$APP_ROOT/storage/app/backups"
WORK_DIR="$BACKUP_ROOT/$STAMP"
ARCHIVE_PATH="$BACKUP_ROOT/${STAMP}.tar.gz"
KEEP_BACKUPS="${KEEP_BACKUPS:-7}"

mkdir -p "$WORK_DIR/db" "$WORK_DIR/files"

echo "Creando dump de base de datos..."
mysqldump \
  --host="$DB_HOST" \
  --port="${DB_PORT:-3306}" \
  --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  "$DB_DATABASE" > "$WORK_DIR/db/database.sql"

echo "Copiando archivos criticos..."
cp .env "$WORK_DIR/.env"
cp composer.lock "$WORK_DIR/composer.lock"

if [[ -d "storage/app" ]]; then
  mkdir -p "$WORK_DIR/files/storage"
  cp -R "storage/app" "$WORK_DIR/files/storage/"
fi

if [[ -d "public/build" ]]; then
  mkdir -p "$WORK_DIR/files/public"
  cp -R "public/build" "$WORK_DIR/files/public/"
fi

cat > "$WORK_DIR/backup-info.txt" <<EOF
app_name=${APP_NAME:-tracking-forcex}
backup_created_at=$STAMP
db_host=$DB_HOST
db_port=${DB_PORT:-3306}
db_database=$DB_DATABASE
app_root=$APP_ROOT
EOF

echo "Comprimiendo backup..."
tar -czf "$ARCHIVE_PATH" -C "$BACKUP_ROOT" "$STAMP"
rm -rf "$WORK_DIR"

echo "Aplicando retencion..."
find "$BACKUP_ROOT" -maxdepth 1 -type f -name "*.tar.gz" | sort | head -n -"${KEEP_BACKUPS}" 2>/dev/null | xargs -r rm -f

echo "Backup listo: $ARCHIVE_PATH"
