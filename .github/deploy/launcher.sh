#!/bin/bash
# Install root-owned as /usr/local/sbin/iianka-artifact-deploy.
# No repository-provided program or argument is ever executed as root.
set -euo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
[[ "$EUID" == 0 ]] || { echo 'Use the owner-installed sudo launcher.' >&2; exit 1; }
[[ $# -ge 2 ]] || exit 1
operation="$1"
environment="$2"
[[ "$environment" =~ ^(staging|production)$ ]] || exit 1

if [[ "$operation" =~ ^services-(stop|start|state)$ ]]; then
    [[ $# == 2 || ( "$operation" == services-start && $# == 4 ) ]] || exit 1
    for service in queue scheduler; do
        unit="iianka-$service@$environment"
        [[ "$(systemctl show "$unit" -p User --value)" == iianka ]]
        [[ "$(systemctl show "$unit" -p Group --value)" == nginx ]]
        [[ "$(systemctl show "$unit" -p WorkingDirectory --value)" == "/var/www/iianka/$environment/current" ]]
    done
    case "$operation" in
        services-state)
            queue_active=0; scheduler_active=0
            if systemctl is-active --quiet "iianka-queue@$environment"; then queue_active=1; fi
            if systemctl is-active --quiet "iianka-scheduler@$environment"; then scheduler_active=1; fi
            printf '%s %s\n' "$queue_active" "$scheduler_active"
            exit 0
            ;;
        services-stop)
            exec systemctl stop "iianka-queue@$environment" "iianka-scheduler@$environment"
            ;;
        services-start)
            [[ $# == 4 && "$3" =~ ^[01]$ && "$4" =~ ^[01]$ ]] || exit 1
            units=()
            if [[ "$3" == 1 ]]; then units+=("iianka-queue@$environment"); fi
            if [[ "$4" == 1 ]]; then units+=("iianka-scheduler@$environment"); fi
            if (( ${#units[@]} )); then exec systemctl start "${units[@]}"; fi
            exit 0
            ;;
    esac
fi
[[ $# == 5 && "$operation" =~ ^(prepare|activate|rollback|labels)$ ]] || exit 1
release_id="$3"
revision="$4"
checksum="$5"
[[ "$revision" =~ ^[0-9a-f]{40}$ && "$release_id" =~ ^${revision}-[0-9]+-[0-9]+$ && "$checksum" =~ ^[0-9a-f]{64}$ ]] || exit 1

trusted_owner_and_mode() {
    [[ ! -L "$1" && "$(stat -c %u "$1")" == 0 ]] || return 1
    (( (8#$(stat -c %a "$1") & 8#022) == 0 ))
}
for directory in / /etc /etc/iianka-artifact-deploy; do
    if [[ ! -d "$directory" ]] || ! trusted_owner_and_mode "$directory"; then
        echo 'Deploy configuration ancestors must be real, root-owned, non-writable directories.' >&2
        exit 1
    fi
done
configuration="/etc/iianka-artifact-deploy/$environment.conf"
if [[ ! -f "$configuration" ]] || ! trusted_owner_and_mode "$configuration"; then
    echo 'Owner-reviewed host readiness configuration missing or unsafe.' >&2
    exit 1
fi
# Treat readiness settings as data, never shell code. Do not inherit missing keys.
unset MEMORY_MAX_BYTES MIN_AVAILABLE_BYTES MIN_FREE_DISK_BYTES HEALTH_URL
seen_keys=' '
while IFS= read -r setting || [[ -n "$setting" ]]; do
    [[ "$setting" =~ ^[[:space:]]*(#.*)?$ ]] && continue
    [[ "$setting" =~ ^(MEMORY_MAX_BYTES|MIN_AVAILABLE_BYTES|MIN_FREE_DISK_BYTES|HEALTH_URL)=(.+)$ ]] || {
        echo 'Readiness configuration requires literal KEY=value settings.' >&2
        exit 1
    }
    key="${BASH_REMATCH[1]}" value="${BASH_REMATCH[2]}"
    [[ "$seen_keys" != *" $key "* ]] || { echo 'Duplicate readiness setting.' >&2; exit 1; }
    seen_keys+="$key "
    printf -v "$key" '%s' "$value"
done < "$configuration"
: "${MEMORY_MAX_BYTES:?Owner must choose a memory ceiling}"
: "${MIN_AVAILABLE_BYTES:?Owner must choose free memory headroom}"
: "${MIN_FREE_DISK_BYTES:?Owner must choose a disk floor}"
: "${HEALTH_URL:?Owner must choose the HTTPS health URL}"
for number in "$MEMORY_MAX_BYTES" "$MIN_AVAILABLE_BYTES" "$MIN_FREE_DISK_BYTES"; do
    [[ "$number" =~ ^[1-9][0-9]{0,11}$ ]] || exit 1
done
[[ "$HEALTH_URL" =~ ^https://[a-zA-Z0-9.-]+/up$ ]] || exit 1
(( MEMORY_MAX_BYTES >= 67108864 && MEMORY_MAX_BYTES <= 1073741824 )) || exit 1
(( MIN_AVAILABLE_BYTES >= MEMORY_MAX_BYTES && MIN_FREE_DISK_BYTES >= 2147483648 )) || exit 1
available="$(awk '/^MemAvailable:/ { printf "%.0f", $2 * 1024 }' /proc/meminfo)"
(( available >= MIN_AVAILABLE_BYTES )) || { echo 'Insufficient owner-approved memory headroom.' >&2; exit 1; }
[[ "$(stat -fc %T /sys/fs/cgroup)" == cgroup2fs ]] || { echo 'cgroup v2 is required.' >&2; exit 1; }
# A single slice bounds aggregate staging/production deploy memory, not each process alone.
slice_max="$(systemctl show iianka-deploy.slice -p MemoryMax --value)"
if [[ ! "$slice_max" =~ ^[1-9][0-9]*$ ]] || (( slice_max > MEMORY_MAX_BYTES )); then
    echo 'Finite approved deploy slice is missing.' >&2
    exit 1
fi
[[ "$(systemctl show iianka-deploy.slice -p MemorySwapMax --value)" == 0 ]] || exit 1

if [[ "$operation" == labels ]]; then
    release="/var/www/iianka/$environment/releases/$release_id"
    [[ ! -L "$release" && "$(readlink -f "$release")" == "$release" ]]
    [[ "$(readlink -f "/var/www/iianka/$environment/current")" != "$release" ]]
    exec restorecon -RF -x "$release"
fi

exec systemd-run --quiet --wait --pipe --collect --service-type=exec \
    --uid=iianka --gid=nginx --slice=iianka-deploy.slice \
    --property=MemoryMax="$MEMORY_MAX_BYTES" \
    --property=MemorySwapMax=0 --property=OOMPolicy=stop --property=KillMode=control-group \
    --property=RuntimeMaxSec=900 --property=TimeoutStopSec=30 \
    --setenv=PATH=/usr/sbin:/usr/bin:/sbin:/bin \
    --setenv=IIANKA_DEPLOY_BASE=/var/www/iianka \
    --setenv=IIANKA_DEPLOY_LAUNCHER=/usr/local/sbin/iianka-artifact-deploy \
    --setenv=IIANKA_DEPLOY_HEALTH_URL="$HEALTH_URL" \
    --setenv=IIANKA_DEPLOY_MIN_FREE_BYTES="$MIN_FREE_DISK_BYTES" \
    /usr/local/libexec/iianka-deploy/host.sh "$operation" "$environment" "$release_id" "$revision" "$checksum"
