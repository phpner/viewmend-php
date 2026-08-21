# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog.

## Unreleased

### Added

- Cron registration client for creating, reading, and disabling a plugin-managed schedule.
- Signed callback verification with timestamp freshness, connection binding, and verification challenge handling.
- Typed Cron registration and callback values with contract tests and at-least-once delivery guidance.
- Dedicated `TokenScopeException` for Site Tracker-formatted tokens rejected by the Cron API without token-existence disclosure.

### Changed

- Replaced the unreleased `pluginCron()`/`connectionKey` draft terminology with `cron()` and the existing `token` authentication term. Site Tracker APIs are unchanged.
- Documented settings read-back, plugin form hydration, server-state fields, and offline-cache rules for `current()`.

## 1.0.0 - 2026-08-16

### Added

- Framework-agnostic `ViewMend` client with a ready-to-use Guzzle transport.
- Default Guzzle transport with 3-second connection and 10-second total timeouts, TLS verification, and redirects disabled.
- Fluent Site Tracker event construction with I/O isolated to `send()`.
- Semantic `contentChanged()` and `metadataChanged()` methods with an explicit custom-field escape hatch.
- PSR-18/PSR-17 transport adapter with PSR-3 logging and token-safe failure handling.
- Retry policy for safe PSR-18 network failures, HTTP 429, and transient 500/502/503/504 responses.
- Immutable Site Tracker event values and typed delivery responses for the v1 Events API.
- Forward-compatible queue status value object.
- Requests to the canonical `https://viewmend.com/api/v1` base with module-relative resource paths.
- Unit and HTTP contract tests, strict static analysis, PSR-12 checks, and a supported-PHP CI matrix.
