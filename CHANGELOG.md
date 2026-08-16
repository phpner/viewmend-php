# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog. A version and release date will be assigned with the first public release.

## Unreleased

### Added

- Framework-agnostic `ViewMendClient` object facade and validated SDK configuration.
- PSR-18/PSR-17 transport adapter with PSR-3 logging and token-safe failure handling.
- Retry policy for safe PSR-18 network failures, HTTP 429, and transient 500/502/503/504 responses.
- Immutable Site Tracker Event value objects, builder, v1 Events client, and typed delivery response.
- Forward-compatible queue status value object.
- Unit and HTTP contract tests, strict static analysis, and PSR-12 checks.
