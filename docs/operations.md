# Operations and recovery

Last verified: 2026-11-03

This document records what the tracked repository currently automates and the minimum safe
operational procedures around it. [SERVER_SETUP.md](../SERVER_SETUP.md) remains the detailed
AlmaLinux/Nginx/PHP-FPM/Redis provisioning guide.

## Environment model

| Concern          | Local default                        | Intended staging/production                    |
| ---------------- | ------------------------------------ | ---------------------------------------------- |
| Primary database | SQLite in `database/database.sqlite` | One persistent SQLite file per environment     |
| Cache            | Database                             | Redis                                          |
| Session          | Database                             | Redis                                          |
| Queue            | Database                             | Redis with a systemd worker                    |
| Scheduler        | Not needed for current app tasks     | systemd `schedule:work` service is provisioned |
| Files            | Private `storage/app/private`        | Shared private storage outside release code    |
| Web              | Artisan server + Vite                | Nginx + PHP-FPM + TLS                          |

SQLite must remain on local server storage, not a network filesystem. Staging and production need
separate application keys, databases, Redis namespaces/databases, storage, and cookie domains.

## CI and release flow

Tracked GitHub workflows:

| Workflow                                | Responsibilities                                                      |
| --------------------------------------- | --------------------------------------------------------------------- |
| `.github/workflows/tests.yml`           | Install, build, and Pest                                              |
| `.github/workflows/lint.yml`            | Pint, frontend formatting/lint, Wayfinder generation                  |
| `.github/workflows/static-analysis.yml` | PHPStan, Psalm, Rector, taint analysis, Composer/npm audits           |
| `.github/workflows/deploy.yml`          | CI artifact build, release validation, guarded environment deployment |

Releases drive deployment:

- A published prerelease whose tag contains `-rc.` deploys to the `staging` GitHub environment.
- A published final release without `-rc.` deploys to the `production` GitHub environment.
- Both forms require a matching final-version section in `CHANGELOG.md`.
- A final release should point at the same commit validated by its release candidate.

The artifact workflow builds and tests a complete production release in CI,
then downloads that exact same-run archive for deployment. The host prepares a
new release and atomically switches `current` only after owner approval and
readiness checks. It does not install dependencies, build assets or migrate
automatically on the host. Missing readiness fails closed; no old-flow fallback
is enabled. See [artifact deployment](deploy-memory-proposal.md) for required
owner preparation, memory/disk limits, migration handling, retention and rollback.
Staging artifact deploys, including automatic migrations, were verified on
2026-11-03; production artifact deploys are not enabled yet.

Before this change, the remote deploy ran over SSH in `DEPLOY_PATH`:

1. Enter maintenance mode.
2. Fetch tags and check out the release tag.
3. Install production Composer dependencies.
4. Run migrations.
5. Clear/cache Laravel configuration, routes, views, events, and optimization.
6. Generate Wayfinder definitions.
7. Install Node dependencies and build client assets.
8. Restart queues/reload long-running Laravel processes.
9. Leave maintenance mode, including through an exit trap.

GitHub environment secrets and variables supply host, SSH key, known hosts, port, and target path.
Never put them in repository files.

### Inertia SSR decision

The owner chose to disable SSR after the v0.5.5-rc.2 staging verification on
2026-11-01. Both the Laravel adapter and the Inertia Vite plugin disable SSR;
the deploy workflow builds only the client bundle. The optional `build:ssr`
script remains available for a future reviewed SSR setup, but no deploy job
calls it. Re-enabling SSR would also require changing both configurations.

No application feature was found to require SSR. Operational routes require
authentication, indexing is blocked by middleware, robots.txt and the root
template, and quotation PDFs are rendered separately by PHP/mPDF. Browser-only
map components already defer rendering until mounted. Client rendering was
already the fallback when the configured SSR endpoint had no listener.

The owner reported no SSR service on the shared 1.7 GB host and reproduced
port 13714 SELinux denials on staging (74 by mid-evening on 2026-11-01).
Production's contribution is unverified because the web pools share `httpd_t`.
Do not allow port 13714 merely to replace a denial with connection refused.

A real SSR service would need an owner-approved Node.js service bound to
loopback, supervision, health checks, release coordination and a separately
reviewed SELinux design. Its RSS is unmeasured; budget at least 128–256 MiB
per environment as an initial planning estimate, then measure under load.
That is 256–512 MiB for two single-process services before headroom, and
clustering would multiply it. The estimate is not a capacity guarantee on
this shared host with no swap. Disabling SSR avoids that persistent cost.

After deploying this change, the owner should refresh each environment's
configuration cache in its current release:

