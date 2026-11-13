# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-13

### Changed

- Neutral example data in docs and tests.

## [1.0.0] - 2026-11-13

### Added

- `policy.json`: the service's usage policy as data (limits, licence, credit, what a request needs), read at its pages on 2026-11-10 and named in `composer.json` (`extra.omnisense.policy`); the core honours it on every request.

### Fixed

- The factory passes `throttle` (seconds between two calls, which every factory takes) to the
  source: `throttle: 2.5` gave a source that left no interval.

### Changed

- Requires `omnisense/omnischolar: ^1.0`: stable versions, so `composer require omnischolar/crossref` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).

