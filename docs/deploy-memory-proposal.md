# Deploy memory failure: proposed artifact releases

Status: proposal for owner review, 2026-11-01. No workflow or host behavior
changes in this PR. Base reviewed: `313c7f824b9a39945794fb904a6d0c54bd479274`.

## Finding and recommendation

[Run 41300996787](https://github.com/msaifmfz/iianka/actions/runs/41300996787)
passed CI and release validation, failed in staging's “Deploy over SSH” step,
and skipped production. Those statuses were checked through the GitHub API;
the memory-exhaustion diagnosis and successful manual recovery are the owner's
observations, not a new host inspection. Raw deploy logs were not retrieved.

Both remote blocks in `.github/workflows/deploy.yml` currently check out code
in `DEPLOY_PATH`, install Composer dependencies, migrate the database, cache
Laravel state, then run `npm ci` and `npm run build:ssr`. An EXIT trap brings
that directory out of maintenance even if a late build fails. The CI job has
already built assets, but does not transfer them. The separate SSR follow-up
changes builds to `npm run build` after the owner's choice to disable SSR.

Recommend **CI-built immutable application artifacts, prepared in a new release
directory, with a separately approved atomic activation**. Include production
`vendor/` as well as client assets: moving only npm off-host leaves Composer's
memory cost. Do not mutate the serving checkout or run npm, Vite or Composer
on the shared host. Keep the old release and its hashed assets for rollback.

This needs owner work, so this PR is deliberately a proposal rather than a
partial workflow fix. `docs/operations.md` already records the mismatch between
the guide's symlink layout and the workflow's in-place checkout. Actual paths,
symlinks, filesystem, cgroup support, PHP extensions, permissions, free disk,
SELinux labels, workload peaks and database compatibility are unverified.
Enabling an automatic cutover based on those assumptions is unsafe.

No repository change can guarantee that an additional process uses **zero**
RAM or never competes with production on a shared 1.7 GB host. CI removes the
large install/build peak. The owner must approve and verify a bounded deploy
budget with adequate production headroom, or move staging/deployment preparation
to separate capacity. If “never take memory away” is literal, separate capacity
is required. Do not enable the automatic deploy while this gate is unresolved.

## Options compared

| Option | Benefit | Limits and recommendation |
| --- | --- | --- |
| Build in CI; ship code, production vendor and assets | Removes host npm/install/build memory; failures happen before activation; reproducible artifact tied to the tested commit | Recommended, with isolated release preparation, integrity checks, host preflight and owner-controlled migration/activation below. Runner PHP/extensions must match the host. |
| Workflow-local `NODE_OPTIONS=--max-old-space-size=384`, `npm ci --maxsockets=2 --no-audit --no-fund` | Reproduces manual recovery without persistent settings | A V8 heap ceiling is not a total RSS/cgroup limit; sockets limit concurrency, not all allocations. Vite, Composer, native allocations and running web pools still compete. Does not repair in-place deployment or database rollback. Temporary owner-approved fallback only, not the durable recommendation. |
| Swap or more host capacity | Can provide headroom for all services | Owner action on the shared host. Swap can introduce latency and disk pressure and does not fix partial deployments. More RAM or separate staging is preferable if load cannot fit a verified budget; no sizing claim is possible from code. Not a substitute for artifacts/atomic activation. |

If the owner deliberately uses the temporary fallback, scope it to the two
commands in the workflow, not a profile, systemd service or shared environment:

```bash
NODE_OPTIONS=--max-old-space-size=384 npm ci --maxsockets=2 --no-audit --no-fund
NODE_OPTIONS=--max-old-space-size=384 npm run build
```

Rollback of that fallback is removing those command-local options; there is no
persistent setting to undo. It still carries the risks above.

## Repository implementation after host readiness

1. Resolve and explicitly check out the validated release tag's commit in the
   same workflow run. The artifact identity must contain the resolved 40-hex
   commit, `github.run_id` and `github.run_attempt`, not just a mutable tag.
   Pull-request artifacts must never be eligible for deployment.
2. In the existing CI job, install locked dependencies and build/test with PHP
   8.5 and Node 22. Keep all current backend, lint, format, type and production
   Playwright checks. Build the client only after the SSR PR is incorporated.
   Generate Wayfinder in CI with `--with-form`; do not generate it on the host.
3. In a clean packaging directory, export tracked application files from that
   exact commit. Install locked production Composer dependencies there with
   `--no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts`.
   Copy the **tested** `public/build` into it. Do not ship CI's dev vendor tree.
   Preserve executable modes by archiving to tar before upload.
4. Exclude environment files, keys, auth files, node_modules, Git metadata,
   hot files, application logs, caches and database files. Do not copy runtime
   storage from CI. Include required empty storage/bootstrap directories and
   package-discovery metadata only if generated against production vendor.
   Do not ship config/route/view caches generated with CI values or paths.
5. Include `REVISION` and a manifest of payload file SHA-256 hashes; create
   `release.tar.gz` and `release.tar.gz.sha256`. Upload once with a unique
   artifact name. Deploy jobs must `needs: ci, validate_release`, download the
   exact same-run artifact by that name, verify checksum, revision and payload
   manifest, and fail on absence/mismatch. A checksum beside the payload checks
   transfer integrity; trust comes from the authenticated same-run artifact,
   not a checksum supplied by an arbitrary party.
6. Verify every client manifest import/dynamic import/asset exists (including
   PDF.js worker), and test the unpacked artifact with a disposable database.
   Verify production vendor platform requirements against the host's PHP 8.5
   and extensions before accepting it. No install/build fallback on the host.
7. Serialize staging and production preparation/cutover with **one host-wide**
   workflow concurrency group, plus a host lock. The existing separate
   `deploy-staging` and `deploy-production` groups permit simultaneous work.
8. Transfer to a non-serving incoming directory. Extract to a unique release
   on the same filesystem as `current`; never extract over `current`. Validate
   archive members (no absolute paths, traversal, devices, or escaping links).
   Check disk space and required files before any application/database change.
9. Fail closed unless the owner-approved layout, memory boundary, labels,
   artifact identity and migration/rollback plan are confirmed. Prepare
   caches within the release; preserve old hashed assets for browser sessions
   that survive the switch. Do not use `optimize:clear` to clear shared caches
   while the old production release is serving traffic.
10. Only then activate in an owner-approved window. Never use a generic EXIT
    trap that automatically exposes an unsuccessful migration or mixed release.
    Keep maintenance on for a failure after database work until recovery is
    verified. The real deploy path remains unverified until the owner runs it.

Artifacts can be passed between jobs without rebuilding, as described in
[GitHub's artifact documentation](https://docs.github.com/en/actions/tutorials/store-and-share-data).

## Owner host preflight and preparation commands (not executed)

These commands use the guide's layout. Run as the owner, substitute the approved
environment, and stop if the actual host differs. Do not paste configuration,
database contents, keys or user records into this PR. No command here should be
run merely to review/merge this proposal.

```bash
# Review both environments. These inspect paths/resources, not environment files.
for environment in staging production; do
    root="/var/www/iianka/$environment"
    test -L "$root/current" || exit 1
    readlink -f "$root/current"
    test -d "$root/releases" || exit 1
    test -d "$root/shared/storage" || exit 1
    stat -c '%d %n' "$root" "$root/releases"
    ls -Zd "$root" "$root/releases" "$root/shared/storage"
done
free -m
swapon --show
stat -fc %T /sys/fs/cgroup
systemd --version
php -v
php -m
df -h /var/www/iianka
```

The owner must measure production peak usage and decide a deploy budget. The
following **192 MiB example is unvalidated**, not permission to reserve it on
this host. If it does not fit, stop and provide capacity. Verify supported
systemd properties before applying; use a dedicated slice shared by all deploy
jobs so parallel processes cannot each consume the full budget:

```bash
sudo install -d -m 0755 /etc/systemd/system
sudo tee /etc/systemd/system/iianka-deploy.slice >/dev/null <<'UNIT'
[Unit]
Description=Bounded iianka deployment preparation
[Slice]
MemoryAccounting=yes
MemoryHigh=160M
MemoryMax=192M
MemorySwapMax=0
CPUWeight=10
IOWeight=10
UNIT
sudo systemctl daemon-reload
sudo systemctl start iianka-deploy.slice
sudo systemctl show iianka-deploy.slice -p MemoryHigh -p MemoryMax -p MemorySwapMax
```

All deploy preparation children (including extraction, Composer platform check,
package discovery and cache commands) must run inside this slice, for example
using a bounded owner shell below. Transfer must also be serialized and
bounded; putting only a shell wrapper in the slice is insufficient if it starts
processes elsewhere. Do not grant unrestricted `systemd-run` or sudo to the
CI account; a root-owned fixed launcher with validated environment/release IDs
and a reviewed narrow sudo rule is required before workflow integration.

```bash
# Owner-only preparation shell. Run the reviewed preparation commands inside it.
sudo systemd-run --wait --collect --pty --uid=iianka --gid=nginx \
    --slice=iianka-deploy.slice \
    --property=MemoryOOMGroup=yes \
    --property=OOMPolicy=stop \
    /bin/bash --noprofile --norc
```

`MemoryMax` limits the deploy cgroup, not other services. It does not reserve
memory for production or eliminate global pressure. See
[systemd resource controls](https://www.freedesktop.org/software/systemd/man/latest/systemd.resource-control.html).
Use separate capacity if the required protection cannot be demonstrated.

Inside that shell, the owner must create `releases/$release_id`, validate and
extract the trusted artifact, link the environment's existing shared storage
and environment configuration without printing it, then run the following PHP preparation commands (no install or build):

```bash
# Inside the NEW release, with the owner's normal application environment.
php -d memory_limit=128M vendor/composer/platform_check.php
php -d memory_limit=128M artisan package:discover --no-interaction
php -d memory_limit=128M artisan config:cache --no-interaction
php -d memory_limit=128M artisan route:cache --no-interaction
php -d memory_limit=128M artisan view:cache --no-interaction
php -d memory_limit=128M artisan event:cache --no-interaction
```

Before accepting it, the owner must run `composer check-platform-reqs --no-dev`
on a representative isolated copy with host PHP/extensions; the generated
platform_check alone may not include every extension requirement. Composer is
not needed for normal subsequent deploys once compatibility is validated and
revalidated whenever the lock or host PHP/extensions change.

New releases also need approved SELinux file-context mappings. After reviewing
that none conflict with existing mappings, the owner can add these patterns
for **each** environment and relabel the prepared release; this grants no
network permissions and does not touch the HTTPS egress module:

```bash
environment=staging # repeat for production only after separate review
root="/var/www/iianka/$environment"
sudo semanage fcontext -a -t httpd_sys_content_t "$root/releases(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "$root/releases/[^/]+/bootstrap/cache(/.*)?"
sudo restorecon -Rv "$root/releases/APPROVED_RELEASE_ID"
```

## Activation and rollback commands for owner review (not executed)

Use the existing guide and `docs/operations.md` backup/restore procedure. The
owner must verify a restorable off-host snapshot and determine whether this
release's migrations are backward compatible **before** choosing activation.
A symlink rollback does not undo database changes. Reject automated activation
of incompatible migrations until a release-specific restore plan is reviewed.
Stop all writers in the selected environment; long-running web requests must
also be drained before migration. If that cannot be isolated from production,
move staging to separate capacity rather than restart shared PHP-FPM.

```bash
# Example for staging; explicitly approve production separately.
set -euo pipefail
environment=staging
root="/var/www/iianka/$environment"
release="$root/releases/APPROVED_RELEASE_ID"
previous="$(readlink -f "$root/current")"
test -d "$release" && test -L "$root/current"
# Save previous outside the release for recovery; never overwrite an existing record.
( set -o noclobber; printf '%s\n' "$previous" > "$root/shared/APPROVED_RELEASE_ID.previous" )
cd "$previous"
php artisan down --retry=60 --no-interaction
sudo systemctl stop "iianka-queue@$environment" "iianka-scheduler@$environment"
# Owner drains existing web requests and takes/verifies the documented snapshot here.
# Do not continue until the snapshot and database rollback gate are satisfied.
cd "$release"
php -d memory_limit=128M artisan migrate --force --no-interaction
# GNU mv on the host: same-filesystem rename, after preparation succeeds.
ln -s "$release" "$root/current.APPROVED_RELEASE_ID"
mv -Tf "$root/current.APPROVED_RELEASE_ID" "$root/current"
# No shared PHP-FPM restart. Owner verifies OPcache follows the new realpath.
# Owner verifies cached config, assets, and application boot before enabling traffic.
php artisan up --no-interaction
sudo systemctl start "iianka-queue@$environment" "iianka-scheduler@$environment"
```

This is an activation sequence for review, **not** an unattended production
script. A failed migration must leave maintenance on. Gate `up` on verified
boot/asset checks, then perform real authenticated smoke tests and inspect
logs. Staging must pass before production is considered. Maintain a common
host lock throughout preparation and activation. Reuse old hashed assets until
old browser sessions expire; otherwise an atomic symlink can still break
lazy-loaded chunks in already open pages.

For a code-only failure with a compatible database, keep writers stopped and
restore the saved previous target with the same atomic rename:

```bash
set -euo pipefail
environment=staging # use the SAME approved environment as activation
root="/var/www/iianka/$environment"
# Keep the same approved release ID from activation.
cd "$root/current"
php artisan down --retry=60 --no-interaction
sudo systemctl stop "iianka-queue@$environment" "iianka-scheduler@$environment"
previous="$(cat "$root/shared/APPROVED_RELEASE_ID.previous")"
test -d "$previous"
ln -s "$previous" "$root/current.APPROVED_RELEASE_ID.rollback"
mv -Tf "$root/current.APPROVED_RELEASE_ID.rollback" "$root/current"
cd "$previous"
# For incompatible/failed migrations, restore the verified DB/files snapshot here
# using docs/operations.md. Do not blindly migrate:rollback or bring traffic up.
php artisan config:cache --no-interaction
# Owner verifies recovered application/database/assets before continuing.
php artisan up --no-interaction
sudo systemctl start "iianka-queue@$environment" "iianka-scheduler@$environment"
```

To roll back new host preparation, first stop/disable the proposed deploy
launcher and wait for all its units to exit. Keep the original deployment
workflow disabled until the owner accepts its memory/partial-deploy risks.
Only remove mappings that this rollout added (restore any pre-existing
mapping instead of deleting it). Keep the release mappings while any served
release relies on them; do not execute their removal against an active release:

```bash
set -euo pipefail
environment=staging # use the SAME approved environment as preparation
root="/var/www/iianka/$environment"
sudo systemctl stop iianka-deploy.slice
sudo rm /etc/systemd/system/iianka-deploy.slice
sudo systemctl daemon-reload
# Use the same environment root as the preparation block, only for new mappings.
sudo semanage fcontext -d "$root/releases/[^/]+/bootstrap/cache(/.*)?"
sudo semanage fcontext -d "$root/releases(/.*)?"
sudo restorecon -Rv "$root/releases/APPROVED_RELEASE_ID"
```

No swap, RAM, server policy, services, deployments, databases or environment
files were changed in preparing this proposal. No host commands were executed.

## Validation and remaining decision

Review the command blocks with Bash syntax checking and ShellCheck; no shell
block should be executed as part of documentation validation. Run repository
commit/push checks. This documentation-only proposal adds no dependencies and
changes no application behavior, so no application regression tests are needed.

Owner decision: approve the artifact/atomic-release direction, verify the
actual layout and memory/isolation budget (or provide separate capacity), and
review a fixed launcher plus migration/rollback gates before a workflow changes
production. No automatic deployment safety claim is made until these gates and
failure-injection tests (bad artifact, OOM, disk exhaustion, migration failure,
failed activation, incompatible database rollback) pass on an isolated host.
The real deploy path is unverified until the owner runs it.
