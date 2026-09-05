# Architecture

## Status

This document records the architecture of the MIT-licensed `viewmend/sdk` package published through Packagist.

## Runtime baseline

The minimum runtime is PHP 8.3. According to the [official PHP support matrix](https://www.php.net/supported-versions.php), PHP 8.2 reaches end of security support in December 2026, while PHP 8.3 remains security-supported through December 2027. PHP 8.3 is therefore the practical compatibility floor for a new production SDK without forcing consumers onto the newest runtime.

Every PHP file uses strict types. The source tree uses PSR-4 and PSR-12.

## Product scope

The repository is the general ViewMend SDK. Site Tracker and Cron are isolated product modules built on the same transport. Site Tracker includes event delivery and the documented integration dashboard/resource reads. Additional modules may be added only for documented API contracts.

Laravel integration will live in `viewmend/laravel` and depend on this package. Laravel and WordPress code are outside this repository.

## Public API

The supported public surface is intentionally small:

- `ViewMend\ViewMend`: default client factory, advanced PSR factory, and module access.
- `ViewMend\SiteTracker\SiteTrackerClient`, `Events`, and `PendingEvent`: fluent Site Tracker event construction.
- `ViewMend\SiteTracker\Response\*`: typed delivery IDs, result, and forward-compatible queue status.
- `SiteTrackerClient::dashboard()` and `resources()`: typed integration dashboard and paginated run resource reads.
- `ViewMend\Cron\CronClient`: schedule registration, inspection, disabling, and callback verification.
- `ViewMend\Cron\CallbackVerifier` and `Callback`: signed callback verification and typed delivery data.
- `ViewMend\Cron\Response\RegistrationResult`: typed server registration state.
- `ViewMend\Exception\*`: stable configuration, validation, transport, and API failures.
- PSR-18, PSR-17, and PSR-3 interfaces used by the advanced factory.

Classes below `ViewMend\Internal` are implementation details and are not compatibility promises.

`ViewMend::client()` is a static named constructor, not a static facade or global service locator. It returns an ordinary immutable client instance and stores no global state.

## Dependency direction

```mermaid
flowchart LR
    App["Consumer application"] --> Entry["ViewMend"]
    Entry --> Tracker["SiteTracker fluent API"]
    Entry --> Cron["Cron API"]
    Tracker --> Sender["Internal EventSender"]
    Cron --> Registration["Internal RegistrationSender"]
    Sender --> Contract["Internal TransportInterface"]
    Registration --> Contract
    Guzzle["Default Guzzle transport"] --> Adapter["Internal PSR-18 adapter"]
    Custom["Injected PSR-18 client"] --> Adapter
    Adapter --> Contract
    Retry["Internal retry decorator"] --> Contract
```

Core transport, configuration, validation, and retry behavior know nothing about Site Tracker. The Site Tracker integration ID is validated only at `siteTracker($integrationId)`; creating the general ViewMend client requires only credentials and transport configuration.

Cron callback verification intentionally sits outside the outbound HTTP transport. It derives the signing secret from the same token passed to `ViewMend::client()`, validates the timestamp and HMAC over the exact raw body, binds the header request ID to the payload run ID, and returns a typed callback. It performs no network I/O.

The supported contract keeps `ViewMend::client(token: ...)` consistent across modules and exposes Cron through `cron()`. Credentials remain module-scoped, and adding Cron does not change the Site Tracker API or its resource path.

Cron registration is application-neutral. `CronClient::register()` sends only the schedule, timezone, callback path, and enabled state. Integration identity and version metadata belong to the consuming application and are neither accepted nor returned by the SDK.

Authentication remains module-scoped even though every module uses the same `token` parameter name. The Cron server rejects the Site Tracker `vmt_` format with `token_scope_invalid`, and the SDK maps only that stable error code to `TokenScopeException`. It never treats the scope response as proof that the supplied Site Tracker token exists.

## Transport construction

`ViewMend::client(token: ...)` creates Guzzle and its PSR-17 factories internally. Guzzle is a production dependency, so consumers do not need to select or configure a transport for the default workflow.

`ViewMend::withPsr18()` accepts any PSR-18 client and PSR-17 request and stream factories for Laravel integration, tests, self-hosted deployments, or applications with managed HTTP infrastructure. Both factories produce the same internal transport stack and behavior. The consumer-facing setup is documented separately in [advanced configuration](advanced-configuration.md) so the main README remains focused on the default workflow.

The default logger is `Psr\Log\NullLogger`.

The default Guzzle client uses a 3-second connection timeout and a 10-second total timeout per attempt. TLS verification remains enabled and redirects are disabled. With at most three attempts and the default exponential backoff, a timeout-only failure is bounded to approximately 31 seconds.

## API URL composition

The canonical versioned API base URL is:

`https://viewmend.com/api/v1`

The Site Tracker module owns only its relative resource path:

`/site-tracker/integrations/{integration}/events`

The resulting production endpoint is:

`https://viewmend.com/api/v1/site-tracker/integrations/{integration}/events`

Site Tracker also owns `/site-tracker/integrations/{integration}/dashboard` and `/site-tracker/integrations/{integration}/runs/{run}/resources`. Each ID is encoded as a single path segment, and query parameters use RFC 3986 encoding.

The Cron module owns one relative resource path:

`/cron/registration`

An `apiBaseUrl` override is available for tests, self-hosted installations, and advanced configuration. The version prefix belongs in `apiBaseUrl`; modules must not duplicate `/api/v1`.

## Site Tracker event flow

Semantic methods such as `deployment()`, `contentUpdate()`, and `maintenance()` choose a valid API event type without requiring an enum import. They return an immutable `PendingEvent`. Fluent methods return a new valid pending value; only `send()` performs I/O.

Known changed-field values use semantic methods such as `contentChanged()` and `metadataChanged()` so consumers do not repeat protocol strings. `customFieldChanged()` preserves support for application-specific and future field names without putting Site Tracker constants on the root SDK client.

Internally, the fluent surface creates and evolves a validated immutable `SiteTrackerEvent`. The sender serializes the exact v1 JSON contract and returns a typed `DeliveryResult`, never a public associative array.

HTTP 202 represents a newly accepted event and HTTP 200 a duplicate delivery. Counts remain integers, `scheduled_for` becomes an immutable date-time, and unknown `queue_status` values are preserved by `QueueStatus`.

## Site Tracker dashboard reads

The read contract was checked against ViewMend's `SiteTrackerDashboardController`, `TrackedPageIntegrationDashboard`, `TrackedPageDashboardApiTest`, and `docs/tracker.md` at backend commit `ec28f1f0` (2026-09-05). The endpoints were introduced in `95dbcf5d`. They reuse the custom integration Bearer token and are scoped by the server to its Tracker group.

The existing `siteTracker($integrationId)` object exposes `dashboard(pageId: ..., device: ...)` and `resources(runId: ..., type: ..., device: ..., page: ..., perPage: ...)`. This is an additive public API change; event and Cron callers keep their existing signatures. The `@internal` SiteTrackerClient constructor now receives the reader alongside the event sender.

`Internal\SiteTracker\Dashboard\Reader` validates query parameters and performs safe GETs through the shared transport. `ResponseMapper` maps the server document into small readonly response objects. Its JSON field reader distinguishes objects from lists, requires documented fields even when nullable, rejects scalar coercion and invalid calendar timestamps, and ignores unknown additive fields. The API's empty `links: []` becomes a DashboardLinks object with null fields.

Response strings such as status, severity, source, category, device, and change type remain open to future values. Request device/type filters are restricted to the values the current controller accepts. Typed collections validate their elements on construction; optional measurements retain null values and byte deltas remain signed. No DTO follows the server's resource endpoint or workspace links: subsequent inventory calls build paths from the configured API base, integration, and run ID.

The new response classes are public API. No framework dependency, transport change, cache, automatic page iteration, or production dependency was added. Contract fixtures contain synthetic data matching the backend serializer and are exercised with mock PSR-18 clients, a frozen clock, and a recording sleeper.

## Retry and error semantics

The initial request counts as attempt one. The default policy permits at most three total attempts. It retries explicitly safe requests for PSR-18 network failures, HTTP 429, and transient 500/502/503/504 responses. Dashboard/resource GETs are safe to repeat and preserve the exact URI and query across attempts.

`Retry-After` is honored in delta-seconds or HTTP-date form and bounded by the configured maximum delay. The identical serialized request and event ID are reused on every attempt.

No automatic retry occurs for 401, 410, 413, 422, non-network PSR request failures, malformed success responses, or requests not marked retry-safe.

Cron registration operations are idempotent and marked retry-safe. Runtime callbacks are delivered by ViewMend with at-least-once semantics; clients must deduplicate by the stable callback run ID.

Server response bodies and authorization data are not copied into exception messages or log context. API tokens are redacted from debug and export output.

## Dependencies

Production dependencies are Guzzle plus the PSR HTTP client, factory, message, and logger interfaces. Guzzle supplies the ready-to-use default client and PSR-17 implementation. The PSR interfaces remain direct dependencies because they are part of the advanced extension API.

Development dependencies provide PHPUnit, PHPStan, PSR-12 checks, and an independent PSR-7 implementation for contract tests.
