# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog. A version and release date will be assigned with the first public release.

## Unreleased

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
