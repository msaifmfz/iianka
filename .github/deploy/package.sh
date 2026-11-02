#!/usr/bin/env bash
# CI only. Never copy the runner's environment, storage or development vendor.
set -euo pipefail

revision="${1:?Expected source commit}"
output="${2:?Expected output directory}"
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
[[ "$(git rev-parse HEAD)" == "$revision" ]]
output="$(mkdir -p "$output" && cd "$output" && pwd)"
workspace="$(mktemp -d)"
trap 'rm -rf "$workspace"' EXIT

git archive "$revision" app bootstrap config database lang public resources routes artisan composer.json composer.lock | tar -x -C "$workspace"
# Only runtime inputs: no seed data, test data, generated caches or SSR bundle.
rm -rf "$workspace/database/seeders" "$workspace/database/factories" "$workspace/bootstrap/cache" "$workspace/bootstrap/ssr"
find "$workspace/database" -type f ! -name '*.php' -delete
rm -rf "$workspace/public/build" "$workspace/public/hot" "$workspace/public/storage"
cp -R public/build "$workspace/public/build"
(
    cd "$workspace"
    # Include required extensions in the generated runtime platform check.
    composer config platform-check true
    composer install --no-dev --no-scripts --no-plugins --no-interaction --prefer-dist --optimize-autoloader
)
# Dependency distributions may include hidden VCS/editor metadata; none is runtime input.
find "$workspace" -mindepth 1 -name '.*' -prune -exec rm -rf {} +
python3 .github/deploy/artifact.py pack "$workspace" "$output/release.tar.gz" "$revision"
printf 'Release revision: %s\n' "$revision"
cat "$output/release.tar.gz.sha256"
