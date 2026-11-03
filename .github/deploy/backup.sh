#!/bin/bash
# Install root-owned as /usr/local/sbin/iianka-backup, with backup.service and
# backup.timer as /etc/systemd/system/iianka-backup.{service,timer}.
# Encrypted restic backup of iianka app data and host config to Cloudflare R2.
# Usage: iianka-backup [--tag scheduled|pre-migration|manual] [--env production|staging]
#        iianka-backup --tag pre-migration --env ENV --deploy-lock-held  (launcher only)
set -euo pipefail
export PATH=/usr/sbin:/usr/bin:/sbin:/bin
umask 077

readonly conf_dir=/etc/iianka-backup
readonly stage_root=/var/lib/iianka-backup/stage
readonly status_dir=/var/log/iianka-backup
readonly lock_file=/var/www/iianka/.artifact-deploy.lock
readonly app_base=/var/www/iianka
readonly lock_wait_seconds=1800

environments=(production staging)
run_tag=scheduled
current_step=start
deploy_lock_held=0
min_mem_mib=600

fail() { echo "iianka-backup: $*" >&2; exit 1; }

while [ $# -gt 0 ]; do
	case "$1" in
	--tag) run_tag="${2:-}"; shift 2 ;;
	--env) environments=("${2:-}"); shift 2 ;;
	--deploy-lock-held) deploy_lock_held=1; shift ;;
	*) fail "unknown argument: $1" ;;
	esac
done
[[ "$run_tag" =~ ^(scheduled|pre-migration|manual)$ ]] || fail "invalid --tag: $run_tag"
for env in "${environments[@]}"; do
	[[ "$env" =~ ^(production|staging)$ ]] || fail "invalid --env: $env"
done
if [ "$deploy_lock_held" -eq 1 ]; then
	# Called by the deploy launcher while activation holds the lock with workers stopped.
	[ "$run_tag" = pre-migration ] && [ "${#environments[@]}" -eq 1 ] ||
		fail '--deploy-lock-held requires --tag pre-migration and one --env'
	min_mem_mib=400
fi

write_status() {
	install -d -m 0755 "$status_dir"
	printf '%s %s %s\n' "$(date -Iseconds)" "$1" "$2" >"$status_dir/last-status.tmp"
	chmod 0644 "$status_dir/last-status.tmp"
	mv -f "$status_dir/last-status.tmp" "$status_dir/last-status"
}

# Optional dead-man alert: one healthchecks.io-style ping URL in healthcheck.url (root 0600).
# Ping failures never fail the backup.
ping_healthcheck() {
	local url_file="$conf_dir/healthcheck.url" url
	[ -r "$url_file" ] || return 0
	url="$(head -n 1 "$url_file")"
	[[ "$url" =~ ^https:// ]] || return 0
	curl -fsS -m 10 --retry 3 -o /dev/null "$url$1" || true
}

on_exit() {
	local rc=$?
	rm -rf "${stage_root:?}"/*
	if [ "$rc" -eq 0 ]; then
		write_status OK "tag=$run_tag envs=${environments[*]}"
		ping_healthcheck ''
	else
		write_status FAIL "tag=$run_tag step=$current_step rc=$rc"
		ping_healthcheck '/fail'
	fi
}
trap on_exit EXIT

current_step=credentials
[ "$(id -u)" -eq 0 ] || fail 'must run as root'
[ -r "$conf_dir/r2.env" ] || fail "missing $conf_dir/r2.env"
[ -r "$conf_dir/restic.pass" ] || fail "missing $conf_dir/restic.pass"
set -a
# shellcheck disable=SC1091
. "$conf_dir/r2.env"
set +a
export RESTIC_PASSWORD_FILE="$conf_dir/restic.pass"
export RESTIC_CACHE_DIR=/var/cache/iianka-backup
export GOGC="${GOGC:-50}"

ping_healthcheck '/start'

current_step=memory
available_mib="$(awk '/^MemAvailable:/ { printf "%d", $2 / 1024 }' /proc/meminfo)"
[ "$available_mib" -ge "$min_mem_mib" ] || fail "MemAvailable ${available_mib}MiB < ${min_mem_mib}MiB"

# Share the deploy lock (read-only fd, never create it) so backups and deploys never overlap.
current_step=deploy-lock
if [ "$deploy_lock_held" -eq 0 ]; then
	[ -f "$lock_file" ] || fail "missing deploy lock file $lock_file"
	exec 9<"$lock_file"
	flock -w "$lock_wait_seconds" 9 || fail 'deploy lock busy'
fi

restic unlock >/dev/null 2>&1 || true

for env in "${environments[@]}"; do
	current_step="snapshot-$env"
	source_db="$app_base/$env/shared/database/database.sqlite"
	env_stage="$stage_root/$env"
	install -d -m 0700 "$env_stage"
	sqlite3 -readonly -cmd '.timeout 10000' "$source_db" ".backup '$env_stage/database.sqlite'"
	[ "$(sqlite3 -readonly "$env_stage/database.sqlite" 'PRAGMA integrity_check;')" = ok ] ||
		fail "$env snapshot failed integrity_check"
	readlink -f "$app_base/$env/current" >"$env_stage/current-release.txt"

	current_step="upload-$env"
	restic backup --host iianka --tag "$env" --tag "$run_tag" --no-scan \
		--exclude "$app_base/$env/shared/storage/app/private/quotation-pdf-cache" \
		"$env_stage" "$app_base/$env/shared/storage/app"
done

if [ "${#environments[@]}" -eq 2 ]; then
	current_step=upload-host-config
	config_paths=()
	for path in /etc/iianka-artifact-deploy /etc/nginx /etc/php-fpm.d /etc/php.ini /etc/php.d \
		/etc/letsencrypt/renewal /usr/local/libexec/iianka-deploy \
		/etc/systemd/system/iianka-* /etc/systemd/system/*.service.d \
		/usr/local/sbin/iianka-* /etc/sudoers.d/iianka*; do
		[ -e "$path" ] && config_paths+=("$path")
	done
	restic backup --host iianka --tag host-config --tag "$run_tag" --no-scan "${config_paths[@]}"
fi

current_step=retention
restic forget --host iianka --tag scheduled --group-by host,tags \
	--keep-daily 14 --keep-weekly 8 --keep-monthly 6
restic forget --host iianka --tag pre-migration --group-by host,tags --keep-within 90d
restic forget --host iianka --tag manual --group-by host,tags --keep-within 30d

if [ "$run_tag" = scheduled ] && [ "$(date +%u)" = 7 ]; then
	current_step=prune
	restic prune --max-unused 10%
	current_step=check
	restic check --read-data-subset 5%
fi

current_step='finished'
