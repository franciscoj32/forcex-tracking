#!/bin/bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PUBLIC_DIR="/home/penguin2/gsc-business.com"
APP_DIR="/home/penguin2/tracking-forcex-app"
PUBLIC_SRC="$REPO_DIR/deployment/gsc-business.com-package/root/"
APP_SRC="$REPO_DIR/deployment/gsc-business.com-package/tracking-forcex-app/"

echo "==> Updating repository"
git -C "$REPO_DIR" pull --ff-only origin main

echo "==> Publishing public root"
rsync -av "$PUBLIC_SRC" "$PUBLIC_DIR/"

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
