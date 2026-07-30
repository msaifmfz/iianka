# Repository documentation

Last verified: 2026-07-30

This directory is the maintained technical map for iianka, an internal Japanese-language
operations application. It supplements the code; it does not replace it.

## Start here

Read these documents in order when joining the project or giving the repository to an LLM:

1. [Architecture](architecture.md) — runtime, layers, module map, and request flow.
2. [Domain guide](domain-guide.md) — business terms, roles, state machines, and invariants.
3. [Database ERD](database-erd.md) — physical schema, relationships, constraints, and drift checks.
4. [Development](development.md) — setup, generated files, tests, quality gates, and completion checks.
5. [Security](security.md) — authentication, authorization, uploads, audit logging, and open decisions.
6. [Operations](operations.md) — CI/CD, production topology, health checks, backup/restore, and known gaps.
7. [LLM guide](llm-guide.md) — task-oriented source map and change guardrails.

The root [README](../README.md) remains the short human entry point. Repository-wide agent
instructions live in [AGENTS.md](../AGENTS.md).

## Document map

| Document                           | Purpose                                                               | Update when                                                                |
| ---------------------------------- | --------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| [Architecture](architecture.md)    | System context, layers, module ownership, frontend/backend boundaries | Adding a module, layer, runtime service, or integration                    |
| [Domain guide](domain-guide.md)    | Business behavior and invariants                                      | Changing roles, statuses, workflows, stock rules, or business dates        |
| [Database ERD](database-erd.md)    | Tables, keys, cardinality, delete behavior, logical references        | Adding or changing a migration or model relationship                       |
| [Development](development.md)      | Local setup, generated artifacts, tests, and quality commands         | Changing scripts, tooling, hooks, CI checks, or test layout                |
| [Security](security.md)            | Trust boundaries and access-control model                             | Changing Fortify, roles, policies, middleware, uploads, or audit events    |
| [Operations](operations.md)        | Release/deploy behavior and recovery procedures                       | Changing workflows, server topology, queues, scheduler, or storage         |
| [LLM guide](llm-guide.md)          | Minimal context and safe change workflow for coding agents            | Moving source files or changing project conventions                        |
| [Reception specification](spec.md) | Original detailed product/implementation specification for 受付       | Changing the reception product contract; verify against current code first |
| [Server setup](../SERVER_SETUP.md) | AlmaLinux, Nginx, PHP-FPM, Redis, and systemd provisioning            | Changing the target server topology                                        |
| [Changelog](../CHANGELOG.md)       | Released user-visible changes                                         | Preparing a release                                                        |

## Sources of truth

When sources disagree, use this order:

1. Executable behavior and tests.
2. Migrations, models, policies, routes, requests, and configuration.
3. Lockfiles and generated route output.
4. These maintained documents.
5. Historical plans or specifications.

`docs/spec.md` describes the reception feature in depth, but parts of it predate the final
implementation. The current enums, policies, workflow service, migrations, and tests take
precedence.

## Verified maintenance notes

These are observed repository facts, not speculative recommendations:

- A database built from current migrations has 38 tables. The developer SQLite file marks every
  migration as run but is missing `reception_cases.work_memo`; see
  [schema drift](database-erd.md#known-local-schema-drift).
- There is no tracked `db:backup` command, backup configuration, or scheduled backup task. The
  automated deploy migrates without first taking a database snapshot; see
  [backup status](operations.md#backup-status-and-required-procedure).
- The server guide describes timestamped release directories and a `current` symlink, while the
  current GitHub deploy workflow checks out a tag directly in `DEPLOY_PATH`; see
  [deployment gaps](operations.md#verified-operational-gaps).
- Fortify enables email-verification routes, but `App\Models\User` does not currently implement
  `MustVerifyEmail`; see [email verification](security.md#email-verification-decision).
- The local nightly issue automation defaults to a `develop` base branch, while the remote
  currently exposes `main` but no `develop`; see
  [nightly automation](operations.md#nightly-codex-issue-automation).

Resolve or deliberately accept each item, then update the linked section.

## Documentation maintenance rule

Behavior changes are incomplete until the affected document is updated. Prefer links to source
files and commands over copied code. Keep volatile values—row counts, local URLs, credentials, and
machine-specific paths—out of durable documentation.
