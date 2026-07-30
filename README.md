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

## Documentation

Start with [`docs/README.md`](docs/README.md) for the maintained architecture map, domain guide,
database ERD, security model, development workflow, operations runbook, and LLM-oriented guide.
Any changes should be updated back in to that documents under docs folder.

## Local setup

Install [Devbox](https://www.jetify.com/docs/devbox/installing-devbox/) and direnv once on a new Mac:

```bash
curl -fsSL https://get.jetify.com/devbox | bash
# Open a new terminal after the installer finishes.
devbox global add direnv@2
```

Add the following to `~/.zshrc`, then run `exec zsh`:

```bash
eval "$(devbox global shellenv)"
eval "$(direnv hook zsh)"
```

Initialize the repository once. After `direnv allow`, entering the directory automatically
activates PHP 8.5, Composer 2, Node 22, SQLite, and Git from the committed Devbox lockfile.

```bash
direnv allow
devbox run setup   # dependencies, .env, SQLite, migrations, assets, hooks, Playwright browsers
devbox run dev     # serve + queue + pail + vite (concurrently)
```

Run `devbox run doctor` to verify the pinned runtime and Composer platform requirements.

## Quality checks

```bash
devbox run check       # pint, rector, psalm, phpstan, JS lint/format/types
devbox run test        # config clear, pint check, Pest
devbox run test:e2e    # Chromium, Firefox, and WebKit
composer security      # psalm taint analysis + composer audit
```

The setup command installs Lefthook explicitly: `pre-commit` auto-fixes
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
Setup: [`SERVER_SETUP.md`](SERVER_SETUP.md). Current deploy, backup, and restore procedures:
[`docs/operations.md`](docs/operations.md).
