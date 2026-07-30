# Operations and recovery

Last verified: 2026-07-30

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

| Workflow                                | Responsibilities                                                   |
| --------------------------------------- | ------------------------------------------------------------------ |
| `.github/workflows/tests.yml`           | Install, build, and Pest                                           |
| `.github/workflows/lint.yml`            | Pint, frontend formatting/lint, Wayfinder generation               |
| `.github/workflows/static-analysis.yml` | PHPStan, Psalm, Rector, taint analysis, Composer/npm audits        |
| `.github/workflows/deploy.yml`          | Combined CI, release validation, staging deploy, production deploy |

Releases drive deployment:

- A published prerelease whose tag contains `-rc.` deploys to the `staging` GitHub environment.
- A published final release without `-rc.` deploys to the `production` GitHub environment.
- Both forms require a matching final-version section in `CHANGELOG.md`.
- A final release should point at the same commit validated by its release candidate.

The current remote deploy runs over SSH in `DEPLOY_PATH`:

1. Enter maintenance mode.
2. Fetch tags and check out the release tag.
3. Install production Composer dependencies.
4. Run migrations.
5. Clear/cache Laravel configuration, routes, views, events, and optimization.
6. Generate Wayfinder definitions.
7. Install Node dependencies and build client plus SSR assets.
8. Restart queues/reload long-running Laravel processes.
9. Leave maintenance mode, including through an exit trap.

GitHub environment secrets and variables supply host, SSH key, known hosts, port, and target path.
Never put them in repository files.

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
4. Verify a current database snapshot exists off-host.
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

## Backup status and required procedure

### Current tracked status

The repository currently has:

- No `db:backup` or restore Artisan command.
- No `config/backup.php`.
- No scheduled backup or verification task.
- No pre-migration snapshot step in `.github/workflows/deploy.yml`.
- No tracked off-site backup configuration or restore-drill record.

Therefore, backups are an external operational responsibility today. Do not claim that deploys
automatically protect SQLite.

### Minimum manual snapshot

Before any production migration, take a SQLite-native snapshot, verify it, and copy it to encrypted
off-host storage. The following is a template; confirm every explicit path and service name first:

```bash
set -euo pipefail

environment_name=production
application_root="/var/www/iianka/${environment_name}/current"
database_file="/var/www/iianka/${environment_name}/shared/database/database.sqlite"
backup_directory="/var/www/iianka/${environment_name}/shared/backups"
snapshot_timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
snapshot_file="${backup_directory}/database-${snapshot_timestamp}.sqlite"

test -d "$application_root"
test -f "$database_file"
install -d -m 0750 "$backup_directory"

cd "$application_root"
php artisan down --render="errors::503" --retry=60
sudo systemctl stop "iianka-queue@${environment_name}" "iianka-scheduler@${environment_name}"

sqlite3 "$database_file" ".backup '${snapshot_file}'"
test "$(sqlite3 "$snapshot_file" 'PRAGMA integrity_check;')" = "ok"
gzip -c "$snapshot_file" > "${snapshot_file}.gz"
```

After the compressed snapshot has been copied off-host and verified:

```bash
sudo systemctl start "iianka-queue@${environment_name}" "iianka-scheduler@${environment_name}"
php artisan up
```

The SQLite `.backup` command creates a transactionally consistent snapshot. Maintenance mode and
stopped background workers also reduce application writes and make the operational boundary
obvious.

Add retention, encryption, failure alerting, and periodic restore drills in the external backup
system. A local file on the same server is not sufficient disaster recovery.

### Restore procedure

Restoring replaces live data. Schedule downtime, identify the code version compatible with the
snapshot, and have a second operator verify paths.

1. Download/decrypt the chosen raw SQLite snapshot.
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

| Gap                                           | Evidence                                                                                    | Required decision                                                             |
| --------------------------------------------- | ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- |
| No automated backup or pre-migration snapshot | No command/config/schedule; deploy calls `migrate --force` directly                         | Implement tracked automation or an externally monitored equivalent            |
| Deployment topology mismatch                  | Server guide uses release directories/current symlink; workflow checks out in `DEPLOY_PATH` | Choose atomic releases or in-place checkout and align both documents/workflow |
| Scheduler provisioned with no app tasks       | `schedule:list` reports none                                                                | Keep service for future use or remove unnecessary operational surface         |
| Nightly automation targets missing branch     | Default `develop`; remote currently only has `main`                                         | Configure the actual base branch before enabling                              |
| Local schema drift                            | Current local DB lacks `work_memo`; fresh migrations include it                             | Check persistent environments and add a forward migration if affected         |

Track these as explicit work items. Do not silently encode an assumed resolution in an unrelated
change.
