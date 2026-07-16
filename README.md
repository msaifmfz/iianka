# iianka

Internal operations app for construction scheduling, attendance, reception (受付) and stock management.

Private business application — not a library, not published. See `CHANGELOG.md` for release history.

## Stack

Laravel 13 · PHP 8.5 · Inertia v3 · React 19 · Tailwind v4 · SQLite · Pest 4.
Wayfinder generates typed route helpers; Fortify handles authentication.

## Features

Construction & business schedules · schedule overview and search · attendance records ·
cleaning duty rules · internal notices · voucher confirmations · reception workflow ·
stock management · admin.

## Local setup

```bash
composer setup   # install deps, .env, key, migrate, npm install, build
composer dev     # serve + queue + pail + vite (concurrently)
```

## Quality checks

```bash
composer check      # pint, rector, psalm, phpstan, JS lint/format/types
composer test       # config clear, pint check, Pest
composer security   # psalm taint analysis + composer audit
```

Git hooks install automatically via `npm install` (lefthook): `pre-commit` auto-fixes
staged files, `pre-push` runs static analysis, `commit-msg` enforces the subject prefix.
See `CLAUDE.md` for the full static-analysis and baseline policy.

## Releasing

Publishing a GitHub Release **is** the deploy trigger: `vX.Y.0-rc.N` deploys to staging,
`vX.Y.0` deploys to production at the same commit the release candidate validated.

The changelog must land **before** the release-candidate tag — CI rejects a release whose
tag has no matching `CHANGELOG.md` section:

```bash
npm run changelog -- --tag vX.Y.0        # prepend the new section
$EDITOR CHANGELOG.md                     # reword any cryptic bullets
git commit -am "docs: changelog for vX.Y.0" && git push
git tag vX.Y.0-rc.1 && git push origin vX.Y.0-rc.1   # -> staging
git tag vX.Y.0 vX.Y.0-rc.1^{commit} && git push origin vX.Y.0   # -> production, same commit
```

## Deployment

AlmaLinux 10 + nginx + Redis + SQLite, with always-running queue workers and scheduler.
Setup: `SERVER_SETUP.md`. Database backup/restore: `agentic_code_docs/database-backup-runbook.md`.
