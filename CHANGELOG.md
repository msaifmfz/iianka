# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Only final releases are listed; `-rc.N` pre-releases fold into their final version.

## [0.4.5](https://github.com/msaifmfz/iianka/compare/v0.4.4...v0.4.5) - 2026-08-03

### Changed

- Update patch

## [0.4.4](https://github.com/msaifmfz/iianka/compare/v0.4.3...v0.4.4) - 2026-08-03

### Added

- Show construction schedule for stock usage

### Fixed

- Changelog script

## [0.4.3](https://github.com/msaifmfz/iianka/compare/v0.4.3...v0.4.3) - 2026-08-03

## [0.4.2](https://github.com/msaifmfz/iianka/compare/v0.4.1...v0.4.2) - 2026-07-31

### Added

- Stock ordering and capturing hardening

## [0.4.1](https://github.com/msaifmfz/iianka/compare/v0.4.0...v0.4.1) - 2026-07-31

### Changed

- Changelog

### Added

- Stock ordering and capturing hardening

### Fixed

- Update patch and fix for update

## [0.4.0](https://github.com/msaifmfz/iianka/compare/v0.3.0...v0.4.0) - 2026-07-31

### Added

- SAST tooling - larastan, typed eslint, lefthook hooks, enforced CI
- SAST follow-ups - phpstan on tests/, casts() inference, baseline ratchet
- Stock management - catalog, term purchases, schedule-driven usage
- Slash stock picker triggers mid-word, no leading space needed
- Changelog mechanism with git-cliff + release-tag CI guard

### Changed

- Remove dead code, consolidate shared frontend utils
- Shared UI primitives - pagination, confirm dialogs, type descriptors
- Dedupe schedule forms (~85% copy-paste between construction/business)
- Extract SubcontractorPicker + SiteGuideFilePicker from construction form (1765->610 lines)
- Extract calendar sidebar + scroll/floating-action hooks from construction index (1930->1138 lines)
- Extract timeline lib + useSlotDragSelection from schedule-overview (2281->1697 lines)
- Schedule-search sessionStorage layer to lib, overview shares return key
- Add reproducible devbox environment
- Rector run
- Schedule index expands cleaning-duty occurrences once, not twice
- Retire is_admin; role enum is the single source of truth
- Drop unused users.is_admin column
- Shared return-to/initial-form-values controller concern
- Extract ScheduleFormOptionsService from schedule controllers
- Extract schedule calendar building from ConstructionScheduleController
- Route role checks through shared gates (manage-content etc.)
- Fix stale selectedDate docblock + typed closures; reconcile analyzer baselines (net -120 psalm, -12 phpstan lines)
- Documentation
- Make stock text more obvious

### Fixed

- Generate wayfinder types in lint CI before type-aware eslint
- CI workflows scan setting error
- Frontend bug batch - theme sync, TZ-safe dates, search state, misc
- Malformed ?date= on schedule index 500s -> fallback to today
- Wrap multi-write store/update in DB::transaction (business/notice/cleaning-duty)
- Site guide files orphaned on disk after delete/replace
- Business schedule form leaked hidden users (picker/availability/leave records)
- Guard stock balance against decimal(12,3) overflow in both directions
- Malformed ?month= on attendance index 500s -> fallback to current period
- Admin user store could commit half-created user without role/flags
- Guide file download 500s when disk file missing -> 404, no success audit
- Reception transition audit rows written inside the transaction -> after commit
- Bound schedule availability/leave data loaded on form render (-1mo/+18mo)
- Static analysis

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