```bash
php artisan config:cache --no-interaction
```

Verify authenticated page loads and the CRM/PDF browser flows, then correlate
Laravel and audit logs: no new port 13714 connection attempts should result
from those page loads. Port 443 geocoding remains separate. No new service or
SELinux change is needed. These host checks have not been performed here.

Rollback is a normal reviewed deployment of the preceding release followed
by `php artisan config:cache --no-interaction`. This restores the previous
SSR attempts and build cost; it does not create a working SSR service.

## Release checklist

Before creating a GitHub Release:

```bash
devbox run check
devbox run test
devbox run test:e2e
composer security
npm run changelog -- --tag vX.Y.Z
```

Then:

1. Review and commit the changelog before tagging.
2. Push the release-candidate tag and publish it as a prerelease.
3. Verify staging business flows, file access, logs, queue, and `/up`.
4. If the release contains migrations and `AUTO_MIGRATE` is off, take a [pre-migration snapshot](#pre-migration-snapshot).
5. Point the final tag at the tested commit and publish a final release.
6. Verify production health and recent audit/application logs.

## Health and diagnostics

Application:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
curl -fsS https://your-host.example/up
```

Services, using the names from the server guide:

```bash
sudo systemctl status php-fpm nginx redis --no-pager
sudo systemctl status iianka-queue@production --no-pager
sudo systemctl status iianka-scheduler@production --no-pager
```

Logs:

```bash
tail -f storage/logs/laravel.log
sudo journalctl -u php-fpm -f
sudo journalctl -u nginx -f
sudo journalctl -u iianka-queue@production -f
sudo journalctl -u iianka-scheduler@production -f
```

Use environment-specific hostnames and service names. Do not paste secrets or user data into issue
reports.

## Queue and scheduler

- Local `composer dev` runs `queue:listen`.
- The production guide runs `queue:work` under systemd and restarts it after deployment.
- `jobs`, `job_batches`, and `failed_jobs` exist even though there are currently no custom
  `app/Jobs` classes.
- `php artisan schedule:list` currently reports no scheduled tasks.

Do not treat a running scheduler service as evidence that backups or maintenance jobs exist.

## Backups

### What runs

Host-level automation outside Laravel (no `db:backup` command or scheduled app task). Sources
are tracked in `.github/deploy/backup.sh`, `backup.service` and `backup.timer`; install them by
hand as described in each file header, then `systemctl daemon-reload`.

| Item          | Value                                                                                                                                                                                                          |
| ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tool          | restic (AlmaLinux package), encrypted client-side, S3 backend                                                                                                                                                  |
| Destination   | Cloudflare R2 bucket `iianka-backups` (Standard class, no public access), restic repo `2109ebf3ea`                                                                                                             |
| Script        | `/usr/local/sbin/iianka-backup` (root)                                                                                                                                                                         |
| Units         | `iianka-backup.service` (oneshot, `MemoryHigh=250M`, `MemoryMax=400M`) and `iianka-backup.timer`                                                                                                               |
| Schedule      | Daily 03:30 JST + up to 10 min random delay; Sunday runs also prune and `check` 5% of data                                                                                                                     |
| Retention     | `scheduled`: 14 daily, 8 weekly, 6 monthly. `pre-migration`: 90 days. `manual`: 30 days                                                                                                                        |
| Status        | `journalctl -u iianka-backup` and `/var/log/iianka-backup/last-status` (`OK`/`FAIL` line)                                                                                                                      |
| R2 credential | `/etc/iianka-backup/r2.env`, root:root 0600 (`AWS_*` keys and `RESTIC_REPOSITORY`). Account API token `iianka-alma restic backup`: Object Read & Write on this bucket only, restricted to the host's IPv4/IPv6 |
| Repo password | `/etc/iianka-backup/restic.pass`, root:root 0600; a copy must exist outside the host                                                                                                                           |
| Alert         | Optional `/etc/iianka-backup/healthcheck.url` (root 0600, one `https://` ping URL, e.g. healthchecks.io with a 1-day period and 2-hour grace); no file, no pings                                               |

Each run:

1. Pings the optional health check `/start`, then refuses to start below 600 MiB `MemAvailable`.
2. Takes the deploy lock `/var/www/iianka/.artifact-deploy.lock` (waits up to 30 min), so a backup
   and a deploy never overlap. A deploy started during a backup fails fast; retry after the run.
3. Per environment: `sqlite3 .backup` into `/var/lib/iianka-backup/stage/<env>/`,
   `PRAGMA integrity_check` must return `ok`, records the `current` release path, then backs up the
   snapshot plus `shared/storage/app` (excluding `private/quotation-pdf-cache`). Tags: `<env>` and
   the run tag.
4. With both environments: backs up host config (`/etc/iianka-artifact-deploy`, `/etc/nginx`,
   `/etc/php-fpm.d`, `/etc/php.ini`, `/etc/php.d`, `/etc/letsencrypt/renewal`, iianka systemd
   units and `*.service.d` drop-ins, `/usr/local/sbin/iianka-*`, `/usr/local/libexec/iianka-deploy`,
   `/etc/sudoers.d/iianka*`) tagged `host-config`.
5. Applies retention, removes the staged copies, writes `last-status` and pings the health check
   (success, or `/fail`).

Not backed up: `releases/` (rebuilt from tags), `storage/framework`, `storage/logs`, deploy
artifact directories, Let's Encrypt keys (re-issue), and `shared/.env` files. Keep each
environment's `.env` (especially `APP_KEY`) in the password manager; without the matching
`APP_KEY`, encrypted columns in a restored database are unreadable.

`restic` commands below need a root shell with the repository settings loaded:

```bash
sudo -i
set -a; . /etc/iianka-backup/r2.env; set +a
export RESTIC_PASSWORD_FILE=/etc/iianka-backup/restic.pass RESTIC_CACHE_DIR=/var/cache/iianka-backup
```

### Run a backup / check status

```bash
sudo systemctl start iianka-backup.service
cat /var/log/iianka-backup/last-status
journalctl -u iianka-backup -n 50 --no-pager
systemctl list-timers iianka-backup.timer
```

### Pre-migration snapshot

Automatic when the environment's deploy config sets `AUTO_MIGRATE=1`: activation takes a local
and off-host `pre-migration` snapshot inside its maintenance window before migrating, and records
the snapshot ID in the release's `MIGRATED` file (see
[automatic migrations](deploy-memory-proposal.md#automatic-migrations-opt-in)).

Otherwise required before any release that contains migrations, before activating it:

```bash
sudo /usr/local/sbin/iianka-backup --tag pre-migration --env production
cat /var/log/iianka-backup/last-status   # must start with today's time and OK
```

Use `--env staging` for a staging release. For the strongest boundary, run it after
`php artisan down` and stopping `iianka-queue@<env>` / `iianka-scheduler@<env>`; the SQLite
`.backup` is consistent either way. Record the snapshot ID (next section) in the release notes.

### List snapshots

```bash
restic snapshots --host iianka                       # all
restic snapshots --host iianka --tag production       # one environment
restic snapshots --host iianka --tag pre-migration
restic stats --mode raw-data                         # repository size in R2
```

### Restore to a scratch location

Never restore over live paths. Restore, verify, then follow the live replacement procedure below.

```bash
environment_name=production
scratch="/var/lib/iianka-backup/restore-$(date -u +%Y%m%dT%H%M%SZ)"
restic restore latest --host iianka --tag "$environment_name" --target "$scratch"

restored_db="$scratch/var/lib/iianka-backup/stage/$environment_name/database.sqlite"
test "$(sqlite3 -readonly "$restored_db" 'PRAGMA integrity_check;')" = ok
test -z "$(sqlite3 -readonly "$restored_db" 'PRAGMA foreign_key_check;')"
cat "$scratch/var/lib/iianka-backup/stage/$environment_name/current-release.txt"
find "$scratch/var/www/iianka/$environment_name/shared/storage/app" -type f | wc -l
```

Use a snapshot ID instead of `latest` for an older point. Single files:
`restic restore <id> --target "$scratch" --include '/var/www/iianka/production/shared/storage/app/private/site-guides'`.
Host config: `restic restore latest --host iianka --tag host-config --target "$scratch"`.
Delete the scratch directory when finished (`rm -rf "$scratch"`); it contains user data.

### Rotate the R2 credential

1. Cloudflare dashboard → R2 → Manage API tokens → create a new token with Object Read & Write
   scoped to `iianka-backups` only.
2. On the host, replace the two `AWS_*` values in `/etc/iianka-backup/r2.env`, keeping root:root
   0600 (`sudo install -m 0600 -o root -g root new.env /etc/iianka-backup/r2.env`, then shred the
   temporary file).
3. `sudo systemctl start iianka-backup.service` and confirm `last-status` is `OK`.
4. Revoke the old token in Cloudflare.

The restic repository password is separate. To rotate it: `restic key add` (new password from a
file), update `restic.pass` and the off-host copy, `restic key list`, then `restic key remove <old-id>`.

### Restore drill

Restore the staging database and storage to a scratch location at least quarterly and record
the date, snapshot ID and checks here.

| Date       | Snapshot                        | Checks                                                                                                                        | Result |
| ---------- | ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- | ------ |
| 2026-11-03 | `bdbfe57d` (staging)            | integrity_check ok, 0 FK violations, 45/45 table row counts and schema hash equal live, 46/46 storage files sha256 equal live | Pass   |
| 2026-11-03 | `5f8c1ede` (production DB only) | integrity_check ok, 0 FK violations                                                                                           | Pass   |

### Replace live data from a verified snapshot

Restoring replaces live data. Schedule downtime, identify the code version compatible with the
snapshot, and have a second operator verify paths.

1. Restore the chosen snapshot to a scratch location (above).
2. Verify `PRAGMA integrity_check` before touching the live database.
3. Enter maintenance mode and stop every process that can write: queue, scheduler, and any other
   worker.
4. Preserve the current database as a timestamped pre-restore copy.
5. Replace the database with the verified snapshot and restore correct owner/mode.
6. Check foreign keys and migration status.
7. Boot with the matching application release and run a smoke test before reopening traffic.

Template:

```bash
set -euo pipefail

environment_name=production
application_root="/var/www/iianka/${environment_name}/current"
database_file="/var/www/iianka/${environment_name}/shared/database/database.sqlite"
snapshot_file="/absolute/path/to/verified-snapshot.sqlite"
pre_restore_copy="${database_file}.pre-restore-$(date -u +%Y%m%dT%H%M%SZ)"

test -f "$database_file"
test -f "$snapshot_file"
test "$(sqlite3 "$snapshot_file" 'PRAGMA integrity_check;')" = "ok"

cd "$application_root"
php artisan down --render="errors::503" --retry=60
sudo systemctl stop "iianka-queue@${environment_name}" "iianka-scheduler@${environment_name}"

cp -p "$database_file" "$pre_restore_copy"
cp "$snapshot_file" "$database_file"
test "$(sqlite3 "$database_file" 'PRAGMA integrity_check;')" = "ok"
test -z "$(sqlite3 "$database_file" 'PRAGMA foreign_key_check;')"
php artisan migrate:status
```

Do not automatically run new migrations until the intended restore point and compatible code
release have been confirmed. After smoke testing:

```bash
sudo systemctl start "iianka-queue@${environment_name}" "iianka-scheduler@${environment_name}"
php artisan up
```

Perform and record a restore drill at least quarterly after backup automation is established.

## Data and file recovery coupling

SQLite backups do not include files under shared private storage. A complete recovery plan must
back up:

- The SQLite database.
- `storage/app/private`, including reception attachments and site guides.
- Environment configuration and secrets through an approved secret manager, not the backup
  archive.
- The exact deployed Git tag/commit.

Restore database and private storage to a mutually consistent point where practical.

## Nightly Codex issue automation

The repository contains a separate local automation under `.codex`, intended for a dedicated
non-production checkout. It can label issues, create a branch, make per-issue commits, push, and
open a draft PR.

Before enabling it:

- Read `.codex/automations/nightly-github-issues.md` and `.codex/scripts/readme.md`.
- Use a checkout that cannot affect the live web root.
- Verify `gh`, Codex, and Git credentials for the cron user.
- Run `composer nightly:issues:check`.
- Resolve the branch mismatch: scripts default to `BASE_BRANCH=develop`, while this repository
  currently exposes `main` and no remote `develop`. Set `BASE_BRANCH=main` or create an intentional
  development branch before the first run.
- Keep GitHub issue bodies untrusted and preserve the automation's protected-path rules.

## Verified operational gaps

| Gap                                       | Evidence                                                                                            | Required decision                                                            |
| ----------------------------------------- | --------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Production artifact deploy not enabled    | Staging verified 2026-11-03; production lacks `production.conf`, sudo rules and the GitHub variable | Prepare and review production before enabling production artifact deploys    |
| No production deploy sudo rule            | Blanket iianka sudo removed 2026-11-03; only staging launcher rules exist                           | Add a reviewed production rule before enabling production artifact deploys   |
| Scheduler provisioned with no app tasks   | `schedule:list` reports none                                                                        | Keep service for future use or remove unnecessary operational surface        |
| Nightly automation targets missing branch | Default `develop`; remote currently only has `main`                                                 | Configure the actual base branch before enabling                             |
| Local schema drift                        | Current local DB lacks `work_memo`; fresh migrations include it                                     | Check persistent environments and add a forward migration if affected        |

Track these as explicit work items. Do not silently encode an assumed resolution in an unrelated
change.
