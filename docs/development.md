# Development guide

Last verified: 2026-07-30

## Supported local environment

The repository pins the development toolchain with Devbox:

- PHP 8.5
- Composer 2
- Node.js 22
- SQLite 3
- Git 2

The committed `devbox.lock` is authoritative. `package.json` requires Node `>=22 <23`.

## First setup

Install Devbox and direnv once, then:

```bash
direnv allow
devbox run setup
devbox run doctor
```

`devbox run setup` installs PHP and Node dependencies, creates `.env` and the SQLite file when
missing, generates the application key, migrates the database, builds assets, installs Lefthook,
and installs Playwright browsers.

The default seeders create demonstration users with known passwords. Seed only disposable local or
test databases.

## Daily commands

| Task                      | Preferred command                   | Underlying behavior                                                      |
| ------------------------- | ----------------------------------- | ------------------------------------------------------------------------ |
| Start app                 | `devbox run dev`                    | Laravel server, queue listener, Pail, and Vite                           |
| Run backend suite         | `devbox run test`                   | Config clear, Pint check, Pest                                           |
| Run full quality gate     | `devbox run check`                  | PHP format, Rector dry-run, Psalm, PHPStan, ESLint, Prettier, TypeScript |
| Run browser tests         | `devbox run test:e2e`               | Playwright with a fresh dedicated SQLite database                        |
| Check environment         | `devbox run doctor`                 | Runtime versions and Composer platform requirements                      |
| Build assets              | `npm run build`                     | Production client build                                                  |
| Build client + SSR bundle | `npm run build:ssr`                 | Used by current CI/deploy workflow                                       |
| Security checks           | `composer security`                 | Psalm taint analysis and Composer audit                                  |
| Generate changelog        | `npm run changelog -- --tag vX.Y.Z` | Fetch tags and prepend release section                                   |

Direct Composer/npm commands remain available; Devbox is preferred because it supplies the pinned
runtime.

## Source generation

Wayfinder generates:

- `resources/js/actions`
- `resources/js/routes`
- `resources/js/wayfinder`

They are Git-ignored and must not be hand-edited. `vite.config.ts` enables form variants.

Generate explicitly when needed:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

The Vite plugin also generates definitions during normal frontend work. If imports are missing or
stale, regenerate before debugging TypeScript code.

Frontend links, forms, and router calls should consume generated functions from `@/actions` or
`@/routes`. Avoid hardcoded application URLs.

## Test structure

| Suite   | Location        | Database/runtime                        | Use for                                                       |
| ------- | --------------- | --------------------------------------- | ------------------------------------------------------------- |
| Unit    | `tests/Unit`    | PHPUnit/Pest, no automatic DB refresh   | Pure enums, parsers, value objects, architecture              |
| Feature | `tests/Feature` | In-memory SQLite and `RefreshDatabase`  | Routes, authorization, validation, persistence, Inertia props |
| Browser | `e2e`           | Fresh `.playwright/database/e2e.sqlite` | Real browser behavior across Chromium, Firefox, and WebKit    |

Focused backend run:

```bash
php artisan test --compact tests/Feature/ReceptionWorkflowTest.php
php artisan test --compact --filter="descriptive test fragment"
```

Focused browser run:

```bash
npm run test:e2e -- e2e/reception-cases.spec.ts --project=chromium
```

Set `E2E_KEEP_DATABASE=1` only when debugging the E2E database; otherwise global teardown removes
it.

Feature tests currently use `RefreshDatabase` through `tests/Pest.php`. Follow that observed
convention unless the suite intentionally migrates to another strategy.

## Change-to-test map

| Area                                           | Primary tests                                                                                                 |
| ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Authentication, 2FA, verification, passkeys    | `tests/Feature/Auth`, `PasskeyAuthenticationTest`, `Settings/SecurityTest`                                    |
| Users and roles                                | `AdminUserManagementTest`, `CreateAdminUserCommandTest`                                                       |
| Construction/business schedules                | `ConstructionScheduleTest`, `ScheduleOverviewTest`, `ScheduleSearchTest`                                      |
| Stock parsing/reconciliation/purchases/reports | `ScheduleContentStockParserTest`, `ConstructionScheduleStockTest`, `StockPurchaseTest`, `StockTermReportTest` |
| Reception workflow                             | `ReceptionWorkflowTest`, `ReceptionCaseStatusTest`, `ReceptionDocumentTypeTest`                               |
| Reception files                                | `ReceptionCaseAttachmentTest`                                                                                 |
| Attendance and communication                   | `AttendanceRecordTest`, `InternalCommunicationTest`                                                           |
| Guide files                                    | `ConstructionSiteLibraryTest`, `SiteGuideFileDownloadTest`                                                    |
| Audit                                          | `AuditLogTest`, `AuditLoggingTest`                                                                            |
| Architecture                                   | `DomainArchitectureTest`                                                                                      |
| Browser-critical schedule/reception behavior   | Corresponding files under `e2e`                                                                               |

## Coding conventions

- Follow sibling files before introducing a new pattern.
- Use descriptive names, typed parameters, and explicit return types.
- Use curly braces for every PHP control structure.
- Use constructor property promotion for dependencies.
- Use PHPDoc for generics and array shapes; avoid explanatory inline comments unless the rule is
  genuinely non-obvious.
- Models use Laravel attributes for fillable/hidden/scope metadata and `casts()` for conversion.
- Mutations use Form Requests and validated data.
- Authorization belongs in policies/gates and Form Requests, not only the React UI.
- Domain code must remain free of HTTP, models, and database dependencies.
- Application workflows must remain free of HTTP dependencies.
- Use named Laravel routes and Wayfinder functions.
- Keep PHP enum values/labels as the frontend source of truth through shared props.
- Reuse components, hooks, services, and factories before creating another abstraction.

## Quality gates

Useful narrow commands:

```bash
composer lint:check
composer rector:check
composer analyse:phpstan
composer analyse:psalm
npm run lint:check
npm run format:check
npm run types:check
```

Before pushing, run:

```bash
composer check
php artisan test --compact
```

Run the relevant E2E spec for browser-visible behavior. Run `composer security` for authentication,
authorization, upload, logging, raw-query, dependency, or other security-sensitive work.

If PHP files changed, format them before finalizing:

```bash
vendor/bin/pint --dirty --format agent
```

Do not regenerate `phpstan-baseline.neon` or `psalm-baseline.xml` to silence new errors. Fix new
errors in the changed code.

## Git hooks and commits

Lefthook is installed by `npm install`/`npm run prepare`:

- Pre-commit runs Rector then Pint on staged PHP and ESLint then Prettier on staged frontend files.
- Pre-push runs PHPStan, Psalm, Rector dry-run, and TypeScript.
- Commit messages must start with an allowed conventional prefix such as `feature:`, `fix:`,
  `docs:`, or `test:`.

CI still runs even if hooks are bypassed.

## Database-safe workflow

- Inspect schema before changing a migration or model.
- Create new migrations with Artisan and `--no-interaction`.
- Treat existing migrations as immutable once used by persistent environments.
- Use factories in tests.
- Use read-only schema/query tools for diagnosis.
- Use `migrate:fresh` only with a disposable database.
- Never run seeders or destructive database commands against production.
- Update [database-erd.md](database-erd.md) after schema changes.

## Documentation completion

Markdown-only changes should still run:

```bash
git diff --check
npx prettier --check "README.md" "docs/**/*.md"
```

Check relative links and Mermaid blocks when changing diagrams. For behavioral changes, documentation
validation does not replace the focused tests and quality gates above.
