# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.0.1] - 2026-03-16

### Changed
- Standardize composer.json: add type, scripts
- Add Development section to README

## [1.0.0] - 2026-03-09

### Added
- Initial release
- `config` driver — define feature flags in `config/feature-flags.php`
- `database` driver — manage flags at runtime via the `feature_flags` table
- Percentage rollout support with deterministic per-user bucketing (`crc32`)
- Schedule support — `enabled_from` / `enabled_until` timestamps
- `Feature` facade with `active()`, `for($user)->active()`, `allFeatures()`, `enable()`, `disable()`
- `@feature` / `@endfeature` Blade directive for global checks
- `@featurefor` / `@endfeaturefor` Blade directive for per-user checks
- `feature:{name}` route middleware — returns 403 when feature is inactive
- Artisan commands: `feature:list`, `feature:enable`, `feature:disable`
- Migration for `feature_flags` table
- Auto-discovery via Laravel package auto-discovery
