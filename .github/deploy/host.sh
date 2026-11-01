#!/usr/bin/env bash
# Installed root-owned; the launcher runs this as iianka inside a bounded cgroup.
set -Eeuo pipefail
umask 0027

operation="${1:?Expected operation}"
environment="${2:?Expected environment}"
release_id="${3:?Expected release identity}"
revision="${4:?Expected revision}"
checksum="${5:?Expected archive checksum}"
[[ "$operation" =~ ^(prepare|activate|rollback)$ ]]
[[ "$environment" =~ ^(staging|production)$ ]]
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
[[ "$release_id" =~ ^${revision}-[0-9]+-[0-9]+$ ]]
[[ "$checksum" =~ ^[0-9a-f]{64}$ ]]

base="${IIANKA_DEPLOY_BASE:-/var/www/iianka}"
tools="$(cd "$(dirname "$0")" && pwd)"
root="$base/$environment"
release="$root/releases/$release_id"
services="${IIANKA_DEPLOY_LAUNCHER:-/usr/local/sbin/iianka-artifact-deploy}"
: "${IIANKA_DEPLOY_HEALTH_URL:?Launcher must provide the approved health URL}"
: "${IIANKA_DEPLOY_MIN_FREE_BYTES:?Launcher must provide the approved disk floor}"
[[ "$IIANKA_DEPLOY_MIN_FREE_BYTES" =~ ^[0-9]+$ ]]

fail() { printf '%s\n' "$*" >&2; exit 1; }
# All environments share one lock, including transfer, preparation and cutover.
exec 9>"$base/.artifact-deploy.lock"
flock -n 9 || fail 'Another deployment is running on this host.'
[[ -L "$root/current" ]] || fail 'current must be a symlink; in-place checkout is unsupported.'
previous="$(readlink -f "$root/current")"
[[ "$(dirname "$previous")" == "$root/releases" && -d "$previous" ]] || fail 'Unexpected current target.'
[[ ! -L "$root/releases" && ! -L "$root/shared" ]] || fail 'Release/shared roots must be real directories.'
[[ ! -L "$root/shared/.env" && ! -L "$root/shared/storage" && ! -L "$root/shared/database" ]] || fail 'Shared paths must not point into another environment.'
[[ -f "$root/shared/.env" && -d "$root/shared/storage" && -f "$root/shared/database/database.sqlite" ]] || fail 'Reviewed shared paths are missing.'
free_bytes="$(df -Pk "$root" | awk 'NR==2 { printf "%.0f", $4 * 1024 }')"
(( free_bytes >= IIANKA_DEPLOY_MIN_FREE_BYTES )) || fail 'Insufficient approved free disk headroom.'

php_command() { php -d memory_limit=128M "$@"; }
boot() { php_command "$tools/runtime.php" "$1" "$root"; }
service_action() {
    if [[ "$1" == services-start ]]; then
        sudo -n "$services" "$1" "$environment" "$queue_active" "$scheduler_active"
    else
        sudo -n "$services" "$1" "$environment"
    fi
}
health() { curl --fail --silent --show-error --max-time 15 "$IIANKA_DEPLOY_HEALTH_URL" >/dev/null; }
point_current() {
    local target="$1" temporary="$root/current.$release_id.$$"
    ln -s "$target" "$temporary"
    mv -Tf "$temporary" "$root/current"
}

