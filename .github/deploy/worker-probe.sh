#!/bin/bash
# Owner-run host probe for the worker startup contract (see docs/deploy-memory-proposal.md).
# Usage: sudo bash .github/deploy/worker-probe.sh .github/deploy/worker-start.sh
#
# It starts only uniquely named transient units against a synthetic scratch tree
# whose labels are copied by reference from the serving paths (read only). The
# synthetic artisan reports identity, SELinux context and directory; it never
# loads Laravel, credentials or application data. Real worker units, serving
# paths, file-context mappings and policy are not changed. Everything it creates
# is removed on exit.
set -euo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
[[ "$EUID" == 0 ]] || { echo 'Run with sudo; the probe submits transient systemd units.' >&2; exit 1; }
source_entrypoint="${1:?Path to the reviewed worker-start.sh}"
[[ -f "$source_entrypoint" ]] || { echo 'Entrypoint source not found.' >&2; exit 1; }
environment=staging
reference="/var/www/iianka/$environment"
queue_arguments=(queue:work --sleep=1 --tries=3 --timeout=120 --max-time=3600)

# Same owner-approved memory gate and finite slice as the launcher; never lowered here.
configuration="/etc/iianka-artifact-deploy/$environment.conf"
setting() { sed -n "s/^$1=\([1-9][0-9]\{0,11\}\)$/\1/p" "$configuration"; }
memory_max="$(setting MEMORY_MAX_BYTES)"
min_available="$(setting MIN_AVAILABLE_BYTES)"
[[ -n "$memory_max" && -n "$min_available" ]] || { echo 'Owner readiness configuration is missing memory settings.' >&2; exit 1; }
available="$(awk '/^MemAvailable:/ { printf "%.0f", $2 * 1024 }' /proc/meminfo)"
if (( available < min_available )); then
    printf 'Insufficient owner-approved memory headroom: MemAvailable %s < MIN_AVAILABLE_BYTES %s.\n' "$available" "$min_available" >&2
    echo 'Nothing was created. Re-run after interactive sessions exit; do not lower the gate.' >&2
    exit 75
fi
slice_max="$(systemctl show iianka-deploy.slice -p MemoryMax --value)"
if [[ ! "$slice_max" =~ ^[1-9][0-9]*$ ]] || (( slice_max > memory_max )); then
    echo 'Finite approved deploy slice is missing.' >&2
    exit 1
fi
[[ "$(systemctl show iianka-deploy.slice -p MemorySwapMax --value)" == 0 ]] || { echo 'Deploy slice must forbid swap.' >&2; exit 1; }

id="$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
probe="/var/www/iianka-worker-probe-$id"
entrypoint="/usr/local/sbin/iianka-worker-probe-$id"
units=()
cleanup() {
    local unit
    for unit in "${units[@]}"; do
        systemctl stop "$unit" 2>/dev/null || true
        systemctl reset-failed "$unit" 2>/dev/null || true
    done
    rm -rf --one-file-system -- "$probe"
    rm -f -- "$entrypoint"
}
trap cleanup EXIT
started_date="$(LC_ALL=C date '+%x')"
started_time="$(LC_ALL=C date '+%H:%M:%S')"

# Synthetic tree mirroring /var/www/iianka/<environment>; labels copied from the real paths.
for release in "$environment/releases/probe-a" "$environment/releases/probe-b" production/releases/probe-other; do
    install -d -o iianka -g nginx -m 0775 "$probe/$release"
    cat >"$probe/$release/artisan" <<'PHP'
<?php
// Synthetic probe target: reports the worker's identity, context and directory, then exits.
$status = (string) file_get_contents('/proc/self/status');
preg_match('/^Uid:\s+(\d+)/m', $status, $uid);
preg_match('/^Gid:\s+(\d+)/m', $status, $gid);
echo json_encode([
    'release' => basename((string) getcwd()),
    'cwd' => getcwd(),
    'argv' => array_slice($argv, 1),
    'uid' => (int) $uid[1],
    'gid' => (int) $gid[1],
    'context' => trim((string) @file_get_contents('/proc/self/attr/current'), "\0\n"),
]), "\n";
PHP
    chown iianka:nginx "$probe/$release/artisan"
    chmod 0644 "$probe/$release/artisan"
    chcon --reference="$reference/releases" "$probe/${release%/*}"
    chcon -R --reference="$(readlink -f "$reference/current")" "$probe/$release"
done
ln -s "$probe/$environment/releases/probe-a" "$probe/$environment/current"
chown -h iianka:iianka "$probe/$environment/current"
chcon -h --reference="$reference/current" "$probe/$environment/current"
chcon --reference="$reference" "$probe/$environment" "$probe/production"
chcon --reference=/var/www/iianka "$probe"

# Installed copy differs only in its fixed base; it gets /usr/local/sbin's default label.
[[ "$(grep -c '^base=/var/www/iianka$' "$source_entrypoint")" == 1 ]] || { echo 'Unexpected entrypoint source.' >&2; exit 1; }
sed "s|^base=/var/www/iianka\$|base=$probe|" "$source_entrypoint" >"$entrypoint.tmp"
install -o root -g root -m 0755 "$entrypoint.tmp" "$entrypoint"
rm -f "$entrypoint.tmp"
restorecon "$entrypoint"

run_unit() {
    local name="$1" directory="$2" status=0
    shift 2
    local unit="iianka-worker-probe-$id-$name"
    units+=("$unit")
    systemd-run --quiet --wait --pipe --unit="$unit" --uid=iianka --gid=nginx \
        --slice=iianka-deploy.slice --property=MemoryMax="$memory_max" --property=MemorySwapMax=0 \
        --property=RuntimeMaxSec=30 --property=WorkingDirectory="$directory" "$@" </dev/null || status=$?
    printf '[%s] exit=%s ExecMainStatus=%s\n' "$name" "$status" "$(systemctl show "$unit" -p ExecMainStatus --value)"
}
point_current() {
    ln -s "$1" "$probe/$environment/current.next"
    chown -h iianka:iianka "$probe/$environment/current.next"
    chcon -h --reference="$reference/current" "$probe/$environment/current.next"
    mv -Tf "$probe/$environment/current.next" "$probe/$environment/current"
}

echo "== Labels"
ls -Zd "$probe" "$probe/$environment" "$probe/$environment/current" "$probe/$environment/releases/probe-a" "$entrypoint"
echo "== Control: WorkingDirectory=current (expected 200/CHDIR under the reported denial)"
run_unit control-current "$probe/$environment/current" /usr/bin/true
echo "== Contract: real environment root, entrypoint selects current (probe-a)"
run_unit queue-a "$probe/$environment" "$entrypoint" "$environment" "${queue_arguments[@]}"
point_current "$probe/$environment/releases/probe-b"
echo "== Release change between starts (probe-b)"
run_unit queue-b "$probe/$environment" "$entrypoint" "$environment" "${queue_arguments[@]}"
point_current "$probe/$environment/releases/probe-a"
echo "== Rollback between starts (probe-a) with scheduler arguments"
run_unit scheduler-rollback "$probe/$environment" "$entrypoint" "$environment" schedule:work
point_current "$probe/production/releases/probe-other"
echo "== Cross-environment current (expected exit 78, no PHP output)"
run_unit cross-environment "$probe/$environment" "$entrypoint" "$environment" "${queue_arguments[@]}"
echo "== SELinux denials since the probe started (expected: only the control's lnk_file read)"
LC_ALL=C ausearch -m AVC,USER_AVC -ts "$started_date" "$started_time" -i 2>/dev/null | grep -F 'avc:' || echo 'none'
