#!/bin/bash
# Publica FORCEX Tracking en el hosting (cPanel) desde el clon del repositorio.
#
#   cd /home/penguin2/repos/forcex-tracking && bash deploy.sh
#
# Estructura en el servidor:
#   /home/penguin2/repos/forcex-tracking   clon de GitHub (no se usa directamente)
#   /home/penguin2/tracking-forcex-app     la app: codigo, .env, storage/, vendor/
#   /home/penguin2/gsc-business.com        carpeta publica: index.php, build/, images/, flags/
#
# Nunca toca .env, storage/ (documentos subidos, logs) ni la base de datos salvo
# las migraciones. Haz un respaldo de la base en cPanel antes de un deploy grande.
set -Eeuo pipefail

# Todo va dentro de main(): bash lee la funcion completa antes de ejecutarla, asi
# el "git pull" puede actualizar este mismo archivo sin romper la ejecucion.
main() {
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="/home/penguin2/tracking-forcex-app"
PUBLIC_DIR="/home/penguin2/gsc-business.com"
PUBLIC_OVERRIDES="$REPO_DIR/deployment/gsc-business.com-package/root"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-}"
if [[ -z "$COMPOSER_BIN" ]]; then
  if command -v composer >/dev/null 2>&1; then
    COMPOSER_BIN="$(command -v composer)"
  elif [[ -x /opt/cpanel/composer/bin/composer ]]; then
    COMPOSER_BIN="/opt/cpanel/composer/bin/composer"
  fi
fi

echo "==> Actualizando el repositorio"
git -C "$REPO_DIR" pull --ff-only origin main

if [[ ! -f "$APP_DIR/.env" ]]; then
  echo "ERROR: falta $APP_DIR/.env. Crealo a partir de .env.example antes de publicar." >&2
  exit 1
fi

if [[ -z "$COMPOSER_BIN" ]]; then
  echo "ERROR: no se encontro composer. Instala dependencias con: COMPOSER_BIN=/ruta/composer bash deploy.sh" >&2
  exit 1
fi

echo "==> Modo mantenimiento"
"$PHP_BIN" "$APP_DIR/artisan" down --retry=15 || true
# Si algo falla a mitad de camino, el sitio no queda en mantenimiento.
trap 'echo "ERROR: el deploy se detuvo. Revisa el mensaje de arriba." >&2; "$PHP_BIN" "$APP_DIR/artisan" up || true' ERR

echo "==> Publicando la app en $APP_DIR"
# --delete quita archivos que ya no existen en el repo (vistas o clases borradas).
# Lo excluido (.env, storage, vendor, cache) no se toca nunca.
rsync -a --delete \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='storage/' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='public/' \
  --exclude='deployment/' \
  --exclude='bootstrap/cache/*.php' \
  --exclude='tests/' \
  --exclude='*.zip' \
  "$REPO_DIR/" "$APP_DIR/"

echo "==> Carpetas de escritura"
mkdir -p "$APP_DIR/storage/app/private" "$APP_DIR/storage/app/public" \
  "$APP_DIR/storage/framework/cache/data" "$APP_DIR/storage/framework/sessions" \
  "$APP_DIR/storage/framework/views" "$APP_DIR/storage/logs" "$APP_DIR/bootstrap/cache"
chmod -R u+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "==> Publicando la carpeta publica en $PUBLIC_DIR"
mkdir -p "$PUBLIC_DIR"
# CSS/JS compilados: se reemplaza la carpeta entera (los nombres cambian en cada build).
rsync -a --delete "$REPO_DIR/public/build/" "$PUBLIC_DIR/build/"
# Imagenes, banderas, favicon y robots. Sin --delete: la carpeta publica puede tener
# otras cosas del hosting (.well-known, cgi-bin...).
rsync -a \
  --exclude='build/' \
  --exclude='index.php' \
  --exclude='.htaccess' \
  --exclude='storage' \
  --exclude='hot' \
  "$REPO_DIR/public/" "$PUBLIC_DIR/"
# index.php y .htaccess propios del hosting (apuntan a ../tracking-forcex-app).
cp "$PUBLIC_OVERRIDES/index.php" "$PUBLIC_DIR/index.php"
cp "$PUBLIC_OVERRIDES/.htaccess" "$PUBLIC_DIR/.htaccess"
find "$PUBLIC_DIR/build" "$PUBLIC_DIR/images" "$PUBLIC_DIR/flags" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$PUBLIC_DIR/build" "$PUBLIC_DIR/images" "$PUBLIC_DIR/flags" -type f -exec chmod 644 {} \; 2>/dev/null || true

echo "==> Enlace publico de storage (logo de la empresa)"
if [[ ! -e "$PUBLIC_DIR/storage" ]]; then
  ln -s "$APP_DIR/storage/app/public" "$PUBLIC_DIR/storage"
fi

echo "==> Dependencias PHP"
"$COMPOSER_BIN" install \
  --working-dir="$APP_DIR" \
  --no-dev \
  --optimize-autoloader \
  --no-interaction

echo "==> Base de datos y caches"
"$PHP_BIN" "$APP_DIR/artisan" migrate --force
"$PHP_BIN" "$APP_DIR/artisan" optimize:clear
# Guarda configuracion, rutas y vistas ya procesadas: cada visita carga mas rapido.
# Si cambias el .env, vuelve a correr este script (o php artisan optimize).
"$PHP_BIN" "$APP_DIR/artisan" optimize

echo "==> Saliendo de mantenimiento"
"$PHP_BIN" "$APP_DIR/artisan" up

echo "==> Deploy completado"
}

main "$@"
exit