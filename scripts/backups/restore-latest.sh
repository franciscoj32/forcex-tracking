#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-$(cd "$(dirname "$0")/../.." && pwd)}"
cd "$APP_ROOT"

BACKUP_ROOT="$APP_ROOT/storage/app/backups"
LATEST_ARCHIVE="${2:-$(find "$BACKUP_ROOT" -maxdepth 1 -type f -name '*.tar.gz' | sort | tail -n 1)}"

if [[ -z "${LATEST_ARCHIVE:-}" || ! -f "$LATEST_ARCHIVE" ]]; then
  echo "No se encontro backup para restaurar."
  exit 1
fi

if [[ ! -f ".env" ]]; then
  echo "No se encontro .env en $APP_ROOT"
  exit 1
fi

eval "$(
php -r '
$keys = ["DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"];
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

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

echo "Extrayendo backup $LATEST_ARCHIVE ..."
tar -xzf "$LATEST_ARCHIVE" -C "$TMP_DIR"

BACKUP_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)"

if [[ ! -f "$BACKUP_DIR/db/database.sql" ]]; then
  echo "El backup no contiene database.sql"
  exit 1
fi

echo "Restaurando base de datos..."
mysql \
  --host="$DB_HOST" \
  --port="${DB_PORT:-3306}" \
  --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" \
  "$DB_DATABASE" < "$BACKUP_DIR/db/database.sql"

if [[ -d "$BACKUP_DIR/files/storage/app" ]]; then
  echo "Restaurando storage/app ..."
  rm -rf "$APP_ROOT/storage/app"
  mkdir -p "$APP_ROOT/storage"
  cp -R "$BACKUP_DIR/files/storage/app" "$APP_ROOT/storage/"
fi

if [[ -d "$BACKUP_DIR/files/public/build" ]]; then
  echo "Restaurando public/build ..."
  rm -rf "$APP_ROOT/public/build"
  mkdir -p "$APP_ROOT/public"
  cp -R "$BACKUP_DIR/files/public/build" "$APP_ROOT/public/"
fi

if [[ -f "$BACKUP_DIR/.env" ]]; then
  echo "Respaldando .env actual y restaurando .env del backup ..."
  cp "$APP_ROOT/.env" "$APP_ROOT/.env.before-restore.$(date +%Y%m%d_%H%M%S)"
  cp "$BACKUP_DIR/.env" "$APP_ROOT/.env"
fi

echo "Restore completado. Ejecuta despues:"
echo "php artisan optimize:clear"
echo "php artisan optimize"
