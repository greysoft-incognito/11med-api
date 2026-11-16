#!/usr/bin/env bash
# Runs on the server inside the app's git clone: checks out the release, installs, migrates, caches.
# Usage: deploy-remote.sh <app path> <branch> <commit> [php binary] [composer command]

set -euo pipefail

APP_PATH="${1:?app path required}"
BRANCH="${2:?branch required}"
COMMIT="${3:?commit required}"
PHP_BIN="${4:-php}"
COMPOSER_CMD="${5:-}"

cd "$APP_PATH"

if [ ! -d .git ]; then
    echo "$APP_PATH is not a git clone." >&2
    exit 1
fi

if [ ! -f .env ]; then
    echo "No .env in $APP_PATH; create it on the server before deploying." >&2
    exit 1
fi

if [ -z "$COMPOSER_CMD" ]; then
    COMPOSER_CMD="$PHP_BIN $(command -v composer)"
fi

artisan() {
    "$PHP_BIN" artisan "$@" --no-interaction
}

artisan down --retry=15 || true
trap 'artisan up || true' EXIT

git fetch --prune origin "$BRANCH"
git checkout --force -B "$BRANCH" "$COMMIT"
rm -f REVISION

# Cached manifests can name packages the new vendor no longer has, which stops artisan from booting.
rm -f bootstrap/cache/*.php
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

$COMPOSER_CMD install --no-dev --prefer-dist --optimize-autoloader --no-progress --no-interaction

artisan migrate --force
artisan app:sync-roles
artisan storage:link || true
artisan config:cache
artisan route:cache
artisan event:cache
artisan queue:restart

echo "Deployed $(git rev-parse --short HEAD) ($BRANCH) to $APP_PATH"
