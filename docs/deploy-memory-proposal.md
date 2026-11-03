# Artifact deployment after the shared-host memory failure

Status: artifact deployment and SSR changes merged in PRs #102 and #103.
The owner's 2026-11-03 staging setup report found an unsupported systemd
property, pinned worker directories and access-control issues. This follow-up
corrects repository code and instructions; installed helpers still need an
owner update. Keep `DEPLOY_ARTIFACTS_READY` unset until host readiness is
reviewed. Live deployment and staging business checks remain unverified.
Email and Slack notifications are unused, as confirmed by the owner.

## What changes

The old workflow changed the serving checkout, ran migrations, then installed
Node dependencies and built on the shared host. Its exit trap could expose an
unsuccessful deployment. [Run 41300996787](https://github.com/msaifmfz/iianka/actions/runs/41300996787)
passed CI/release validation and failed in staging's SSH step. The npm memory
exhaustion and manual recovery are owner-provided findings; no raw host logs
were retrieved.

The new workflow builds in CI and ships a complete release: application code,
**production-only locked PHP dependencies**, tested client assets, revision,
and file hashes. The host runs **no npm, Composer installation, Vite build or
Wayfinder generation**. No application dependency versions change.

PR #103 disables SSR in Laravel and Vite. This workflow builds the client only
and excludes SSR bundles from the artifact. Merged changes do not alter the
currently serving release until a separately authorized deployment.

| Alternative | Assessment |
| --- | --- |
| CI artifacts and isolated releases | Implemented direction: removes dependency installation/build peaks from the host; validates before switching code; retains previous code and assets. |
| `NODE_OPTIONS=--max-old-space-size=384 npm ci --maxsockets=2 --no-audit --no-fund` | Process-local recovery workaround only. V8's heap cap does not cap native allocations, total RSS or Composer. It does not repair in-place deployment. No fallback to this is implemented. |
| Swap, more RAM, or separate staging | Owner actions, never performed here. Swap does not solve partial deployments; separate capacity is required for a literal guarantee of no production memory contention. |

## Implemented release contract

1. CI records the exact checked-out commit. Release validation verifies the
   tag resolves to the event commit and checks the existing release/changelog
   rules. Existing backend, frontend and production-browser checks remain.
2. CI exports tracked runtime inputs into a clean temporary directory, installs
   production Composer dependencies without scripts/plugins, and copies the
   tested build. Generated Composer platform checks include required extensions.
   No existing configuration, credentials, databases, storage, hot files or CI
   caches are copied. Hidden distribution metadata is removed.
   The only frontend build-time application setting found is `VITE_APP_NAME`.
   CI uses the repository variable of that name, falling back to `iianka`.
   Set the intended public title there before release; no server configuration
   or private key is copied into the build. That repository setting was not
   read or changed in this task.
3. The archive validator requires the complete production package set from the
   lock and every referenced Vite asset/import, including the JavaScript PDF
   worker. It rejects development vendor, traversal, links, devices, duplicate
   members, runtime files, missing hashes and incorrect revisions. Compressed
   input is limited to 256 MiB, extracted input to 1 GiB and members to 50,000.
4. CI unpacks the exact production artifact and boots it using a generated test
   key, process-local synthetic configuration and a disposable SQLite database.
   It also verifies rejection of a different environment's database path.
5. Only release-event artifacts are uploaded, with identity
   `release-<40-hex-commit>-<run-id>-<run-attempt>`. Deploy downloads that exact
   same-run name and verifies the archive checksum. Downstream jobs use the
   producer CI job's captured identity, including when only failed jobs rerun. There is no cross-run or
   missing-artifact fallback. Pull-request builds cannot be deployed.
6. Staging and production deploy jobs share one workflow concurrency group.
   A separate host-wide file lock covers transfer, preparation and activation.
7. The owner-installed, root-owned launcher validates arguments and host
   readiness, then executes the helper as `iianka:nginx` inside a finite shared
   systemd memory slice. It checks current available memory and free disk.
   Preparation reserves 2 GiB in addition to the owner's free-disk floor.
   After release validation, a separate job attaches the same tested bundle and
   checksum to the owner's existing published GitHub Release under an immutable
   commit/run/attempt filename. Only that job has `contents: write`; it never
   creates or publishes a release. Deployment also requires archival success.
   The existing v0.5.5-rc.2 release metadata was read-only checked and reports
   `immutable: false`. Published immutable releases cannot accept new assets;
   that case fails before host access and needs a pre-publication asset flow,
   not automatic disabling of immutability. See [GitHub's guidance](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases).
8. Preparation creates a new release directory, validates the payload, carries
   old hashed assets forward without overwriting different bytes under the same
   URL, links shared runtime paths, checks PHP compatibility and warms caches.
   Caches are forced to per-release paths. Compiled views are per-release: `view:cache` must not clear the serving
   release's shared views. Retained hashed assets are capped at 256 MiB; above
   that, stop for owner-reviewed cleanup rather than fill the filesystem.
9. Only the owner's existing SELinux file-context mappings are applied to the
   candidate. The helper changes no policy, boolean, Nginx or PHP-FPM settings.
   READY is written only after preparation succeeds. The serving pointer and
   services remain unchanged throughout preparation. Activation also refuses a
   candidate if current changed since preparation, preserving hashed-asset and
   rollback continuity across separately invoked operations.
10. Activation uses the existing owner-published release and GitHub environment
    authorization, with one-time owner-completed host readiness. It rechecks
    boot, verifies the expected local SQLite path, and uses Laravel 13's `migrate:status --pending=17` to reject pending
    migrations **before** maintenance or service changes. No migration or
    database rollback is automated.
11. Activation records the previous release, enters maintenance, stops only the
    selected environment's queue/scheduler, records their active state, atomically renames `current`, checks
    health, starts only previously active services and exits maintenance. Shared PHP-FPM/Nginx
    are never restarted. Catchable activation failures restore previous code;
    failed recovery leaves maintenance on and the job failed. TERM, INT and HUP
    exit nonzero through the recovery handler. Recovery restores the actual
    previous pointer even if a signal arrives immediately after atomic rename.

A cgroup kill, host crash or power loss may prevent shell cleanup. Current is
always either the old or a fully prepared release; it is never an extracted
half-release. Maintenance/services may still need owner recovery. The explicit
rollback below uses the recorded previous target. Database compatibility and
restorable backups remain prerequisites, not consequences of a symlink rename.

The launcher uses `OOMPolicy=stop` and `KillMode=control-group`. An OOM stop can
signal the shell and its children, allowing a surviving shell to attempt
cleanup. It cannot promise recovery if the shell itself is killed or the stop
deadline expires. Do not add `MemoryOOMGroup` (not an accepted systemd-run
property on the reported systemd 257 host) or switch to `OOMPolicy=kill`:
the latter requests a group kill that prevents shell recovery. See the
[systemd 257 OOM policy](https://www.freedesktop.org/software/systemd/man/257/systemd.service.html#OOMPolicy=)
and [termination behavior](https://www.freedesktop.org/software/systemd/man/257/systemd.kill.html#KillMode=).

The memory boundary caps deployment processes; it **does not reserve RAM for
production** or prove zero contention. Worker/scheduler units must run as `iianka:nginx` in that environment's current
directory; the launcher verifies these properties before service operations. Actual load, cgroup behavior, disk,
SELinux labels, FPM OPcache behavior and live deployment remain unverified.
Fail closed until the owner confirms these conditions. Do not enable the old
in-place workflow as an automatic fallback.

## Retention and recovery

Actions artifacts have **30-day retention** in this workflow, subject to the
repository's limits. They are the build-to-deploy transfer copy. The separate
GitHub Release asset archive is not governed by Actions artifact retention; it
remains available until explicitly removed along with normal release/repository
management. Expiry of the Actions copy does not delete that archive or extracted
server files.
Prepared payloads are retained at
`shared/deploy-artifacts/<release-id>.tar.gz`, and previous release directories
remain available. Neither local bundle nor old-release cleanup is automatic.
The free-space gate will refuse further deployments rather than silently prune
rollback material.

The release asset job provides the retained off-host build archive. Additional
independent backup remains advisable for repository deletion/account loss.
Before schema work, verify a current database/files snapshot under
[the operations recovery procedure](operations.md#backup-status-and-required-procedure).
An Actions artifact or a second file on the same server is insufficient disaster
recovery. Release bundles contain no runtime data and are not database/files
backups. Data backup retention and restore drills remain owner-managed.

## Owner host preparation — commands for owner review

Use a reviewed checkout of this PR. First confirm the guide's layout is real:
`/var/www/iianka/<environment>/current` must be a symlink into that environment's
`releases` directory, with real `shared/.env`, `shared/storage` and
`shared/database/database.sqlite` paths. The helper rejects in-place checkouts, cross-environment paths, and non-file
maintenance drivers. FPM must use the normal per-release bootstrap cache paths;
confirm no pool-level cache-path or storage-path overrides bypass those paths. If the actual topology differs, stop and prepare
a separate owner-reviewed conversion; do not move a serving checkout blindly.

Required host tools: PHP 8.5 with the locked production extensions, Python 3.11+
(standard library only), Bash, GNU coreutils, util-linux/flock, curl, sudo,
systemd with cgroup v2, and SELinux restorecon/semanage. No Node or Composer is
needed for normal deployment. Verify production PHP/extensions and FPM OPcache
behavior against the package on an isolated copy before enabling activation.

For an already prepared staging host, do not replay every installation command.
Compare installed files, retain a root-only copy of replaced helpers and record
ownership/modes before changing them. Update only the reviewed differences.
The shared helper and slice must not be overwritten during another deployment.
Do not prepare production as a side effect of staging setup.

All configuration ancestors (`/`, `/etc`, and `/etc/iianka-artifact-deploy`)
must be real, root-owned directories with no
group/other write permission. A root-owned config file inside an app-owned
parent is insufficient. The follow-up moves readiness configuration out of
the reported app-owned `/etc/iianka` tree so backup/restic access does not need
to change. Do not chown that existing tree or move its other files. On the
prepared host, write the four reviewed non-secret values into
`/etc/iianka-artifact-deploy/staging.conf` before installing the new launcher;
the old `/etc/iianka/artifact-deploy/staging.conf` is no longer used, and there
is no legacy fallback. Record the old/new helper and config state for rollback.
The launcher refuses an unsafe ancestor. The four readiness entries are
literal, unquoted `KEY=value` data: no exports, substitutions, extra keys or
duplicate keys. Missing settings cannot be inherited from a shell environment.

Install the fixed helpers; the CI account must never install or overwrite them:

```bash
sudo install -d -o root -g root -m 0755 /usr/local/libexec/iianka-deploy
sudo install -o root -g root -m 0755 .github/deploy/launcher.sh /usr/local/sbin/iianka-artifact-deploy
sudo install -o root -g root -m 0755 .github/deploy/host.sh /usr/local/libexec/iianka-deploy/host.sh
sudo install -o root -g root -m 0644 .github/deploy/artifact.py /usr/local/libexec/iianka-deploy/artifact.py
sudo install -o root -g root -m 0644 .github/deploy/runtime.php /usr/local/libexec/iianka-deploy/runtime.php
sudo restorecon -Rv /usr/local/libexec/iianka-deploy /usr/local/sbin/iianka-artifact-deploy

sudo install -d -o root -g root -m 0755 /etc/iianka-artifact-deploy
root=/var/www/iianka/staging
test -L "$root/current" || exit 1
sudo install -d -o iianka -g nginx -m 0750 "$root/shared/deploy-incoming" "$root/shared/deploy-artifacts"
sudo touch /var/www/iianka/.artifact-deploy.lock
sudo chown iianka:nginx /var/www/iianka/.artifact-deploy.lock
sudo chmod 0600 /var/www/iianka/.artifact-deploy.lock
```

Choose budgets from measured peak production usage and disk requirements. The
following **192 MiB ceiling, 512 MiB available-memory gate and 2 GiB disk floor
are unvalidated examples**, not a claim that this host has that capacity. If
they do not fit, provide capacity or separate staging. Preparation additionally
requires its bounded 2 GiB workspace reserve. The shared slice ceiling must be
finite and no higher than the configured per-environment ceiling.

```bash
sudo tee /etc/systemd/system/iianka-deploy.slice >/dev/null <<'UNIT'
[Unit]
Description=Bounded iianka artifact deployment
[Slice]
MemoryAccounting=yes
MemoryMax=192M
MemorySwapMax=0
CPUWeight=10
UNIT
sudo systemctl daemon-reload
sudo systemctl start iianka-deploy.slice
sudo systemctl show iianka-deploy.slice -p MemoryMax -p MemorySwapMax

# Replace examples with measured owner-approved values and the real HTTPS host.
sudo tee /etc/iianka-artifact-deploy/staging.conf >/dev/null <<'CONFIG'
MEMORY_MAX_BYTES=201326592
MIN_AVAILABLE_BYTES=536870912
MIN_FREE_DISK_BYTES=2147483648
HEALTH_URL=https://staging.example.com/up
CONFIG
sudo chown root:root /etc/iianka-artifact-deploy/staging.conf
sudo chmod 0644 /etc/iianka-artifact-deploy/staging.conf
# Create production.conf separately with the production health URL and reviewed budgets.
```

`MemoryHigh` is deliberately omitted: the reported no-swap host drill stalled
under reclaim until timeout with that setting. `MemoryMax` remains the hard
cap. `IOWeight` is omitted because the report found an unsupported disk
scheduler for proportional I/O weighting. Do not overwrite the measured host
settings with these example numbers. The report's 192 MiB cap, 536 MiB
available-memory gate and 10 GiB disk floor were measured with a synthetic
application, not the real CI artifact. Re-measure available memory after the
interactive coding agent exits; an estimate is not a passing gate. Do not
lower the gate just to force a deployment through.

Worker units must use `WorkingDirectory=/var/www/iianka/staging/current` and
their `ExecStart` commands must resolve that current release's artisan, not a
pinned historical release. Inspect effective values and drop-ins first. Record
the exact prior units, check `NeedDaemonReload` for unrelated pending edits,
and apply only a reviewed staging correction (a narrowly scoped drop-in is
preferable to broad text replacement). `systemctl daemon-reload` does not
restart running workers; their new directory takes effect on the next start,
including an automatic worker restart. Do not restart them during setup or
modify production units. Roll back the exact edited file/drop-in and reload
the daemon again. If unrelated pending edits exist, stop for owner review.

Use `visudo` to grant only the fixed validating launcher, not a shell, arbitrary
systemd-run, arbitrary systemctl or arbitrary restorecon. The launcher accepts
only reviewed operations, fixed environments, hex digests and numeric run
identities; app/bootstrap code always runs as iianka, never root. For staging
use the following argument-regex rules (sudo 1.9.10+); the launcher additionally
validates the argument vector and matching release/revision. Review with
`visudo` and test the allow/deny matrix before installation:

```sudoers
iianka ALL=(root) NOPASSWD: /usr/local/sbin/iianka-artifact-deploy ^(prepare|activate|rollback|labels) staging [0-9a-f]{40}-[0-9]+-[0-9]+ [0-9a-f]{40} [0-9a-f]{64}$
iianka ALL=(root) NOPASSWD: /usr/local/sbin/iianka-artifact-deploy ^services-(state|stop) staging$
iianka ALL=(root) NOPASSWD: /usr/local/sbin/iianka-artifact-deploy ^services-start staging [01] [01]$
```

An existing `NOPASSWD: ALL` grant still wins: adding these rules does not
constrain the CI key. Before removing a broad grant, verify independent owner
administrative access and inventory production/backup automation that uses
the same account. Removing it can affect production even without editing a
production unit. If that cannot be resolved within staging-only authority,
leave deployment disabled and present the access transition for owner approval.
Do not silently restore a broad grant as an automated fallback. A separately
reviewed production rule is required before production deployment is enabled.

Review `.env` metadata only. Staging's file must not be world-readable; choose
ownership/group access from the actual FPM and worker identities, retain their
read access, and record the previous metadata for rollback. Do not print or
copy secret contents. Production permission changes require separate scope.

Review existing mappings first. Add these only if absent; preserve pre-existing
entries instead of overwriting them. Repeat for production only after review.
The launcher applies them to new candidates; it does not create these mappings:

```bash
environment=staging
root="/var/www/iianka/$environment"
sudo semanage fcontext -a -t httpd_sys_content_t "$root/releases(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "$root/releases/[^/]+/bootstrap/cache(/.*)?"
```

Ensure shared runtime paths retain their existing correct mappings. Do not
change `httpd_can_network_connect` or the HTTPS egress module. Do not allow SSR
port 13714. In GitHub, configure each environment's existing SSH settings and
review protection, then set `DEPLOY_ARTIFACTS_READY=true` only after host
readiness is established. `DEPLOY_PATH` is no longer used: the launcher fixes
the reviewed per-environment root. No repository/environment settings were
changed by this task.

## Release authorization and migration procedure

The normal release/environment authorization remains the deploy authorization;
there is no additional manual checksum-approval file for every deployment.
The CI summary and archived release asset expose the exact commit, run, attempt
and checksum for review. Before publishing/approving a release, review code and
database backward compatibility for both activation and code rollback. This task
does not publish or approve any release or change environment protection rules.

If migrations are pending, automatic activation stops without maintenance,
service changes or a pointer switch. The owner must use the documented backup
procedure, stop that environment's writers, drain in-flight web requests, and
run the prepared release's migrations during an approved maintenance window.
For an incompatible migration, do not resume the old application against the
new schema: keep maintenance on and use a release-specific cutover/recovery
plan. No generic database rollback or schema compatibility guess is provided.
The no-pending-migration automatic path intentionally refuses an already
maintenance-mode environment.

Example owner-only migration invocation, after the snapshot/drain/maintenance
steps and with the exact prepared release path reviewed:

```bash
sudo systemd-run --wait --pipe --collect --uid=iianka --gid=nginx \
    --slice=iianka-deploy.slice --property=MemorySwapMax=0 \
    --property=OOMPolicy=stop --property=KillMode=control-group \
    --working-directory=/var/www/iianka/staging/releases/APPROVED_RELEASE_ID \
    /usr/bin/php -d memory_limit=128M artisan migrate --force --no-interaction
```

This command is not part of CI and must not be run without its snapshot,
compatibility and writer-drain gates. If recovery is required, preserve the
failed database and use the reviewed restore procedure. Do not blindly run
`migrate:rollback`, automatically retry activation, or expose failed schema work. Rerun all jobs
to create a fresh artifact identity after reviewing recovery state; rerunning
only failed deploy jobs retains the producer identity and will reject an
already-created candidate instead of overwriting it.

## Code rollback and interrupted-deploy recovery

For a current artifact release with compatible schema, the owner can roll back to its recorded previous target. Supply the exact
current identity, its source revision and its original artifact checksum:

```bash
sudo /usr/local/sbin/iianka-artifact-deploy rollback staging \
    APPROVED_RELEASE_ID SOURCE_COMMIT ORIGINAL_ARTIFACT_SHA256
```

The rollback performs the same boot/migration-status/health checks, scoped
service handling and atomic switch. It does not restore a database snapshot.
If the process was killed while maintenance was active, first identify current,
READY and PREVIOUS without changing them. Use the operations recovery procedure
and an owner-reviewed manual switch if the automatic maintenance gate rejects
recovery; do not simply run `artisan up` on an unknown state.

To disable this deployment mechanism, set `DEPLOY_ARTIFACTS_READY=false` in both
GitHub environments first, wait for all
transient deploy units to exit, and remove the launcher sudo rule with visudo.
Only then remove helper files/configuration and stop/remove the deploy slice.
Keep all served release mappings, release directories and archived bundles
until the owner verifies that no serving/rollback state relies on them. Restore
any prior unit/configuration rather than delete pre-existing owner settings.
Do not re-enable the old in-place deploy without accepting its original risks.

## Validation boundary

Repository tests execute launcher decisions with stubbed system commands and
synthetic configuration metadata. The unprivileged launcher copy changes only
the root identity check, fixed configuration location and command lookup; the
installed launcher has no test override. Host tests use synthetic filesystem
states and stub PHP/services/health calls, including catchable group signals.
Linux CI uses real GNU rename/readlink/flock; local macOS tests adapt those
primitives. With `IIANKA_TEST_SYSTEMD=1`, Linux CI also submits the launcher's
actual emitted properties to real systemd and triggers a bounded child OOM
during synthetic activation, checking restoration of the previous pointer and
maintenance state. No serving host or application service is involved. Local
macOS skips these two systemd checks. A simulated child exit 137 alone is not
an OOM drill, and the CI drill does not validate this host's aggregate slice
or memory budget. The unpacked-artifact smoke uses actual production
dependencies and a throwaway database. Shell syntax, ShellCheck, PHP
lint/static analysis, normal repository checks and CI apply.

Real systemd/cgroup/SELinux/FPM behavior, live activation/rollback, off-host
backup recovery and business-flow staging verification are **unverified** until
the owner performs them after implementation, as requested. The staging setup
report is owner-supplied evidence; this repository correction did not access
the server, read existing environment files or production data, or run any
host changes. Keep deployment disabled pending the reviewed host update.
