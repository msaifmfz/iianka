# LLM repository guide

Last verified: 2026-07-30

## Minimal context

iianka is a private Japanese-language Laravel 13 + Inertia React 3 application for construction
scheduling, attendance, internal communication, reception cases, stock, administration, and audit
logging.

Key facts:

- PHP 8.5, React 19, Tailwind 4, SQLite, Pest 4, Playwright.
- Session-authenticated web app; no public JSON API.
- Fortify handles login, password reset, 2FA, and passkeys.
- Wayfinder generates typed TypeScript actions and named routes.
- Laravel timezone is UTC; business dates use `Asia/Tokyo`.
- Domain/application boundaries are enforced by an architecture test.
- Reception and stock have transaction-sensitive application workflows.

## Read order by task

Always start with [AGENTS.md](../AGENTS.md), then use the smallest relevant set:

| Task                       | Read                                                                                                                     |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Any unfamiliar feature     | [Architecture](architecture.md), then the module row in [Domain guide](domain-guide.md)                                  |
| Migration/model/query      | [Database ERD](database-erd.md), related migration/model/factory/tests                                                   |
| Reception                  | Reception section of [Domain guide](domain-guide.md#reception-workflow), enums, policy, workflow, tests                  |
| Stock                      | Stock section of [Domain guide](domain-guide.md#stock), application services, value objects, parser/reconciliation tests |
| Authentication/roles/files | [Security](security.md), Fortify config/provider, user model, policies, auth tests                                       |
| React/Inertia page         | [Architecture frontend structure](architecture.md#frontend-structure), sibling page/components, controller props         |
| Local/CI failure           | [Development](development.md), Composer/npm scripts, matching workflow                                                   |
| Deploy/recovery/automation | [Operations](operations.md), deploy workflow, server guide, `.codex` prompt/scripts                                      |

Use version-specific Laravel/Inertia/Fortify/Wayfinder documentation before changing framework
patterns.

## Source map

| Path                                          | Meaning                                                            |
| --------------------------------------------- | ------------------------------------------------------------------ |
| `app/Domain`                                  | Pure domain enums, parsing, and value objects                      |
| `app/Application`                             | Transactional workflows and application queries                    |
| `app/Models`                                  | Eloquent persistence, relationships, casts, model hooks            |
| `app/Http/Controllers`                        | HTTP/Inertia orchestration                                         |
| `app/Http/Requests`                           | Normalization, authorization, validation                           |
| `app/Policies`                                | Object/state-aware authorization                                   |
| `app/Services`                                | Cross-domain application services                                  |
| `app/Providers`                               | Gates, defaults, Fortify setup, audit listeners                    |
| `routes`                                      | Named app routes and console commands                              |
| `database/migrations`                         | Physical schema source of truth                                    |
| `database/factories`                          | Required test data builders                                        |
| `database/seeders`                            | Demo/master data; unsafe for production unless explicitly reviewed |
| `resources/js/pages`                          | Inertia route-level React pages                                    |
| `resources/js/components`                     | Reusable app/UI components                                         |
| `resources/js/hooks`                          | Shared browser behavior                                            |
| `resources/js/lib`                            | Shared frontend business/presentation helpers                      |
| `resources/js/types`                          | TypeScript page/shared contracts                                   |
| `resources/js/actions`, `routes`, `wayfinder` | Generated and ignored; never hand-edit                             |
| `tests/Feature`                               | HTTP, auth, database, Inertia behavior                             |
| `tests/Unit`                                  | Pure rules and architecture                                        |
| `e2e`                                         | Real-browser critical flows                                        |

## Non-negotiable invariants

### Architecture

- `App\Domain` must not depend on HTTP, Eloquent models, or database/facade classes.
- `App\Application` must not depend on `App\Http`.
- Follow existing sibling conventions before introducing a new abstraction.
- Use a Form Request for mutations and validate/authorize at the boundary.
- Use gates/policies server-side; React permission flags are display hints only.

### Routes and frontend

- Use named routes.
- Use Wayfinder imports from `@/actions` or `@/routes`; no hardcoded app URLs.
- Do not edit generated Wayfinder directories.
- Keep PHP enums/labels authoritative and share them through Inertia.
- Reuse pages, components, hooks, and `resources/js/lib` helpers.
- Inertia v3 uses built-in HTTP/form clients; do not introduce Axios without an approved dependency
  change.

### Dates

- Laravel timestamps use UTC.
- "Today," daily case numbering, and stock terms use `BusinessDate` (`Asia/Tokyo`).
- Preserve date-only versus timestamp semantics.

### Reception

- Status transitions go through `ReceptionCaseWorkflow`.
- Preserve row locking, stale-state detection, idempotency, activity creation, and after-commit
  audit events.
- Drafts are private to their receptor.
- Entering `in_progress` requires an assignee.
- Attachment actions must preserve policy, format/size/count validation, private storage, and file
  cleanup.

### Stock

- `stock_transactions` is immutable.
- `stocks.current_quantity`, ledger delta, and schedule/purchase state change together in one
  transaction.
- Lock stock rows in ascending ID order.
- Preserve optimistic `content_version` checks and content hash no-op behavior.
- Negative inventory is currently allowed within numeric range.
- Inactive stock may be unwound but not increased.
- Schedule source columns are logical references without FKs; cleanup is application-owned.

### Audit and files

- Audit metadata must remain recursively sanitized.
- Audit failures must not falsely roll back successful business work unless a deliberate new
  compliance requirement changes that contract.
- New reception attachments and site guides use private storage and authorized download routes.
- Deleting file-backed models must remove stored objects.

### Database

- Create new forward migrations; do not rewrite migrations used by persistent environments.
- Define indexes, foreign keys, nullability, defaults, and deletion behavior explicitly.
- Update model attributes/casts/relations, factories, tests, and the ERD together.
- Never run `migrate:fresh` against persistent data.
- Be aware of the verified local drift around `reception_cases.work_memo`.

## Change playbooks

### Add or change a backend field

1. Inspect live/fresh schema and related queries.
2. Create a migration with Artisan.
3. Update model fillable/default/cast metadata.
4. Update Form Requests and authorization.
5. Update factories/seeders only as appropriate.
6. Update controller/presenter Inertia props and TypeScript types.
7. Update forms/detail/list UI.
8. Add focused feature tests.
9. Update ERD/domain docs.

### Add or change an Inertia page action

1. Inspect route, controller, Form Request, and sibling pages.
2. Add a named backend route and server-side authorization.
3. Regenerate Wayfinder types.
4. Import the generated action/route object.
5. Use `<Form>`, `useForm`, `useHttp`, or `router` following the closest existing pattern.
6. Preserve validation error, processing, flash, return-to, and scroll behavior.
7. Add a feature test for the server contract and E2E coverage only for browser-critical behavior.

### Change reception state

1. Update `ReceptionCaseStatus` transition rules.
2. Update `ReceptionCaseWorkflow`.
3. Update policy and transition Form Requests.
4. Update presenter/shared labels and UI controls.
5. Extend unit transition matrix and feature workflow/race/authorization tests.
6. Update the state diagram and action matrix.

### Change stock behavior

1. Identify whether the owner is parser, value object, reconciliation, purchase recorder, or report.
2. Preserve transactional and immutable-ledger behavior.
3. Add pure unit tests first when possible.
4. Add feature tests for deltas, rollback, inactive stock, concurrency version, and reporting.
5. Update the stock domain guide and logical ERD references.

### Add a role or permission

1. Update `UserRole` and user capability helpers.
2. Update gates/policies and Form Request authorization.
3. Update shared Inertia permissions and TypeScript contracts.
4. Update navigation/UI visibility.
5. Test allowed and denied behavior for every relevant role.
6. Update security and domain matrices.

## Verification

Use the narrowest test first:

```bash
php artisan test --compact path/to/FocusedTest.php
npm run types:check
```

Before pushing a normal code change:

```bash
composer check
php artisan test --compact
```

Add the relevant E2E spec for browser behavior and `composer security` for security-sensitive work.
If PHP changed:

```bash
vendor/bin/pint --dirty --format agent
```

Do not regenerate static-analysis baselines for new errors.

For documentation-only work:

```bash
git diff --check
npx prettier --check "README.md" "docs/**/*.md"
```

## Common failure modes

- Treating `docs/spec.md` as newer than current reception code/tests.
- Editing ignored generated TypeScript route files.
- Hardcoding URLs instead of Wayfinder.
- Duplicating role/status labels in React.
- Using UTC "today" for a Japanese business-date rule.
- Updating reception status directly.
- Updating stock balance without an immutable ledger delta.
- Assuming logical `schedule_type`/`schedule_id` links have FK cleanup.
- Assuming `verified` currently enforces email ownership on `User`.
- Assuming deploy creates a database backup.
- Running demo seeders or destructive migrations on persistent data.
- Enabling nightly automation without correcting its `develop` base branch.

## Keep documentation useful

State facts as either:

- **Observed current behavior**, backed by code/tests/configuration.
- **Required convention**, backed by repository instructions or enforced tests.
- **Open decision/gap**, with evidence and no assumed resolution.

Update the document map when adding a new durable guide. Avoid task-local plans and volatile
machine state in tracked docs.
