# Changelog

## [Unreleased]

### Fixed

- The factory passes `throttle` (seconds between two calls, which every factory takes) to the
  source: `throttle: 2.5` gave a source that left no interval.
