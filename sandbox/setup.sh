#!/bin/sh
# Ptah contribution sandbox — creates a fresh Laravel app wired to the local
# package source (path repository with symlink) and serves it on :8000.
#
# Idempotent: re-running the container reuses the app created in the volume.
set -e

PTAH_SRC=/ptah
APP_DIR=/app

# ── 1. Fresh Laravel app (first run only) ────────────────────────────────────
if [ ! -f "$APP_DIR/composer.json" ]; then
    echo "==> Creating fresh Laravel app..."
    composer create-project laravel/laravel "$APP_DIR" --no-interaction
fi

cd "$APP_DIR"

# ── 2. Wire the local package (symlinked — host edits apply instantly) ──────
if ! grep -q '"jonytonet/ptah"' composer.json; then
    echo "==> Requiring local jonytonet/ptah via path repository..."
    composer config repositories.ptah '{"type": "path", "url": "/ptah", "options": {"symlink": true}}'
    composer config minimum-stability dev
    composer config prefer-stable true
    composer require jonytonet/ptah:@dev --no-interaction
fi

# ── 3. SQLite (zero config) ──────────────────────────────────────────────────
touch database/database.sqlite
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
sed -i '/^DB_HOST=/d;/^DB_PORT=/d;/^DB_DATABASE=/d;/^DB_USERNAME=/d;/^DB_PASSWORD=/d' .env

# ── 4. Install Ptah + modules (first run only) ───────────────────────────────
if [ ! -f config/ptah.php ]; then
    echo "==> Running ptah:install (npm skipped — CLI-only image)..."
    php artisan ptah:install --skip-npm --no-interaction

    echo "==> Enabling modules..."
    php artisan ptah:module auth --no-interaction
    php artisan ptah:module menu --no-interaction
    php artisan ptah:module permissions --no-interaction

    echo "==> Forging a sample entity..."
    php artisan ptah:forge Task \
        --fields="title:string,done:boolean,due_at:datetime:nullable" \
        --no-interaction

    php artisan migrate --no-interaction
    php artisan ptah:menu-sync --fresh --no-interaction
fi

# ── 5. Serve ─────────────────────────────────────────────────────────────────
echo ""
echo "=============================================================="
echo " Sandbox ready: http://localhost:8000/login"
echo " Admin credentials were printed by ptah:module permissions"
echo " above (random password — scroll up and copy it)."
echo "=============================================================="
echo ""
php artisan serve --host=0.0.0.0 --port=8000
