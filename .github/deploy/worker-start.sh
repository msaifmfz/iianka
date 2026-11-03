#!/bin/bash
# Install root-owned as /usr/local/sbin/iianka-worker-start (default label bin_t).
#
# systemd changes into WorkingDirectory before exec, while still in init_t, and
# SELinux denies init_t reading the httpd_sys_content_t `current` symlink. Units
# therefore use the real environment root as WorkingDirectory and exec this
# entrypoint. After the exec transition it resolves `current`, validates that it
# names one of this environment's releases, enters it and execs PHP, so every
# worker start (including after rollback) runs the release current names then.
set -euo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin

fail() {
    printf 'iianka-worker-start: %s\n' "$*" >&2
    # EX_CONFIG. Restart=always retries, so a corrected current recovers on its own.
    exit 78
}

[[ "$EUID" != 0 ]] || fail 'Refusing to run application code as root.'
[[ "$(id -un)" == iianka && "$(id -gn)" == nginx ]] || fail 'Workers must run as iianka:nginx.'
[[ $# -ge 2 ]] || fail 'Usage: iianka-worker-start ENVIRONMENT queue:work|schedule:work [ARGUMENT...]'
environment="$1"
shift
[[ "$environment" =~ ^(staging|production)$ ]] || fail 'Unknown environment.'
[[ "$1" =~ ^(queue:work|schedule:work)$ ]] || fail 'Only queue:work and schedule:work may be started.'

base=/var/www/iianka
root="$base/$environment"
releases="$root/releases"
for directory in "$root" "$releases"; do
    [[ -d "$directory" && ! -L "$directory" && "$(readlink -f "$directory")" == "$directory" ]] \
        || fail "$directory must be a real directory."
done
[[ -L "$root/current" ]] || fail 'current must be a symlink; in-place checkouts are unsupported.'
release="$(readlink -e "$root/current")" || fail 'current is missing or broken.'
[[ "$(dirname "$release")" == "$releases" && -d "$release" ]] \
    || fail "current must name a release of $environment, not $release."
[[ -f "$release/artisan" && ! -L "$release/artisan" ]] || fail "$release has no artisan entrypoint."
cd -P "$release"
[[ "$(pwd -P)" == "$release" ]] || fail 'Release changed while entering it.'

context="$(tr -d '\0' 2>/dev/null </proc/self/attr/current)" || context=unknown
printf 'iianka-worker-start: %s %s from release %s (security context %s)\n' \
    "$environment" "$1" "$(basename "$release")" "${context:-unknown}" >&2
exec /usr/bin/php artisan "$@"
