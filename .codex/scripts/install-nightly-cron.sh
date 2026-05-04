#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
RUNS_DIR="${RUNS_DIR:-$REPO_ROOT/.codex-nightly}"
RUNNER="$REPO_ROOT/.codex/scripts/run-nightly-automation.sh"
TIMEZONE="${TIMEZONE:-Asia/Tokyo}"
CRON_SCHEDULE="${CRON_SCHEDULE:-0 0 * * *}"
CRON_MARKER="${CRON_MARKER:-iianka-codex-nightly}"

case "$RUNS_DIR" in
    /*)
        ;;
    *)
        RUNS_DIR="$REPO_ROOT/$RUNS_DIR"
        ;;
esac

CRON_LOG_FILE="${CRON_LOG_FILE:-$RUNS_DIR/cron.log}"

case "$CRON_LOG_FILE" in
    /*)
        ;;
    *)
        CRON_LOG_FILE="$REPO_ROOT/$CRON_LOG_FILE"
        ;;
esac

usage() {
    cat <<'USAGE'
Usage:
  .codex/scripts/install-nightly-cron.sh install
  .codex/scripts/install-nightly-cron.sh print
  .codex/scripts/install-nightly-cron.sh uninstall

Environment overrides:
  CRON_SCHEDULE="0 0 * * *" TIMEZONE=Asia/Tokyo
  CRON_MARKER=iianka-codex-nightly RUNS_DIR=/path/to/runtime
USAGE
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing required command: $1" >&2
        exit 1
    fi
}

shell_quote() {
    printf "%s" "$1" | sed "s/'/'\\\\''/g; s/^/'/; s/$/'/"
}

cron_command() {
    printf 'cd %s && TIMEZONE=%s %s >> %s 2>&1' \
        "$(shell_quote "$REPO_ROOT")" \
        "$(shell_quote "$TIMEZONE")" \
        "$(shell_quote "$RUNNER")" \
        "$(shell_quote "$CRON_LOG_FILE")"
}

cron_entry() {
    printf '%s %s\n' "$CRON_SCHEDULE" "$(cron_command)"
}

print_block() {
    printf '# BEGIN %s\n' "$CRON_MARKER"
    cron_entry
    printf '# END %s\n' "$CRON_MARKER"
}

current_crontab() {
    crontab -l 2>/dev/null || true
}

without_managed_block() {
    awk -v marker="$CRON_MARKER" '
        $0 == "# BEGIN " marker { skipping = 1; next }
        $0 == "# END " marker { skipping = 0; next }
        ! skipping { print }
    '
}

install_cron() {
    require_command crontab
    mkdir -p "$RUNS_DIR"

    local existing
    existing="$(mktemp)"

    current_crontab | without_managed_block >"$existing"

    if ! {
        cat "$existing"
        if [[ -s "$existing" ]]; then
            printf '\n'
        fi
        print_block
    } | crontab -; then
        rm -f "$existing"
        return 1
    fi

    rm -f "$existing"

    echo "Installed nightly Codex cron entry:"
    cron_entry
}

uninstall_cron() {
    require_command crontab

    current_crontab | without_managed_block | crontab -
    echo "Removed nightly Codex cron entry for marker '$CRON_MARKER'."
}

main() {
    local command="${1:-install}"

    case "$command" in
        install)
            install_cron
            ;;
        print)
            print_block
            ;;
        uninstall)
            uninstall_cron
            ;;
        -h|--help|help)
            usage
            ;;
        *)
            echo "Unknown command: $command" >&2
            usage >&2
            exit 1
            ;;
    esac
}

main "$@"
