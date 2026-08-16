# Changelog

All notable changes to this project will be documented in this file.

The format follows Keep a Changelog. A version and release date will be assigned with the first public release.

## Unreleased

### Added

- Framework-agnostic `ViewMend` client with a ready-to-use Guzzle transport.
- Fluent Site Tracker event methods with I/O isolated to `send()`.
- Semantic `contentChanged()` and `metadataChanged()` methods with an explicit custom-field escape hatch.
- PSR-18/PSR-17 transport adapter with PSR-3 logging and token-safe failure handling.
- Retry policy for safe PSR-18 network failures, HTTP 429, and transient 500/502/503/504 responses.
- Immutable Site Tracker Event value objects, builder, v1 Events client, and typed delivery response.
- Forward-compatible queue status value object.
- Unit and HTTP contract tests, strict static analysis, and PSR-12 checks.

### Changed

- Set the canonical versioned API base URL to `https://viewmend.com/api/v1` and made module paths relative to it.
- Moved the Site Tracker integration ID from general client configuration to `siteTracker($integrationId)`.
- Replaced manual `SdkConfig`, HTTP client, factory, enum, and event-object assembly with the one-import `ViewMend` entry point.
- Moved transport, retry, configuration, validation, and event implementation classes into `ViewMend\Internal`.
- Moved stable exceptions to `ViewMend\Exception` and kept PSR-18 injection as an advanced factory.
- Removed the original pre-release public namespaces without compatibility shims; no public package version used them.
