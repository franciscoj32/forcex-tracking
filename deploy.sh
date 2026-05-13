#!/bin/bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC_DIR="/home/penguin2/gsc-business.com"
APP_DIR="/home/penguin2/tracking-forcex-app"
PUBLIC_SRC="$REPO_DIR/deployment/gsc-business.com-package/root/"
APP_SRC="$REPO_DIR/deployment/gsc-business.com-package/tracking-forcex-app/"
FLAGS_SRC="$REPO_DIR/public/vendor/flag-icons/"
FLAGS_DEST="$PUBLIC_DIR/flags/flag-icons"

echo "==> Updating repository"
git -C "$REPO_DIR" pull --ff-only origin main

echo "==> Publishing public root"
rsync -av "$PUBLIC_SRC" "$PUBLIC_DIR/"

if [[ -d "$FLAGS_SRC" ]]; then
  echo "==> Publishing public flags override"
  mkdir -p "$FLAGS_DEST"
  rsync -av --delete "$FLAGS_SRC" "$FLAGS_DEST/"

  echo "==> Setting flags permissions"
  find "$PUBLIC_DIR/flags" -type d -exec chmod 755 {} \;
  find "$PUBLIC_DIR/flags" -type f -exec chmod 644 {} \;
else
  echo "==> Flags source not found, skipping public flags override"
fi

echo "==> Publishing Laravel app"
rsync -av \
  --exclude='.env' \
  --exclude='.env.hosting' \
  --exclude='storage/' \
  --exclude='vendor/' \
  "$APP_SRC" "$APP_DIR/"

if command -v composer >/dev/null 2>&1; then
  echo "==> Installing PHP dependencies"
  composer install \
    --working-dir="$APP_DIR" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction
else
  echo "==> Composer not found, skipping dependency install"
fi

echo "==> Running Laravel maintenance commands"
php "$APP_DIR/artisan" migrate --force
php "$APP_DIR/artisan" optimize:clear

echo "==> Deploy completed"
