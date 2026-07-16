# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Only final releases are listed; `-rc.N` pre-releases fold into their final version.

## [0.3.0](https://github.com/msaifmfz/iianka/compare/v0.2.0...v0.3.0) - 2026-07-16

### Added

- Static analysis and code-quality tooling — Larastan, Psalm, and type-aware ESLint — with git hooks, enforced in CI.
- Static analysis extended across the test suite, with a baseline ratchet that blocks new violations.
- Stock management: item catalog, term purchases, and schedule-driven usage tracking.
- Stock picker triggers mid-word, without requiring a leading space.
- Project changelog, generated from commit history and enforced by CI at release time.

### Fixed

- Wayfinder types are now generated before type-aware ESLint runs in CI.
- Corrected the code-scanning configuration in the CI workflows.
