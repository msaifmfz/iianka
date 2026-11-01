#!/usr/bin/env bash
# CI only: boot the exact unpacked production-vendor payload against synthetic SQLite.
set -euo pipefail
archive="${1:?Expected archive}"
revision="${2:?Expected revision}"
workspace="$(mktemp -d)"
trap 'rm -rf "$workspace"' EXIT
root="$workspace/staging"
release="$root/releases/$revision"
mkdir -p "$release" "$root/shared/database" "$root/shared/storage/app/public" \
    "$root/shared/storage/framework/"{cache/data,sessions,views} "$root/shared/storage/logs"
python3 .github/deploy/artifact.py extract "$archive" "$release" "$revision"
mkdir -p "$release/bootstrap/cache/views"
ln -s "$root/shared/storage" "$release/storage"
touch "$root/shared/database/database.sqlite"
export APP_ENV=testing APP_DEBUG=false APP_URL=http://localhost APP_LOCALE=ja APP_FALLBACK_LOCALE=ja
APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
export APP_KEY
export DB_CONNECTION=sqlite DB_DATABASE="$root/shared/database/database.sqlite" DB_URL=''
export CACHE_STORE=array SESSION_DRIVER=array MAIL_MAILER=array QUEUE_CONNECTION=sync LOG_CHANNEL=null
export VIEW_COMPILED_PATH="$release/bootstrap/cache/views"
export LARAVEL_STORAGE_PATH="$root/shared/storage"
export APP_CONFIG_CACHE="$release/bootstrap/cache/config.php"
export APP_ROUTES_CACHE="$release/bootstrap/cache/routes-v7.php"
export APP_EVENTS_CACHE="$release/bootstrap/cache/events.php"
export APP_SERVICES_CACHE="$release/bootstrap/cache/services.php"
export APP_PACKAGES_CACHE="$release/bootstrap/cache/packages.php"
(
    cd "$release"
    php -d memory_limit=128M artisan package:discover --no-interaction
    php -d memory_limit=128M artisan config:cache --no-interaction
    php -d memory_limit=128M artisan route:cache --no-interaction
    php -d memory_limit=128M artisan view:cache --no-interaction
    php -d memory_limit=128M artisan event:cache --no-interaction
    php -d memory_limit=128M artisan migrate --force --no-interaction
    php -d memory_limit=128M artisan migrate:status --pending=17 --no-interaction
)
php -d memory_limit=128M .github/deploy/runtime.php "$release" "$root"
mkdir -p "$workspace/wrong-environment/shared/database"
touch "$workspace/wrong-environment/shared/database/database.sqlite"
if php -d memory_limit=128M .github/deploy/runtime.php "$release" "$workspace/wrong-environment"; then
    echo 'Runtime incorrectly accepted a different environment database.' >&2
    exit 1
fi
printf 'Unpacked production release booted successfully.\n'
