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

trusted_owner_and_mode() {
    [[ ! -L "$1" && "$(stat -c %u "$1")" == 0 ]] || return 1
    (( (8#$(stat -c %a "$1") & 8#022) == 0 ))
}

if [[ "$operation" =~ ^services-(stop|start|state)$ ]]; then
    [[ $# == 2 || ( "$operation" == services-start && $# == 4 ) ]] || exit 1
    # Worker startup contract: systemd enters the real environment root (never the
    # current symlink, which init_t may not read under SELinux) and execs the
    # root-owned entrypoint; it selects and enters current after the exec.
    worker_entrypoint=/usr/local/sbin/iianka-worker-start
    for path in / /usr /usr/local /usr/local/sbin "$worker_entrypoint"; do
        if [[ ! -e "$path" ]] || ! trusted_owner_and_mode "$path"; then
            echo 'Worker entrypoint and its ancestors must be real, root-owned and not group/other-writable.' >&2
            exit 1
        fi
    done
    [[ -f "$worker_entrypoint" && -x "$worker_entrypoint" ]] || { echo 'Worker entrypoint is not an executable file.' >&2; exit 1; }
    for service in queue scheduler; do
        unit="iianka-$service@$environment"
        case "$service" in
            queue) command=queue:work ;;
            scheduler) command=schedule:work ;;
        esac
        [[ "$(systemctl show "$unit" -p User --value)" == iianka ]]
        [[ "$(systemctl show "$unit" -p Group --value)" == nginx ]]
        [[ "$(systemctl show "$unit" -p WorkingDirectory --value)" == "/var/www/iianka/$environment" ]] \
            || { echo "$unit must use WorkingDirectory=/var/www/iianka/$environment." >&2; exit 1; }
        # Exactly one ExecStart: the trusted entrypoint, this environment, this role.
        exec_start="$(systemctl show "$unit" -p ExecStart --value)"
        prefix="{ path=$worker_entrypoint ; argv[]=$worker_entrypoint $environment $command"
        if [[ "$exec_start" != "$prefix "* || "$exec_start" == *"{ path="*"{ path="* ]]; then
            echo "$unit must ExecStart=$worker_entrypoint $environment $command ..." >&2
            exit 1
        fi
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
if [[ "$operation" == snapshot ]]; then
    [[ $# == 2 ]] || exit 1
    backup_entrypoint=/usr/local/sbin/iianka-backup
    for path in "$backup_entrypoint" /usr/local/sbin /usr/local /usr /; do
        if [[ ! -e "$path" ]] || ! trusted_owner_and_mode "$path"; then
            echo 'Backup entrypoint and its ancestors must be real, root-owned and not group/other-writable.' >&2
            exit 1
        fi
    done
    [[ -f "$backup_entrypoint" && -x "$backup_entrypoint" ]] || { echo 'Backup entrypoint is not an executable file.' >&2; exit 1; }
    # Pre-migration snapshots belong to a running activation: refuse unless the
    # shared deploy lock is currently held. The backup then skips that lock.
    exec 8<"/var/www/iianka/.artifact-deploy.lock"
    if flock -n 8; then
        echo 'Snapshots run only inside an active deployment.' >&2
        exit 1
    fi
    exec 8<&-
    backup_status=/var/log/iianka-backup/last-status
    previous_status="$(cat "$backup_status" 2>/dev/null || true)"
    # Own unit and limits: restic does not fit inside the deploy slice. No --pipe:
    # this runs inside the deploy unit's own --pipe unit, and a nested --pipe loses
    # its descriptors on the way to systemd (staging, 2026-11-03). Output goes to
    # the journal; the snapshot ID comes from the backup's root-owned status line.
    if ! systemd-run --quiet --wait --collect --service-type=exec \
        --property=MemoryHigh=250M --property=MemoryMax=400M --property=MemorySwapMax=0 \
        --property=RuntimeMaxSec=900 --nice=10 --property=IOSchedulingClass=idle \
        --setenv=GOGC=50 \
        "$backup_entrypoint" --tag pre-migration --env "$environment" --deploy-lock-held; then
        echo 'Pre-migration backup failed; see journalctl for its unit.' >&2
        exit 1
    fi
    for path in "$(dirname "$backup_status")" "$backup_status"; do
        trusted_owner_and_mode "$path" || { echo 'Backup status must be root-owned and not group/other-writable.' >&2; exit 1; }
    done
    status="$(cat "$backup_status")"
    status_pattern="^[^ ]+ OK tag=pre-migration envs=$environment snapshots=([0-9a-f]{8,})$"
    if [[ "$status" == "$previous_status" || ! "$status" =~ $status_pattern ]]; then
        echo 'Backup reported no new pre-migration snapshot.' >&2
        exit 1
    fi
    echo "snapshot ${BASH_REMATCH[1]} saved"
    exit 0
fi
[[ $# == 5 && "$operation" =~ ^(prepare|activate|rollback|labels)$ ]] || exit 1
release_id="$3"
revision="$4"
checksum="$5"
[[ "$revision" =~ ^[0-9a-f]{40}$ && "$release_id" =~ ^${revision}-[0-9]+-[0-9]+$ && "$checksum" =~ ^[0-9a-f]{64}$ ]] || exit 1

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
unset MEMORY_MAX_BYTES MIN_AVAILABLE_BYTES MIN_FREE_DISK_BYTES HEALTH_URL AUTO_MIGRATE
seen_keys=' '
while IFS= read -r setting || [[ -n "$setting" ]]; do
    [[ "$setting" =~ ^[[:space:]]*(#.*)?$ ]] && continue
    [[ "$setting" =~ ^(MEMORY_MAX_BYTES|MIN_AVAILABLE_BYTES|MIN_FREE_DISK_BYTES|HEALTH_URL|AUTO_MIGRATE)=(.+)$ ]] || {
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
# Optional: 1 lets activation snapshot and migrate inside its maintenance window.
AUTO_MIGRATE="${AUTO_MIGRATE:-0}"
[[ "$AUTO_MIGRATE" =~ ^[01]$ ]] || { echo 'AUTO_MIGRATE must be 0 or 1.' >&2; exit 1; }
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
    --setenv=IIANKA_DEPLOY_AUTO_MIGRATE="$AUTO_MIGRATE" \
    /usr/local/libexec/iianka-deploy/host.sh "$operation" "$environment" "$release_id" "$revision" "$checksum"
