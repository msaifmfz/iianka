#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TIMEZONE="${TIMEZONE:-Asia/Tokyo}"
RUNS_DIR="${RUNS_DIR:-$REPO_ROOT/.codex-nightly}"

case "$RUNS_DIR" in
    /*)
        ;;
    *)
        RUNS_DIR="$REPO_ROOT/$RUNS_DIR"
        ;;
esac

LOG_DIR="${LOG_DIR:-$RUNS_DIR/logs}"

case "$LOG_DIR" in
    /*)
        ;;
    *)
        LOG_DIR="$REPO_ROOT/$LOG_DIR"
        ;;
esac

LOCK_DIR="$RUNS_DIR/lock"
PROMPT_FILE="${PROMPT_FILE:-$REPO_ROOT/.codex/automations/nightly-github-issues.md}"
CODEX_BIN="${CODEX_BIN:-codex}"
CODEX_MODEL="${CODEX_MODEL:-gpt-5.5}"
CODEX_SANDBOX="${CODEX_SANDBOX:-danger-full-access}"
CODEX_APPROVAL_POLICY="${CODEX_APPROVAL_POLICY:-never}"
CODEX_PROFILE="${CODEX_PROFILE:-}"
CHECK_ONLY="0"

export PATH="$HOME/.volta/bin:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:$PATH"

usage() {
    cat <<'USAGE'
Usage:
  .codex/scripts/run-nightly-automation.sh
  .codex/scripts/run-nightly-automation.sh --check

Environment overrides:
  CODEX_BIN=codex CODEX_MODEL=gpt-5.5 CODEX_PROFILE=nightly_issues
  CODEX_SANDBOX=danger-full-access CODEX_APPROVAL_POLICY=never
  TIMEZONE=Asia/Tokyo RUNS_DIR=/path/to/runtime
USAGE
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing required command: $1" >&2
        exit 1
    fi
}

check_environment() {
    require_command git
    require_command gh
    require_command node
    require_command "$CODEX_BIN"

    if [[ ! -f "$PROMPT_FILE" ]]; then
        echo "Missing automation prompt: $PROMPT_FILE" >&2
        exit 1
    fi

    git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null
}

case "${1:-}" in
    "")
        ;;
    --check)
        CHECK_ONLY="1"
        ;;
    -h|--help|help)
        usage
        exit 0
        ;;
    *)
        echo "Unknown command: $1" >&2
        usage >&2
        exit 1
        ;;
esac

check_environment

if [[ "$CHECK_ONLY" == "1" ]]; then
    echo "Nightly Codex automation environment looks ready."
    exit 0
fi

mkdir -p "$RUNS_DIR" "$LOG_DIR"

if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    echo "Nightly Codex automation is already running." >&2
    exit 1
fi

cleanup() {
    rmdir "$LOCK_DIR" 2>/dev/null || true
}
trap cleanup EXIT

log_file="$LOG_DIR/$(TZ="$TIMEZONE" date '+%Y-%m-%d-%H%M%S-%z').log"
codex_args=(exec -C "$REPO_ROOT" -m "$CODEX_MODEL" -s "$CODEX_SANDBOX" -c "approval_policy=\"$CODEX_APPROVAL_POLICY\"")

if [[ -n "$CODEX_PROFILE" ]]; then
    codex_args+=(-p "$CODEX_PROFILE")
fi

codex_args+=(-)

"$CODEX_BIN" "${codex_args[@]}" <"$PROMPT_FILE" 2>&1 | tee "$log_file"
