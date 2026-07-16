# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Only final releases are listed; `-rc.N` pre-releases fold into their final version.

## [0.3.0](https://github.com/msaifmfz/iianka/compare/v0.2.0...v0.3.0) - 2026-07-16

### Features

- Static analysis and code-quality tooling: Larastan, Psalm, type-aware ESLint, and lefthook git hooks, enforced in CI
- Extend static analysis over the test suite, with `casts()` inference and a baseline ratchet
- Stock management: item catalog, term purchases, and schedule-driven usage tracking
- Stock picker triggers mid-word, without a leading space

### Bug Fixes

- Generate Wayfinder types before type-aware ESLint in CI
- Correct code-scanning setting in CI workflows