if [[ "$operation" == prepare ]]; then
    (( free_bytes >= IIANKA_DEPLOY_MIN_FREE_BYTES + 2147483648 )) || fail 'Insufficient disk space for bounded preparation plus the approved reserve.'
    [[ ! -e "$release" && ! -L "$release" ]] || fail 'Release identity already exists; use a new run attempt.'
    archive="$(mktemp "$root/shared/deploy-incoming/$release_id.XXXXXX")"
    trap 'rm -f "$archive"' EXIT
    # Bound compressed input as well as extracted size; no unbounded SSH upload.
    head -c 268435457 >"$archive"
    (( $(wc -c <"$archive") <= 268435456 )) || fail 'Archive exceeds 256 MiB.'
    [[ "$(sha256sum "$archive" | cut -d ' ' -f1)" == "$checksum" ]] || fail 'Archive checksum mismatch.'
    mkdir "$release"
    python3 "$tools/artifact.py" extract "$archive" "$release" "$revision"
    python3 "$tools/artifact.py" carry-assets "$previous" "$release"
    mkdir -p "$release/bootstrap/cache/views"
    ln -s "$root/shared/.env" "$release/.env"
    ln -s "$root/shared/storage" "$release/storage"
    ln -s "$root/shared/storage/app/public" "$release/public/storage"
    cd "$release"
    # view:cache invokes view:clear. Isolate compiled views from the serving release.
    export VIEW_COMPILED_PATH="$release/bootstrap/cache/views"
    export APP_CONFIG_CACHE="$release/bootstrap/cache/config.php"
    export APP_ROUTES_CACHE="$release/bootstrap/cache/routes-v7.php"
    export APP_EVENTS_CACHE="$release/bootstrap/cache/events.php"
    export APP_SERVICES_CACHE="$release/bootstrap/cache/services.php"
    export APP_PACKAGES_CACHE="$release/bootstrap/cache/packages.php"
    export LARAVEL_STORAGE_PATH="$root/shared/storage"
    php_command artisan package:discover --no-interaction
    php_command artisan config:cache --no-interaction
    php_command artisan route:cache --no-interaction
    php_command artisan view:cache --no-interaction
    php_command artisan event:cache --no-interaction
    boot "$release"
    chmod -R g+rX,o-rwx "$release/bootstrap/cache"
    # Apply only the owner's existing file-context mappings; no policy changes.
    sudo -n "$services" labels "$environment" "$release_id" "$revision" "$checksum"
    # Store the exact payload for owner-controlled off-host archival/recovery.
    install -m 0640 "$archive" "$root/shared/deploy-artifacts/$release_id.tar.gz"
    printf '%s\n' "$checksum" >"$release/ARTIFACT_SHA256"
    printf '%s\n' "$revision" >"$release/READY"
    printf 'Prepared %s; current was not changed.\n' "$release_id"
    exit 0
fi

[[ ! -L "$release" && -f "$release/READY" && -f "$release/ARTIFACT_SHA256" ]] || fail 'Release preparation did not complete.'
[[ "$(cat "$release/READY")" == "$revision" && "$(cat "$release/ARTIFACT_SHA256")" == "$checksum" ]] || fail 'Prepared release identity mismatch.'
[[ "$(cat "$release/REVISION")" == "$revision" ]] || fail 'Release revision mismatch.'

target="$release"
if [[ "$operation" == rollback ]]; then
    [[ "$previous" == "$release" && -f "$release/PREVIOUS" ]] || fail 'Rollback must name the current artifact release.'
    target="$(cat "$release/PREVIOUS")"
    [[ "$(dirname "$target")" == "$root/releases" && -d "$target" && ! -L "$target" ]] || fail 'Previous release is unavailable.'
else
    [[ "$previous" != "$release" ]] || fail 'Release is already current.'
    [[ ! -e "$release/PREVIOUS" ]] || fail 'Activation was already attempted; review recovery before retrying.'
fi
boot "$target"
# Laravel 13 returns 17 for pending migrations with this explicit option.
# Never migrate automatically: schema work requires an owner-run maintenance window.
(cd "$target" && php_command artisan migrate:status --pending=17 --no-interaction) || fail 'Pending migrations or migration-status failure; current was not changed.'
[[ ! -e "$root/shared/storage/framework/down" ]] || fail 'Environment is already in maintenance; owner recovery is required.'

service_state="$(service_action services-state)"
[[ "$service_state" =~ ^[01]\ [01]$ ]] || fail 'Unexpected worker state.'
read -r queue_active scheduler_active <<<"$service_state"
maintenance=no
switched=no
recover() {
    local result=$?
    trap - EXIT
    if (( result != 0 )) && [[ "$maintenance" == yes ]]; then
        printf 'Activation failed; recovering the previous code release.\n' >&2
        # No migrations were run, so recovery does not guess at database rollback.
        if service_action services-stop \
            && { [[ "$switched" == no ]] || point_current "$previous"; } \
            && boot "$previous" \
            && service_action services-start \
            && (cd "$previous" && php_command artisan up --no-interaction) \
            && health; then
            printf 'Previous release restored. Deployment still failed.\n' >&2
        else
            (cd "$root/current" && php_command artisan down --retry=60 --no-interaction) || true
            printf 'Recovery incomplete; maintenance must remain on for owner intervention.\n' >&2
        fi
    fi
    exit "$result"
}
trap recover EXIT
if [[ "$operation" == activate ]]; then
    (set -o noclobber; printf '%s\n' "$previous" >"$release/PREVIOUS")
    (set -o noclobber; printf '%s\n' "$service_state" >"$release/SERVICES_PREVIOUS")
fi
maintenance=yes
(cd "$previous" && php_command artisan down --retry=60 --no-interaction)
service_action services-stop
point_current "$target"
switched=yes
boot "$target"
health
service_action services-start
(cd "$target" && php_command artisan up --no-interaction)
health
maintenance=no
printf '%s succeeded: %s\n' "$operation" "$(basename "$target")"
